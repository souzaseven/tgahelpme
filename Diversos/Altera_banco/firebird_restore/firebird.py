"""
firebird.py
-----------
Descoberta de instalações do Firebird no Windows e leitura da versão real do
gbak.exe encontrado. Não assume nenhum caminho fixo: procura em locais comuns,
no PATH e no Registro do Windows.
"""
from __future__ import annotations

import os
import re
import subprocess
from dataclasses import dataclass
from pathlib import Path
from shutil import which

import logger

PASTAS_COMUNS = [
    Path(r"C:\Program Files\Firebird"),
    Path(r"C:\Program Files (x86)\Firebird"),
]

CHAVES_REGISTRO = [
    (r"SOFTWARE\Firebird Project\Firebird Server\Instances", None),
    (r"SOFTWARE\WOW6432Node\Firebird Project\Firebird Server\Instances", None),
]


@dataclass
class InstalacaoFirebird:
    pasta: Path
    gbak_path: Path
    gfix_path: Path | None
    isql_path: Path | None
    versao_texto: str
    versao_tupla: tuple[int, ...]
    origem: str  # "pasta_padrao" | "registro" | "path"

    @property
    def rotulo(self) -> str:
        return f"Firebird {'.'.join(map(str, self.versao_tupla)) or '?'} - {self.pasta}"


def _extrair_versao(saida: str) -> tuple[str, tuple[int, ...]]:
    """gbak -z imprime algo como:
    'gbak:gbak version WI-V5.0.3.1683 Firebird 5.0'
    Extraímos o texto completo e uma tupla numérica para comparação."""
    saida = saida.strip()
    match = re.search(r"Firebird\s+(\d+(?:\.\d+)*)", saida)
    if match:
        partes = tuple(int(p) for p in match.group(1).split("."))
        return saida, partes
    return saida or "versão desconhecida", ()


def obter_versao_gbak(gbak_path: Path) -> tuple[str, tuple[int, ...]] | None:
    try:
        resultado = subprocess.run(
            [str(gbak_path), "-z"],
            capture_output=True,
            text=True,
            timeout=10,
        )
    except (OSError, subprocess.TimeoutExpired):
        return None
    saida = (resultado.stdout or "") + "\n" + (resultado.stderr or "")
    linha_versao = next((l for l in saida.splitlines() if "version" in l.lower()), saida)
    return _extrair_versao(linha_versao)


def _candidatos_executavel(pasta_instalacao: Path) -> Path | None:
    """gbak.exe pode estar direto na raiz da instalação (ex.: Firebird 5.0
    no Windows) ou dentro de uma subpasta bin\\ (comum em builds antigas)."""
    for candidato in (pasta_instalacao / "gbak.exe", pasta_instalacao / "bin" / "gbak.exe"):
        if candidato.is_file():
            return candidato
    return None


def _montar_instalacao(gbak_path: Path, origem: str) -> InstalacaoFirebird | None:
    info_versao = obter_versao_gbak(gbak_path)
    if info_versao is None:
        return None
    texto, tupla = info_versao
    pasta = gbak_path.parent
    gfix = pasta / "gfix.exe"
    isql = pasta / "isql.exe"
    return InstalacaoFirebird(
        pasta=pasta,
        gbak_path=gbak_path,
        gfix_path=gfix if gfix.is_file() else None,
        isql_path=isql if isql.is_file() else None,
        versao_texto=texto,
        versao_tupla=tupla,
        origem=origem,
    )


def _buscar_em_pastas_padrao() -> list[InstalacaoFirebird]:
    encontradas = []
    for base in PASTAS_COMUNS:
        if not base.is_dir():
            continue
        # A própria pasta base (raro) e cada subpasta de versão
        subpastas = [base] + [p for p in base.iterdir() if p.is_dir()]
        for subpasta in subpastas:
            gbak = _candidatos_executavel(subpasta)
            if gbak:
                inst = _montar_instalacao(gbak, "pasta_padrao")
                if inst:
                    encontradas.append(inst)
    return encontradas


def _buscar_no_registro() -> list[InstalacaoFirebird]:
    encontradas = []
    try:
        import winreg
    except ImportError:
        return encontradas

    for caminho_chave, _ in CHAVES_REGISTRO:
        for hive in (winreg.HKEY_LOCAL_MACHINE,):
            try:
                with winreg.OpenKey(hive, caminho_chave) as chave:
                    i = 0
                    while True:
                        try:
                            nome_valor, valor, _ = winreg.EnumValue(chave, i)
                        except OSError:
                            break
                        i += 1
                        pasta_instalacao = Path(str(valor))
                        gbak = _candidatos_executavel(pasta_instalacao)
                        if gbak:
                            inst = _montar_instalacao(gbak, "registro")
                            if inst:
                                encontradas.append(inst)
            except FileNotFoundError:
                continue
            except OSError:
                continue
    return encontradas


def _buscar_no_path() -> list[InstalacaoFirebird]:
    caminho = which("gbak.exe") or which("gbak")
    if not caminho:
        return []
    inst = _montar_instalacao(Path(caminho), "path")
    return [inst] if inst else []


def localizar_instalacoes() -> list[InstalacaoFirebird]:
    """Retorna todas as instalações do Firebird encontradas, sem duplicar
    pelo caminho do gbak.exe, ordenadas da versão mais nova para a mais antiga."""
    todas: dict[str, InstalacaoFirebird] = {}
    for inst in (*_buscar_em_pastas_padrao(), *_buscar_no_registro(), *_buscar_no_path()):
        chave = str(inst.gbak_path).lower()
        todas.setdefault(chave, inst)

    resultado = list(todas.values())
    resultado.sort(key=lambda i: i.versao_tupla, reverse=True)
    for inst in resultado:
        logger.info(f"Firebird detectado: {inst.versao_texto} em {inst.gbak_path} (origem: {inst.origem})")
    if not resultado:
        logger.aviso("Nenhuma instalação do Firebird foi encontrada automaticamente.")
    return resultado


def escolher_melhor(instalacoes: list[InstalacaoFirebird]) -> InstalacaoFirebird | None:
    """Escolha automática: versão mais recente encontrada."""
    return instalacoes[0] if instalacoes else None


# Padrões conhecidos de mensagens do gbak/gfix relacionadas a incompatibilidade
# de versão entre o backup e a instalação usada para restaurar.
PADROES_INCOMPATIBILIDADE = [
    r"wrong ODS version",
    r"unsupported on-disk structure",
    r"invalid or unsupported backup version",
    r"I don't recognize the format",
    r"filesystem name length",
    r"wrong version of software",
]


def indica_incompatibilidade_de_versao(saida_tecnica: str) -> bool:
    return any(re.search(p, saida_tecnica, re.IGNORECASE) for p in PADROES_INCOMPATIBILIDADE)
