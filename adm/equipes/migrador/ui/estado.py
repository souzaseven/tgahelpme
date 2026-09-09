"""
ui/estado.py
------------
Estado compartilhado entre as abas durante uma sessão de migração. A janela
principal cria um único objeto e passa para todas as abas.
"""
from __future__ import annotations

from dataclasses import dataclass, field

from core.firebird import InstalacaoFirebird
from core.migracao import OpcoesMigracao
from core.modelos import Aviso, ResultadoAnalise


@dataclass
class Estado:
    instalacoes: list[InstalacaoFirebird] = field(default_factory=list)

    inst_origem: InstalacaoFirebird | None = None
    inst_destino: InstalacaoFirebird | None = None

    caminho_origem: str = ""
    usuario: str = "SYSDBA"
    senha: str = "masterkey"

    analise: ResultadoAnalise | None = None
    avisos: list[Aviso] = field(default_factory=list)

    pasta_destino: str = ""
    nome_fbk: str = ""
    nome_destino: str = ""
    opcoes: OpcoesMigracao = field(default_factory=OpcoesMigracao)
    validacao_completa: bool = True

    @property
    def pronto_para_analisar(self) -> bool:
        return bool(self.caminho_origem and self.inst_origem and self.inst_destino)
