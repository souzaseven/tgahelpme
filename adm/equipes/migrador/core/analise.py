"""
core/analise.py
---------------
Análise do banco de ORIGEM (Firebird 2.5) antes de migrar. Combina:

- `gstat -h <arquivo>` para o cabeçalho: ODS, page size, dialeto, atributos,
  sweep interval (lido direto do arquivo, sem servidor).
- `isql` rodando um script contra as tabelas RDB$ / MON$ para: charset padrão,
  contagens de objetos, lista de tabelas de usuário, UDFs, tabelas externas e
  nomes de objetos que colidem com palavras reservadas novas.

As funções de parsing são puras (recebem texto, devolvem dados) para poderem
ser testadas sem um Firebird instalado. `analisar()` é o orquestrador que
dispara os subprocessos.
"""
from __future__ import annotations

import re
import subprocess
import tempfile
from pathlib import Path

import logger
from core.compatibilidade import PALAVRAS_RESERVADAS_NOVAS
from core.firebird import InstalacaoFirebird
from core.modelos import ResultadoAnalise

_TIMEOUT_GSTAT = 60
_TIMEOUT_ISQL = 180
_PREFIXO = "MIG"  # marcador das linhas úteis na saída do isql


# --------------------------------------------------------------------------- #
# Parsing de `gstat -h`
# --------------------------------------------------------------------------- #
def parse_gstat_header(texto: str) -> dict:
    """Extrai os campos de interesse do cabeçalho impresso por `gstat -h`."""
    dados: dict = {"atributos": []}
    for linha in texto.splitlines():
        s = linha.strip()
        m = re.match(r"Page size\s+(\d+)", s)
        if m:
            dados["page_size"] = int(m.group(1))
            continue
        m = re.match(r"ODS version\s+([\d.]+)", s)
        if m:
            dados["ods"] = m.group(1)
            continue
        m = re.match(r"Database dialect\s+(\d+)", s)
        if m:
            dados["sql_dialect"] = int(m.group(1))
            continue
        m = re.match(r"Attributes\s+(.*)", s)
        if m:
            valor = m.group(1).strip()
            dados["atributos"] = [p.strip() for p in valor.split(",") if p.strip()] if valor else []
            dados["forced_writes"] = "sim" if "force write" in valor.lower() else "não"
            continue
        m = re.match(r"Sweep interval:?\s+(\d+)", s)
        if m:
            dados["sweep_interval"] = int(m.group(1))
            continue
    return dados


