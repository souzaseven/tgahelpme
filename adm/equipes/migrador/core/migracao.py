"""
core/migracao.py
----------------
Orquestra a migração 2.5 -> 5.0 pelo caminho correto:

    banco 2.5 --(gbak -b, Firebird 2.5)--> .fbk --(gbak -c, Firebird 5.0)--> .fdb 5.0

Regras (do master prompt):
- o backup usa o gbak da ORIGEM; o restore usa o gbak do DESTINO;
- o arquivo original nunca é renomeado nem aberto para escrita (`-g` inibe a
  coleta de lixo, que escreveria no arquivo; há ainda a opção de trabalhar
  sobre uma cópia);
- o destino nunca é sobrescrito (`gbak -c` falha se já existir);
- argumentos sempre em lista, nunca shell=True, senha nunca concatenada em log.

`classificar_resultado_gbak` é uma função pura, coberta por testes — ela é o
ponto onde o erro dos prints ("expected backup description record") é
reconhecido como falha fatal.

Este módulo NÃO importa PySide6: usa subprocess + uma thread de leitura e um
callback de linha. A interface roda `executar_migracao` dentro de um QThread.
"""
from __future__ import annotations

import re
import shutil
import subprocess
import threading
import time
from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path
from typing import Callable

import logger
from core.firebird import InstalacaoFirebird
from core.modelos import ResultadoMigracao

# Erros que invalidam a migração inteira, mesmo que um .fdb parcial tenha sido
# criado antes de o gbak abortar.
PADROES_FATAIS = [
    r"expected backup description record",
    r"unexpected end of file",
    r"unexpected end of database file",
    r"could not read from file",
    r"cannot open backup file",
    r"backup file .* not found",
    r"wrong ODS version",
    r"unsupported on-disk structure",
    r"invalid or unsupported backup version",
    r"I don't recognize the format",
    r"database .* appears corrupt",
    r"bad checksum",
    r"can't format message",
    r"no permission for",
    r"operating system directive .* failed",
    r"device is full",
    r"disk full",
    r"file exists",              # gbak -c num destino que já existe
    r"database already exists",
]

# Erros que NÃO impedem a conclusão: dados órfãos dentro de um backup íntegro
# batem numa FK/índice único ao reativar os índices no fim do restore.
PADROES_INDICE_NAO_FATAL = [
    r"cannot commit index",
    r"violation of (PRIMARY|FOREIGN) KEY constraint",
    r"attempt to store duplicate value.*unique index",
    r"key size (exceeds|too big)",
]

_LINHA_ERRO_GBAK = re.compile(r"\bgbak:\s*(ERROR|Exiting)", re.IGNORECASE)


@dataclass
class OpcoesMigracao:
    # "(preservar do backup)" mantém o page size original; ou um número (bytes).
    page_size: str = "(preservar do backup)"
    # Charset real para -fix_fss_data / -fix_fss_metadata (ex.: "WIN1252"); vazio = não usar.
    fix_fss_charset: str = ""
    # Workers paralelos do gbak 5 no restore; 1 = saída determinística.
    par_workers: int = 1
    # Faz o backup contra uma cópia temporária da origem (nem leitura no arquivo real).
    trabalhar_sobre_copia: bool = False
    # `-g` no backup: inibe garbage collection (não escreve na origem). Recomendado ligado.
    inibir_garbage_collection: bool = True
    # `-o` no restore: commit por tabela (mais lento, ajuda com backups problemáticos).
    restore_um_por_vez: bool = False


@dataclass
class _Execucao:
    returncode: int = 0
    linhas: list[str] = field(default_factory=list)
    cancelada: bool = False


def nome_backup(origem_fdb: str | Path, quando: datetime | None = None) -> str:
    quando = quando or datetime.now()
    base = Path(origem_fdb).stem
    return f"{base}_FB25_{quando:%Y%m%d_%H%M%S}.fbk"


def nome_destino(origem_fdb: str | Path, quando: datetime | None = None) -> str:
    quando = quando or datetime.now()
    base = Path(origem_fdb).stem
    return f"{base}_FB50_{quando:%Y%m%d_%H%M%S}.FDB"


