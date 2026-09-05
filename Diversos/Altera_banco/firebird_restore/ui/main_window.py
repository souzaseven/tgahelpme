"""
ui/main_window.py
------------------
Interface gráfica (Tkinter) do Restaurador Firebird. Mantida deliberadamente
simples: campos grandes, um único botão de ação principal, status sempre
visível e um painel de detalhes para acompanhar o progresso em tempo real.

A restauração roda em uma thread separada para a janela nunca "travar";
toda atualização de widgets a partir dessa thread passa por `self.after(...)`,
que é a forma thread-safe de mexer no Tkinter.

Os diálogos secundários (resumo, histórico, lista de erros...) moram em
ui/dialogos.py; pequenos utilitários de widget (tooltip, busca, salvar .txt)
em ui/widgets.py; e as paletas de cor em ui/estilos.py — este arquivo fica só
com a janela principal em si.
"""
from __future__ import annotations

import re
import sys
import threading
import tkinter as tk
from datetime import datetime
from pathlib import Path
from tkinter import filedialog, messagebox, scrolledtext, ttk

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import config
import firebird
import logger
import notificacoes
import restore
import validator
from ui.dialogos import (
    DialogoConflitoDestino,
    ItemHistorico,
    JanelaDetalhesTecnicos,
    JanelaHistoricoSessao,
    JanelaResumo,
    JanelaSomenteErros,
)
from ui.estilos import CORES, fundo_log, paleta_log
from ui.janela_fila_restauracao import JanelaFilaRestauracao
from ui.janela_recuperacao import JanelaRecuperacao
from ui.widgets import adicionar_barra_busca, dica, salvar_texto_como_arquivo
from version import VERSAO

try:
    import winsound  # só existe no Windows — é o SO alvo desta ferramenta
except ImportError:
    winsound = None


