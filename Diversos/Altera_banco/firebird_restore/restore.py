"""
restore.py
----------
Orquestra o fluxo completo e seguro de restauração:

    SELECIONAR .FBK
        -> VALIDAR ARQUIVO
        -> DESCOMPACTAR SE .GZ
        -> LOCALIZAR/VALIDAR GBAK
        -> VALIDAR DESTINO (nunca sobrescreve sem confirmação explícita)
        -> VERIFICAR ESPAÇO
        -> RESTAURAR (subprocess com argumentos separados, sem shell=True)
        -> CAPTURAR SAÍDA DO GBAK
        -> VALIDAR BANCO RESTAURADO
        -> SUCESSO/ERRO

Este módulo nunca decide sozinho sobrescrever um .fdb existente: quem chama
executar_restauracao() é responsável por já ter resolvido esse conflito
(nome alternativo / restauração paralela) antes de invocar a função — mas o
módulo confere de novo por segurança (defesa em profundidade).
"""
from __future__ import annotations

import re
import subprocess
import tempfile
from dataclasses import dataclass, field
from pathlib import Path
from typing import Callable, Optional

import logger
import validator
from firebird import InstalacaoFirebird, indica_incompatibilidade_de_versao

OnStatus = Callable[[str], None]


@dataclass
class ResultadoRestauracao:
    sucesso: bool
    mensagem_usuario: str
    detalhes_tecnicos: str = ""
    caminho_destino: str = ""


# Tradução de erros técnicos comuns do gbak para linguagem simples.
# A ordem importa: padrões mais específicos primeiro.
_PADROES_ERRO_CONHECIDOS: list[tuple[str, str]] = [
    (r"already exists", "Já existe um banco de dados com esse nome no destino."),
    (r"no permission for|access.*denied|permission denied",
     "Sem permissão para gravar no destino informado."),
    (r"unavailable database|I/O error|error while trying to open file",
     "Não foi possível abrir ou criar o arquivo de destino. Verifique o caminho e o disco."),
    (r"your user name and password are not defined|login",
     "Usuário ou senha inválidos para o Firebird."),
    (r"wrong ODS version|unsupported on-disk structure|invalid or unsupported backup version"
     r"|I don't recognize the format|wrong version of software",
     "O backup foi criado por uma versão do Firebird incompatível com a instalada. "
     "Tente selecionar outra instalação do Firebird."),
    (r"no such file or directory|file.*not found", "Arquivo de backup não encontrado durante a restauração."),
    (r"insufficient|not enough.*space|disk full", "Espaço em disco insuficiente durante a restauração."),
    (r"network error|unable to complete network request",
     "Erro de comunicação com o serviço do Firebird."),
]


def traduzir_erro_gbak(saida_tecnica: str) -> str:
    for padrao, mensagem in _PADROES_ERRO_CONHECIDOS:
        if re.search(padrao, saida_tecnica, re.IGNORECASE):
            return mensagem
    return "Ocorreu um erro durante a restauração. Consulte os detalhes técnicos."


def _rodar_gbak_restore(
    gbak_path: Path,
    backup_efetivo: Path,
    destino: Path,
    usuario: str,
    senha: str,
    on_status: OnStatus,
) -> tuple[int, str]:
    comando = [str(gbak_path), "-c", "-v"]
    if usuario:
        comando += ["-user", usuario]
    if senha:
        comando += ["-password", senha]
    comando += [str(backup_efetivo), str(destino)]

    segredos = [senha] if senha else []
    logger.info(f"Executando: {' '.join(comando)}", segredos=segredos)

    linhas_saida: list[str] = []
    processo = subprocess.Popen(
        comando,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
        bufsize=1,
        creationflags=subprocess.CREATE_NO_WINDOW if hasattr(subprocess, "CREATE_NO_WINDOW") else 0,
    )
    assert processo.stdout is not None
    for linha in processo.stdout:
        linha = linha.rstrip("\n")
        if not linha:
            continue
        # Mascarar antes de guardar/repassar: a senha nunca deve sobreviver
        # no texto de detalhes técnicos exibido na interface, mesmo no caso
        # improvável de ela ecoar na saída do processo.
        linha_segura = logger.mask_secrets(linha, segredos)
        linhas_saida.append(linha_segura)
        logger.info(linha_segura)
        on_status(linha_segura[:120])

    processo.wait()
    return processo.returncode, "\n".join(linhas_saida)


def _validar_conexao_pos_restauracao(
    isql_path: Optional[Path], destino: Path, usuario: str, senha: str
) -> validator.ResultadoValidacao:
    if isql_path is None:
        return validator.ResultadoValidacao(
            True, "isql.exe não encontrado para validação de conexão (checagem pulada).",
        )

    comando = [str(isql_path)]
    if usuario:
        comando += ["-user", usuario]
    if senha:
        comando += ["-password", senha]
    comando += [str(destino)]

    try:
        resultado = subprocess.run(
            comando,
            input="SELECT 1 FROM RDB$DATABASE;\nEXIT;\n",
            capture_output=True,
            text=True,
            timeout=30,
            creationflags=subprocess.CREATE_NO_WINDOW if hasattr(subprocess, "CREATE_NO_WINDOW") else 0,
        )
    except (OSError, subprocess.TimeoutExpired) as exc:
        return validator.ResultadoValidacao(
            False, "Não foi possível abrir o banco restaurado para validação.",
            detalhes_tecnicos=str(exc),
        )

    saida = (resultado.stdout or "") + (resultado.stderr or "")
    if resultado.returncode != 0 or "Statement failed" in saida or "error" in saida.lower():
        return validator.ResultadoValidacao(
            False, "O banco foi criado, mas não foi possível confirmar que está íntegro.",
            detalhes_tecnicos=saida,
        )
    return validator.ResultadoValidacao(True, "Banco restaurado validado com sucesso (conexão de teste OK).")


