"""
Armazena e recupera "fotografias" da estrutura do banco (tabelas, campos,
procedures, triggers e generators) para detectar o que mudou depois de uma
atualização do sistema/ERP.

Cada banco (identificado pelo caminho do arquivo) tem seu próprio arquivo de
referência em snapshots/<hash>.json, salvo localmente — nada é enviado para
fora da máquina.
"""
from __future__ import annotations

import hashlib
import json
from pathlib import Path
from typing import Optional

SNAPSHOTS_DIR = Path(__file__).resolve().parent.parent / "snapshots"


def _arquivo_para(database: str) -> Path:
    SNAPSHOTS_DIR.mkdir(exist_ok=True)
    chave = hashlib.md5(database.strip().lower().encode("utf-8")).hexdigest()
    return SNAPSHOTS_DIR / f"{chave}.json"


def carregar_snapshot(database: str) -> Optional[dict]:
    """Retorna {"capturado_em": ..., "database": ..., "schema": {...}} ou None."""
    arquivo = _arquivo_para(database)
    if not arquivo.exists():
        return None
    try:
        return json.loads(arquivo.read_text(encoding="utf-8"))
    except (json.JSONDecodeError, OSError):
        return None


def salvar_snapshot(database: str, schema: dict, capturado_em: str) -> None:
    arquivo = _arquivo_para(database)
    conteudo = {"capturado_em": capturado_em, "database": database, "schema": schema}
    arquivo.write_text(json.dumps(conteudo, ensure_ascii=False, indent=2), encoding="utf-8")