class JanelaPrincipal(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title(f"Restaurador Firebird v{VERSAO}")
        # Layout em duas colunas (controles + detalhes lado a lado): mais
        # largo e menos alto, para o painel de Detalhes nunca ficar escondido
        # abaixo do botão Restaurar em telas menores ou janelas não maximizadas.
        # Altura padrão aumentada (era 680) para acomodar os campos e botões
        # que foram sendo adicionados — os botões de ação/status, de qualquer
        # forma, ficam sempre visíveis mesmo em janelas menores (ver
        # frame_acoes_fixas, fora da área rolável).
        self.geometry("1180x760")
        self.minsize(900, 520)

        self.settings = config.carregar_configuracoes()
        self.instalacoes: list[firebird.InstalacaoFirebird] = []
        self.metadados_backup: dict | None = None
        self.ultimo_resultado: restore.ResultadoRestauracao | None = None
        self.restaurando = False
        self.hora_inicio_restauracao: datetime | None = None
        self.percentual_atual = 0.0
        # Histórico de todas as restaurações feitas nesta sessão (não persiste
        # entre execuções do programa) — cada item é um ItemHistorico.
        self.historico_sessao: list[ItemHistorico] = []
        self.sinal_cancelamento: restore.SinalCancelamento | None = None
        # Visual da área de acompanhamento (normal x "hacker") — lembrado
        # entre execuções, igual às demais preferências salvas.
        self.tema_hacker = self.settings.tema_hacker
        # ID do próximo "tick" do cronômetro agendado via self.after(); usado
        # para cancelar o loop anterior ao iniciar uma nova restauração — sem
        # isso, restaurar mais de uma vez na mesma sessão deixava ticks
        # antigos rodando em paralelo com o novo, cada um decaindo/recalculando
        # o "tempo restante" de forma independente e fazendo o valor exibido
        # pular para cima quando um tick atrasado sobrescrevia o mais recente.
        self._id_tick_cronometro: str | None = None

        self._construir_widgets()
        logger.registrar_listener(self._on_log_thread_safe)
        self.protocol("WM_DELETE_WINDOW", self._ao_fechar)
        self._detectar_firebird_async()

    # ------------------------------------------------------------------ UI
    def _construir_widgets(self) -> None:
        # Empacotado antes do resto (side="bottom") para reservar o espaço no
        # rodapé — ajuda a identificar a versão ao reportar um problema.
        tk.Label(self, text=f"Restaurador Firebird v{VERSAO}", fg="#999", font=("Segoe UI", 8)).pack(
            side="bottom", pady=(0, 4)
        )

        tk.Label(self, text="RESTAURADOR FIREBIRD", font=("Segoe UI", 16, "bold")).pack(pady=(12, 2))
        tk.Label(
            self,
            text="Restaura um backup (.fbk ou .fbk.gz) para um NOVO banco .fdb, sem alterar o original.",
            fg="#555",
        ).pack(pady=(0, 8))

        # Layout em duas colunas: os controles ficam à esquerda (rolável, para
        # nunca depender de esticar a janela), e o log de "Detalhes" fica
        # sempre visível à direita, ocupando toda a altura — assim ele nunca
        # fica escondido abaixo do botão Restaurar em janelas menores.
        painel = ttk.PanedWindow(self, orient="horizontal")
        painel.pack(fill="both", expand=True, padx=10, pady=(0, 10))

        painel_esquerdo = tk.Frame(painel)
        painel.add(painel_esquerdo, weight=0)

        # Ações e status ficam FORA da área rolável, ancorados no rodapé desta
        # coluna — sempre visíveis, nunca dependem do usuário perceber que dá
        # para rolar. Empacotado ANTES do Canvas (side="bottom") para reservar
        # esse espaço primeiro; o Canvas rolável abaixo ocupa o restante.
        # (ver mais abaixo, seção "Ações e status (fixos)")
        frame_acoes_fixas = tk.Frame(painel_esquerdo)
        frame_acoes_fixas.pack(side="bottom", fill="x")
        ttk.Separator(painel_esquerdo, orient="horizontal").pack(side="bottom", fill="x", pady=(4, 4))

        canvas_esq = tk.Canvas(painel_esquerdo, highlightthickness=0, width=430)
        scrollbar_esq = ttk.Scrollbar(painel_esquerdo, orient="vertical", command=canvas_esq.yview)
        container = tk.Frame(canvas_esq)
        container.bind("<Configure>", lambda e: canvas_esq.configure(scrollregion=canvas_esq.bbox("all")))
        canvas_esq.create_window((0, 0), window=container, anchor="nw", width=430)
        canvas_esq.configure(yscrollcommand=scrollbar_esq.set)
        canvas_esq.pack(side="left", fill="both", expand=True)
        scrollbar_esq.pack(side="right", fill="y")
        canvas_esq.bind("<Enter>", lambda e: canvas_esq.bind_all(
            "<MouseWheel>", lambda ev: canvas_esq.yview_scroll(int(-1 * (ev.delta / 120)), "units")
        ))
        canvas_esq.bind("<Leave>", lambda e: canvas_esq.unbind_all("<MouseWheel>"))

        container.columnconfigure(0, weight=1)

        # --- Backup ------------------------------------------------------
        secao_backup = tk.LabelFrame(container, text="Backup (.fbk / .fbk.gz)", padx=10, pady=8)
        secao_backup.pack(fill="x", pady=(0, 6))
        secao_backup.columnconfigure(0, weight=1)

        self.var_backup = tk.StringVar()
        entry_backup = tk.Entry(secao_backup, textvariable=self.var_backup, state="readonly")
        entry_backup.grid(row=0, column=0, sticky="ew")
        botao_sel_backup = tk.Button(secao_backup, text="Selecionar...", command=self._selecionar_backup)
        botao_sel_backup.grid(row=0, column=1, padx=(8, 0))
        dica(entry_backup, "Caminho do arquivo de backup do Firebird que será restaurado. "
                            "Aceita .fbk (puro) ou .fbk.gz (comprimido).")
        dica(botao_sel_backup, "Abre o seletor de arquivos para escolher o backup (.fbk ou .fbk.gz) a restaurar.")

        self.var_info_backup = tk.StringVar(value="Nenhum backup selecionado.")
        tk.Label(secao_backup, textvariable=self.var_info_backup, fg="#555", justify="left", anchor="w").grid(
            row=1, column=0, columnspan=2, sticky="w", pady=(6, 0)
        )

        # --- Destino -------------------------------------------------------
        secao_destino = tk.LabelFrame(container, text="Banco de destino (novo .fdb)", padx=10, pady=8)
        secao_destino.pack(fill="x", pady=6)
        secao_destino.columnconfigure(0, weight=1)

        self.var_destino = tk.StringVar()
        entry_destino = tk.Entry(secao_destino, textvariable=self.var_destino)
        entry_destino.grid(row=0, column=0, sticky="ew")
        botao_sel_destino = tk.Button(secao_destino, text="Selecionar...", command=self._selecionar_destino)
        botao_sel_destino.grid(row=0, column=1, padx=(8, 0))
        dica(entry_destino, "Caminho do NOVO arquivo .fdb que será criado com os dados do backup. "
                             "Se já existir um arquivo com esse nome, você poderá escolher outro nome ou "
                             "criar uma restauração paralela — nunca é sobrescrito automaticamente.")
        dica(botao_sel_destino, "Escolhe em qual pasta e com qual nome o banco restaurado será criado.")

        label_original = tk.Label(secao_destino, text="Banco original (opcional, p/ comparar tamanho):", fg="#555")
        label_original.grid(row=1, column=0, columnspan=2, sticky="w", pady=(8, 0))
        frame_original = tk.Frame(secao_destino)
        frame_original.grid(row=2, column=0, columnspan=2, sticky="ew", pady=(2, 0))
        frame_original.columnconfigure(0, weight=1)
        self.var_banco_original_comparacao = tk.StringVar()
        entry_original = tk.Entry(frame_original, textvariable=self.var_banco_original_comparacao, state="readonly")
        entry_original.grid(row=0, column=0, sticky="ew")
        botao_sel_original = tk.Button(frame_original, text="Selecionar...", command=self._selecionar_banco_original)
        botao_sel_original.grid(row=0, column=1, padx=(8, 0))
        texto_dica_original = (
            "Opcional: aponte o arquivo .fdb do banco ANTES do backup (se ainda existir) para o resumo "
            "final mostrar quanto o tamanho mudou depois da restauração. Não é usado na restauração em si."
        )
        dica(label_original, texto_dica_original)
        dica(entry_original, texto_dica_original)
        dica(botao_sel_original, texto_dica_original)

        # --- Firebird --------------------------------------------------------
        secao_fb = tk.LabelFrame(container, text="Firebird", padx=10, pady=8)
        secao_fb.pack(fill="x", pady=6)
        secao_fb.columnconfigure(0, weight=1)

        self.combo_firebird = ttk.Combobox(secao_fb, state="readonly")
        self.combo_firebird.grid(row=0, column=0, sticky="ew")
        botao_detectar_fb = tk.Button(secao_fb, text="Detectar automaticamente", command=self._detectar_firebird_async)
        botao_detectar_fb.grid(row=0, column=1, padx=(8, 0))
        dica(self.combo_firebird, "Instalação do Firebird (gbak/gfix/gstat) usada para restaurar. "
                                   "Qualquer versão instalada aparece aqui (2.5, 3.0, 4.0, 5.0...) — troque "
                                   "aqui se a restauração falhar por incompatibilidade de versão.")
        dica(botao_detectar_fb, "Procura novamente por instalações do Firebird no computador "
                                 "(Program Files, Registro do Windows e PATH).")

        label_page_size = tk.Label(secao_fb, text="Page Size:")
        label_page_size.grid(row=1, column=0, sticky="w", pady=(8, 0))
        # A rotina de manutenção usada até hoje sempre restaura com -p 16384,
        # independente do page size original do backup — por isso é a sugestão
        # padrão aqui. "(usar o do backup)" deixa o gbak decidir sozinho.
        self.combo_page_size = ttk.Combobox(
            secao_fb, state="readonly", width=20,
            values=["(usar o do backup)", "4096", "8192", "16384", "32768"],
        )
        self.combo_page_size.set(self.settings.page_size_padrao)
        self.combo_page_size.grid(row=1, column=1, sticky="w", pady=(8, 0), padx=(0, 0))
        texto_dica_page_size = (
            "Tamanho de página forçado no banco restaurado. A rotina usada até hoje sempre usa 16384 — "
            "isso pode ser diferente do page size original do backup, o que explica por que o tamanho do "
            "arquivo final muda. Escolha \"(usar o do backup)\" para manter o valor original."
        )
        dica(label_page_size, texto_dica_page_size)
        dica(self.combo_page_size, texto_dica_page_size)

        label_charset = tk.Label(secao_fb, text="Charset (avançado):")
        label_charset.grid(row=2, column=0, sticky="w", pady=(6, 0))
        # -fix_fss_data / -fix_fss_metadata do gbak: corrige dados/metadados
        # gravados com charset malformado — comum em bases antigas migradas
        # do InterBase, ou quando o backup tem charset NONE. Sem isso, colunas
        # com acentuação podem falhar com "cannot transliterate" no meio da
        # restauração.
        self.combo_charset = ttk.Combobox(
            secao_fb, state="readonly", width=20,
            values=["(padrão do backup)", "WIN1252", "ISO8859_1", "UTF8", "NONE"],
        )
        self.combo_charset.set(self.settings.charset_padrao)
        self.combo_charset.grid(row=2, column=1, sticky="w", pady=(6, 0))
        texto_dica_charset = (
            "Corrige dados/metadados com charset malformado (gbak -fix_fss_data / -fix_fss_metadata). "
            "Use se a restauração falhar com erro de \"cannot transliterate\" ou acentos aparecerem "
            "corrompidos — comum em bases antigas migradas do InterBase. Deixe em \"(padrão do backup)\" "
            "na maioria dos casos."
        )
        dica(label_charset, texto_dica_charset)
        dica(self.combo_charset, texto_dica_charset)

        # --- Autenticação ----------------------------------------------------
        secao_auth = tk.LabelFrame(container, text="Autenticação", padx=10, pady=8)
        secao_auth.pack(fill="x", pady=6)

        label_usuario = tk.Label(secao_auth, text="Usuário:")
        label_usuario.grid(row=0, column=0, sticky="w")
        self.var_usuario = tk.StringVar(value=self.settings.usuario_padrao or "SYSDBA")
        entry_usuario = tk.Entry(secao_auth, textvariable=self.var_usuario, width=18)
        entry_usuario.grid(row=0, column=1, padx=(4, 20))
        dica(entry_usuario, "Usuário de conexão ao Firebird. 'SYSDBA' é o administrador padrão "
                             "e normalmente é o que deve ser usado aqui.")

        label_senha = tk.Label(secao_auth, text="Senha:")
        label_senha.grid(row=0, column=2, sticky="w")
        # "masterkey" é a senha padrão de fábrica do usuário SYSDBA em
        # instalações do Firebird — não é um segredo do usuário, é um valor
        # inicial sugerido para agilizar; nunca é gravado em settings.json.
        self.var_senha = tk.StringVar(value="masterkey")
        entry_senha = tk.Entry(secao_auth, textvariable=self.var_senha, show="*", width=18)
        entry_senha.grid(row=0, column=3, padx=(4, 0))
        dica(entry_senha, "Senha do usuário acima. 'masterkey' é a senha padrão de fábrica do SYSDBA — "
                           "troque se o Firebird deste servidor usa outra senha.")

        self.var_validacao_completa = tk.BooleanVar(value=self.settings.validacao_completa_padrao)
        check_validacao = tk.Checkbutton(
            secao_auth,
            text="Validação completa após restaurar (gfix -v -full — mais lento em bancos grandes)",
            variable=self.var_validacao_completa,
        )
        check_validacao.grid(row=1, column=0, columnspan=4, sticky="w", pady=(6, 0))
        dica(check_validacao, "Roda uma varredura completa do banco recém-criado (gfix -v -full) para contar "
                               "erros, avisos e identificar tabelas com registros corrompidos ou perdidos. "
                               "Deixe desmarcado para bancos grandes se quiser um resultado mais rápido.")

        self.var_tocar_som = tk.BooleanVar(value=self.settings.tocar_som_padrao)
        check_som = tk.Checkbutton(
            secao_auth, text="🔊 Tocar som ao concluir (sucesso ou erro)", variable=self.var_tocar_som,
        )
        check_som.grid(row=2, column=0, columnspan=4, sticky="w", pady=(2, 0))
        dica(check_som, "Toca um som do Windows quando a restauração terminar — útil em bancos grandes, "
                         "que podem demorar minutos, para você não precisar ficar checando a tela.")

        self.var_notificacao_windows = tk.BooleanVar(value=self.settings.notificacao_windows_padrao)
        check_notificacao = tk.Checkbutton(
            secao_auth, text="🔔 Notificação do Windows ao concluir", variable=self.var_notificacao_windows,
        )
        check_notificacao.grid(row=3, column=0, columnspan=4, sticky="w", pady=(2, 0))
        dica(check_notificacao, "Mostra um aviso do Windows (canto da tela) quando a restauração terminar — "
                                 "aparece mesmo com a janela minimizada ou em segundo plano.")

        botao_salvar_perfil = tk.Button(
            secao_auth, text="💾 Salvar usuário/page size/charset/opções como padrão",
            command=self._salvar_perfil_padrao,
        )
        botao_salvar_perfil.grid(row=4, column=0, columnspan=4, sticky="w", pady=(8, 0))
        dica(botao_salvar_perfil, "Guarda os valores atuais destes campos (nunca a senha) para já virem "
                                    "preenchidos na próxima vez que você abrir o programa.")

        # --- Ações e status (fixos) ----------------------------------------------
        # Tudo daqui até o fim de _construir_widgets vai em frame_acoes_fixas,
        # não em container — fica sempre visível, sem depender de rolar (ver
        # comentário acima, na criação de frame_acoes_fixas).
        secao_status = tk.LabelFrame(frame_acoes_fixas, text="Status", padx=10, pady=8)
        secao_status.pack(fill="x", pady=(0, 6))

        self.var_status = tk.StringVar(value="Pronto para restaurar.")
        tk.Label(secao_status, textvariable=self.var_status, anchor="w").pack(fill="x")

        frame_progresso = tk.Frame(secao_status)
        frame_progresso.pack(fill="x", pady=(6, 0))
        # Percentual aproximado: acompanha o crescimento do arquivo de destino
        # em relação a uma estimativa de tamanho final — o gbak não expõe um
        # percentual real, então isto é sempre uma aproximação.
        self.progresso = ttk.Progressbar(frame_progresso, mode="determinate", maximum=100)
        self.progresso.pack(side="left", fill="x", expand=True)
        self.var_percentual = tk.StringVar(value="0%")
        label_percentual = tk.Label(frame_progresso, textvariable=self.var_percentual, width=6, anchor="e")
        label_percentual.pack(side="left", padx=(8, 0))
        dica(self.progresso, "Progresso aproximado: o gbak não informa um percentual exato, então isto é "
                              "estimado comparando o tamanho do arquivo sendo criado com uma previsão de "
                              "tamanho final.")
        dica(label_percentual, "Percentual aproximado — veja a explicação na barra de progresso ao lado.")

        # Tempo decorrido é medido de verdade; tempo restante é estimado a
        # partir do percentual aproximado acima — por isso sempre marcado
        # como "aprox." para não parecer uma previsão exata.
        frame_tempos = tk.Frame(secao_status)
        frame_tempos.pack(fill="x", pady=(4, 0))
        self.var_tempo_decorrido = tk.StringVar(value="Tempo decorrido: 00:00")
        self.var_tempo_restante = tk.StringVar(value="Tempo restante: --:--")
        label_decorrido = tk.Label(frame_tempos, textvariable=self.var_tempo_decorrido, fg="#555")
        label_decorrido.pack(side="left")
        label_restante = tk.Label(frame_tempos, textvariable=self.var_tempo_restante, fg="#555")
        label_restante.pack(side="left", padx=(20, 0))
        dica(label_decorrido, "Tempo real desde o início desta restauração.")
        dica(label_restante, "Estimativa calculada a partir do percentual aproximado acima e do tempo já "
                              "decorrido — pode variar bastante, principalmente no início da restauração.")

        # --- Botão principal -------------------------------------------------
        self.botao_restaurar = tk.Button(
            frame_acoes_fixas,
            text="RESTAURAR BANCO",
            font=("Segoe UI", 12, "bold"),
            bg="#15803d",
            fg="white",
            height=2,
            command=self._iniciar_restauracao,
        )
        self.botao_restaurar.pack(fill="x", pady=(0, 4))
        dica(self.botao_restaurar, "Inicia a restauração real do backup selecionado para o destino informado, "
                                    "usando o Firebird escolhido acima. Pede confirmação antes de começar.")

        self.botao_cancelar = tk.Button(
            frame_acoes_fixas,
            text="❌ CANCELAR RESTAURAÇÃO",
            font=("Segoe UI", 10, "bold"),
            bg="#b91c1c",
            fg="white",
            command=self._cancelar_restauracao,
            state="disabled",
        )
        self.botao_cancelar.pack(fill="x", pady=(0, 4))
        dica(self.botao_cancelar, "Interrompe a restauração em andamento. O arquivo de destino, se chegou a "
                                    "ser criado, fica incompleto e é removido automaticamente — nada é perdido "
                                    "do backup original.")

        botao_historico = tk.Button(
            frame_acoes_fixas, text="📋 Histórico desta sessão", command=self._ver_historico_sessao,
        )
        botao_historico.pack(fill="x", pady=(0, 4))
        dica(botao_historico, "Mostra um relatório com todas as restaurações feitas desde que este programa "
                               "foi aberto — útil quando o mesmo backup é restaurado mais de uma vez.")

        botao_fila = tk.Button(
            frame_acoes_fixas, text="📚 Restaurar em lote", command=self._abrir_fila_restauracao,
        )
        botao_fila.pack(fill="x", pady=(0, 4))
        dica(botao_fila, "Adicione vários backups de uma vez e restaure todos em sequência, usando as "
                          "mesmas configurações (Firebird, usuário, senha...) para todos.")

        botao_recuperacao = tk.Button(
            frame_acoes_fixas, text="🩹 Recuperar banco corrompido", fg="#b45309", command=self._abrir_recuperacao,
        )
        botao_recuperacao.pack(fill="x")
        dica(botao_recuperacao, "Para quando o BANCO ORIGINAL (não o backup) está corrompido: tenta gerar "
                                 "um backup a partir dele ignorando erros de página, sem nunca alterar o "
                                 "arquivo original. Recuperação parcial — dados realmente corrompidos são "
                                 "descartados, não recuperados.")

        # --- Detalhes / log — painel direito, sempre visível --------------------
        # Fica em uma coluna separada (não empilhado abaixo do botão) para
        # nunca ficar escondido em janelas menores ou não maximizadas.
        painel_direito = tk.LabelFrame(painel, text="Detalhes", padx=10, pady=8)
        painel.add(painel_direito, weight=1)

        cabecalho_log = tk.Frame(painel_direito)
        cabecalho_log.pack(fill="x")
        tk.Label(cabecalho_log, text="Acompanhamento em tempo real da restauração:", fg="#555").pack(side="left")
        botao_somente_erros = tk.Button(
            cabecalho_log, text="⚠ Ver somente os erros", fg="#b91c1c", command=self._ver_somente_erros
        )
        botao_somente_erros.pack(side="right")
        dica(botao_somente_erros, "Mostra, em uma janela separada, só as linhas de erro registradas até agora "
                                   "— útil para não precisar procurar em meio a centenas de linhas de progresso.")
        botao_salvar_log = tk.Button(
            cabecalho_log, text="💾 Salvar como .txt",
            command=lambda: salvar_texto_como_arquivo(
                self, self.area_log.get("1.0", "end"),
                f"acompanhamento_restauracao_{datetime.now():%Y%m%d_%H%M%S}.txt",
            ),
        )
        botao_salvar_log.pack(side="right", padx=(0, 8))
        dica(botao_salvar_log, "Salva todo o acompanhamento exibido abaixo em um arquivo .txt — "
                                "útil para levar para outra máquina ou anexar em um chamado de suporte.")
        self.botao_tema_hacker = tk.Button(cabecalho_log, command=self._alternar_tema_hacker)
        self.botao_tema_hacker.pack(side="right", padx=(0, 8))
        dica(self.botao_tema_hacker, "Alterna o visual da área abaixo entre o padrão claro e um terminal "
                                      "preto/verde — puramente estético, não muda nada na restauração.")

        self.area_log = scrolledtext.ScrolledText(painel_direito, font=("Consolas", 9), state="disabled")

        barra_busca_log = adicionar_barra_busca(painel_direito, self.area_log)
        barra_busca_log.pack(fill="x", pady=(6, 4))
        self.area_log.pack(fill="both", expand=True)
        self._aplicar_tema_log()

        # Linhas de nível ERRO acumuladas desde que o programa foi aberto —
        # alimenta a janela "Ver somente os erros", sem precisar reprocessar
        # a área de log completa (nem depender de raspar suas tags de cor).
        self.linhas_erro_log: list[str] = []
        self.janela_somente_erros: JanelaSomenteErros | None = None

    # ------------------------------------------------------------- Backup
    def _selecionar_backup(self) -> None:
        caminho = filedialog.askopenfilename(
            title="Selecionar backup Firebird",
            initialdir=self.settings.pasta_padrao_backups or None,
            filetypes=[("Backup Firebird", "*.fbk *.fbk.gz"), ("Todos os arquivos", "*.*")],
        )
        if caminho:
            self._usar_backup_selecionado(caminho)

    def _usar_backup_selecionado(self, caminho: str) -> bool:
        """Valida e preenche os campos de backup a partir de um caminho já
        escolhido — reaproveitado tanto pelo diálogo de seleção quanto pelo
        botão "Usar este backup para restaurar agora" da janela de
        recuperação. Retorna True se o backup era válido e foi aceito."""
        resultado = validator.validar_arquivo_backup(caminho)
        if not resultado.ok:
            messagebox.showerror("Backup inválido", resultado.mensagem_usuario)
            return False

        self.var_backup.set(caminho)
        meta = resultado.metadados
        nome_original = validator.extrair_nome_banco_original(caminho, meta.get("comprimido", False))
        info = (
            f"Backup: {meta['nome']}\n"
            f"Tamanho: {meta['tamanho_formatado']}"
            f"{'  (comprimido)' if meta['comprimido'] else ''}\n"
            f"Modificado em: {meta['modificado']}\n"
            f"Banco original detectado: {nome_original or '(não identificado no cabeçalho do backup)'}"
        )
        self.var_info_backup.set(info)
        self.metadados_backup = meta

        self.settings.pasta_padrao_backups = str(Path(caminho).parent)
        config.salvar_configuracoes(self.settings)

        if not self.var_destino.get():
            self.var_destino.set(self._sugerir_destino_a_partir_do_backup(caminho, meta))
        return True

    @staticmethod
    def _sugerir_destino_a_partir_do_backup(caminho_backup: str, meta: dict) -> str:
        # 1ª tentativa: o próprio gbak grava o caminho do banco original dentro
        # do cabeçalho do backup — mais confiável que o nome do arquivo, já que
        # a rotina de backup atual usa nomes genéricos como
        # "backup_20260904_105746.fbk.gz" (não carrega o nome do cliente/banco).
        nome_original = validator.extrair_nome_banco_original(caminho_backup, meta.get("comprimido", False))

        if nome_original:
            nome_base = Path(nome_original).stem
        else:
            # 2ª tentativa (fallback): usar o nome do próprio arquivo de backup.
            nome = Path(caminho_backup).name
            for sufixo in (".fbk.gz", ".fbk"):
                if nome.lower().endswith(sufixo):
                    nome = nome[: -len(sufixo)]
                    break
            # Remove um padrão comum de timestamp tipo _20260904_105746 do final do nome
            nome_base = re.sub(r"_\d{8}_\d{6}$", "", nome) or nome

        pasta = Path(caminho_backup).parent
        return str(pasta / f"{nome_base}.FDB")

    # ------------------------------------------------------------- Destino
    def _selecionar_destino(self) -> None:
        sugestao = Path(self.var_destino.get()) if self.var_destino.get() else None
        caminho = filedialog.asksaveasfilename(
            title="Onde criar o banco restaurado",
            initialdir=self.settings.pasta_padrao_destino or (str(sugestao.parent) if sugestao else None),
            initialfile=sugestao.name if sugestao else "RESTAURADO.FDB",
            defaultextension=".fdb",
            filetypes=[("Banco Firebird", "*.fdb")],
        )
        if not caminho:
            return
        self.var_destino.set(caminho)
        self.settings.pasta_padrao_destino = str(Path(caminho).parent)
        config.salvar_configuracoes(self.settings)

    def _selecionar_banco_original(self) -> None:
        caminho = filedialog.askopenfilename(
            title="Selecionar o banco original (antes do backup) — apenas para comparação de tamanho",
            filetypes=[("Banco Firebird", "*.fdb"), ("Todos os arquivos", "*.*")],
        )
        if caminho:
            self.var_banco_original_comparacao.set(caminho)

    # ----------------------------------------------------------- Firebird
    def _detectar_firebird_async(self) -> None:
        self.var_status.set("Procurando instalações do Firebird...")
        threading.Thread(target=self._worker_detectar_firebird, daemon=True).start()

    def _worker_detectar_firebird(self) -> None:
        instalacoes = firebird.localizar_instalacoes()
        self.after(0, self._atualizar_lista_firebird, instalacoes)

    def _atualizar_lista_firebird(self, instalacoes: list[firebird.InstalacaoFirebird]) -> None:
        self.instalacoes = instalacoes
        self.combo_firebird["values"] = [i.rotulo for i in instalacoes]
        if instalacoes:
            self.combo_firebird.current(0)
            self.var_status.set("Pronto para restaurar.")
        else:
            self.var_status.set("Nenhuma instalação do Firebird foi encontrada. Verifique se está instalado.")

    def _instalacao_selecionada(self) -> firebird.InstalacaoFirebird | None:
        indice = self.combo_firebird.current()
        if indice is None or indice < 0 or indice >= len(self.instalacoes):
            return None
        return self.instalacoes[indice]

    # --------------------------------------------------------------- Log
    def _on_log_thread_safe(self, nivel: str, mensagem: str) -> None:
        self.after(0, self._append_log, nivel, mensagem)

    def _append_log(self, nivel: str, mensagem: str) -> None:
        hora = datetime.now().strftime("%H:%M:%S")
        self.area_log.configure(state="normal")
        self.area_log.insert("end", f"{hora} - {mensagem}\n", nivel if nivel in CORES else "INFO")
        self.area_log.see("end")
        self.area_log.configure(state="disabled")
        if nivel == "ERRO":
            linha_formatada = f"{hora} - {mensagem}"
            self.linhas_erro_log.append(linha_formatada)
            if self.janela_somente_erros is not None and self.janela_somente_erros.winfo_exists():
                self.janela_somente_erros.adicionar_linha(linha_formatada, len(self.linhas_erro_log))

    def _ver_somente_erros(self) -> None:
        if self.janela_somente_erros is not None and self.janela_somente_erros.winfo_exists():
            self.janela_somente_erros.lift()
            self.janela_somente_erros.focus_set()
            return
        self.janela_somente_erros = JanelaSomenteErros(self, list(self.linhas_erro_log), self.tema_hacker)

    def _aplicar_tema_log(self) -> None:
        """Aplica o visual normal ou 'hacker' (terminal preto/verde) à área
        de acompanhamento — só cores/fundo, nenhum comportamento muda."""
        paleta = paleta_log(self.tema_hacker)
        fundo = fundo_log(self.tema_hacker)
        self.area_log.configure(
            bg=fundo, fg=paleta["INFO"], insertbackground=paleta["INFO"],
            selectbackground=paleta["INFO"], selectforeground=fundo,
        )
        for nivel, cor in paleta.items():
            self.area_log.tag_configure(nivel, foreground=cor)
        self.botao_tema_hacker.configure(
            text="☀ Modo normal" if self.tema_hacker else "👾 Modo hacker",
        )

    def _alternar_tema_hacker(self) -> None:
        self.tema_hacker = not self.tema_hacker
        self._aplicar_tema_log()
        self.settings.tema_hacker = self.tema_hacker
        config.salvar_configuracoes(self.settings)

    # --------------------------------------------------------- Restauração
    def _iniciar_restauracao(self) -> None:
        if self.restaurando:
            return

        backup = self.var_backup.get().strip()
        destino = self.var_destino.get().strip()
        instalacao = self._instalacao_selecionada()

        if not backup:
            messagebox.showwarning("Campo obrigatório", "Selecione o arquivo de backup (.fbk / .fbk.gz).")
            return
        if not destino:
            messagebox.showwarning("Campo obrigatório", "Informe onde o banco restaurado deve ser criado.")
            return
        if instalacao is None:
            messagebox.showwarning(
                "Firebird não selecionado",
                "Nenhuma instalação do Firebird foi detectada/selecionada. Clique em 'Detectar automaticamente'.",
            )
            return

        # Regra crítica: nunca sobrescrever destino existente sem decisão explícita.
        validacao_destino = validator.validar_destino(destino)
        if validacao_destino.ok and validacao_destino.metadados.get("ja_existe"):
            sugestao = validator.sugerir_nome_restauracao_paralela(destino)
            dialogo = DialogoConflitoDestino(self, destino, sugestao)
            self.wait_window(dialogo)
            if dialogo.resultado is None:
                self.var_status.set("Restauração cancelada pelo usuário.")
                return
            destino = dialogo.resultado
            self.var_destino.set(destino)
            # Reconfere: o novo caminho escolhido também não pode já existir.
            revalidacao = validator.validar_destino(destino)
            if revalidacao.ok and revalidacao.metadados.get("ja_existe"):
                messagebox.showerror(
                    "Ainda em conflito",
                    "O novo caminho escolhido também já existe. Escolha outro nome.",
                )
                return

        usuario = self.var_usuario.get().strip()
        senha = self.var_senha.get()

        confirmar = messagebox.askyesno(
            "Confirmar restauração",
            f"Restaurar:\n{backup}\n\npara o novo banco:\n{destino}\n\n"
            f"usando {instalacao.rotulo}?\n\nEsta operação pode demorar alguns minutos.",
        )
        if not confirmar:
            return

        self.restaurando = True
        self.botao_restaurar.configure(state="disabled", text="RESTAURANDO...")
        self.botao_cancelar.configure(state="normal")
        self.progresso["value"] = 0
        self.var_percentual.set("0%")
        logger.info("=== Nova restauração iniciada pela interface ===")

        validar_completo = self.var_validacao_completa.get()
        banco_original_comparacao = self.var_banco_original_comparacao.get().strip()
        page_size_texto = self.combo_page_size.get()
        page_size = int(page_size_texto) if page_size_texto.isdigit() else None
        charset_texto = self.combo_charset.get()
        charset = None if charset_texto.startswith("(") else charset_texto
        self.sinal_cancelamento = restore.SinalCancelamento()
        self._iniciar_cronometro()

        threading.Thread(
            target=self._worker_restaurar,
            args=(backup, destino, instalacao, usuario, senha, validar_completo, banco_original_comparacao,
                  page_size, charset, self.sinal_cancelamento),
            daemon=True,
        ).start()

    def _salvar_perfil_padrao(self) -> None:
        self.settings.usuario_padrao = self.var_usuario.get().strip() or "SYSDBA"
        self.settings.page_size_padrao = self.combo_page_size.get()
        self.settings.charset_padrao = self.combo_charset.get()
        self.settings.validacao_completa_padrao = self.var_validacao_completa.get()
        self.settings.tocar_som_padrao = self.var_tocar_som.get()
        self.settings.notificacao_windows_padrao = self.var_notificacao_windows.get()
        config.salvar_configuracoes(self.settings)
        messagebox.showinfo(
            "Perfil salvo",
            "Usuário, Page Size, Charset e as opções marcadas foram salvos como padrão "
            "(a senha nunca é salva).",
        )

    def _cancelar_restauracao(self) -> None:
        if not self.restaurando or self.sinal_cancelamento is None:
            return
        if not messagebox.askyesno(
            "Cancelar restauração",
            "Tem certeza que deseja interromper a restauração em andamento?\n\n"
            "O arquivo de destino, se já tiver sido criado, ficará incompleto e será removido.",
        ):
            return
        self.sinal_cancelamento.set()
        self.botao_cancelar.configure(state="disabled")
        self.var_status.set("Cancelando... aguarde o processo ser interrompido.")

    def _worker_restaurar(
        self, backup, destino, instalacao, usuario, senha, validar_completo, banco_original_comparacao,
        page_size, charset, sinal_cancelamento,
    ) -> None:
        resultado = restore.executar_restauracao(
            caminho_backup=backup,
            caminho_destino=destino,
            instalacao=instalacao,
            usuario=usuario,
            senha=senha,
            fator_estimativa=self.settings.fator_estimativa_fdb_por_fbk,
            margem_seguranca=self.settings.margem_seguranca_espaco,
            on_status=lambda msg: self.after(0, self._atualizar_status, msg),
            on_progress=lambda pct: self.after(0, self._atualizar_progresso_percentual, pct),
            validar_integridade_completa=validar_completo,
            caminho_banco_original_comparacao=banco_original_comparacao,
            page_size=page_size,
            charset=charset,
            sinal_cancelamento=sinal_cancelamento,
        )
        self.after(0, self._finalizar_restauracao, resultado)

    def _atualizar_status(self, texto: str) -> None:
        self.var_status.set(texto)

    def _atualizar_progresso_percentual(self, pct: float) -> None:
        self.progresso["value"] = pct
        self.var_percentual.set(f"{pct:.0f}%")
        self.percentual_atual = pct

    # ------------------------------------------------------------ Cronômetro
    @staticmethod
    def _formatar_mmss(segundos: float) -> str:
        segundos = max(0, int(segundos))
        horas, resto = divmod(segundos, 3600)
        minutos, segs = divmod(resto, 60)
        if horas:
            return f"{horas}:{minutos:02d}:{segs:02d}"
        return f"{minutos:02d}:{segs:02d}"

    def _cancelar_tick_cronometro_pendente(self) -> None:
        if self._id_tick_cronometro is not None:
            try:
                self.after_cancel(self._id_tick_cronometro)
            except (ValueError, tk.TclError):
                pass
            self._id_tick_cronometro = None

    def _iniciar_cronometro(self) -> None:
        # Garante que nunca haja dois loops de tick rodando ao mesmo tempo
        # (ver comentário no __init__ sobre _id_tick_cronometro).
        self._cancelar_tick_cronometro_pendente()
        self.hora_inicio_restauracao = datetime.now()
        self.percentual_atual = 0.0
        self._cron_hora_anterior = self.hora_inicio_restauracao
        self._cron_pct_anterior = 0.0
        self._cron_velocidade_suavizada: float | None = None
        self._cron_restante_exibido: float | None = None
        self.var_tempo_decorrido.set("Tempo decorrido: 00:00")
        self.var_tempo_restante.set("Tempo restante: calculando...")
        self._tick_cronometro()

    def _tick_cronometro(self) -> None:
        if not self.restaurando or self.hora_inicio_restauracao is None:
            self._id_tick_cronometro = None
            return
        agora = datetime.now()
        decorrido = (agora - self.hora_inicio_restauracao).total_seconds()
        self.var_tempo_decorrido.set(f"Tempo decorrido: {self._formatar_mmss(decorrido)}")

        # Velocidade de progresso suavizada (% por segundo), com média móvel
        # exponencial — o crescimento do arquivo não é linear no tempo (fases
        # de metadados avançam sem quase alterar o tamanho do arquivo, depois
        # os dados são gravados rapidamente), então usar só "decorrido/pct%"
        # desde o início fazia a estimativa de tempo SUBIR sempre que o
        # progresso ficava parado por um instante.
        dt = (agora - self._cron_hora_anterior).total_seconds()
        dpct = self.percentual_atual - self._cron_pct_anterior
        self._cron_hora_anterior = agora
        self._cron_pct_anterior = self.percentual_atual
        if dt > 0:
            velocidade_instantanea = dpct / dt
            if self._cron_velocidade_suavizada is None:
                self._cron_velocidade_suavizada = velocidade_instantanea
            else:
                alfa = 0.3
                self._cron_velocidade_suavizada = (
                    alfa * velocidade_instantanea + (1 - alfa) * self._cron_velocidade_suavizada
                )

        # O valor exibido nunca aumenta de um tick para o outro: decai junto
        # com o relógio real e só é revisado para baixo quando a nova conta
        # apontar um tempo menor — evita a sensação de "andar para trás".
        if self._cron_restante_exibido is not None:
            self._cron_restante_exibido = max(0.0, self._cron_restante_exibido - dt)

        velocidade = self._cron_velocidade_suavizada
        if self.percentual_atual >= 2.0 and velocidade and velocidade > 0.01:
            restante_calculado = (100 - self.percentual_atual) / velocidade
            if self._cron_restante_exibido is None:
                self._cron_restante_exibido = restante_calculado
            else:
                self._cron_restante_exibido = min(self._cron_restante_exibido, restante_calculado)
            self.var_tempo_restante.set(
                f"Tempo restante: {self._formatar_mmss(self._cron_restante_exibido)} (aprox.)"
            )
        else:
            self.var_tempo_restante.set("Tempo restante: calculando...")

        self._id_tick_cronometro = self.after(1000, self._tick_cronometro)

    def _tocar_som(self, sucesso: bool) -> None:
        if not self.var_tocar_som.get() or winsound is None:
            return
        try:
            winsound.MessageBeep(winsound.MB_ICONASTERISK if sucesso else winsound.MB_ICONHAND)
        except RuntimeError:
            pass  # nunca deixa uma falha de som interromper o fluxo da restauração

    def _notificar_conclusao_windows(self, resultado: restore.ResultadoRestauracao) -> None:
        if not self.var_notificacao_windows.get():
            return
        titulo = "✔ Restauração concluída" if resultado.sucesso else "✖ Restauração falhou"
        notificacoes.notificar_windows(titulo, resultado.mensagem_usuario)

    def _finalizar_restauracao(self, resultado: restore.ResultadoRestauracao) -> None:
        self.restaurando = False
        self._cancelar_tick_cronometro_pendente()
        self.ultimo_resultado = resultado
        self.sinal_cancelamento = None
        self.botao_restaurar.configure(state="normal", text="RESTAURAR BANCO")
        self.botao_cancelar.configure(state="disabled")
        self.var_status.set(resultado.mensagem_usuario)

        if self.hora_inicio_restauracao is not None:
            decorrido_total = (datetime.now() - self.hora_inicio_restauracao).total_seconds()
            self.var_tempo_decorrido.set(f"Tempo decorrido: {self._formatar_mmss(decorrido_total)}")
        self.var_tempo_restante.set(
            "Tempo restante: concluído." if resultado.sucesso else "Tempo restante: interrompido."
        )

        # Registra esta tentativa no histórico da sessão, sucesso ou falha —
        # é o que alimenta o relatório consolidado (JanelaHistoricoSessao).
        if resultado.resumo:
            self.historico_sessao.append(ItemHistorico(
                timestamp=datetime.now(),
                nome_arquivo_backup=resultado.resumo.nome_arquivo_backup,
                nome_banco_destino=resultado.resumo.nome_banco_novo,
                sucesso=True,
                duracao_formatada=resultado.resumo.duracao_formatada,
                firebird_versao_detalhada=resultado.resumo.versao_firebird_detalhada,
                page_size=resultado.resumo.page_size,
                tabelas_com_problema=resultado.resumo.tabelas_com_problema,
                validacao_avisos=resultado.resumo.validacao_avisos,
                gbak_erros_count=resultado.resumo.gbak_erros_count,
            ))
        else:
            self.historico_sessao.append(ItemHistorico(
                timestamp=datetime.now(),
                nome_arquivo_backup=Path(self.var_backup.get()).name if self.var_backup.get() else "(desconhecida)",
                nome_banco_destino=Path(resultado.caminho_destino).name if resultado.caminho_destino else
                Path(self.var_destino.get()).name if self.var_destino.get() else "(desconhecido)",
                sucesso=False,
                duracao_formatada=self.var_tempo_decorrido.get().replace("Tempo decorrido: ", ""),
                mensagem_erro=resultado.mensagem_usuario,
            ))

        self._tocar_som(resultado.sucesso)
        self._notificar_conclusao_windows(resultado)

        if resultado.sucesso:
            self.progresso["value"] = 100
            self.var_percentual.set("100%")
            if resultado.resumo:
                JanelaResumo(self, resultado.resumo)
            else:
                messagebox.showinfo(
                    "Restauração concluída",
                    f"BANCO RESTAURADO COM SUCESSO.\n\nArquivo criado em:\n{resultado.caminho_destino}",
                )
        else:
            self.progresso["value"] = 0
            self.var_percentual.set("0%")
            if "cancelada pelo usuário" in resultado.mensagem_usuario.lower():
                messagebox.showinfo("Restauração cancelada", resultado.mensagem_usuario)
            else:
                resposta = messagebox.askyesno(
                    "Erro na restauração",
                    f"{resultado.mensagem_usuario}\n\nDeseja ver os detalhes técnicos (para suporte)?",
                    icon="error",
                )
                if resposta:
                    JanelaDetalhesTecnicos(self, resultado.detalhes_tecnicos)

    def _ver_historico_sessao(self) -> None:
        JanelaHistoricoSessao(self, self.historico_sessao)

    def _abrir_recuperacao(self) -> None:
        def usar_backup(caminho: str) -> None:
            if self._usar_backup_selecionado(caminho):
                messagebox.showinfo(
                    "Backup pronto para restaurar",
                    f"O backup de recuperação foi carregado no campo de backup:\n{caminho}\n\n"
                    "Revise o destino e clique em RESTAURAR BANCO quando quiser prosseguir.",
                )

        JanelaRecuperacao(
            self, self.instalacoes, self.var_usuario.get().strip(), self.var_senha.get(), usar_backup,
        )

    def _abrir_fila_restauracao(self) -> None:
        page_size_texto = self.combo_page_size.get()
        page_size = int(page_size_texto) if page_size_texto.isdigit() else None
        charset_texto = self.combo_charset.get()
        charset = None if charset_texto.startswith("(") else charset_texto
        config_padrao = {
            "usuario": self.var_usuario.get().strip(),
            "senha": self.var_senha.get(),
            "validar_completo": self.var_validacao_completa.get(),
            "page_size": page_size,
            "charset": charset,
            "fator_estimativa": self.settings.fator_estimativa_fdb_por_fbk,
            "margem_seguranca": self.settings.margem_seguranca_espaco,
            "instalacao_index": self.combo_firebird.current(),
        }
        JanelaFilaRestauracao(self, self.instalacoes, config_padrao)

    # ------------------------------------------------------------- Saída
    def _ao_fechar(self) -> None:
        config.salvar_configuracoes(self.settings)
        self.destroy()


def rodar() -> None:
    app = JanelaPrincipal()
    app.mainloop()
