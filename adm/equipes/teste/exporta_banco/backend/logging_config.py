"""
Configuração de logging da aplicação.

Grava em arquivo (logs/app.log, com rotação) além do console, para que
erros e eventos de conexão possam ser diagnosticados depois, sem precisar
reproduzir manualmente o problema.
"""
from __future__ import annotations

import logging
from logging.handlers import RotatingFileHandler
from pathlib import Path

LOG_DIR = Path(__file__).resolve().parent.parent / "logs"
LOG_FILE = LOG_DIR / "app.log"


def configurar_logging() -> logging.Logger:
    LOG_DIR.mkdir(exist_ok=True)

    logger = logging.getLogger("inspetor_firebird")
    if logger.handlers:
        # Evita duplicar handlers se essa função for chamada mais de uma vez
        # (por exemplo, em recarregamentos do uvicorn).
        return logger

    logger.setLevel(logging.INFO)

    formato = logging.Formatter(
        "%(asctime)s [%(levelname)s] %(message)s", datefmt="%Y-%m-%d %H:%M:%S"
    )

    arquivo = RotatingFileHandler(LOG_FILE, maxBytes=2_000_000, backupCount=3, encoding="utf-8")
    arquivo.setFormatter(formato)
    logger.addHandler(arquivo)

    return logger


logger = configurar_logging()