def classificar_resultado_gbak(
    saida: str,
    returncode: int,
    *,
    destino_criado: bool,
) -> tuple[str, int, list[str]]:
    """Devolve (erro_fatal, qtd_erros_indice_nao_fatais, linhas_de_erro).

    erro_fatal == "" significa que a operação pode ser considerada concluída
    (com ou sem avisos de índice). Caso contrário, é a mensagem curta do
    primeiro problema fatal encontrado.
    """
    linhas = saida.splitlines()
    linhas_erro = [l.strip() for l in linhas if _LINHA_ERRO_GBAK.search(l)]

    fatal = ""
    for padrao in PADROES_FATAIS:
        m = next((l for l in linhas if re.search(padrao, l, re.IGNORECASE)), None)
        if m:
            fatal = m.strip()
            break

    erros_indice = 0
    for padrao in PADROES_INDICE_NAO_FATAL:
        erros_indice += sum(1 for l in linhas if re.search(padrao, l, re.IGNORECASE))

    # returncode != 0 sem padrão fatal conhecido e sem sequer ter criado o
    # arquivo de destino: trata como fatal genérico (não deixa passar por bom).
    if not fatal and returncode != 0 and not destino_criado:
        ultima = next(
            (l.strip() for l in reversed(linhas) if l.strip()), ""
        )
        fatal = ultima or f"gbak terminou com código {returncode} e sem gerar o banco."

    return fatal, erros_indice, linhas_erro


def _stream_processo(
    cmd: list[str],
    callback_linha: Callable[[str], None] | None,
    deve_cancelar: Callable[[], bool] | None,
    timeout_seg: int,
) -> _Execucao:
    """Roda `cmd`, repassa cada linha de stdout/stderr ao callback e ao log,
    respeitando um pedido de cancelamento. Nunca usa shell."""
    logger.info("Executando: " + logger.mascarar_segredos(" ".join(cmd)))
    exe = _Execucao()
    try:
        proc = subprocess.Popen(
            cmd,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True,
            bufsize=1,
            creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0),
        )
    except OSError as e:
        linha = f"gbak: ERROR: não foi possível iniciar o processo: {e}"
        exe.linhas.append(linha)
        if callback_linha:
            callback_linha(linha)
        logger.erro(linha)
        exe.returncode = -1
        return exe

    inicio = time.monotonic()

    def _matar():
        try:
            proc.kill()
        except OSError:
            pass

    assert proc.stdout is not None
    for linha in proc.stdout:
        linha = linha.rstrip("\n")
        if linha:
            exe.linhas.append(linha)
            logger.info(linha) if not _LINHA_ERRO_GBAK.search(linha) else logger.erro(linha)
            if callback_linha:
                callback_linha(linha)
        if deve_cancelar and deve_cancelar():
            exe.cancelada = True
            _matar()
            break
        if time.monotonic() - inicio > timeout_seg:
            exe.linhas.append(f"gbak: ERROR: tempo limite de {timeout_seg}s excedido.")
            _matar()
            break

    proc.wait()
    exe.returncode = proc.returncode if proc.returncode is not None else -1
    return exe


def _cmd_backup(
    inst_origem: InstalacaoFirebird,
    origem_fdb: str,
    fbk_path: str,
    usuario: str,
    senha: str,
    opcoes: OpcoesMigracao,
) -> list[str]:
    cmd = [str(inst_origem.gbak_path), "-b", "-v"]
    if opcoes.inibir_garbage_collection:
        cmd.append("-g")
    cmd.append("-l")  # ignora transações em limbo (base de produção pode ter)
    if usuario:
        cmd += ["-user", usuario]
    if senha:
        cmd += ["-password", senha]
    cmd += [origem_fdb, fbk_path]
    return cmd


def _cmd_restore(
    inst_destino: InstalacaoFirebird,
    fbk_path: str,
    destino_fdb: str,
    usuario: str,
    senha: str,
    opcoes: OpcoesMigracao,
) -> list[str]:
    cmd = [str(inst_destino.gbak_path), "-c", "-v"]
    ps = opcoes.page_size.strip()
    if ps.isdigit():
        cmd += ["-p", ps]
    if opcoes.fix_fss_charset.strip():
        cs = opcoes.fix_fss_charset.strip().upper()
        cmd += ["-fix_fss_data", cs, "-fix_fss_metadata", cs]
    if opcoes.par_workers and opcoes.par_workers > 1:
        cmd += ["-par", str(opcoes.par_workers)]
    if opcoes.restore_um_por_vez:
        cmd.append("-o")
    if usuario:
        cmd += ["-user", usuario]
    if senha:
        cmd += ["-password", senha]
    cmd += [fbk_path, destino_fdb]
    return cmd


_RE_REGISTROS = re.compile(r"(\d+)\s+records", re.IGNORECASE)


def _somar_registros_reportados(linhas: list[str]) -> int:
    total = 0
    for l in linhas:
        m = _RE_REGISTROS.search(l)
        if m and "writing" not in l.lower():
            total += int(m.group(1))
    return total


