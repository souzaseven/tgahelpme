"""
core/modelos.py
---------------
Estruturas de dados compartilhadas entre os módulos do núcleo (análise,
compatibilidade, migração, comparação, relatório). Ficam num arquivo só para
evitar import circular entre eles.
"""
from __future__ import annotations

from dataclasses import dataclass, field


@dataclass
class ResultadoAnalise:
    """Retrato do banco de ORIGEM, montado por core/analise.py a partir de
    `gstat -h` e de consultas às tabelas RDB$ via `isql`."""
    caminho: str
    tamanho_bytes: int = 0

    # Cabeçalho (gstat -h)
    ods: str = "?"                 # ex.: "11.2"
    page_size: int = 0
    forced_writes: str = "?"
    sweep_interval: int = 0
    atributos: list[str] = field(default_factory=list)

    # Metadados (isql)
    sql_dialect: int = 0
    charset_padrao: str = "?"

    tabelas: list[str] = field(default_factory=list)
    qtd_tabelas: int = 0
    qtd_views: int = 0
    qtd_procedures: int = 0
    qtd_triggers: int = 0
    qtd_generators: int = 0
    qtd_domains: int = 0
    qtd_indices: int = 0
    qtd_foreign_keys: int = 0

    # Itens que alimentam a análise de compatibilidade
    udfs: list[str] = field(default_factory=list)          # nomes de RDB$FUNCTIONS externas
    tabelas_externas: list[str] = field(default_factory=list)
    identificadores: list[str] = field(default_factory=list)  # nomes de objetos p/ checar palavra reservada

    # Diagnóstico
    erros: list[str] = field(default_factory=list)     # falhas que impedem prosseguir
    observacoes: list[str] = field(default_factory=list)

    @property
    def familia_provavel(self) -> str:
        """Família Firebird deduzida da ODS (não da extensão do arquivo)."""
        mapa = {"11": "2.5", "12": "3.0", "13": "4.0/5.0"}
        return mapa.get(self.ods.split(".")[0], "?")

    @property
    def ok(self) -> bool:
        return not self.erros


@dataclass
class Aviso:
    """Um ponto de atenção de compatibilidade. `nivel`:
    - "info"     — informativo, não exige ação
    - "atencao"  — a migração segue, mas algo precisa de ajuste depois
    - "bloqueio" — risco de perda de dados; a migração não deve prosseguir
    """
    nivel: str
    categoria: str
    titulo: str
    detalhe: str
    itens: list[str] = field(default_factory=list)


@dataclass
class TabelaComparada:
    nome: str
    registros_origem: int | None
    registros_destino: int | None

    @property
    def status(self) -> str:
        if self.registros_origem is None or self.registros_destino is None:
            return "INDETERMINADO"
        return "OK" if self.registros_origem == self.registros_destino else "DIVERGENTE"


@dataclass
class ResultadoComparacao:
    tabelas: list[TabelaComparada] = field(default_factory=list)
    generators_origem: int = 0
    generators_destino: int = 0
    observacoes: list[str] = field(default_factory=list)

    @property
    def total_origem(self) -> int:
        return sum(t.registros_origem or 0 for t in self.tabelas)

    @property
    def total_destino(self) -> int:
        return sum(t.registros_destino or 0 for t in self.tabelas)

    @property
    def divergentes(self) -> list[TabelaComparada]:
        return [t for t in self.tabelas if t.status == "DIVERGENTE"]

    @property
    def indeterminadas(self) -> list[TabelaComparada]:
        return [t for t in self.tabelas if t.status == "INDETERMINADO"]

    @property
    def tudo_confere(self) -> bool:
        return bool(self.tabelas) and not self.divergentes and not self.indeterminadas


@dataclass
class ResultadoMigracao:
    """Preenchido por core/migracao.py ao longo do fluxo backup -> restore."""
    caminho_origem: str
    caminho_fbk: str = ""
    caminho_destino: str = ""

    backup_ok: bool = False
    restore_ok: bool = False

    # Classificação da saída do gbak
    erro_fatal: str = ""                       # vazio = sem erro fatal
    erros_indice_nao_fatais: int = 0           # FK/índice órfão dentro de backup íntegro
    linhas_erro_gbak: list[str] = field(default_factory=list)

    registros_reportados_gbak: int = 0
    duracao_backup_seg: float = 0.0
    duracao_restore_seg: float = 0.0

    @property
    def sucesso(self) -> bool:
        return self.backup_ok and self.restore_ok and not self.erro_fatal