# --------------------------------------------------------------------------- #
# Script e parsing do `isql`
# --------------------------------------------------------------------------- #
def montar_script_isql() -> str:
    """SQL que imprime linhas 'MIG;CHAVE;VALOR' fáceis de parsear. Compatível
    com Firebird 2.5 (usa RDB$RELATION_TYPE e MON$DATABASE, ambos presentes)."""
    reservadas = ",".join(f"'{p}'" for p in PALAVRAS_RESERVADAS_NOVAS)
    p = _PREFIXO
    return f"""SET HEADING OFF;
SET LIST OFF;
SET BLOBDISPLAY 0;
SET WIDTH 0;

SELECT '{p};CHARSET;' || TRIM(RDB$CHARACTER_SET_NAME) FROM RDB$DATABASE;
SELECT '{p};DIALECT;' || MON$SQL_DIALECT FROM MON$DATABASE;
SELECT '{p};ODS;' || MON$ODS_MAJOR || '.' || MON$ODS_MINOR FROM MON$DATABASE;
SELECT '{p};PAGESIZE;' || MON$PAGE_SIZE FROM MON$DATABASE;

SELECT '{p};COUNT;TABLES;' || COUNT(*) FROM RDB$RELATIONS
  WHERE RDB$RELATION_TYPE = 0 AND COALESCE(RDB$SYSTEM_FLAG,0) = 0
    AND RDB$EXTERNAL_FILE IS NULL;
SELECT '{p};COUNT;VIEWS;' || COUNT(*) FROM RDB$RELATIONS
  WHERE RDB$RELATION_TYPE = 1 AND COALESCE(RDB$SYSTEM_FLAG,0) = 0;
SELECT '{p};COUNT;PROCEDURES;' || COUNT(*) FROM RDB$PROCEDURES
  WHERE COALESCE(RDB$SYSTEM_FLAG,0) = 0;
SELECT '{p};COUNT;TRIGGERS;' || COUNT(*) FROM RDB$TRIGGERS
  WHERE COALESCE(RDB$SYSTEM_FLAG,0) = 0;
SELECT '{p};COUNT;GENERATORS;' || COUNT(*) FROM RDB$GENERATORS
  WHERE COALESCE(RDB$SYSTEM_FLAG,0) = 0;
SELECT '{p};COUNT;DOMAINS;' || COUNT(*) FROM RDB$FIELDS
  WHERE COALESCE(RDB$SYSTEM_FLAG,0) = 0 AND RDB$FIELD_NAME NOT STARTING WITH 'RDB$';
SELECT '{p};COUNT;INDICES;' || COUNT(*) FROM RDB$INDICES
  WHERE COALESCE(RDB$SYSTEM_FLAG,0) = 0;
SELECT '{p};COUNT;FK;' || COUNT(*) FROM RDB$RELATION_CONSTRAINTS
  WHERE RDB$CONSTRAINT_TYPE = 'FOREIGN KEY';

SELECT '{p};TABLE;' || TRIM(RDB$RELATION_NAME) FROM RDB$RELATIONS
  WHERE RDB$RELATION_TYPE = 0 AND COALESCE(RDB$SYSTEM_FLAG,0) = 0
    AND RDB$EXTERNAL_FILE IS NULL
  ORDER BY RDB$RELATION_NAME;

SELECT '{p};UDF;' || TRIM(RDB$FUNCTION_NAME) FROM RDB$FUNCTIONS
  WHERE COALESCE(RDB$SYSTEM_FLAG,0) = 0;

SELECT '{p};EXTTABLE;' || TRIM(RDB$RELATION_NAME) FROM RDB$RELATIONS
  WHERE RDB$EXTERNAL_FILE IS NOT NULL;

SELECT '{p};RESVD;' || TRIM(RDB$RELATION_NAME) FROM RDB$RELATIONS
  WHERE TRIM(RDB$RELATION_NAME) IN ({reservadas});
SELECT '{p};RESVD;' || TRIM(RDB$FIELD_NAME) FROM RDB$RELATION_FIELDS
  WHERE TRIM(RDB$FIELD_NAME) IN ({reservadas});
SELECT '{p};RESVD;' || TRIM(RDB$PROCEDURE_NAME) FROM RDB$PROCEDURES
  WHERE TRIM(RDB$PROCEDURE_NAME) IN ({reservadas});
SELECT '{p};RESVD;' || TRIM(RDB$TRIGGER_NAME) FROM RDB$TRIGGERS
  WHERE TRIM(RDB$TRIGGER_NAME) IN ({reservadas});
SELECT '{p};RESVD;' || TRIM(RDB$GENERATOR_NAME) FROM RDB$GENERATORS
  WHERE TRIM(RDB$GENERATOR_NAME) IN ({reservadas});
SELECT '{p};RESVD;' || TRIM(RDB$FIELD_NAME) FROM RDB$FIELDS
  WHERE TRIM(RDB$FIELD_NAME) IN ({reservadas});
SELECT '{p};RESVD;' || TRIM(RDB$PARAMETER_NAME) FROM RDB$PROCEDURE_PARAMETERS
  WHERE TRIM(RDB$PARAMETER_NAME) IN ({reservadas});
"""


def parse_isql_saida(texto: str) -> dict:
    """Lê as linhas 'MIG;...' produzidas por montar_script_isql()."""
    out: dict = {
        "charset_padrao": "?",
        "sql_dialect": 0,
        "ods": "?",
        "page_size": 0,
        "contagens": {},
        "tabelas": [],
        "udfs": [],
        "tabelas_externas": [],
        "identificadores": [],
    }
    for linha in texto.splitlines():
        s = linha.strip()
        if not s.startswith(_PREFIXO + ";"):
            continue
        partes = s.split(";")
        chave = partes[1] if len(partes) > 1 else ""
        if chave == "CHARSET":
            out["charset_padrao"] = partes[2].strip() if len(partes) > 2 else "?"
        elif chave == "DIALECT":
            out["sql_dialect"] = _int(partes[2] if len(partes) > 2 else "")
        elif chave == "ODS":
            out["ods"] = partes[2].strip() if len(partes) > 2 else "?"
        elif chave == "PAGESIZE":
            out["page_size"] = _int(partes[2] if len(partes) > 2 else "")
        elif chave == "COUNT" and len(partes) > 3:
            out["contagens"][partes[2]] = _int(partes[3])
        elif chave == "TABLE" and len(partes) > 2:
            out["tabelas"].append(partes[2].strip())
        elif chave == "UDF" and len(partes) > 2:
            out["udfs"].append(partes[2].strip())
        elif chave == "EXTTABLE" and len(partes) > 2:
            out["tabelas_externas"].append(partes[2].strip())
        elif chave == "RESVD" and len(partes) > 2:
            out["identificadores"].append(partes[2].strip())
    return out


def _int(s: str) -> int:
    try:
        return int(str(s).strip())
    except (ValueError, TypeError):
        return 0