def executar_restauracao(
    caminho_backup: str,
    caminho_destino: str,
    instalacao: InstalacaoFirebird,
    usuario: str,
    senha: str,
    fator_estimativa: float,
    margem_seguranca: float,
    on_status: OnStatus,
) -> ResultadoRestauracao:
    destino = Path(caminho_destino)

    # --- 1) Validar arquivo de backup -----------------------------------
    on_status("Validando backup...")
    validacao_backup = validator.validar_arquivo_backup(caminho_backup)
    if not validacao_backup.ok:
        return ResultadoRestauracao(False, validacao_backup.mensagem_usuario, validacao_backup.detalhes_tecnicos)

    # --- 2) Validar destino (defesa em profundidade contra sobrescrita) --
    validacao_destino = validator.validar_destino(caminho_destino)
    if not validacao_destino.ok:
        return ResultadoRestauracao(False, validacao_destino.mensagem_usuario, validacao_destino.detalhes_tecnicos)
    if validacao_destino.metadados.get("ja_existe"):
        return ResultadoRestauracao(
            False,
            "Já existe um banco no destino informado. A restauração foi interrompida para não sobrescrevê-lo.",
            detalhes_tecnicos=str(destino),
        )

    # --- 3) Verificar espaço em disco -------------------------------------
    on_status("Verificando espaço em disco...")
    validacao_espaco = validator.verificar_espaco_disco(
        str(destino.parent), validacao_backup.metadados["tamanho_bytes"], fator_estimativa, margem_seguranca
    )
    if not validacao_espaco.ok:
        return ResultadoRestauracao(False, validacao_espaco.mensagem_usuario, validacao_espaco.detalhes_tecnicos)

    # --- 4) Descompactar se necessário (.fbk.gz) --------------------------
    caminho_temporario_para_limpar: Optional[Path] = None
    with tempfile.TemporaryDirectory(prefix="firebird_restore_") as pasta_temp_str:
        pasta_temp = Path(pasta_temp_str)

        if validacao_backup.metadados.get("comprimido"):
            on_status("Descompactando backup...")
            preparo = validator.preparar_backup_para_restauracao(caminho_backup, pasta_temp)
            if not preparo.ok:
                return ResultadoRestauracao(False, preparo.mensagem_usuario, preparo.detalhes_tecnicos)
            backup_efetivo = Path(preparo.metadados["caminho_efetivo"])
        else:
            backup_efetivo = Path(caminho_backup)

        # --- 5) Checagem heurística de conteúdo ---------------------------
        indicios = validator.checar_indicios_de_backup_valido(str(backup_efetivo))
        if not indicios.ok:
            return ResultadoRestauracao(False, indicios.mensagem_usuario, indicios.detalhes_tecnicos)

        # --- 6) Restaurar de fato ------------------------------------------
        on_status("Iniciando restauração...")
        logger.info(f"Restauração iniciada: backup={caminho_backup} destino={destino} "
                    f"firebird={instalacao.versao_texto}")
        try:
            codigo_retorno, saida_tecnica = _rodar_gbak_restore(
                instalacao.gbak_path, backup_efetivo, destino, usuario, senha, on_status
            )
        except OSError as exc:
            return ResultadoRestauracao(
                False, "Não foi possível executar o gbak.exe. Verifique a instalação do Firebird.",
                detalhes_tecnicos=str(exc),
            )

    # pasta_temp (e o .fbk descompactado) são removidos automaticamente aqui.

    if codigo_retorno != 0 or "ERROR" in saida_tecnica.upper():
        mensagem = traduzir_erro_gbak(saida_tecnica)
        if indica_incompatibilidade_de_versao(saida_tecnica):
            logger.erro("Possível incompatibilidade de versão entre backup e Firebird instalado.")
        logger.erro(f"Restauração falhou (código {codigo_retorno}).")
        return ResultadoRestauracao(False, mensagem, saida_tecnica, str(destino))

    # --- 7) Validar resultado ------------------------------------------------
    on_status("Validando banco restaurado...")
    if not destino.exists() or destino.stat().st_size <= 0:
        return ResultadoRestauracao(
            False, "O gbak terminou sem erro, mas o arquivo de destino não foi criado corretamente.",
            detalhes_tecnicos=saida_tecnica, caminho_destino=str(destino),
        )

    validacao_conexao = _validar_conexao_pos_restauracao(instalacao.isql_path, destino, usuario, senha)
    if not validacao_conexao.ok:
        logger.aviso(validacao_conexao.mensagem_usuario)
        # Não trata como falha bloqueante: o arquivo existe e o gbak reportou
        # sucesso; a validação de conexão é uma confirmação extra.

    on_status("Concluído.")
    logger.info(f"Restauração concluída com sucesso: {destino}")
    return ResultadoRestauracao(
        True, "Banco restaurado com sucesso.", saida_tecnica, str(destino)
    )
