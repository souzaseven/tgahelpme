"""
Inspetor de Banco Firebird — API local (FastAPI).

Executa em http://127.0.0.1:8000 e serve tanto a API (/api/*) quanto o
dashboard estático (frontend/).
"""
from __future__ import annotations

import os
import string
from contextlib import asynccontextmanager
from datetime import date, datetime
from pathlib import Path
from typing import Optional

from fastapi import FastAPI, HTTPException, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse, JSONResponse
from fastapi.staticfiles import StaticFiles
from pydantic import BaseModel

from . import metadata, schema_snapshot
from .db import manager, test_connection as db_test_connection
from .logging_config import logger
from .security import ConsultaNaoPermitida, validar_somente_leitura

EXTENSOES_BANCO = {".fdb", ".gdb"}

BASE_DIR = Path(__file__).resolve().parent.parent
FRONTEND_DIR = BASE_DIR / "frontend"


@asynccontextmanager
async def lifespan(app: FastAPI):
    logger.info("Inspetor de Banco Firebird iniciado.")
    yield
    manager.close()
    logger.info("Inspetor de Banco Firebird encerrado.")


app = FastAPI(title="Inspetor de Banco Firebird", lifespan=lifespan)


@app.exception_handler(Exception)
async def erro_nao_tratado(request: Request, exc: Exception):
    """
    Rede de segurança: qualquer exceção que escape dos endpoints (ex.: um
    erro de SQL inesperado) é registrada no log com o traceback completo e
    devolvida ao navegador como JSON amigável, em vez do "Internal Server
    Error" cru do Starlette.
    """
    logger.exception(f"Erro não tratado em {request.method} {request.url.path}")
    return JSONResponse(
        status_code=500,
        content={"detail": "Erro interno no servidor. Veja logs/app.log para detalhes."},
    )


@app.middleware("http")
async def sem_cache(request, call_next):
    """
    Evita que o navegador guarde em cache o HTML/CSS/JS locais. Como esta é
    uma ferramenta em desenvolvimento ativo rodando localmente, é melhor
    sempre buscar a versão mais recente do que arriscar telas desatualizadas.
    """
    resposta = await call_next(request)
    resposta.headers["Cache-Control"] = "no-store, no-cache, must-revalidate, max-age=0"
    return resposta


app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://127.0.0.1:8000", "http://localhost:8000"],
    allow_methods=["*"],
    allow_headers=["*"],
)


# ---------------------------------------------------------------------------
# Modelos de requisição
# ---------------------------------------------------------------------------

class ConnectRequest(BaseModel):
    database: str
    host: str = "localhost"
    port: int = 3050
    user: str = "SYSDBA"
    password: str = ""
    charset: str = "UTF8"
    role: Optional[str] = None


class QueryRequest(BaseModel):
    sql: str
    limite: int = 500


# ---------------------------------------------------------------------------
# Dependência simples de conexão
# ---------------------------------------------------------------------------

def _con():
    try:
        return manager.get_connection()
    except ConnectionError as exc:
        raise HTTPException(status_code=409, detail=str(exc))


# ---------------------------------------------------------------------------
# Conexão
# ---------------------------------------------------------------------------

@app.post("/api/connect")
def connect(req: ConnectRequest):
    try:
        version = manager.connect(
            database=req.database,
            host=req.host,
            port=req.port,
            user=req.user,
            password=req.password,
            charset=req.charset,
            role=req.role,
        )
    except ConnectionError as exc:
        logger.warning(f"Falha ao conectar em {req.database} ({req.host}:{req.port}): {exc}")
        raise HTTPException(status_code=400, detail=str(exc))
    logger.info(f"Conectado a {req.database} ({req.host}:{req.port}) como {req.user} — Firebird {version}")
    return {"ok": True, "version": version, "connection": manager.info.as_public_dict()}


@app.post("/api/test-connection")
def test_connection(req: ConnectRequest):
    """Valida usuário/senha/caminho sem alterar a conexão ativa da ferramenta."""
    try:
        version = db_test_connection(
            database=req.database,
            host=req.host,
            port=req.port,
            user=req.user,
            password=req.password,
            charset=req.charset,
            role=req.role,
        )
    except ConnectionError as exc:
        raise HTTPException(status_code=400, detail=str(exc))
    return {"ok": True, "version": version}