# --------------------------------------------------------------------------- #
# Orquestração
# --------------------------------------------------------------------------- #
def _rodar_gstat(inst: InstalacaoFirebird, caminho_fdb: str, usuario: str, senha: str) -> str:
    if not inst.gstat_path:
        return ""
    cmd = [str(inst.gstat_path), "-h", caminho_fdb]
    if usuario:
        cmd += ["-user", usuario]
    if senha:
        cmd += ["-password", senha]
    logger.info(f"Análise: {logger.mascarar_segredos(' '.join(cmd))}")
    try:
        r = subprocess.run(cmd, capture_output=True, text=True, timeout=_TIMEOUT_GSTAT)
    except (OSError, subprocess.TimeoutExpired) as e:
        logger.aviso(f"gstat -h falhou: {e}")
        return ""
    return f"{r.stdout or ''}\n{r.stderr or ''}"


def _rodar_isql(inst: InstalacaoFirebird, caminho_fdb: str, usuario: str, senha: str) -> tuple[str, str]:
    if not inst.isql_path:
        return "", "isql.exe não encontrado na instalação de origem."
    script = montar_script_isql()
    tmp = Path(tempfile.mkstemp(prefix="mig_analise_", suffix=".sql")[1])
    tmp.write_text(script, encoding="utf-8")
    try:
        cmd = [
            str(inst.isql_path),
            "-q",
            "-ch", "NONE",
            "-i", str(tmp),
        ]
        if usuario:
            cmd += ["-user", usuario]
        if senha:
            cmd += ["-password", senha]
        cmd.append(caminho_fdb)
        logger.info(f"Análise: {logger.mascarar_segredos(' '.join(cmd))}")
        try:
            r = subprocess.run(cmd, capture_output=True, text=True, timeout=_TIMEOUT_ISQL)
        except (OSError, subprocess.TimeoutExpired) as e:
            return "", f"isql falhou: {e}"
        return f"{r.stdout or ''}", f"{r.stderr or ''}"
    finally:
        try:
            tmp.unlink()
        except OSError:
            pass


def analisar(
    inst_origem: InstalacaoFirebird,
    caminho_fdb: str,
    usuario: str,
    senha: str,
) -> ResultadoAnalise:
    caminho = str(Path(caminho_fdb))
    res = ResultadoAnalise(caminho=caminho)

    try:
        res.tamanho_bytes = Path(caminho).stat().st_size
    except OSError as e:
        res.erros.append(f"Não foi possível ler o arquivo de origem: {e}")
        return res

    # Cabeçalho via gstat -h
    saida_gstat = _rodar_gstat(inst_origem, caminho, usuario, senha)
    header = parse_gstat_header(saida_gstat)
    res.ods = header.get("ods", "?")
    res.page_size = header.get("page_size", 0)
    res.sql_dialect = header.get("sql_dialect", 0)
    res.forced_writes = header.get("forced_writes", "?")
    res.sweep_interval = header.get("sweep_interval", 0)
    res.atributos = header.get("atributos", [])

    # Metadados via isql
    stdout_isql, stderr_isql = _rodar_isql(inst_origem, caminho, usuario, senha)
    dados = parse_isql_saida(stdout_isql)

    res.charset_padrao = dados["charset_padrao"]
    if not res.sql_dialect:
        res.sql_dialect = dados["sql_dialect"]
    if res.ods == "?":
        res.ods = dados["ods"]
    if not res.page_size:
        res.page_size = dados["page_size"]

    c = dados["contagens"]
    res.tabelas = dados["tabelas"]
    res.qtd_tabelas = c.get("TABLES", len(res.tabelas))
    res.qtd_views = c.get("VIEWS", 0)
    res.qtd_procedures = c.get("PROCEDURES", 0)
    res.qtd_triggers = c.get("TRIGGERS", 0)
    res.qtd_generators = c.get("GENERATORS", 0)
    res.qtd_domains = c.get("DOMAINS", 0)
    res.qtd_indices = c.get("INDICES", 0)
    res.qtd_foreign_keys = c.get("FK", 0)
    res.udfs = dados["udfs"]
    res.tabelas_externas = dados["tabelas_externas"]
    res.identificadores = dados["identificadores"]

    # Diagnóstico: se nem o gstat nem o isql trouxeram nada útil, é erro.
    if res.ods == "?" and not res.tabelas and not c:
        msg = "Não foi possível ler os metadados do banco de origem."
        erro_texto = (stderr_isql or "").strip() or (saida_gstat or "").strip()
        if erro_texto:
            msg += f" Detalhe: {erro_texto.splitlines()[-1][:300]}"
        res.erros.append(msg)
    else:
        erro_texto = (stderr_isql or "").strip()
        if "Statement failed" in erro_texto or "SQLSTATE" in erro_texto:
            res.observacoes.append(
                "Algumas consultas de metadados retornaram erro (a análise pode "
                f"estar incompleta): {erro_texto.splitlines()[-1][:200]}"
            )

    return res
