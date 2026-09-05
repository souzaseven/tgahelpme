"""
recuperacao.py
--------------
Recuperação best-effort de um banco Firebird (.fdb) corrompido: gera um novo
backup (.fbk) a partir dele, ignorando erros de página/checksum ao máximo
possível, para depois restaurar esse backup como um banco novo — usando o
fluxo normal de restore.py, sem nenhuma etapa especial.

    COPIAR (nunca mexe no original)
        -> gfix -mend -full   (marca estruturas corrompidas para serem
                                ignoradas, em vez de travar a leitura)
        -> gfix -sweep        (limpeza de transações antigas, opcional)
        -> gbak -b -g -l -ignore  (gera o backup pulando o que não
                                    conseguir ler, em vez de abortar)

Regra de segurança inegociável: NUNCA opera sobre o arquivo indicado pelo
usuário — toda a sequência acima roda sobre uma CÓPIA feita antes de
qualquer coisa, numa pasta temporária apagada ao final. O arquivo original
nunca é aberto em modo de escrita/reparo.

Isto é recuperação PARCIAL, não perfeita: páginas/registros realmente
corrompidos são descartados do resultado final, não "consertados" — o
objetivo é salvar o máximo possível do que ainda está íntegro. Quando isso
não é suficiente (corrupção grave demais), a orientação é buscar suporte
especializado em recuperação de banco de dados.

Suporta cancelamento a qualquer momento (mesmo no meio de uma etapa longa,
não só entre etapas) via restore.SinalCancelamento — reaproveitado de
restore.py em vez de duplicado, é o mesmo mecanismo já usado pela
restauração normal.
"""
from __future__ import annotations

import re
import shutil
import subprocess
import tempfile
import threading
from dataclasses import dataclass
from pathlib import Path
from typing import Callable, Optional

import logger
from firebird import InstalacaoFirebird
from restore import SinalCancelamento

OnStatus = Callable[[str], None]

_CREATION_FLAGS = subprocess.CREATE_NO_WINDOW if hasattr(subprocess, "CREATE_NO_WINDOW") else 0

# gfix -mend e gbak -ignore lidam com bancos que já têm problema — podem
# demorar bem mais que uma restauração normal do mesmo tamanho. Mesmo limite
# generoso usado em stats.rodar_validacao_completa (gfix -v -full).
TIMEOUT_PADRAO_POR_ETAPA = 1800

# Formato da saída de um BACKUP (gbak -b) — diferente da saída de um RESTORE
# (gbak -c/-r), que usa "restoring data for table" / "N records restored"
# (ver restore.analisar_saida_gbak). Aqui o gbak imprime, por tabela:
#   gbak:    writing data for table T
#   gbak:2 records written
_PADRAO_TABELA_BACKUP = re.compile(r"writing data for table (\S+)", re.IGNORECASE)
_PADRAO_REGISTROS_BACKUP = re.compile(r"(\d+)\s+records?\s+written", re.IGNORECASE)


@dataclass
class AnaliseSaidaBackup:
    total_registros: int = 0
    quantidade_tabelas_com_dados: int = 0


def _analisar_saida_backup(saida: str) -> AnaliseSaidaBackup:
    tabela_atual: Optional[str] = None
    registros_por_tabela: dict = {}
    for linha in saida.splitlines():
        m_tabela = _PADRAO_TABELA_BACKUP.search(linha)
        if m_tabela:
            tabela_atual = m_tabela.group(1)
            continue
        m_reg = _PADRAO_REGISTROS_BACKUP.search(linha)
        if m_reg and tabela_atual:
            registros_por_tabela[tabela_atual] = registros_por_tabela.get(tabela_atual, 0) + int(m_reg.group(1))
    return AnaliseSaidaBackup(
        total_registros=sum(registros_por_tabela.values()),
        quantidade_tabelas_com_dados=len(registros_por_tabela),
    )


@dataclass
class ResultadoRecuperacao:
    sucesso: bool
    mensagem_usuario: str
    detalhes_tecnicos: str = ""
    caminho_backup_gerado: str = ""
    total_registros_recuperados: int = 0
    quantidade_tabelas_com_dados: int = 0
    cancelado: bool = False


def sugerir_nome_backup_recuperacao(caminho_fdb_corrompido: str) -> str:
    """Nome sugerido para o .fbk de saída: MESMO NOME + sufixo, na mesma
    pasta do banco corrompido — nunca sobrescreve nada por já ser um nome
    novo com timestamp embutido no momento de uso pela UI, se necessário."""
    origem = Path(caminho_fdb_corrompido)
    return str(origem.parent / f"{origem.stem}_RECUPERADO.fbk")


