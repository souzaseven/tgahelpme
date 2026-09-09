"""
logger.py
---------
Log em arquivo (logs/migration_AAAAMMDD_HHMMSS.log) + distribuição das mesmas
linhas, em tempo real, para quem quiser exibir na interface (callbacks).

Regra de segurança (do master prompt): senha NUNCA vai para o log. Toda linha
passa por `mascarar_segredos` antes de ser gravada ou repassada.
"""
from __future__ import annotations

import re
import time
from datetime import datetime, timedelta
from pathlib import Path
from typing import Callable

from config import DIAS_RETENCAO_LOG, LOGS_DIR

# Padrões de "senha" em linhas de comando e mensagens do gbak/gfix/isql.
_PADROES_SEGREDO = [
    re.compile(r"(-pas?s?w?o?r?d?\s+)(\S+)", re.IGNORECASE),  # -pw / -pass / -password X
    re.compile(r"(--password[=\s]+)(\S+)", re.IGNORECASE),
    re.compile(r"(\bpassword\s*[:=]\s*)(\S+)", re.IGNORECASE),
    re.compile(r"(ISC_PASSWORD\s*[:=]\s*)(\S+)", re.IGNORECASE),
]

_MASCARA = "********"

_ouvintes: list[Callable[[str], None]] = []
_arquivo_log: Path | None = None


def mascarar_segredos(texto: str) -> str:
    """Substitui o valor de senha por asteriscos, preservando o rótulo."""
    for padrao in _PADROES_SEGREDO:
        texto = padrao.sub(lambda m: m.group(1) + _MASCARA, texto)
    return texto


def iniciar_arquivo_log() -> Path:
    """Cria um novo arquivo de log para esta execução e devolve o caminho."""
    global _arquivo_log
    LOGS_DIR.mkdir(parents=True, exist_ok=True)
    nome = f"migration_{datetime.now():%Y%m%d_%H%M%S}.log"
    _arquivo_log = LOGS_DIR / nome
    _arquivo_log.touch()
    return _arquivo_log


def arquivo_log_atual() -> Path | None:
    return _arquivo_log


def registrar_ouvinte(callback: Callable[[str], None]) -> None:
    """A interface registra aqui uma função que recebe cada linha já formatada."""
    _ouvintes.append(callback)


def remover_ouvinte(callback: Callable[[str], None]) -> None:
    if callback in _ouvintes:
        _ouvintes.remove(callback)


def _emitir(nivel: str, mensagem: str) -> None:
    linha = f"{datetime.now():%Y-%m-%d %H:%M:%S} [{nivel}] {mascarar_segredos(mensagem)}"
    if _arquivo_log is not None:
        try:
            with _arquivo_log.open("a", encoding="utf-8") as fh:
                fh.write(linha + "\n")
        except OSError:
            pass
    for cb in list(_ouvintes):
        try:
            cb(linha)
        except Exception:
            # Um ouvinte com defeito nunca pode derrubar o fluxo de migração.
            pass


def info(mensagem: str) -> None:
    _emitir("INFO", mensagem)


def aviso(mensagem: str) -> None:
    _emitir("AVISO", mensagem)


def erro(mensagem: str) -> None:
    _emitir("ERRO", mensagem)


def limpar_logs_antigos() -> int:
    """Apaga logs com mais de DIAS_RETENCAO_LOG dias. Devolve quantos apagou."""
    if not LOGS_DIR.is_dir():
        return 0
    limite = time.time() - timedelta(days=DIAS_RETENCAO_LOG).total_seconds()
    removidos = 0
    for arq in LOGS_DIR.glob("migration_*.log"):
        try:
            if arq.stat().st_mtime < limite:
                arq.unlink()
                removidos += 1
        except OSError:
            continue
    return removidos