@app.post("/api/disconnect")
def disconnect():
    manager.close()
    logger.info("Desconectado.")
    return {"ok": True}


@app.get("/api/status")
def status():
    if not manager.is_connected:
        return {"connected": False}
    return {"connected": True, "connection": manager.info.as_public_dict()}


# ---------------------------------------------------------------------------
# Explorador de arquivos do servidor (para escolher o .FDB pela interface)
# ---------------------------------------------------------------------------

@app.get("/api/browse")
def browse(path: Optional[str] = None):
    """
    Lista o conteúdo de uma pasta no servidor onde a ferramenta está rodando.
    Sem `path`, lista as unidades de disco (C:\\, D:\\, ...).
    """
    if not path:
        unidades = []
        for letra in string.ascii_uppercase:
            raiz = f"{letra}:\\"
            if os.path.exists(raiz):
                unidades.append({"name": raiz, "path": raiz})
        return {"current_path": None, "parent": None, "directories": unidades, "files": []}

    p = Path(path)
    if not p.exists() or not p.is_dir():
        raise HTTPException(status_code=400, detail="Caminho inválido ou inacessível.")

    diretorios, arquivos = [], []
    try:
        for entry in sorted(p.iterdir(), key=lambda e: e.name.lower()):
            try:
                if entry.is_dir():
                    diretorios.append({"name": entry.name, "path": str(entry)})
                elif entry.suffix.lower() in EXTENSOES_BANCO:
                    arquivos.append({"name": entry.name, "path": str(entry)})
            except (PermissionError, OSError):
                continue
    except (PermissionError, OSError) as exc:
        raise HTTPException(status_code=400, detail=f"Não foi possível listar a pasta: {exc}")

    pai = str(p.parent) if p.parent != p else None
    return {"current_path": str(p), "parent": pai, "directories": diretorios, "files": arquivos}


# ---------------------------------------------------------------------------
# Visão geral
# ---------------------------------------------------------------------------

@app.get("/api/overview")
def overview():
    con = _con()
    return metadata.get_engine_info(con)


@app.get("/api/business-summary")
def business_summary():
    """
    Resumo de negócio (produtos/serviços, clientes/fornecedores) para bancos
    que seguem o padrão TPRODUTO/FCFO. Retorna {} se o banco não tiver essas
    tabelas — a seção de cards correspondente simplesmente não aparece.
    """
    con = _con()
    return metadata.get_business_summary(con) or {}


@app.get("/api/financeiro-valores")
def financeiro_valores(inicio: Optional[str] = None, fim: Optional[str] = None):
    """
    Totais financeiros de FLAN (a pagar/a receber em aberto, e recebido/pago
    dentro do período). Sem parâmetros, usa o mês corrente.
    """
    con = _con()
    hoje = date.today()
    if not inicio:
        inicio = hoje.replace(day=1).isoformat()
    if not fim:
        fim = hoje.isoformat()
    return metadata.get_financeiro_valores(con, inicio, fim) or {}


# ---------------------------------------------------------------------------
# Mudanças na estrutura (comparação com uma referência salva)
# ---------------------------------------------------------------------------

@app.get("/api/schema/status")
def schema_status():
    """Diz se já existe uma referência salva para o banco conectado."""
    _con()  # garante que há conexão ativa
    salvo = schema_snapshot.carregar_snapshot(manager.info.database)
    if not salvo:
        return {"tem_referencia": False}
    return {"tem_referencia": True, "capturado_em": salvo["capturado_em"]}


@app.post("/api/schema/snapshot")
def schema_salvar_snapshot():
    """Salva a estrutura atual como a nova referência para comparações futuras."""
    con = _con()
    snap = metadata.obter_snapshot_schema(con)
    agora = datetime.now().isoformat(timespec="seconds")
    schema_snapshot.salvar_snapshot(manager.info.database, snap, agora)
    logger.info(f"Nova referência de estrutura salva para {manager.info.database}")
    return {"ok": True, "capturado_em": agora}


@app.get("/api/schema/diff")
def schema_diff():
    """Compara a estrutura atual com a última referência salva (se houver)."""
    con = _con()
    salvo = schema_snapshot.carregar_snapshot(manager.info.database)
    if not salvo:
        return {"tem_referencia": False}
    atual = metadata.obter_snapshot_schema(con)
    diff = metadata.comparar_snapshots(salvo["schema"], atual)
    return {
        "tem_referencia": True,
        "capturado_em": salvo["capturado_em"],
        "tem_mudancas": metadata.diff_tem_mudancas(diff),
        "diff": diff,
    }