def _rodar_e_logar(
    comando: list[str], timeout: int, segredos: list[str],
    sinal_cancelamento: Optional[SinalCancelamento] = None,
) -> tuple[int, str, bool]:
    """Roda um comando via Popen (não subprocess.run) para poder matá-lo no
    meio da execução se o cancelamento for solicitado — uma etapa como
    `gbak -ignore` pode rodar por dezenas de minutos num banco grande, e
    esperar ela terminar sozinha para só então checar cancelamento deixaria
    o botão "Cancelar" da interface sem efeito prático.
    Retorna (código de retorno, saída completa, foi cancelado)."""
    logger.info(f"Executando: {' '.join(comando)}", segredos=segredos)

    if sinal_cancelamento is not None and sinal_cancelamento.is_set():
        return -1, "Cancelado antes de iniciar esta etapa.", True

    try:
        processo = subprocess.Popen(
            comando, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True,
            creationflags=_CREATION_FLAGS,
        )
    except OSError as exc:
        mensagem = f"Não foi possível executar o comando: {exc}"
        logger.erro(mensagem)
        return -1, mensagem, False

    parar_monitor = threading.Event()
    estado_cancelamento = {"cancelado": False}

    def _monitorar() -> None:
        if sinal_cancelamento is None:
            return
        while not parar_monitor.is_set():
            if sinal_cancelamento.wait(timeout=0.3):
                estado_cancelamento["cancelado"] = True
                try:
                    processo.kill()
                except OSError:
                    pass
                return

    thread_monitor = threading.Thread(target=_monitorar, daemon=True)
    thread_monitor.start()

    try:
        saida, _ = processo.communicate(timeout=timeout)
    except subprocess.TimeoutExpired:
        processo.kill()
        saida, _ = processo.communicate()
        parar_monitor.set()
        thread_monitor.join(timeout=2)
        mensagem = f"Tempo limite ({timeout}s) excedido executando: {' '.join(comando)}"
        logger.erro(mensagem)
        return -1, mensagem, False
    finally:
        parar_monitor.set()
        thread_monitor.join(timeout=2)

    saida_segura = logger.mask_secrets(saida or "", segredos)
    for linha in saida_segura.splitlines():
        if linha.strip():
            logger.info(linha)

    if estado_cancelamento["cancelado"]:
        return processo.returncode, saida_segura, True
    return processo.returncode, saida_segura, False


