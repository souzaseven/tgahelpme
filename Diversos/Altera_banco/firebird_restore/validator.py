"""
validator.py
------------
Todas as checagens que devem passar ANTES de disparar o gbak, mais os
utilitários de preparo do arquivo de backup (descompactação de .fbk.gz) e
de nomenclatura segura do destino.

Importante: a validação de "isto é realmente um backup Firebird válido" feita
aqui é best-effort (extensão, tamanho, cabeçalho binário plausível). A única
confirmação definitiva é a tentativa real de restauração pelo gbak — por isso
o restore.py sempre trata o retorno do gbak como a fonte da verdade final.
"""
from __future__ import annotations

import gzip
import os
import shutil
from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path

EXTENSOES_BACKUP_VALIDAS = (".fbk", ".fbk.gz")


@dataclass
class ResultadoValidacao:
    ok: bool
    mensagem_usuario: str
    detalhes_tecnicos: str = ""
    metadados: dict = field(default_factory=dict)


def _tem_extensao_valida(caminho: Path) -> bool:
    nome = caminho.name.lower()
    return any(nome.endswith(ext) for ext in EXTENSOES_BACKUP_VALIDAS)


def formatar_tamanho(tamanho_bytes: int) -> str:
    tamanho = float(tamanho_bytes)
    for unidade in ("B", "KB", "MB", "GB", "TB"):
        if tamanho < 1024 or unidade == "TB":
            return f"{tamanho:.2f} {unidade}" if unidade != "B" else f"{int(tamanho)} B"
        tamanho /= 1024
    return f"{tamanho:.2f} TB"


def validar_arquivo_backup(caminho_str: str) -> ResultadoValidacao:
    if not caminho_str:
        return ResultadoValidacao(False, "Selecione um arquivo de backup (.fbk ou .fbk.gz).")

    caminho = Path(caminho_str)

    if not caminho.exists():
        return ResultadoValidacao(False, "O arquivo de backup selecionado não existe.",
                                   detalhes_tecnicos=f"Caminho inexistente: {caminho}")
    if not caminho.is_file():
        return ResultadoValidacao(False, "O caminho selecionado não é um arquivo.",
                                   detalhes_tecnicos=str(caminho))
    if not _tem_extensao_valida(caminho):
        return ResultadoValidacao(
            False,
            "O arquivo selecionado não parece ser um backup Firebird (.fbk ou .fbk.gz).",
            detalhes_tecnicos=f"Extensão encontrada: {caminho.suffix}",
        )
    if not os.access(caminho, os.R_OK):
        return ResultadoValidacao(False, "Sem permissão de leitura para este arquivo.",
                                   detalhes_tecnicos=str(caminho))

    tamanho = caminho.stat().st_size
    if tamanho <= 0:
        return ResultadoValidacao(False, "O arquivo de backup está vazio (0 bytes).",
                                   detalhes_tecnicos=str(caminho))

    modificado = datetime.fromtimestamp(caminho.stat().st_mtime)
    metadados = {
        "nome": caminho.name,
        "caminho": str(caminho),
        "tamanho_bytes": tamanho,
        "tamanho_formatado": formatar_tamanho(tamanho),
        "modificado": modificado.strftime("%d/%m/%Y %H:%M"),
        "comprimido": caminho.name.lower().endswith(".gz"),
    }
    return ResultadoValidacao(True, "Backup válido para prosseguir.", metadados=metadados)


def preparar_backup_para_restauracao(caminho_str: str, pasta_temp: Path) -> ResultadoValidacao:
    """Se o backup estiver comprimido (.fbk.gz), descompacta para um arquivo
    temporário .fbk e devolve o caminho efetivo em metadados['caminho_efetivo'].
    Caso contrário, devolve o próprio caminho original."""
    caminho = Path(caminho_str)

    if not caminho.name.lower().endswith(".gz"):
        return ResultadoValidacao(True, "Backup já está descompactado.",
                                   metadados={"caminho_efetivo": str(caminho), "temporario": False})

    pasta_temp.mkdir(parents=True, exist_ok=True)
    nome_saida = caminho.name[: -len(".gz")]
    if not nome_saida:
        nome_saida = "backup_extraido.fbk"
    destino_temp = pasta_temp / nome_saida

    try:
        with gzip.open(caminho, "rb") as origem, open(destino_temp, "wb") as saida:
            shutil.copyfileobj(origem, saida, length=1024 * 1024)
    except (OSError, gzip.BadGzipFile) as exc:
        return ResultadoValidacao(
            False,
            "Não foi possível descompactar o arquivo de backup (.gz corrompido ou inválido).",
            detalhes_tecnicos=str(exc),
        )

    if destino_temp.stat().st_size <= 0:
        return ResultadoValidacao(False, "A descompactação resultou em um arquivo vazio.",
                                   detalhes_tecnicos=str(destino_temp))

    return ResultadoValidacao(
        True,
        "Backup descompactado com sucesso.",
        metadados={"caminho_efetivo": str(destino_temp), "temporario": True},
    )


