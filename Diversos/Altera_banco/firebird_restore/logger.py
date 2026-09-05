"""
logger.py
---------
Log em arquivo (para suporte/diagnóstico) + distribuição de mensagens para
quem quiser acompanhar em tempo real (a interface gráfica registra um
"listener" e recebe cada linha assim que ela é gravada).

Regra de segurança: nenhuma senha é gravada em log. Todo texto que passar
por aqui é filtrado por mask_secrets() antes de ser persistido ou exibido.
"""
from __future__ import annotations

import logging
import re
from datetime import datetime
from pathlib import Path
from typing import Callable, List

from config import LOGS_DIR

_LISTENERS: List[Callable[[str, str], None]] = []


def mask_secrets(texto: str, segredos: list[str]) -> str:
    """Substitui qualquer ocorrência literal dos segredos (ex.: senha digitada)
    por asteriscos, em qualquer texto que vá para log, tela ou mensagem de erro."""
    if not texto:
        return texto
    resultado = texto
    for segredo in segredos:
        if segredo:
            resultado = resultado.replace(segredo, "******")
    # Cobre também o padrão "-password <algo>" caso escape por algum motivo
    resultado = re.sub(
        r"(-password\s+)\S+", r"\1******", resultado, flags=re.IGNORECASE
    )
    return resultado


def _configurar_logging_arquivo() -> logging.Logger:
    LOGS_DIR.mkdir(parents=True, exist_ok=True)
    nome_arquivo = LOGS_DIR / f"restore_{datetime.now():%Y%m%d}.log"

    logger = logging.getLogger("firebird_restore")
    logger.setLevel(logging.INFO)
    if not logger.handlers:
        handler = logging.FileHandler(nome_arquivo, encoding="utf-8")
        handler.setFormatter(
            logging.Formatter("%(asctime)s [%(levelname)s] %(message)s", "%Y-%m-%d %H:%M:%S")
        )
        logger.addHandler(handler)
    return logger


_logger = _configurar_logging_arquivo()


def registrar_listener(callback: Callable[[str, str], None]) -> None:
    """callback(nivel, mensagem) é chamado a cada nova entrada de log,
    além de ser gravada em arquivo. Usado pela interface para o painel
    'DETALHES' em tempo real."""
    _LISTENERS.append(callback)


def _emitir(nivel: str, mensagem: str) -> None:
    for callback in list(_LISTENERS):
        try:
            callback(nivel, mensagem)
        except Exception:
            # Um listener com problema (ex.: janela já fechada) não pode
            # derrubar o processo de restauração.
            pass


def info(mensagem: str, segredos: list[str] | None = None) -> None:
    mensagem = mask_secrets(mensagem, segredos or [])
    _logger.info(mensagem)
    _emitir("INFO", mensagem)


def aviso(mensagem: str, segredos: list[str] | None = None) -> None:
    mensagem = mask_secrets(mensagem, segredos or [])
    _logger.warning(mensagem)
    _emitir("AVISO", mensagem)


def erro(mensagem: str, segredos: list[str] | None = None) -> None:
    mensagem = mask_secrets(mensagem, segredos or [])
    _logger.error(mensagem)
    _emitir("ERRO", mensagem)


def caminho_log_atual() -> Path:
    return LOGS_DIR / f"restore_{datetime.now():%Y%m%d}.log"
