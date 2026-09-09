"""
core/validacao.py
-----------------
Valida o banco recém-migrado usando as ferramentas do Firebird de DESTINO (5.0):

- `gstat -h` no arquivo novo -> confirma a ODS (13.x no Firebird 5.0) e o page size;
- `isql` -> `SELECT 1 FROM RDB$DATABASE` (a conexão abre) + recontagem dos metadados;
- opcional (ligado por padrão): `gfix -v -full` -> conta erros/avisos de integridade.

Critério de sucesso do master prompt: "conexão ao destino funciona; metadados
foram carregados; tabelas existem; não existem erros críticos".
"""
from __future__ import annotations

import re
import subprocess
import tempfile
from dataclasses import dataclass, field
from pathlib import Path

import logger
from core.analise import parse_gstat_header
from core.firebird import InstalacaoFirebird

_TIMEOUT_ISQL = 180
_TIMEOUT_GSTAT = 120
_TIMEOUT_GFIX = 3 * 3600
_P = "VAL"


@dataclass
class ResultadoValidacao:
    conecta_ok: bool = False
    ods: str = "?"
    page_size: int = 0

    qtd_tabelas: int = 0
    qtd_views: int = 0
    qtd_procedures: int = 0
    qtd_triggers: int = 0
    qtd_generators: int = 0

    gfix_executado: bool = False
    gfix_erros: int = 0
    gfix_avisos: int = 0

    mensagens: list[str] = field(default_factory=list)

    @property
    def ok(self) -> bool:
        return self.conecta_ok and self.qtd_tabelas > 0 and self.gfix_erros == 0


def _script_validacao() -> str:
    p = _P
    return f"""SET HEADING OFF;
SET LIST OFF;
SET WIDTH 0;
SELECT '{p};CONECTA;' || 1 FROM RDB$DATABASE;
SELECT '{p};ODS;' || MON$ODS_MAJOR || '.' || MON$ODS_MINOR FROM MON$DATABASE;
SELECT '{p};PAGESIZE;' || MON$PAGE_SIZE FROM MON$DATABASE;
SELECT '{p};TABLES;' || COUNT(*) FROM RDB$RELATIONS
  WHERE RDB$RELATION_TYPE = 0 AND COALESCE(RDB$SYSTEM_FLAG,0) = 0 AND RDB$EXTERNAL_FILE IS NULL;
SELECT '{p};VIEWS;' || COUNT(*) FROM RDB$RELATIONS
  WHERE RDB$RELATION_TYPE = 1 AND COALESCE(RDB$SYSTEM_FLAG,0) = 0;
SELECT '{p};PROCEDURES;' || COUNT(*) FROM RDB$PROCEDURES WHERE COALESCE(RDB$SYSTEM_FLAG,0) = 0;
SELECT '{p};TRIGGERS;' || COUNT(*) FROM RDB$TRIGGERS WHERE COALESCE(RDB$SYSTEM_FLAG,0) = 0;
SELECT '{p};GENERATORS;' || COUNT(*) FROM RDB$GENERATORS WHERE COALESCE(RDB$SYSTEM_FLAG,0) = 0;
"""


def _parse_validacao(texto: str) -> dict:
    out: dict = {}
    for linha in texto.splitlines():
        s = linha.strip()
        if not s.startswith(_P + ";"):
            continue
        partes = s.split(";")
        if len(partes) < 3:
            continue
        chave, valor = partes[1], partes[2].strip()
        out[chave] = valor
    return out


def _rodar_isql(inst: InstalacaoFirebird, fdb: str, usuario: str, senha: str) -> tuple[str, str]:
    if not inst.isql_path:
        return "", "isql.exe não encontrado na instalação de destino."
    tmp = Path(tempfile.mkstemp(prefix="mig_valida_", suffix=".sql")[1])
    tmp.write_text(_script_validacao(), encoding="utf-8")
    try:
        cmd = [str(inst.isql_path), "-q", "-ch", "NONE", "-i", str(tmp)]
        if usuario:
            cmd += ["-user", usuario]
        if senha:
            cmd += ["-password", senha]
        cmd.append(fdb)
        logger.info("Validação: " + logger.mascarar_segredos(" ".join(cmd)))
        try:
            r = subprocess.run(cmd, capture_output=True, text=True, timeout=_TIMEOUT_ISQL)
        except (OSError, subprocess.TimeoutExpired) as e:
            return "", f"isql falhou: {e}"
        return r.stdout or "", r.stderr or ""
    finally:
        try:
            tmp.unlink()
        except OSError:
            pass