# ---------------------------------------------------------------------------
# Análise Financeira (saldo de caixas, hoje/atraso, aging list)
# ---------------------------------------------------------------------------

@app.get("/api/analise-financeira")
def analise_financeira(data: Optional[date] = None):
    """`data` (YYYY-MM-DD) permite ver a posição financeira em outra data que não hoje."""
    con = _con()
    return metadata.get_analise_financeira(con, data) or {}


@app.get("/api/analise-estoque")
def analise_estoque(limite: int = 10, dias_parado: int = 90):
    """`limite`: quantas linhas em cada ranking/tabela. `dias_parado`: dias sem
    movimentação para um produto em estoque contar como "sem giro"."""
    con = _con()
    return metadata.get_analise_estoque(con, limite=limite, dias_parado=dias_parado) or {}


# ---------------------------------------------------------------------------
# Tabelas / Views
# ---------------------------------------------------------------------------

@app.get("/api/tables")
def tables():
    con = _con()
    return metadata.list_tables(con)


@app.get("/api/tables/{name}")
def table_detail(name: str):
    con = _con()
    return metadata.get_table_detail(con, name.upper())


@app.get("/api/tables/{name}/count")
def table_count(name: str):
    con = _con()
    return {"table": name, "row_count": metadata.get_row_count(con, name.upper())}


@app.get("/api/tables/{name}/columns")
def table_columns(name: str):
    """Versão leve, usada para expandir uma tabela na árvore da barra lateral."""
    con = _con()
    return metadata.get_columns(con, name.upper())


# ---------------------------------------------------------------------------
# Triggers (lista global)
# ---------------------------------------------------------------------------

@app.get("/api/triggers")
def triggers():
    con = _con()
    return metadata.list_triggers(con)


@app.get("/api/triggers/{name}")
def trigger_detail(name: str):
    con = _con()
    detalhe = metadata.get_trigger_detail(con, name.upper())
    if detalhe is None:
        raise HTTPException(status_code=404, detail="Trigger não encontrada.")
    return detalhe


# ---------------------------------------------------------------------------
# Procedures
# ---------------------------------------------------------------------------

@app.get("/api/procedures")
def procedures():
    con = _con()
    return metadata.list_procedures(con)


@app.get("/api/procedures/{name}")
def procedure_detail(name: str):
    con = _con()
    return metadata.get_procedure_detail(con, name.upper())


# ---------------------------------------------------------------------------
# Generators / Sequences
# ---------------------------------------------------------------------------

@app.get("/api/generators")
def generators():
    con = _con()
    return metadata.list_generators(con)


# ---------------------------------------------------------------------------
# Busca
# ---------------------------------------------------------------------------

@app.get("/api/search")
def search(q: str):
    con = _con()
    if not q or len(q.strip()) < 2:
        raise HTTPException(status_code=400, detail="Informe ao menos 2 caracteres para buscar.")
    return metadata.search_structure(con, q.strip())


# ---------------------------------------------------------------------------
# Console de consultas (somente leitura)
# ---------------------------------------------------------------------------

@app.post("/api/query")
def run_query(req: QueryRequest):
    con = _con()
    try:
        sql = validar_somente_leitura(req.sql)
    except ConsultaNaoPermitida as exc:
        raise HTTPException(status_code=400, detail=str(exc))

    limite = max(1, min(req.limite, 5000))
    cur = con.cursor()
    try:
        cur.execute(sql)
        colunas = [d[0] for d in cur.description] if cur.description else []
        linhas = []
        truncado = False
        for row in cur:
            if len(linhas) >= limite:
                truncado = True
                break
            linhas.append([metadata.serializar_valor(v) for v in row])
        return {"colunas": colunas, "linhas": linhas, "truncado": truncado}
    except Exception as exc:
        raise HTTPException(status_code=400, detail=f"Erro ao executar consulta: {exc}")
    finally:
        cur.close()


# ---------------------------------------------------------------------------
# Frontend estático
# ---------------------------------------------------------------------------

app.mount("/static", StaticFiles(directory=FRONTEND_DIR), name="static")


@app.get("/")
def index():
    return FileResponse(FRONTEND_DIR / "index.html")
