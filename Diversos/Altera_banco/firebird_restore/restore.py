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
        -> MONTAR RESUMO (page size, ODS, tempos, validação opcional)
        -> SUCESSO/ERRO

Este módulo nunca decide sozinho sobrescrever um .fdb existente: quem chama
executar_restauracao() é responsável por já ter resolvido esse conflito
(nome alternativo / restauração paralela) antes de invocar a função — mas o
módulo confere de novo por segurança (defesa em profundidade).

Funciona com qualquer versão do Firebird cuja instalação seja passada em
`instalacao` (2.5, 3.0, 4.0, 5.0...) — a lógica de comando do gbak/gfix/gstat
é a mesma em todas; o que muda é apenas o executável apontado.
"""
from __future__ import annotations

import re
import shutil
import subprocess
import tempfile
import threading
import time
from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path
from typing import Callable, Optional

import logger
import stats
import validator
from firebird import InstalacaoFirebird, indica_incompatibilidade_de_versao

OnStatus = Callable[[str], None]
OnProgress = Callable[[float], None]

_CREATION_FLAGS = subprocess.CREATE_NO_WINDOW if hasattr(subprocess, "CREATE_NO_WINDOW") else 0

# Abaixo desse espaço livre no disco de destino, a restauração é abortada
# automaticamente — evita descobrir "disco cheio" só pelo erro genérico do
# gbak no meio de uma restauração longa.
ESPACO_MINIMO_CRITICO_BYTES = 100 * 1024 * 1024  # 100 MB


class SinalCancelamento:
    """Encapsula um threading.Event com um motivo textual — usado tanto pelo
    botão "Cancelar" da interface quanto pelo monitor de espaço em disco
    (que pode interromper a restauração sozinho, sem o usuário pedir)."""

    def __init__(self) -> None:
        self._evento = threading.Event()
        self.motivo = "Restauração cancelada pelo usuário."

    def set(self, motivo: Optional[str] = None) -> None:
        if motivo:
            self.motivo = motivo
        self._evento.set()

    def is_set(self) -> bool:
        return self._evento.is_set()

    def wait(self, timeout: Optional[float] = None) -> bool:
        return self._evento.wait(timeout)


@dataclass
class ResumoRestauracao:
    """Tudo que aparece na tela de resumo ao final de uma restauração
    bem-sucedida."""
    firebird_usado: str
    nome_banco_antigo: str
    nome_banco_novo: str
    caminho_destino: str
    tamanho_backup_formatado: str
    tamanho_destino_formatado: str
    hora_inicio: str
    hora_fim: str
    duracao_formatada: str
    duracao_segundos: float = 0.0
    nome_arquivo_backup: str = ""
    versao_firebird_detalhada: str = ""
    formato_backup_versao: Optional[str] = None
    page_size: Optional[int] = None
    ods_version: Optional[str] = None
    dialeto: Optional[int] = None
    validacao_executada: bool = False
    validacao_erros: int = 0
    validacao_avisos: int = 0
    validacao_mensagem: str = ""
    tabelas_com_problema: list = field(default_factory=list)
    total_registros_restaurados: int = 0
    quantidade_tabelas_com_dados: int = 0
    # Comparação de tamanho com o banco original (antes do backup), quando o
    # usuário informa esse arquivo — o backup por si só não guarda o tamanho
    # físico original, só os dados lógicos.
    tamanho_original_formatado: Optional[str] = None
    diferenca_tamanho_formatada: Optional[str] = None
    # Versão do sistema TGA (tabela GDIVERSOS), quando o banco restaurado for
    # um banco do sistema TGA — None quando não aplicável.
    tga_versao_base: Optional[str] = None
    tga_data_atualizacao: Optional[str] = None
    tga_versao_mobile: Optional[str] = None
    tga_start: Optional[str] = None
    # Erros reportados pelo próprio gbak durante a restauração (ex.: violação
    # de FOREIGN KEY ao ativar um índice) que NÃO impediram a conclusão do
    # restore — diferente de validacao_erros, que só é preenchido quando a
    # validação completa opcional (gfix -v -full) é executada. Isto fica
    # sempre disponível, mesmo sem marcar essa opção.
    gbak_erros_count: int = 0
    gbak_linhas_erro: list = field(default_factory=list)


@dataclass
class ResultadoRestauracao:
    sucesso: bool
    mensagem_usuario: str
    detalhes_tecnicos: str = ""
    caminho_destino: str = ""
    resumo: Optional[ResumoRestauracao] = None


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
     "Tente selecionar outra instalação do Firebird (por exemplo, 2.5 em vez de 5.0)."),
    (r"no such file or directory|file.*not found", "Arquivo de backup não encontrado durante a restauração."),
    (r"insufficient|not enough.*space|disk full", "Espaço em disco insuficiente durante a restauração."),
    (r"network error|unable to complete network request",
     "Erro de comunicação com o serviço do Firebird."),
    # Indícios de que o ARQUIVO DE BACKUP em si está corrompido ou truncado
    # (cópia incompleta, disco cheio durante o backup original, transferência
    # interrompida...) — diferente de um erro de índice/chave estrangeira
    # (dados órfãos dentro de um backup íntegro), isto invalida a restauração
    # inteira: o gbak pode abortar no meio já com o .fdb parcialmente criado,
    # e sem este padrão esse caso seria confundido com "sucesso com avisos".
    (r"unexpected end of file|end of file encountered|expected backup start|bad attribute block length"
     r"|decompression overrun|database file appears corrupt|checksum error",
     "O arquivo de backup parece estar corrompido ou incompleto (truncado). Gere um novo backup a partir "
     "do banco original, se possível, ou tente restaurar de outra cópia deste arquivo."),
]


def traduzir_erro_gbak(saida_tecnica: str) -> str:
    for padrao, mensagem in _PADROES_ERRO_CONHECIDOS:
        if re.search(padrao, saida_tecnica, re.IGNORECASE):
            return mensagem
    return "Ocorreu um erro durante a restauração. Consulte os detalhes técnicos."


_PADRAO_TABELA_RESTORE = re.compile(r"restoring (?:data for )?table (\S+)", re.IGNORECASE)
_PADRAO_REGISTROS_RESTAURADOS = re.compile(r"(\d+)\s+records?\s+restored", re.IGNORECASE)

# Identifica, linha a linha, quando o próprio gbak já sinalizou um problema
# (ex.: "gbak: ERROR:violation of FOREIGN KEY constraint..." ou
# "gbak:cannot commit index ..."). Usado para destacar essas linhas em
# vermelho na área de log da interface, em vez de misturá-las com o
# progresso normal (que é apenas informativo).
_PADRAO_LINHA_ERRO_GBAK = re.compile(r"\b(error|cannot|failed)\b", re.IGNORECASE)


@dataclass
class ClassificacaoResultadoGbak:
    eh_falha_fatal: bool
    linhas_erro: list = field(default_factory=list)


def classificar_resultado_gbak(
    codigo_retorno: int, saida_tecnica: str, banco_foi_criado: bool
) -> ClassificacaoResultadoGbak:
    """Decide se uma restauração deve ser tratada como falha FATAL (nada de
    aproveitável foi criado: erro de infraestrutura como permissão, disco,
    versão incompatível, arquivo não encontrado etc. — sempre coberto por
    _PADROES_ERRO_CONHECIDOS) ou como conclusão bem-sucedida com erros NÃO
    fatais que o próprio gbak reportou sem abortar o processo (o caso mais
    comum: "cannot commit index" / "violation of FOREIGN KEY constraint" ao
    tentar ativar um índice deferred — os dados são restaurados normalmente,
    só aquele índice fica sem ser recriado).

    Um código de retorno != 0 sozinho não basta para classificar como fatal:
    o gbak retorna esse mesmo código de erro de índice mesmo quando o
    restore foi concluído e o banco existe com os dados — por isso também
    olhamos se o arquivo de destino foi de fato criado.

    Extraída como função pura (sem I/O) para poder ser testada de forma
    isolada e exaustiva, sem precisar rodar o gbak de verdade nem mockar
    subprocess — ver tests/test_restore.py."""
    erro_fatal_conhecido = any(
        re.search(padrao, saida_tecnica, re.IGNORECASE) for padrao, _ in _PADROES_ERRO_CONHECIDOS
    )
    if erro_fatal_conhecido or (codigo_retorno != 0 and not banco_foi_criado):
        return ClassificacaoResultadoGbak(eh_falha_fatal=True)

    linhas_erro = [linha for linha in saida_tecnica.splitlines() if _PADRAO_LINHA_ERRO_GBAK.search(linha)]
    return ClassificacaoResultadoGbak(eh_falha_fatal=False, linhas_erro=linhas_erro)


@dataclass
class AnaliseSaidaGbak:
    """Contagens extraídas da própria saída do gbak durante o restore — não
    depende de rodar a validação completa opcional. É a fonte mais confiável
    de 'quantos registros foram efetivamente gravados', já que o gbak imprime
    isso tabela por tabela em tempo real."""
    total_registros_restaurados: int = 0
    quantidade_tabelas_com_dados: int = 0
    registros_por_tabela: dict = field(default_factory=dict)


def analisar_saida_gbak(saida: str) -> AnaliseSaidaGbak:
    tabela_atual: Optional[str] = None
    registros_por_tabela: dict = {}
    for linha in saida.splitlines():
        m_tabela = _PADRAO_TABELA_RESTORE.search(linha)
        if m_tabela:
            tabela_atual = m_tabela.group(1)
            continue
        m_reg = _PADRAO_REGISTROS_RESTAURADOS.search(linha)
        if m_reg and tabela_atual:
            registros_por_tabela[tabela_atual] = registros_por_tabela.get(tabela_atual, 0) + int(m_reg.group(1))
    return AnaliseSaidaGbak(
        total_registros_restaurados=sum(registros_por_tabela.values()),
        quantidade_tabelas_com_dados=len(registros_por_tabela),
        registros_por_tabela=registros_por_tabela,
    )


def formatar_duracao(segundos: float) -> str:
    segundos = int(round(segundos))
    horas, resto = divmod(segundos, 3600)
    minutos, segs = divmod(resto, 60)
    if horas:
        return f"{horas}h {minutos}min {segs}s"
    if minutos:
        return f"{minutos}min {segs}s"
    return f"{segs}s"


def extrair_tabelas_com_dados_da_saida_metadados(saida: str) -> set:
    """A partir da saída de um `gbak -m` (metadata only), retorna o conjunto
    de tabelas que têm dados próprios — exclui views, que aparecem na saída
    mas nunca disparam "restoring data for table" na restauração real (sem
    isso, o denominador do progresso ficaria maior que o número de
    incrementos possíveis, e a barra nunca chegaria perto de 100%)."""
    tabelas: set = set()
    tabela_atual: Optional[str] = None
    for linha in saida.splitlines():
        m = re.search(r"restoring table (\S+)", linha, re.IGNORECASE)
        if m:
            tabela_atual = m.group(1)
            tabelas.add(tabela_atual)
            continue
        if tabela_atual and re.search(r"\bis a view\b", linha, re.IGNORECASE):
            tabelas.discard(tabela_atual)
            tabela_atual = None
    return tabelas


def contar_tabelas_do_backup(
    gbak_path: Path, backup_efetivo: Path, usuario: str, senha: str, timeout: int = 120
) -> Optional[int]:
    """Faz uma restauração SÓ DE METADADOS (gbak -m) num banco temporário e
    descartável, contando quantas tabelas de dados existem — isso demora uma
    fração do tempo da restauração completa (não grava nenhum registro) e
    permite uma barra de progresso real ("tabela 8 de 42") em vez de uma
    estimativa por tamanho de arquivo. Retorna None se algo falhar — quem
    chama deve então cair de volta para a estimativa por tamanho."""
    with tempfile.TemporaryDirectory(prefix="firebird_contagem_") as pasta_temp:
        banco_temporario = Path(pasta_temp) / "contagem.fdb"
        comando = [str(gbak_path), "-c", "-v", "-m"]
        if usuario:
            comando += ["-user", usuario]
        if senha:
            comando += ["-password", senha]
        comando += [str(backup_efetivo), str(banco_temporario)]

        try:
            resultado = subprocess.run(
                comando, capture_output=True, text=True, timeout=timeout, creationflags=_CREATION_FLAGS
            )
        except (OSError, subprocess.TimeoutExpired) as exc:
            logger.aviso(f"Não foi possível pré-contar tabelas do backup (seguindo com estimativa por tamanho): {exc}")
            return None

        saida = (resultado.stdout or "") + (resultado.stderr or "")
        tabelas = extrair_tabelas_com_dados_da_saida_metadados(saida)
        if not tabelas:
            return None
        return len(tabelas)


def _monitorar_progresso_por_tamanho(
    destino: Path, tamanho_estimado_final: int, on_progress: OnProgress, parar: threading.Event
) -> None:
    """Estimativa de progresso (fallback quando a pré-contagem de tabelas não
    deu certo): acompanha o crescimento do arquivo de destino e compara com
    o tamanho final estimado. Aproximado por natureza — por isso o teto fica
    em 97% até a confirmação final de sucesso."""
    while not parar.is_set():
        try:
            if destino.exists() and tamanho_estimado_final > 0:
                tamanho_atual = destino.stat().st_size
                pct = min(97.0, (tamanho_atual / tamanho_estimado_final) * 100)
                on_progress(pct)
        except OSError:
            pass
        parar.wait(0.5)


def _monitorar_espaco_disco(
    destino: Path, sinal_cancelamento: SinalCancelamento, parar: threading.Event,
    limite_bytes: int = ESPACO_MINIMO_CRITICO_BYTES,
) -> None:
    """Aborta a restauração automaticamente se o espaço livre no disco de
    destino cair abaixo do limite crítico — evita descobrir "disco cheio"
    só pela mensagem genérica de erro do gbak, no meio de uma restauração
    de um banco grande."""
    pasta = destino.parent
    while not parar.is_set():
        try:
            if pasta.exists():
                livre = shutil.disk_usage(pasta).free
                if livre < limite_bytes:
                    sinal_cancelamento.set(
                        f"Restauração interrompida automaticamente: sobraram apenas "
                        f"{validator.formatar_tamanho(livre)} de espaço livre no disco de destino."
                    )
                    return
        except OSError:
            pass
        parar.wait(2.0)


def _monitorar_cancelamento(
    obter_processo: Callable[[], Optional[subprocess.Popen]], sinal_cancelamento: SinalCancelamento,
    parar: threading.Event,
) -> None:
    """Mata o processo do gbak assim que o cancelamento for pedido (pelo
    usuário ou pelo monitor de espaço em disco), mesmo se ele estiver
    "quieto" (sem imprimir novas linhas) no momento — não depende do loop
    de leitura de stdout perceber o pedido."""
    while not parar.is_set():
        if sinal_cancelamento.wait(timeout=0.3):
            processo = obter_processo()
            if processo is not None and processo.poll() is None:
                try:
                    processo.kill()
                except OSError:
                    pass
            return


_PADRAO_DADOS_TABELA = re.compile(r"restoring data for table (\S+)", re.IGNORECASE)


def _rodar_gbak_restore(
    gbak_path: Path,
    backup_efetivo: Path,
    destino: Path,
    usuario: str,
    senha: str,
    on_status: OnStatus,
    on_progress: Optional[OnProgress],
    tamanho_estimado_final: int,
    page_size: Optional[int] = None,
    charset: Optional[str] = None,
    sinal_cancelamento: Optional[SinalCancelamento] = None,
    total_tabelas: Optional[int] = None,
) -> tuple[int, str, Optional[str], bool]:
    comando = [str(gbak_path), "-c", "-v"]
    # A rotina de manutenção usada até hoje na empresa sempre restaura com
    # "-p 16384" (page size fixo), independente do page size gravado no
    # backup original — isso muda o tamanho físico final do banco. Deixamos
    # configurável em vez de fixo, mas com esse valor como sugestão padrão.
    if page_size:
        comando += ["-p", str(page_size)]
    # Corrige dados/metadados gravados com charset malformado (comum em bases
    # antigas migradas do InterBase, ou com charset NONE original) — sem isso,
    # colunas com acentuação podem falhar com "cannot transliterate".
    if charset:
        comando += ["-fix_fss_data", charset, "-fix_fss_metadata", charset]
    if usuario:
        comando += ["-user", usuario]
    if senha:
        comando += ["-password", senha]
    comando += [str(backup_efetivo), str(destino)]

    segredos = [senha] if senha else []
    logger.info(f"Executando: {' '.join(comando)}", segredos=segredos)

    parar_monitor = threading.Event()
    threads_auxiliares: list[threading.Thread] = []

    # Progresso por tabela é sempre preferido quando disponível (mais preciso
    # que o crescimento do arquivo, que não é linear no tempo); só cai para
    # a estimativa por tamanho quando a pré-contagem de tabelas não deu certo.
    tabelas_processadas = {"n": 0}
    usar_progresso_por_tabela = on_progress is not None and total_tabelas
    if on_progress is not None and not usar_progresso_por_tabela:
        thread_progresso = threading.Thread(
            target=_monitorar_progresso_por_tamanho,
            args=(destino, tamanho_estimado_final, on_progress, parar_monitor),
            daemon=True,
        )
        thread_progresso.start()
        threads_auxiliares.append(thread_progresso)

    if sinal_cancelamento is not None:
        thread_espaco = threading.Thread(
            target=_monitorar_espaco_disco,
            args=(destino, sinal_cancelamento, parar_monitor),
            daemon=True,
        )
        thread_espaco.start()
        threads_auxiliares.append(thread_espaco)

    linhas_saida: list[str] = []
    versao_formato_backup: Optional[str] = None
    padrao_versao_backup = re.compile(r"backup version is\s*(\S+)", re.IGNORECASE)
    processo_ref: dict = {"p": None}
    cancelado = False
    processo: Optional[subprocess.Popen] = None

    try:
        processo = subprocess.Popen(
            comando,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True,
            bufsize=1,
            creationflags=_CREATION_FLAGS,
        )
        processo_ref["p"] = processo

        if sinal_cancelamento is not None:
            thread_cancelamento = threading.Thread(
                target=_monitorar_cancelamento,
                args=(lambda: processo_ref["p"], sinal_cancelamento, parar_monitor),
                daemon=True,
            )
            thread_cancelamento.start()
            threads_auxiliares.append(thread_cancelamento)

        assert processo.stdout is not None
        for linha in processo.stdout:
            if sinal_cancelamento is not None and sinal_cancelamento.is_set():
                cancelado = True
                break
            linha = linha.rstrip("\n")
            if not linha:
                continue
            # Mascarar antes de guardar/repassar: a senha nunca deve sobreviver
            # no texto de detalhes técnicos exibido na interface, mesmo no caso
            # improvável de ela ecoar na saída do processo.
            linha_segura = logger.mask_secrets(linha, segredos)
            linhas_saida.append(linha_segura)
            # Linhas em que o próprio gbak já sinaliza erro (ex.: violação de
            # FOREIGN KEY, "cannot commit index"...) aparecem destacadas em
            # vermelho na tela, em vez de misturadas com o progresso normal.
            if _PADRAO_LINHA_ERRO_GBAK.search(linha_segura):
                logger.erro(linha_segura)
            else:
                logger.info(linha_segura)
            on_status(linha_segura[:120])

            m = padrao_versao_backup.search(linha_segura)
            if m:
                versao_formato_backup = m.group(1)

            if usar_progresso_por_tabela and _PADRAO_DADOS_TABELA.search(linha_segura):
                tabelas_processadas["n"] += 1
                pct = min(99.0, (tabelas_processadas["n"] / total_tabelas) * 100)
                on_progress(pct)

        if cancelado:
            try:
                processo.kill()
            except OSError:
                pass
        processo.wait()
    finally:
        parar_monitor.set()
        for thread in threads_auxiliares:
            thread.join(timeout=2)
        if processo is not None and processo.stdout is not None:
            processo.stdout.close()

    if sinal_cancelamento is not None and sinal_cancelamento.is_set():
        cancelado = True

    return processo.returncode, "\n".join(linhas_saida), versao_formato_backup, cancelado


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
            creationflags=_CREATION_FLAGS,
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
    on_progress: Optional[OnProgress] = None,
    validar_integridade_completa: bool = False,
    caminho_banco_original_comparacao: str = "",
    page_size: Optional[int] = None,
    charset: Optional[str] = None,
    sinal_cancelamento: Optional[SinalCancelamento] = None,
) -> ResultadoRestauracao:
    destino = Path(caminho_destino)
    hora_inicio_dt = datetime.now()

    # --- 1) Validar arquivo de backup -----------------------------------
    on_status("Validando backup...")
    validacao_backup = validator.validar_arquivo_backup(caminho_backup)
    if not validacao_backup.ok:
        return ResultadoRestauracao(False, validacao_backup.mensagem_usuario, validacao_backup.detalhes_tecnicos)

    nome_banco_antigo = (
        validator.extrair_nome_banco_original(caminho_backup, validacao_backup.metadados.get("comprimido", False))
        or validacao_backup.metadados["nome"]
    )

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

    tamanho_estimado_final = int(validacao_backup.metadados["tamanho_bytes"] * fator_estimativa)

    # --- 4) Descompactar se necessário (.fbk.gz) --------------------------
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

        # --- 6) Pré-contar tabelas para progresso preciso ---------------------
        # Uma restauração só de metadados (rápida, sem gravar dados) revela
        # quantas tabelas existem — a barra de progresso vira "tabela X de Y"
        # em vez de uma estimativa por tamanho de arquivo. Se falhar por
        # qualquer motivo, cai de volta para a estimativa por tamanho sozinha.
        total_tabelas = None
        if on_progress is not None:
            on_status("Contando tabelas do backup para estimar progresso...")
            total_tabelas = contar_tabelas_do_backup(instalacao.gbak_path, backup_efetivo, usuario, senha)
            if total_tabelas:
                logger.info(f"{total_tabelas} tabela(s) encontrada(s) no backup — progresso será por tabela.")

        # --- 7) Restaurar de fato ------------------------------------------
        on_status("Iniciando restauração...")
        logger.info(f"Restauração iniciada: backup={caminho_backup} destino={destino} "
                    f"firebird={instalacao.versao_texto}")
        if sinal_cancelamento is not None and sinal_cancelamento.is_set():
            return ResultadoRestauracao(False, sinal_cancelamento.motivo)

        try:
            codigo_retorno, saida_tecnica, versao_formato_backup, cancelado = _rodar_gbak_restore(
                instalacao.gbak_path, backup_efetivo, destino, usuario, senha,
                on_status, on_progress, tamanho_estimado_final, page_size, charset, sinal_cancelamento,
                total_tabelas,
            )
        except OSError as exc:
            return ResultadoRestauracao(
                False, "Não foi possível executar o gbak.exe. Verifique a instalação do Firebird.",
                detalhes_tecnicos=str(exc),
            )

    # pasta_temp (e o .fbk descompactado) são removidos automaticamente aqui.

    if cancelado:
        motivo = sinal_cancelamento.motivo if sinal_cancelamento is not None else "Restauração cancelada."
        logger.aviso(motivo)
        # O arquivo de destino, se chegou a ser criado, está incompleto e não
        # deve ser confundido com um banco válido — como garantimos antes que
        # ele não existia previamente, é seguro apagar o que sobrou.
        try:
            if destino.exists():
                destino.unlink()
                logger.info(f"Arquivo parcial removido após cancelamento: {destino}")
        except OSError as exc:
            logger.aviso(f"Não foi possível remover o arquivo parcial após cancelar: {exc}")
        return ResultadoRestauracao(False, motivo, saida_tecnica)

    classificacao = classificar_resultado_gbak(
        codigo_retorno, saida_tecnica, banco_foi_criado=destino.exists() and destino.stat().st_size > 0
    )
    if classificacao.eh_falha_fatal:
        mensagem = traduzir_erro_gbak(saida_tecnica)
        if indica_incompatibilidade_de_versao(saida_tecnica):
            logger.erro("Possível incompatibilidade de versão entre backup e Firebird instalado.")
        logger.erro(f"Restauração falhou (código {codigo_retorno}).")
        return ResultadoRestauracao(False, mensagem, saida_tecnica, str(destino))

    linhas_erro_gbak = classificacao.linhas_erro
    if linhas_erro_gbak:
        logger.erro(
            f"Restauração concluída, mas o gbak reportou {len(linhas_erro_gbak)} erro(s)/aviso(s) "
            f"durante o processo (código de retorno {codigo_retorno}) — provavelmente índice(s) que "
            f"não puderam ser recriados. Veja o resumo e os detalhes técnicos."
        )

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

    # --- 8) Coletar informações para o resumo (page size, ODS...) ------------
    on_status("Coletando informações do banco (page size, ODS)...")
    info_header = stats.obter_info_header(instalacao.gstat_path, str(destino), usuario, senha)

    # Versão do sistema TGA (tabela GDIVERSOS) — não é um erro se o banco não
    # tiver essa tabela, só significa que não é um banco do sistema TGA.
    info_tga = stats.obter_versao_sistema_tga(instalacao.isql_path, str(destino), usuario, senha)

    validacao_integridade = None
    if validar_integridade_completa:
        on_status("Validando integridade completa (gfix -v -full, pode demorar)...")
        validacao_integridade = stats.rodar_validacao_completa(instalacao.gfix_path, str(destino), usuario, senha)

    # Registros efetivamente gravados, extraídos da própria saída do gbak —
    # sempre disponível, mesmo sem marcar a validação completa.
    analise_gbak = analisar_saida_gbak(saida_tecnica)

    # Comparação opcional com o tamanho do banco ANTES do backup. O backup em
    # si não guarda esse dado (só os registros lógicos), então só é possível
    # comparar se o usuário apontar o arquivo .fdb original.
    tamanho_original_formatado = None
    diferenca_tamanho_formatada = None
    if caminho_banco_original_comparacao:
        try:
            tamanho_original_bytes = Path(caminho_banco_original_comparacao).stat().st_size
            tamanho_destino_bytes = destino.stat().st_size
            tamanho_original_formatado = validator.formatar_tamanho(tamanho_original_bytes)
            diferenca_bytes = tamanho_destino_bytes - tamanho_original_bytes
            sinal = "+" if diferenca_bytes >= 0 else "-"
            diferenca_tamanho_formatada = f"{sinal}{validator.formatar_tamanho(abs(diferenca_bytes))}"
        except OSError as exc:
            logger.aviso(f"Não foi possível ler o tamanho do banco original para comparação: {exc}")

    if on_progress is not None:
        on_progress(100.0)
    on_status("Concluído.")

    hora_fim_dt = datetime.now()
    duracao_segundos = (hora_fim_dt - hora_inicio_dt).total_seconds()
    resumo = ResumoRestauracao(
        firebird_usado=instalacao.rotulo,
        nome_banco_antigo=nome_banco_antigo,
        nome_banco_novo=destino.name,
        caminho_destino=str(destino),
        tamanho_backup_formatado=validacao_backup.metadados["tamanho_formatado"],
        tamanho_destino_formatado=validator.formatar_tamanho(destino.stat().st_size),
        hora_inicio=hora_inicio_dt.strftime("%d/%m/%Y %H:%M:%S"),
        hora_fim=hora_fim_dt.strftime("%d/%m/%Y %H:%M:%S"),
        duracao_formatada=formatar_duracao(duracao_segundos),
        duracao_segundos=duracao_segundos,
        nome_arquivo_backup=Path(caminho_backup).name,
        versao_firebird_detalhada=instalacao.versao_detalhada,
        formato_backup_versao=versao_formato_backup,
        page_size=info_header.page_size,
        ods_version=info_header.ods_version,
        dialeto=info_header.dialeto,
        validacao_executada=validacao_integridade is not None,
        validacao_erros=validacao_integridade.erros if validacao_integridade else 0,
        validacao_avisos=validacao_integridade.avisos if validacao_integridade else 0,
        validacao_mensagem=validacao_integridade.mensagem if validacao_integridade else "Não executada.",
        tabelas_com_problema=validacao_integridade.tabelas_com_problema if validacao_integridade else [],
        total_registros_restaurados=analise_gbak.total_registros_restaurados,
        quantidade_tabelas_com_dados=analise_gbak.quantidade_tabelas_com_dados,
        tamanho_original_formatado=tamanho_original_formatado,
        diferenca_tamanho_formatada=diferenca_tamanho_formatada,
        tga_versao_base=info_tga.versao_base if info_tga.encontrado else None,
        tga_data_atualizacao=info_tga.data_atualizacao if info_tga.encontrado else None,
        tga_versao_mobile=info_tga.versao_mobile if info_tga.encontrado else None,
        tga_start=info_tga.tga_start if info_tga.encontrado else None,
        gbak_erros_count=len(linhas_erro_gbak),
        gbak_linhas_erro=linhas_erro_gbak,
    )

    logger.info(f"Restauração concluída com sucesso: {destino} (duração {resumo.duracao_formatada})")
    return ResultadoRestauracao(
        True, "Banco restaurado com sucesso.", saida_tecnica, str(destino), resumo=resumo,
    )