def _rodar_gstat(inst: InstalacaoFirebird, fdb: str, usuario: str, senha: str) -> str:
    if not inst.gstat_path:
        return ""
    cmd = [str(inst.gstat_path), "-h", fdb]
    if usuario:
        cmd += ["-user", usuario]
    if senha:
        cmd += ["-password", senha]
    try:
        r = subprocess.run(cmd, capture_output=True, text=True, timeout=_TIMEOUT_GSTAT)
    except (OSError, subprocess.TimeoutExpired) as e:
        logger.aviso(f"gstat -h (validação) falhou: {e}")
        return ""
    return f"{r.stdout or ''}\n{r.stderr or ''}"


_RE_GFIX_SUMMARY = re.compile(
    r"(\d+)\s+records?\s+in error|"
    r"Number of record level errors\s*:\s*(\d+)|"
    r"Number of.*warnings?\s*:\s*(\d+)",
    re.IGNORECASE,
)


def _rodar_gfix_full(inst: InstalacaoFirebird, fdb: str, usuario: str, senha: str) -> tuple[int, int, str]:
    if not inst.gfix_path:
        return 0, 0, "gfix.exe não encontrado — validação completa pulada."
    cmd = [str(inst.gfix_path), "-v", "-full", fdb]
    if usuario:
        cmd += ["-user", usuario]
    if senha:
        cmd += ["-password", senha]
    logger.info("Validação completa: " + logger.mascarar_segredos(" ".join(cmd)))
    try:
        r = subprocess.run(cmd, capture_output=True, text=True, timeout=_TIMEOUT_GFIX)
    except (OSError, subprocess.TimeoutExpired) as e:
        return 0, 0, f"gfix -v -full falhou: {e}"
    saida = f"{r.stdout or ''}\n{r.stderr or ''}"
    erros = len(re.findall(r"\berror\b", saida, re.IGNORECASE))
    avisos = len(re.findall(r"\bwarning\b", saida, re.IGNORECASE))
    return erros, avisos, saida.strip()


def validar(
    inst_destino: InstalacaoFirebird,
    destino_fdb: str,
    usuario: str,
    senha: str,
    *,
    completa: bool = True,
) -> ResultadoValidacao:
    res = ResultadoValidacao()
    destino_fdb = str(Path(destino_fdb))

    header = parse_gstat_header(_rodar_gstat(inst_destino, destino_fdb, usuario, senha))
    res.ods = header.get("ods", "?")
    res.page_size = header.get("page_size", 0)

    stdout_isql, stderr_isql = _rodar_isql(inst_destino, destino_fdb, usuario, senha)
    dados = _parse_validacao(stdout_isql)
    res.conecta_ok = dados.get("CONECTA") == "1"
    if res.ods == "?":
        res.ods = dados.get("ODS", "?")
    if not res.page_size:
        try:
            res.page_size = int(dados.get("PAGESIZE", "0"))
        except ValueError:
            res.page_size = 0
    res.qtd_tabelas = _i(dados.get("TABLES"))
    res.qtd_views = _i(dados.get("VIEWS"))
    res.qtd_procedures = _i(dados.get("PROCEDURES"))
    res.qtd_triggers = _i(dados.get("TRIGGERS"))
    res.qtd_generators = _i(dados.get("GENERATORS"))

    if not res.conecta_ok:
        detalhe = (stderr_isql or "").strip().splitlines()[-1:] or ["sem detalhe"]
        res.mensagens.append(f"Não foi possível conectar ao banco migrado: {detalhe[0][:200]}")

    if completa:
        erros, avisos, saida = _rodar_gfix_full(inst_destino, destino_fdb, usuario, senha)
        res.gfix_executado = True
        res.gfix_erros = erros
        res.gfix_avisos = avisos
        if erros:
            res.mensagens.append(f"gfix -v -full apontou {erros} erro(s) de integridade.")
        if saida and not saida.lower().startswith("gfix"):
            logger.info("Saída gfix -v -full:\n" + saida)

    return res


def _i(v) -> int:
    try:
        return int(str(v).strip())
    except (ValueError, TypeError):
        return 0
