"""
config.py
---------
Leitura/gravação das configurações do RestauradorFirebird.

Regra de segurança: este módulo NUNCA grava senha em disco. Usuário e senha
de conexão ficam somente em memória durante a execução da interface.
"""
from __future__ import annotations

import json
import os
from dataclasses import asdict, dataclass, field
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent
CONFIG_DIR = BASE_DIR / "config"
CONFIG_FILE = CONFIG_DIR / "settings.json"
LOGS_DIR = BASE_DIR / "logs"


@dataclass
class Settings:
    # Pasta sugerida ao abrir o diálogo de seleção de backup (.fbk / .fbk.gz)
    pasta_padrao_backups: str = ""
    # Pasta sugerida ao escolher onde criar o .fdb restaurado
    pasta_padrao_destino: str = ""
    # Caminho do gbak.exe escolhido manualmente pelo usuário (opcional).
    # Se vazio, a detecção automática decide.
    firebird_path_preferencial: str = ""
    # Multiplicador usado para estimar o tamanho final do .fdb a partir do
    # tamanho do .fbk (um backup Firebird costuma ser bem mais compacto que
    # o banco restaurado). Ajustável porque depende muito dos dados do cliente.
    fator_estimativa_fdb_por_fbk: float = 4.0
    # Margem de segurança adicional sobre a estimativa de espaço em disco.
    margem_seguranca_espaco: float = 1.3
    # Usuário padrão sugerido no campo de conexão (não é segredo).
    usuario_padrao: str = "SYSDBA"

    def to_dict(self) -> dict:
        return asdict(self)


def _garantir_pastas() -> None:
    CONFIG_DIR.mkdir(parents=True, exist_ok=True)
    LOGS_DIR.mkdir(parents=True, exist_ok=True)


def carregar_configuracoes() -> Settings:
    """Carrega settings.json; se não existir ou estiver corrompido, usa defaults
    e recria o arquivo (nunca derruba a aplicação por causa de config ruim)."""
    _garantir_pastas()
    if not CONFIG_FILE.exists():
        settings = Settings()
        salvar_configuracoes(settings)
        return settings

    try:
        dados = json.loads(CONFIG_FILE.read_text(encoding="utf-8"))
        campos_validos = {f for f in Settings.__dataclass_fields__}
        dados_filtrados = {k: v for k, v in dados.items() if k in campos_validos}
        return Settings(**dados_filtrados)
    except (json.JSONDecodeError, OSError, TypeError):
        settings = Settings()
        salvar_configuracoes(settings)
        return settings


def salvar_configuracoes(settings: Settings) -> None:
    _garantir_pastas()
    CONFIG_FILE.write_text(
        json.dumps(settings.to_dict(), indent=2, ensure_ascii=False),
        encoding="utf-8",
    )