def checar_indicios_de_backup_valido(caminho_str: str) -> ResultadoValidacao:
    """Checagem heurística (não definitiva) do cabeçalho binário do arquivo
    já descompactado. Só descarta casos óbvios (texto puro, outro formato
    binário conhecido, arquivo minúsculo demais para ser um backup real)."""
    caminho = Path(caminho_str)
    try:
        with open(caminho, "rb") as f:
            cabecalho = f.read(16)
    except OSError as exc:
        return ResultadoValidacao(False, "Não foi possível ler o início do arquivo de backup.",
                                   detalhes_tecnicos=str(exc))

    if len(cabecalho) < 8:
        return ResultadoValidacao(False, "Arquivo pequeno demais para ser um backup Firebird válido.")

    assinaturas_conhecidas_de_outros_formatos = {
        b"PK\x03\x04": "arquivo ZIP",
        b"\x1f\x8b": "arquivo GZIP (deveria ter sido descompactado antes)",
        b"%PDF": "arquivo PDF",
        b"\x89PNG": "imagem PNG",
    }
    for assinatura, nome_formato in assinaturas_conhecidas_de_outros_formatos.items():
        if cabecalho.startswith(assinatura):
            return ResultadoValidacao(
                False,
                f"O conteúdo do arquivo parece ser {nome_formato}, não um backup Firebird.",
                detalhes_tecnicos=f"Cabeçalho: {cabecalho[:8]!r}",
            )

    # Um arquivo de texto puro (só ASCII imprimível) também não é um backup gbak.
    if all(32 <= b <= 126 or b in (9, 10, 13) for b in cabecalho):
        return ResultadoValidacao(
            False,
            "O conteúdo do arquivo parece ser texto simples, não um backup binário do Firebird.",
            detalhes_tecnicos=f"Cabeçalho: {cabecalho!r}",
        )

    return ResultadoValidacao(True, "Nenhum indício de arquivo inválido encontrado (checagem preliminar).")


def validar_destino(caminho_str: str) -> ResultadoValidacao:
    if not caminho_str:
        return ResultadoValidacao(False, "Informe onde o banco restaurado deve ser criado.")

    caminho = Path(caminho_str)
    if caminho.suffix.lower() != ".fdb":
        return ResultadoValidacao(False, "O destino deve ter extensão .fdb.",
                                   detalhes_tecnicos=str(caminho))

    pasta = caminho.parent
    if not pasta.exists():
        try:
            pasta.mkdir(parents=True, exist_ok=True)
        except OSError as exc:
            return ResultadoValidacao(False, "Não foi possível criar a pasta de destino.",
                                       detalhes_tecnicos=str(exc))

    if not os.access(pasta, os.W_OK):
        return ResultadoValidacao(False, "Sem permissão de escrita na pasta de destino.",
                                   detalhes_tecnicos=str(pasta))

    ja_existe = caminho.exists()
    return ResultadoValidacao(
        True,
        "Destino válido." if not ja_existe else "Já existe um arquivo com esse nome no destino.",
        metadados={"ja_existe": ja_existe, "caminho": str(caminho)},
    )


def sugerir_nome_restauracao_paralela(caminho_destino_str: str) -> str:
    caminho = Path(caminho_destino_str)
    agora = datetime.now().strftime("%Y%m%d_%H%M%S")
    novo_nome = f"{caminho.stem}_RESTAURADO_{agora}{caminho.suffix}"
    return str(caminho.parent / novo_nome)


def verificar_espaco_disco(
    pasta_destino_str: str,
    tamanho_backup_bytes: int,
    fator_estimativa: float = 4.0,
    margem_seguranca: float = 1.3,
) -> ResultadoValidacao:
    pasta = Path(pasta_destino_str)
    if not pasta.exists():
        pasta = pasta.parent if pasta.parent.exists() else Path(pasta.anchor or ".")

    try:
        uso = shutil.disk_usage(pasta)
    except OSError as exc:
        return ResultadoValidacao(False, "Não foi possível verificar o espaço em disco disponível.",
                                   detalhes_tecnicos=str(exc))

    estimativa_necessaria = int(tamanho_backup_bytes * fator_estimativa * margem_seguranca)
    metadados = {
        "espaco_livre_bytes": uso.free,
        "espaco_livre_formatado": formatar_tamanho(uso.free),
        "estimativa_necessaria_bytes": estimativa_necessaria,
        "estimativa_necessaria_formatada": formatar_tamanho(estimativa_necessaria),
    }

    if uso.free < estimativa_necessaria:
        return ResultadoValidacao(
            False,
            (
                f"Espaço em disco insuficiente. Estimativa necessária: "
                f"{formatar_tamanho(estimativa_necessaria)}; disponível: "
                f"{formatar_tamanho(uso.free)}."
            ),
            metadados=metadados,
        )
    return ResultadoValidacao(True, "Espaço em disco suficiente (estimativa).", metadados=metadados)
