"""
core/firebird.py
----------------
Descoberta de instalações do Firebird no Windows e leitura da versão real de
cada `gbak.exe` encontrado. Não assume caminho fixo: procura em pastas comuns,
no Registro do Windows e no PATH.

O Migrador precisa distinguir a instalação de ORIGEM (Firebird 2.5, usada para
o backup) da de DESTINO (Firebird 5.0, usada para o restore) — regra do master
prompt: "associar cada ferramenta à sua versão", nunca usar o primeiro
`gbak.exe` que aparecer.
"""
from __future__ import annotations

import re
import subprocess
from dataclasses import dataclass
from pathlib import Path
from shutil import which

import logger

PASTAS_COMUNS = [
    Path(r"C:\Program Files\Firebird"),
    Path(r"C:\Program Files (x86)\Firebird"),
    Path(r"C:\Firebird"),
]

CHAVES_REGISTRO = [
    r"SOFTWARE\Firebird Project\Firebird Server\Instances",
    r"SOFTWARE\WOW6432Node\Firebird Project\Firebird Server\Instances",
]

_TIMEOUT_VERSAO = 10


@dataclass
class InstalacaoFirebird:
    pasta: Path
    gbak_path: Path
    gfix_path: Path | None
    isql_path: Path | None
    gstat_path: Path | None
    versao_texto: str            # linha crua do `gbak -z`
    versao_tupla: tuple[int, ...]  # ex.: (5, 0, 3, 1683)
    origem_deteccao: str         # "pasta" | "registro" | "path" | "manual"

    @property
    def familia(self) -> str:
        """'2.5', '3.0', '4.0', '5.0' — o par major.minor, usado na matriz de
        migração. '?' quando a versão não pôde ser lida."""
        if len(self.versao_tupla) >= 2:
            return f"{self.versao_tupla[0]}.{self.versao_tupla[1]}"
        if len(self.versao_tupla) == 1:
            return f"{self.versao_tupla[0]}.0"
        return "?"

    @property
    def versao_build(self) -> str:
        """Versão completa, ex.: '5.0.3.1683', extraída de
        'gbak: gbak version WI-V5.0.3.1683 Firebird 5.0'."""
        m = re.search(r"V(\d+(?:\.\d+){2,3})", self.versao_texto)
        if m:
            return m.group(1)
        return ".".join(map(str, self.versao_tupla)) or "desconhecida"

    @property
    def rotulo(self) -> str:
        return f"Firebird {self.familia}  ({self.versao_build})  —  {self.pasta}"

    @property
    def rotulo_curto(self) -> str:
        """Versão enxuta para caber num combo — o caminho completo fica no
        tooltip do item."""
        return f"Firebird {self.familia}  ({self.versao_build})"


def _extrair_versao(saida: str) -> tuple[str, tuple[int, ...]]:
    """`gbak -z` imprime algo como:
    'gbak: gbak version WI-V5.0.3.1683 Firebird 5.0'
    Preferimos o número de build de dentro do 'V...'; caímos para o 'Firebird X.Y'."""
    saida = saida.strip()
    m = re.search(r"V(\d+(?:\.\d+){1,3})", saida)
    if m:
        return saida, tuple(int(p) for p in m.group(1).split("."))
    m = re.search(r"Firebird\s+(\d+(?:\.\d+)*)", saida)
    if m:
        return saida, tuple(int(p) for p in m.group(1).split("."))
    return saida or "versão desconhecida", ()


def obter_versao_gbak(gbak_path: Path) -> tuple[str, tuple[int, ...]] | None:
    try:
        resultado = subprocess.run(
            [str(gbak_path), "-z"],
            capture_output=True,
            text=True,
            timeout=_TIMEOUT_VERSAO,
        )
    except (OSError, subprocess.TimeoutExpired):
        return None
    saida = f"{resultado.stdout or ''}\n{resultado.stderr or ''}"
    linha = next((l for l in saida.splitlines() if "version" in l.lower()), saida)
    return _extrair_versao(linha)


def _achar_exe(pasta: Path, nome: str) -> Path | None:
    """Um utilitário do Firebird pode estar na raiz da instalação (Firebird 5.0
    no Windows) ou numa subpasta bin\\ (builds mais antigas)."""
    for candidato in (pasta / nome, pasta / "bin" / nome):
        if candidato.is_file():
            return candidato
    return None


