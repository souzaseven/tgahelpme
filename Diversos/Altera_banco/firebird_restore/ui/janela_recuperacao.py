"""
ui/janela_recuperacao.py
-------------------------
Janela "Recuperar banco corrompido": interface para recuperacao.py — gera um
backup a partir de um .fdb com problemas (gfix -mend + gbak -ignore), nunca
tocando no arquivo original, e oferece restaurar esse backup em seguida
usando o fluxo normal já existente na janela principal.

Segue o mesmo padrão de threading de ui/main_window.py: a operação roda em
uma thread separada; toda atualização de widget a partir dela passa por
self.after(...).
"""
from __future__ import annotations

import threading
import tkinter as tk
from datetime import datetime
from pathlib import Path
from tkinter import filedialog, messagebox, scrolledtext, ttk

import firebird
import logger
import recuperacao
from restore import SinalCancelamento
from ui.estilos import CORES
from ui.widgets import adicionar_barra_busca, dica, salvar_texto_como_arquivo


class JanelaRecuperacao(tk.Toplevel):
    """`usar_backup_callback(caminho_fbk)` é chamado quando o usuário escolhe
    "Usar este backup para restaurar agora" — quem abriu esta janela decide o
    que fazer com o caminho (normalmente: preencher o campo de backup da tela
    principal e fechar esta janela)."""

    def __init__(
        self,
        parent: tk.Tk,
        instalacoes: list[firebird.InstalacaoFirebird],
        usuario_padrao: str,
        senha_padrao: str,
        usar_backup_callback,
    ):
        super().__init__(parent)
        self.title("Recuperar banco corrompido")
        self.geometry("640x560")
        self.minsize(560, 460)
        self.transient(parent)

        self.instalacoes = instalacoes
        self.usar_backup_callback = usar_backup_callback
        self.recuperando = False
        self.caminho_backup_gerado: str | None = None
        self.sinal_cancelamento: SinalCancelamento | None = None
        self._fechar_apos_cancelar = False

        pad = {"padx": 14, "pady": 4}

        tk.Label(
            self, text="🩹 Recuperar banco corrompido", font=("Segoe UI", 13, "bold"),
        ).pack(pady=(14, 2))
        tk.Label(
            self,
            text="Gera um backup a partir de um .fdb com problemas, descartando o que estiver "
                 "realmente corrompido. O arquivo original NUNCA é alterado — toda a operação "
                 "roda sobre uma cópia temporária. Recuperação parcial: dados de páginas "
                 "danificadas são perdidos, não recuperados.",
            fg="#b45309", bg="#fffbeb", wraplength=580, justify="left", padx=10, pady=8,
        ).pack(fill="x", padx=14, pady=(0, 10))

        campos = tk.Frame(self)
        campos.pack(fill="x", **pad)
        campos.columnconfigure(1, weight=1)

        tk.Label(campos, text="Banco corrompido (.fdb):").grid(row=0, column=0, sticky="w", pady=4)
        self.var_origem = tk.StringVar()
        entry_origem = tk.Entry(campos, textvariable=self.var_origem)
        entry_origem.grid(row=0, column=1, sticky="ew", padx=(6, 6))
        tk.Button(campos, text="Selecionar...", command=self._selecionar_origem).grid(row=0, column=2)

        tk.Label(campos, text="Salvar backup gerado em:").grid(row=1, column=0, sticky="w", pady=4)
        self.var_destino = tk.StringVar()
        entry_destino = tk.Entry(campos, textvariable=self.var_destino)
        entry_destino.grid(row=1, column=1, sticky="ew", padx=(6, 6))
        tk.Button(campos, text="Escolher...", command=self._selecionar_destino).grid(row=1, column=2)

        tk.Label(campos, text="Firebird a usar:").grid(row=2, column=0, sticky="w", pady=4)
        self.combo_firebird = ttk.Combobox(campos, values=[i.rotulo for i in instalacoes], state="readonly")
        if instalacoes:
            self.combo_firebird.current(0)
        self.combo_firebird.grid(row=2, column=1, columnspan=2, sticky="ew", padx=(6, 0))

        tk.Label(campos, text="Usuário:").grid(row=3, column=0, sticky="w", pady=4)
        self.var_usuario = tk.StringVar(value=usuario_padrao)
        tk.Entry(campos, textvariable=self.var_usuario).grid(row=3, column=1, sticky="ew", padx=(6, 6))

        tk.Label(campos, text="Senha:").grid(row=4, column=0, sticky="w", pady=4)
        self.var_senha = tk.StringVar(value=senha_padrao)
        tk.Entry(campos, textvariable=self.var_senha, show="•").grid(row=4, column=1, sticky="ew", padx=(6, 6))

        self.var_sweep = tk.BooleanVar(value=True)
        check_sweep = tk.Checkbutton(
            self, text="Rodar limpeza de transações antigas (gfix -sweep)", variable=self.var_sweep,
        )
        check_sweep.pack(anchor="w", padx=14, pady=(4, 0))
        dica(check_sweep, "Recomendado manter marcado — ajuda a limpar o banco antes de gerar o backup. "
                           "Só desmarque se essa etapa der problema isoladamente.")

        self.var_status = tk.StringVar(value="Pronto.")
        tk.Label(self, textvariable=self.var_status, fg="#555").pack(anchor="w", padx=14, pady=(10, 2))

        frame_botoes_acao = tk.Frame(self)
        frame_botoes_acao.pack(fill="x", padx=14, pady=(0, 8))
        self.botao_iniciar = tk.Button(
            frame_botoes_acao, text="🩹 INICIAR RECUPERAÇÃO", font=("Segoe UI", 10, "bold"),
            bg="#b45309", fg="white", command=self._iniciar,
        )
        self.botao_iniciar.pack(side="left", fill="x", expand=True)
        self.botao_cancelar = tk.Button(
            frame_botoes_acao, text="❌ Cancelar", font=("Segoe UI", 10, "bold"),
            bg="#b91c1c", fg="white", command=self._cancelar, state="disabled",
        )
        self.botao_cancelar.pack(side="left", padx=(6, 0))
        dica(self.botao_cancelar, "Interrompe a etapa em andamento imediatamente (mata o processo gfix/gbak "
                                   "atual, mesmo no meio da execução) — não espera a etapa terminar sozinha.")

        cabecalho_log = tk.Frame(self)
        cabecalho_log.pack(fill="x", padx=14)
        tk.Label(cabecalho_log, text="Detalhes técnicos:", fg="#555").pack(side="left")
        tk.Button(
            cabecalho_log, text="💾 Salvar como .txt",
            command=lambda: salvar_texto_como_arquivo(
                self, self.area_log.get("1.0", "end"),
                f"recuperacao_{datetime.now():%Y%m%d_%H%M%S}.txt",
            ),
        ).pack(side="right")

        self.area_log = scrolledtext.ScrolledText(self, font=("Consolas", 9), height=10, state="disabled")
        for nivel, cor in CORES.items():
            self.area_log.tag_configure(nivel, foreground=cor)
        barra_busca = adicionar_barra_busca(self, self.area_log)
        barra_busca.pack(fill="x", padx=14, pady=(4, 4))
        self.area_log.pack(fill="both", expand=True, padx=14, pady=(0, 8))

        self.frame_pos_conclusao = tk.Frame(self)
        # (populado dinamicamente ao concluir — ver _mostrar_botoes_pos_conclusao)

        # Registrado uma única vez aqui (não em _iniciar) — permitir tentar
        # de novo na mesma janela sem duplicar linhas no log.
        logger.registrar_listener(self._log_thread_safe)
        self.protocol("WM_DELETE_WINDOW", self._fechar)

    # ------------------------------------------------------------- Seleção
    def _selecionar_origem(self) -> None:
        caminho = filedialog.askopenfilename(
            parent=self, title="Selecionar o banco corrompido",
            filetypes=[("Banco Firebird", "*.fdb"), ("Todos os arquivos", "*.*")],
        )
        if not caminho:
            return
        self.var_origem.set(caminho)
        if not self.var_destino.get():
            self.var_destino.set(recuperacao.sugerir_nome_backup_recuperacao(caminho))

    def _selecionar_destino(self) -> None:
        sugestao = Path(self.var_destino.get()) if self.var_destino.get() else None
        caminho = filedialog.asksaveasfilename(
            parent=self, title="Onde salvar o backup de recuperação",
            initialdir=str(sugestao.parent) if sugestao else None,
            initialfile=sugestao.name if sugestao else "RECUPERADO.fbk",
            defaultextension=".fbk", filetypes=[("Backup Firebird", "*.fbk")],
        )
        if caminho:
            self.var_destino.set(caminho)

    def _instalacao_selecionada(self) -> firebird.InstalacaoFirebird | None:
        indice = self.combo_firebird.current()
        if indice is None or indice < 0 or indice >= len(self.instalacoes):
            return None
        return self.instalacoes[indice]

    # --------------------------------------------------------------- Log
    def _log_thread_safe(self, nivel: str, mensagem: str) -> None:
        self.after(0, self._append_log, nivel, mensagem)

    def _append_log(self, nivel: str, mensagem: str) -> None:
        hora = datetime.now().strftime("%H:%M:%S")
        self.area_log.configure(state="normal")
        self.area_log.insert("end", f"{hora} - {mensagem}\n", nivel if nivel in CORES else "INFO")
        self.area_log.see("end")
        self.area_log.configure(state="disabled")

    # --------------------------------------------------------- Recuperação
    def _iniciar(self) -> None:
        if self.recuperando:
            return

        origem = self.var_origem.get().strip()
        destino = self.var_destino.get().strip()
        instalacao = self._instalacao_selecionada()

        if not origem:
            messagebox.showwarning("Campo obrigatório", "Selecione o banco corrompido (.fdb).", parent=self)
            return
        if not destino:
            messagebox.showwarning(
                "Campo obrigatório", "Informe onde salvar o backup de recuperação.", parent=self
            )
            return
        if instalacao is None:
            messagebox.showwarning(
                "Firebird não selecionado", "Selecione uma instalação do Firebird.", parent=self
            )
            return

        if not messagebox.askyesno(
            "Confirmar recuperação",
            f"Tentar recuperar:\n{origem}\n\ngerando o backup:\n{destino}\n\n"
            "O arquivo original NUNCA é alterado. Esta operação pode demorar bastante "
            "em bancos grandes. Continuar?",
            parent=self,
        ):
            return

        self.recuperando = True
        self.sinal_cancelamento = SinalCancelamento()
        self.botao_iniciar.configure(state="disabled", text="RECUPERANDO...")
        self.botao_cancelar.configure(state="normal")
        for widget in self.frame_pos_conclusao.winfo_children():
            widget.destroy()
        self.frame_pos_conclusao.pack_forget()

        threading.Thread(
            target=self._worker,
            args=(origem, destino, instalacao, self.var_usuario.get().strip(), self.var_senha.get(),
                  self.var_sweep.get(), self.sinal_cancelamento),
            daemon=True,
        ).start()

    def _cancelar(self) -> None:
        if not self.recuperando or self.sinal_cancelamento is None:
            return
        if not messagebox.askyesno(
            "Cancelar recuperação",
            "Tem certeza que deseja interromper a recuperação em andamento?",
            parent=self,
        ):
            return
        self.sinal_cancelamento.set("Recuperação cancelada pelo usuário.")
        self.botao_cancelar.configure(state="disabled")
        self.var_status.set("Cancelando... aguarde o processo atual ser interrompido.")

    def _worker(self, origem, destino, instalacao, usuario, senha, rodar_sweep, sinal_cancelamento) -> None:
        resultado = recuperacao.recuperar_banco_corrompido(
            caminho_fdb_corrompido=origem,
            caminho_backup_saida=destino,
            instalacao=instalacao,
            usuario=usuario,
            senha=senha,
            on_status=lambda msg: self.after(0, self.var_status.set, msg),
            rodar_sweep=rodar_sweep,
            sinal_cancelamento=sinal_cancelamento,
        )
        self.after(0, self._finalizar, resultado)

    def _finalizar(self, resultado: recuperacao.ResultadoRecuperacao) -> None:
        self.recuperando = False
        self.sinal_cancelamento = None
        self.botao_iniciar.configure(state="normal", text="🩹 INICIAR RECUPERAÇÃO")
        self.botao_cancelar.configure(state="disabled")
        self.var_status.set(resultado.mensagem_usuario)

        if self._fechar_apos_cancelar:
            logger.remover_listener(self._log_thread_safe)
            self.destroy()
            return

        if resultado.sucesso:
            self.caminho_backup_gerado = resultado.caminho_backup_gerado
            texto = (
                f"✔ Backup de recuperação gerado: {resultado.total_registros_recuperados} registro(s) "
                f"em {resultado.quantidade_tabelas_com_dados} tabela(s).\n{resultado.caminho_backup_gerado}"
            )
            self._mostrar_botoes_pos_conclusao(sucesso=True, texto=texto)
        elif resultado.cancelado:
            self._mostrar_botoes_pos_conclusao(sucesso=False, texto=f"⏹ {resultado.mensagem_usuario}")
        else:
            self._mostrar_botoes_pos_conclusao(sucesso=False, texto=f"✖ {resultado.mensagem_usuario}")

    def _mostrar_botoes_pos_conclusao(self, sucesso: bool, texto: str) -> None:
        self.frame_pos_conclusao.pack(fill="x", padx=14, pady=(0, 10))
        cor = "#15803d" if sucesso else "#b91c1c"
        tk.Label(
            self.frame_pos_conclusao, text=texto, fg=cor, wraplength=580, justify="left",
        ).pack(anchor="w", pady=(0, 6))
        if sucesso:
            tk.Button(
                self.frame_pos_conclusao, text="Usar este backup para restaurar agora",
                bg="#15803d", fg="white",
                command=lambda: self.usar_backup_callback(self.caminho_backup_gerado) or self._fechar(),
            ).pack(side="left")
        tk.Button(self.frame_pos_conclusao, text="Fechar", command=self._fechar).pack(side="left", padx=(8, 0))

    def _fechar(self) -> None:
        if self.recuperando:
            if not messagebox.askyesno(
                "Recuperação em andamento",
                "A recuperação ainda está em andamento. Fechar esta janela agora vai CANCELAR a "
                "recuperação (o processo atual é interrompido, mas pode levar um instante). "
                "Cancelar e fechar?",
                parent=self,
            ):
                return
            # Não destrói de imediato: a thread ainda vai chamar self.after(...)
            # ao terminar de interromper o processo. Esconde a janela e deixa
            # _finalizar destruí-la de verdade quando isso acontecer — evita
            # erro de "widget já destruído" na thread em segundo plano.
            self._fechar_apos_cancelar = True
            if self.sinal_cancelamento is not None:
                self.sinal_cancelamento.set("Recuperação cancelada (janela fechada pelo usuário).")
            self.withdraw()
            return
        logger.remover_listener(self._log_thread_safe)
        self.destroy()
