"""
config.py
---------
Leitura/gravação das preferências do Migrador Firebird.

Regra de segurança (do master prompt): este módulo NUNCA grava senha em disco.
Usuário e senha de conexão ficam apenas em memória durante a execução.

Quando rodando como .exe empacotado (PyInstaller), `__file__` aponta para a
pasta temporária onde o bootloader extrai os arquivos, que some ao fechar o
programa. Por isso `BASE_DIR` usa a pasta do executável real quando congelado —
assim `config/` e `logs/` sobrevivem entre execuções.
"""
from __future__ import annotations

import json
import sys
from dataclasses import asdict, dataclass
from pathlib import Path

if getattr(sys, "frozen", False):
    BASE_DIR = Path(sys.executable).resolve().parent
else:
    BASE_DIR = Path(__file__).resolve().parent

CONFIG_DIR = BASE_DIR / "config"
CONFIG_FILE = CONFIG_DIR / "settings.json"
LOGS_DIR = BASE_DIR / "logs"

# Logs mais antigos que isto são apagados ao abrir o programa.
DIAS_RETENCAO_LOG = 90


@dataclass
class Settings:
    # Pasta sugerida ao abrir o diálogo de seleção do banco de origem (.fdb).
    pasta_padrao_origem: str = ""
    # Pasta sugerida ao escolher onde gravar o .fbk e o .fdb migrado.
    pasta_padrao_destino: str = ""
    # Caminho do gbak.exe do Firebird 2.5, informado manualmente pelo usuário
    # quando a detecção automática não encontra uma instalação 2.5. Se vazio,
    # a detecção automática decide.
    gbak_origem_preferencial: str = ""
    # Idem para o Firebird de destino (5.0). Normalmente a detecção acha.
    gbak_destino_preferencial: str = ""
    # Usuário sugerido no campo de conexão (não é segredo).
    usuario_padrao: str = "SYSDBA"
    # Opções de restore lembradas entre execuções.
    page_size_padrao: str = "(preservar do backup)"
    validacao_completa_padrao: bool = True
    trabalhar_sobre_copia_padrao: bool = False
    # Margem de segurança sobre a estimativa de espaço em disco.
    margem_seguranca_espaco: float = 1.3
    # Estimativas de tamanho (fração do tamanho do .fdb de origem).
    fator_fbk_por_fdb: float = 0.4
    fator_fdb_restaurado_por_fdb: float = 2.0

    def to_dict(self) -> dict:
        return asdict(self)


def _garantir_pastas() -> None:
    CONFIG_DIR.mkdir(parents=True, exist_ok=True)
    LOGS_DIR.mkdir(parents=True, exist_ok=True)


def carregar_configuracoes() -> Settings:
    """Carrega settings.json; se não existir ou estiver corrompido, usa os
    padrões e recria o arquivo (nunca derruba a aplicação por config ruim)."""
    _garantir_pastas()
    if not CONFIG_FILE.exists():
        settings = Settings()
        salvar_configuracoes(settings)
        return settings
    try:
        dados = json.loads(CONFIG_FILE.read_text(encoding="utf-8"))
        campos_validos = set(Settings.__dataclass_fields__)
        return Settings(**{k: v for k, v in dados.items() if k in campos_validos})
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