def _montar_instalacao(gbak_path: Path, origem: str) -> InstalacaoFirebird | None:
    info_versao = obter_versao_gbak(gbak_path)
    if info_versao is None:
        return None
    texto, tupla = info_versao
    pasta = gbak_path.parent
    return InstalacaoFirebird(
        pasta=pasta,
        gbak_path=gbak_path,
        gfix_path=_achar_exe(pasta, "gfix.exe"),
        isql_path=_achar_exe(pasta, "isql.exe"),
        gstat_path=_achar_exe(pasta, "gstat.exe"),
        versao_texto=texto,
        versao_tupla=tupla,
        origem_deteccao=origem,
    )


def _buscar_em_pastas() -> list[InstalacaoFirebird]:
    achadas: list[InstalacaoFirebird] = []
    for base in PASTAS_COMUNS:
        if not base.is_dir():
            continue
        for pasta in [base, *[p for p in base.iterdir() if p.is_dir()]]:
            gbak = _achar_exe(pasta, "gbak.exe")
            if gbak:
                inst = _montar_instalacao(gbak, "pasta")
                if inst:
                    achadas.append(inst)
    return achadas


def _buscar_no_registro() -> list[InstalacaoFirebird]:
    achadas: list[InstalacaoFirebird] = []
    try:
        import winreg
    except ImportError:
        return achadas
    for caminho_chave in CHAVES_REGISTRO:
        for hive in (winreg.HKEY_LOCAL_MACHINE, winreg.HKEY_CURRENT_USER):
            try:
                with winreg.OpenKey(hive, caminho_chave) as chave:
                    i = 0
                    while True:
                        try:
                            _, valor, _ = winreg.EnumValue(chave, i)
                        except OSError:
                            break
                        i += 1
                        gbak = _achar_exe(Path(str(valor)), "gbak.exe")
                        if gbak:
                            inst = _montar_instalacao(gbak, "registro")
                            if inst:
                                achadas.append(inst)
            except OSError:
                continue
    return achadas


def _buscar_no_path() -> list[InstalacaoFirebird]:
    caminho = which("gbak.exe") or which("gbak")
    if not caminho:
        return []
    inst = _montar_instalacao(Path(caminho), "path")
    return [inst] if inst else []


def localizar_instalacoes() -> list[InstalacaoFirebird]:
    """Todas as instalações encontradas, sem duplicar pelo caminho do gbak.exe,
    ordenadas da mais nova para a mais antiga."""
    todas: dict[str, InstalacaoFirebird] = {}
    for inst in (*_buscar_em_pastas(), *_buscar_no_registro(), *_buscar_no_path()):
        todas.setdefault(str(inst.gbak_path).lower(), inst)

    resultado = sorted(todas.values(), key=lambda i: i.versao_tupla, reverse=True)
    for inst in resultado:
        logger.info(
            f"Firebird detectado: {inst.versao_build} em {inst.gbak_path} "
            f"(origem: {inst.origem_deteccao})"
        )
    if not resultado:
        logger.aviso("Nenhuma instalação do Firebird foi encontrada automaticamente.")
    return resultado


def instalacao_manual(gbak_path: str | Path) -> InstalacaoFirebird | None:
    """Constrói uma InstalacaoFirebird a partir de um gbak.exe apontado à mão
    pelo usuário (usado quando a detecção não acha o Firebird 2.5)."""
    p = Path(gbak_path)
    if not p.is_file():
        return None
    return _montar_instalacao(p, "manual")


def escolher_por_familia(
    instalacoes: list[InstalacaoFirebird], familia: str
) -> InstalacaoFirebird | None:
    """Primeira instalação cuja família (major.minor) casa, ex.: '2.5' ou '5.0'.
    Aceita '5' como sinônimo de '5.0'."""
    familia = familia if "." in familia else f"{familia}.0"
    for inst in instalacoes:
        if inst.familia == familia:
            return inst
    return None


def escolher_origem(instalacoes: list[InstalacaoFirebird]) -> InstalacaoFirebird | None:
    """A mais antiga encontrada (candidata natural a Firebird de origem)."""
    return instalacoes[-1] if instalacoes else None


def escolher_destino(instalacoes: list[InstalacaoFirebird]) -> InstalacaoFirebird | None:
    """A mais nova encontrada (candidata natural a Firebird de destino)."""
    return instalacoes[0] if instalacoes else None