def recuperar_banco_corrompido(
    caminho_fdb_corrompido: str,
    caminho_backup_saida: str,
    instalacao: InstalacaoFirebird,
    usuario: str,
    senha: str,
    on_status: OnStatus,
    rodar_sweep: bool = True,
    timeout_por_etapa: int = TIMEOUT_PADRAO_POR_ETAPA,
    sinal_cancelamento: Optional[SinalCancelamento] = None,
) -> ResultadoRecuperacao:
    origem = Path(caminho_fdb_corrompido)
    destino_backup = Path(caminho_backup_saida)
    segredos = [senha] if senha else []

    if not origem.exists():
        return ResultadoRecuperacao(False, "O arquivo do banco corrompido informado não existe.")
    if not origem.is_file():
        return ResultadoRecuperacao(False, "O caminho informado não é um arquivo.")
    if instalacao.gfix_path is None:
        return ResultadoRecuperacao(
            False, "gfix.exe não encontrado nesta instalação do Firebird — necessário para a recuperação."
        )
    if destino_backup.exists():
        return ResultadoRecuperacao(
            False, "Já existe um arquivo com esse nome no destino do backup de recuperação. Escolha outro nome.",
        )

    def com_credenciais(comando: list[str]) -> list[str]:
        if usuario:
            comando = comando + ["-user", usuario]
        if senha:
            comando = comando + ["-password", senha]
        return comando

    def cancelado_agora() -> bool:
        return sinal_cancelamento is not None and sinal_cancelamento.is_set()

    def resultado_cancelado(blocos: list[str]) -> ResultadoRecuperacao:
        motivo = sinal_cancelamento.motivo if sinal_cancelamento is not None else "Recuperação cancelada."
        logger.aviso(motivo)
        return ResultadoRecuperacao(False, motivo, detalhes_tecnicos="\n\n".join(blocos), cancelado=True)

    if cancelado_agora():
        return resultado_cancelado([])

    # --- 0) Nunca mexe no original: sempre trabalha numa cópia ---------------
    on_status("Copiando banco para uma área de trabalho temporária (o original nunca é alterado)...")
    pasta_trabalho = Path(tempfile.mkdtemp(prefix="firebird_recuperacao_"))
    copia = pasta_trabalho / "copia_para_reparo.fdb"
    try:
        shutil.copy2(origem, copia)
    except OSError as exc:
        shutil.rmtree(pasta_trabalho, ignore_errors=True)
        return ResultadoRecuperacao(
            False, "Não foi possível copiar o banco para a área de trabalho temporária.",
            detalhes_tecnicos=str(exc),
        )

    blocos_detalhes: list[str] = []
    try:
        if cancelado_agora():
            return resultado_cancelado(blocos_detalhes)

        # --- 1) gfix -mend -full: marca estruturas corrompidas -----------------
        on_status("Marcando estruturas corrompidas para serem ignoradas (gfix -mend -full)...")
        comando_mend = com_credenciais([str(instalacao.gfix_path), "-mend", "-full", str(copia)])
        codigo_mend, saida_mend, cancelado_mend = _rodar_e_logar(
            comando_mend, timeout_por_etapa, segredos, sinal_cancelamento
        )
        blocos_detalhes.append(f"--- gfix -mend -full (código {codigo_mend}) ---\n{saida_mend}")
        if cancelado_mend:
            return resultado_cancelado(blocos_detalhes)
        # gfix -mend costuma retornar código != 0 mesmo quando "funcionou" — o
        # banco tinha mesmo problemas — por isso não tratamos isso sozinho
        # como falha; o teste real é se o gbak consegue gerar um backup
        # depois (etapa 3).

        # --- 2) gfix -sweep (opcional): limpeza de transações antigas ----------
        if rodar_sweep:
            on_status("Limpando transações antigas (gfix -sweep)...")
            comando_sweep = com_credenciais([str(instalacao.gfix_path), "-sweep", str(copia)])
            codigo_sweep, saida_sweep, cancelado_sweep = _rodar_e_logar(
                comando_sweep, timeout_por_etapa, segredos, sinal_cancelamento
            )
            blocos_detalhes.append(f"--- gfix -sweep (código {codigo_sweep}) ---\n{saida_sweep}")
            if cancelado_sweep:
                return resultado_cancelado(blocos_detalhes)

        # --- 3) gbak -b -g -l -ignore: gera o backup "recuperado" ---------------
        if instalacao.gbak_path is None:
            return ResultadoRecuperacao(
                False, "gbak.exe não encontrado nesta instalação do Firebird.",
                detalhes_tecnicos="\n\n".join(blocos_detalhes),
            )
        on_status("Gerando backup ignorando erros de páginas/checksums (gbak -ignore)...")
        destino_backup.parent.mkdir(parents=True, exist_ok=True)
        comando_backup = com_credenciais(
            [str(instalacao.gbak_path), "-b", "-v", "-g", "-l", "-ignore", str(copia), str(destino_backup)]
        )
        codigo_backup, saida_backup, cancelado_backup = _rodar_e_logar(
            comando_backup, timeout_por_etapa, segredos, sinal_cancelamento
        )
        blocos_detalhes.append(f"--- gbak -b -ignore (código {codigo_backup}) ---\n{saida_backup}")
        if cancelado_backup:
            # Um backup parcial pode ter sido criado antes do kill — como o
            # arquivo de destino não é o original, é seguro apagá-lo, para
            # não confundir com um resultado íntegro.
            try:
                if destino_backup.exists():
                    destino_backup.unlink()
            except OSError:
                pass
            return resultado_cancelado(blocos_detalhes)
    finally:
        shutil.rmtree(pasta_trabalho, ignore_errors=True)

    detalhes_tecnicos = "\n\n".join(blocos_detalhes)

    if not destino_backup.exists() or destino_backup.stat().st_size <= 0:
        return ResultadoRecuperacao(
            False,
            "Não foi possível gerar um backup a partir deste banco — os problemas podem ser graves demais "
            "para esta recuperação automática. Consulte os detalhes técnicos; para casos assim, procure "
            "suporte especializado em recuperação de banco de dados.",
            detalhes_tecnicos=detalhes_tecnicos,
        )

    analise = _analisar_saida_backup(saida_backup)
    on_status("Backup de recuperação gerado.")
    logger.info(
        f"Recuperação concluída: backup gerado em {destino_backup} "
        f"({analise.total_registros} registro(s) em {analise.quantidade_tabelas_com_dados} tabela(s))."
    )

    return ResultadoRecuperacao(
        True,
        "Backup de recuperação gerado. Dados que estavam realmente corrompidos foram descartados — "
        "restaure este backup a seguir para conferir o que foi recuperado.",
        detalhes_tecnicos=detalhes_tecnicos,
        caminho_backup_gerado=str(destino_backup),
        total_registros_recuperados=analise.total_registros,
        quantidade_tabelas_com_dados=analise.quantidade_tabelas_com_dados,
    )