def executar_migracao(
    inst_origem: InstalacaoFirebird,
    inst_destino: InstalacaoFirebird,
    origem_fdb: str,
    destino_fdb: str,
    fbk_path: str,
    usuario: str,
    senha: str,
    opcoes: OpcoesMigracao,
    *,
    callback_linha: Callable[[str], None] | None = None,
    callback_fase: Callable[[str], None] | None = None,
    deve_cancelar: Callable[[], bool] | None = None,
    timeout_backup_seg: int = 3 * 3600,
    timeout_restore_seg: int = 3 * 3600,
) -> ResultadoMigracao:
    origem_fdb = str(Path(origem_fdb))
    destino_fdb = str(Path(destino_fdb))
    fbk_path = str(Path(fbk_path))
    res = ResultadoMigracao(
        caminho_origem=origem_fdb, caminho_fbk=fbk_path, caminho_destino=destino_fdb
    )

    if Path(destino_fdb).exists():
        res.erro_fatal = f"O destino já existe: {destino_fdb}. Escolha outro nome."
        return res
    if Path(fbk_path).exists():
        res.erro_fatal = f"O arquivo de backup já existe: {fbk_path}. Escolha outro nome."
        return res

    # -------- Fase 1: backup com o Firebird de ORIGEM -------------------------
    if callback_fase:
        callback_fase("Backup do banco de origem (Firebird 2.5)")
    fonte_backup = origem_fdb
    copia_temp: Path | None = None
    if opcoes.trabalhar_sobre_copia:
        copia_temp = Path(fbk_path).with_suffix(".origem_copia.fdb")
        logger.info(f"Copiando origem para {copia_temp} (trabalhar sobre cópia).")
        try:
            shutil.copy2(origem_fdb, copia_temp)
            fonte_backup = str(copia_temp)
        except OSError as e:
            res.erro_fatal = f"Falha ao copiar o banco de origem: {e}"
            return res

    try:
        t0 = time.monotonic()
        exe_bkp = _stream_processo(
            _cmd_backup(inst_origem, fonte_backup, fbk_path, usuario, senha, opcoes),
            callback_linha,
            deve_cancelar,
            timeout_backup_seg,
        )
        res.duracao_backup_seg = time.monotonic() - t0
    finally:
        if copia_temp and copia_temp.exists():
            try:
                copia_temp.unlink()
            except OSError:
                pass

    if exe_bkp.cancelada:
        res.erro_fatal = "Migração cancelada durante o backup."
        _remover(fbk_path)
        return res

    fbk_ok = Path(fbk_path).is_file() and Path(fbk_path).stat().st_size > 0
    fatal_bkp, _, linhas_erro_bkp = classificar_resultado_gbak(
        "\n".join(exe_bkp.linhas), exe_bkp.returncode, destino_criado=fbk_ok
    )
    res.linhas_erro_gbak += linhas_erro_bkp
    if fatal_bkp or not fbk_ok:
        res.erro_fatal = fatal_bkp or "O backup não gerou um arquivo .fbk válido."
        _remover(fbk_path)
        return res
    res.backup_ok = True
    logger.info(f"Backup concluído: {fbk_path} ({Path(fbk_path).stat().st_size} bytes)")

    # -------- Fase 2: restore com o Firebird de DESTINO ----------------------
    if callback_fase:
        callback_fase("Restauração no Firebird 5.0")
    if deve_cancelar and deve_cancelar():
        res.erro_fatal = "Migração cancelada antes da restauração."
        return res

    t0 = time.monotonic()
    exe_res = _stream_processo(
        _cmd_restore(inst_destino, fbk_path, destino_fdb, usuario, senha, opcoes),
        callback_linha,
        deve_cancelar,
        timeout_restore_seg,
    )
    res.duracao_restore_seg = time.monotonic() - t0

    destino_criado = Path(destino_fdb).is_file() and Path(destino_fdb).stat().st_size > 0
    if exe_res.cancelada:
        res.erro_fatal = "Migração cancelada durante a restauração."
        _remover(destino_fdb)
        return res

    fatal_res, erros_indice, linhas_erro_res = classificar_resultado_gbak(
        "\n".join(exe_res.linhas), exe_res.returncode, destino_criado=destino_criado
    )
    res.linhas_erro_gbak += linhas_erro_res
    res.erros_indice_nao_fatais = erros_indice
    res.registros_reportados_gbak = _somar_registros_reportados(exe_res.linhas)

    if fatal_res:
        res.erro_fatal = fatal_res
        _remover(destino_fdb)  # nunca deixa um .fdb parcial passando por válido
        return res

    if not destino_criado:
        res.erro_fatal = "A restauração terminou sem gerar o arquivo .fdb de destino."
        return res

    res.restore_ok = True
    logger.info(
        f"Restauração concluída: {destino_fdb} "
        f"(erros de índice não fatais: {erros_indice})"
    )
    return res


def _remover(caminho: str) -> None:
    try:
        p = Path(caminho)
        if p.exists():
            p.unlink()
            logger.info(f"Arquivo parcial removido: {caminho}")
    except OSError as e:
        logger.aviso(f"Não foi possível remover {caminho}: {e}")
