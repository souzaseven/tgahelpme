"""
core/espaco.py
--------------
Estimativa e checagem de espaço em disco antes de migrar (regra do master
prompt: "não iniciar se o espaço disponível for claramente insuficiente").

O fluxo cria dois arquivos além do original: o backup `.fbk` e o `.fdb` novo.
A conta é toda em bytes; as funções principais são puras e testáveis.
"""
from __future__ import annotations

import shutil
from dataclasses import dataclass
from pathlib import Path


@dataclass
class EstimativaEspaco:
    tamanho_origem: int
    estimado_fbk: int
    estimado_fdb: int
    necessario_com_margem: int
    disponivel_no_destino: int

    @property
    def suficiente(self) -> bool:
        return self.disponivel_no_destino >= self.necessario_com_margem

    @property
    def faltando(self) -> int:
        return max(0, self.necessario_com_margem - self.disponivel_no_destino)


def formatar_bytes(n: int) -> str:
    passo = 1024.0
    valor = float(n)
    for unidade in ("B", "KB", "MB", "GB", "TB"):
        if valor < passo or unidade == "TB":
            return f"{valor:.1f} {unidade}" if unidade != "B" else f"{int(valor)} B"
        valor /= passo
    return f"{valor:.1f} TB"


def estimar(
    tamanho_origem: int,
    disponivel_no_destino: int,
    *,
    fator_fbk: float,
    fator_fdb: float,
    margem: float,
) -> EstimativaEspaco:
    """Todas as entradas em bytes. `fator_fbk`/`fator_fdb` são frações do
    tamanho do `.fdb` de origem; `margem` é o multiplicador de segurança."""
    estimado_fbk = int(tamanho_origem * fator_fbk)
    estimado_fdb = int(tamanho_origem * fator_fdb)
    necessario = int((estimado_fbk + estimado_fdb) * margem)
    return EstimativaEspaco(
        tamanho_origem=tamanho_origem,
        estimado_fbk=estimado_fbk,
        estimado_fdb=estimado_fdb,
        necessario_com_margem=necessario,
        disponivel_no_destino=disponivel_no_destino,
    )


def espaco_livre(pasta: str | Path) -> int:
    """Bytes livres na unidade que contém `pasta`. Sobe na árvore até achar
    um diretório existente (a pasta de destino pode ainda não ter sido criada)."""
    p = Path(pasta).resolve()
    while not p.exists() and p != p.parent:
        p = p.parent
    return shutil.disk_usage(p).free


def checar(
    origem_fdb: str | Path,
    pasta_destino: str | Path,
    *,
    fator_fbk: float,
    fator_fdb: float,
    margem: float,
) -> EstimativaEspaco:
    tamanho = Path(origem_fdb).stat().st_size
    return estimar(
        tamanho,
        espaco_livre(pasta_destino),
        fator_fbk=fator_fbk,
        fator_fdb=fator_fdb,
        margem=margem,
    )
