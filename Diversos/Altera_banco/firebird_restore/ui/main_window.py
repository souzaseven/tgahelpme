"""
ui/main_window.py
------------------
Interface gráfica (Tkinter) do Restaurador Firebird. Mantida deliberadamente
simples: campos grandes, um único botão de ação principal, status sempre
visível e um painel de detalhes para acompanhar o progresso em tempo real.

A restauração roda em uma thread separada para a janela nunca "travar";
toda atualização de widgets a partir dessa thread passa por `self.after(...)`,
que é a forma thread-safe de mexer no Tkinter.
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
import restore
import validator

CORES = {
    "INFO": "#1f2937",
    "AVISO": "#b45309",
    "ERRO": "#b91c1c",
    "OK": "#15803d",
}


class DialogoConflitoDestino(tk.Toplevel):
    """Regra crítica do projeto: nunca sobrescrever um .fdb existente sem
    decisão explícita do usuário. Este diálogo é a única porta de saída
    quando o destino escolhido já existe."""

    def __init__(self, parent: tk.Tk, caminho_original: str, caminho_sugerido: str):
        super().__init__(parent)
        self.title("Atenção — banco já existe")
        self.resizable(False, False)
        self.resultado: str | None = None  # None = cancelado; senão, novo caminho de destino
        self.transient(parent)
        self.grab_set()

        pad = {"padx": 16, "pady": 6}

        tk.Label(self, text="⚠ ATENÇÃO", font=("Segoe UI", 12, "bold"), fg="#b45309").pack(**pad)
        tk.Label(
            self,
            text=f"Já existe um banco no destino:\n{caminho_original}\n\n"
                 "Para proteger seus dados, ele nunca será sobrescrito automaticamente.",
            justify="left",
            wraplength=420,
        ).pack(**pad)

        frame_botoes = tk.Frame(self)
        frame_botoes.pack(pady=(10, 16))

        tk.Button(frame_botoes, text="CANCELAR", width=18, command=self._cancelar).grid(row=0, column=0, padx=6)
        tk.Button(frame_botoes, text="ESCOLHER OUTRO NOME", width=20,
                  command=lambda: self._escolher_outro_nome(caminho_original)).grid(row=0, column=1, padx=6)
        tk.Button(
            frame_botoes,
            text="CRIAR RESTAURAÇÃO PARALELA\n(recomendado)",
            width=26,
            bg="#15803d",
            fg="white",
            command=lambda: self._usar_sugestao(caminho_sugerido),
        ).grid(row=0, column=2, padx=6)

        self.protocol("WM_DELETE_WINDOW", self._cancelar)
        self.wait_visibility()
        self.focus_set()

    def _cancelar(self):
        self.resultado = None
        self.destroy()

    def _usar_sugestao(self, caminho_sugerido: str):
        self.resultado = caminho_sugerido
        self.destroy()

    def _escolher_outro_nome(self, caminho_original: str):
        pasta_inicial = str(Path(caminho_original).parent)
        escolhido = filedialog.asksaveasfilename(
            parent=self,
            title="Escolher outro nome para o banco restaurado",
            initialdir=pasta_inicial,
            defaultextension=".fdb",
            filetypes=[("Banco Firebird", "*.fdb")],
        )
        if escolhido:
            self.resultado = escolhido
            self.destroy()


class JanelaDetalhesTecnicos(tk.Toplevel):
    def __init__(self, parent: tk.Tk, texto: str):
        super().__init__(parent)
        self.title("Detalhes técnicos")
        self.geometry("640x420")
        area = scrolledtext.ScrolledText(self, wrap="word", font=("Consolas", 9))
        area.pack(fill="both", expand=True, padx=8, pady=8)
        area.insert("1.0", texto or "(sem detalhes técnicos)")
        area.configure(state="disabled")
        tk.Button(self, text="Fechar", command=self.destroy).pack(pady=(0, 8))


class JanelaPrincipal(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title("Restaurador Firebird")
        self.geometry("780x720")
        self.minsize(700, 640)

        self.settings = config.carregar_configuracoes()
        self.instalacoes: list[firebird.InstalacaoFirebird] = []
        self.metadados_backup: dict | None = None
        self.ultimo_resultado: restore.ResultadoRestauracao | None = None
        self.restaurando = False

        self._construir_widgets()
        logger.registrar_listener(self._on_log_thread_safe)
        self.protocol("WM_DELETE_WINDOW", self._ao_fechar)
        self._detectar_firebird_async()

    # ------------------------------------------------------------------ UI
    def _construir_widgets(self) -> None:
        tk.Label(self, text="RESTAURADOR FIREBIRD", font=("Segoe UI", 16, "bold")).pack(pady=(14, 4))
        tk.Label(
            self,
            text="Restaura um backup (.fbk ou .fbk.gz) para um NOVO banco .fdb, sem alterar o original.",
            fg="#555",
        ).pack(pady=(0, 10))

        container = tk.Frame(self)
        container.pack(fill="both", expand=True, padx=16, pady=4)
        container.columnconfigure(0, weight=1)

        # --- Backup ------------------------------------------------------
        secao_backup = tk.LabelFrame(container, text="Backup (.fbk / .fbk.gz)", padx=10, pady=8)
        secao_backup.pack(fill="x", pady=6)
        secao_backup.columnconfigure(0, weight=1)

        self.var_backup = tk.StringVar()
        tk.Entry(secao_backup, textvariable=self.var_backup, state="readonly").grid(row=0, column=0, sticky="ew")
        tk.Button(secao_backup, text="Selecionar...", command=self._selecionar_backup).grid(row=0, column=1, padx=(8, 0))

        self.var_info_backup = tk.StringVar(value="Nenhum backup selecionado.")
        tk.Label(secao_backup, textvariable=self.var_info_backup, fg="#555", justify="left", anchor="w").grid(
            row=1, column=0, columnspan=2, sticky="w", pady=(6, 0)
        )

        # --- Destino -------------------------------------------------------
        secao_destino = tk.LabelFrame(container, text="Banco de destino (novo .fdb)", padx=10, pady=8)
        secao_destino.pack(fill="x", pady=6)
        secao_destino.columnconfigure(0, weight=1)

        self.var_destino = tk.StringVar()
        tk.Entry(secao_destino, textvariable=self.var_destino).grid(row=0, column=0, sticky="ew")
        tk.Button(secao_destino, text="Selecionar...", command=self._selecionar_destino).grid(row=0, column=1, padx=(8, 0))

        # --- Firebird --------------------------------------------------------
        secao_fb = tk.LabelFrame(container, text="Firebird", padx=10, pady=8)
        secao_fb.pack(fill="x", pady=6)
        secao_fb.columnconfigure(0, weight=1)

        self.combo_firebird = ttk.Combobox(secao_fb, state="readonly")
        self.combo_firebird.grid(row=0, column=0, sticky="ew")
        tk.Button(secao_fb, text="Detectar automaticamente", command=self._detectar_firebird_async).grid(
            row=0, column=1, padx=(8, 0)
        )

        # --- Autenticação ----------------------------------------------------
        secao_auth = tk.LabelFrame(container, text="Autenticação", padx=10, pady=8)
        secao_auth.pack(fill="x", pady=6)

        tk.Label(secao_auth, text="Usuário:").grid(row=0, column=0, sticky="w")
        self.var_usuario = tk.StringVar(value=self.settings.usuario_padrao or "SYSDBA")
        tk.Entry(secao_auth, textvariable=self.var_usuario, width=18).grid(row=0, column=1, padx=(4, 20))

        tk.Label(secao_auth, text="Senha:").grid(row=0, column=2, sticky="w")
        self.var_senha = tk.StringVar()
        tk.Entry(secao_auth, textvariable=self.var_senha, show="*", width=18).grid(row=0, column=3, padx=(4, 0))

        # --- Status / progresso ------------------------------------------------
        secao_status = tk.LabelFrame(container, text="Status", padx=10, pady=8)
        secao_status.pack(fill="x", pady=6)

        self.var_status = tk.StringVar(value="Pronto para restaurar.")
        tk.Label(secao_status, textvariable=self.var_status, anchor="w").pack(fill="x")
        self.progresso = ttk.Progressbar(secao_status, mode="indeterminate")
        self.progresso.pack(fill="x", pady=(6, 0))

        # --- Botão principal -------------------------------------------------
        self.botao_restaurar = tk.Button(
            container,
            text="RESTAURAR BANCO",
            font=("Segoe UI", 12, "bold"),
            bg="#15803d",
            fg="white",
            height=2,
            command=self._iniciar_restauracao,
        )
        self.botao_restaurar.pack(fill="x", pady=10)

        # --- Detalhes / log ----------------------------------------------------
        secao_log = tk.LabelFrame(container, text="Detalhes", padx=10, pady=8)
        secao_log.pack(fill="both", expand=True, pady=6)

        self.area_log = scrolledtext.ScrolledText(secao_log, height=12, font=("Consolas", 9), state="disabled")
        self.area_log.pack(fill="both", expand=True)
        for nivel, cor in CORES.items():
            self.area_log.tag_configure(nivel, foreground=cor)

    # ------------------------------------------------------------- Backup
    def _selecionar_backup(self) -> None:
        caminho = filedialog.askopenfilename(
            title="Selecionar backup Firebird",
            initialdir=self.settings.pasta_padrao_backups or None,
            filetypes=[("Backup Firebird", "*.fbk *.fbk.gz"), ("Todos os arquivos", "*.*")],
        )
        if not caminho:
            return

        resultado = validator.validar_arquivo_backup(caminho)
        if not resultado.ok:
            messagebox.showerror("Backup inválido", resultado.mensagem_usuario)
            return

        self.var_backup.set(caminho)
        meta = resultado.metadados
        info = (
            f"Backup: {meta['nome']}\n"
            f"Tamanho: {meta['tamanho_formatado']}"
            f"{'  (comprimido)' if meta['comprimido'] else ''}\n"
            f"Modificado em: {meta['modificado']}"
        )
        self.var_info_backup.set(info)
        self.metadados_backup = meta

        self.settings.pasta_padrao_backups = str(Path(caminho).parent)
        config.salvar_configuracoes(self.settings)

        if not self.var_destino.get():
            self.var_destino.set(self._sugerir_destino_a_partir_do_backup(caminho))

    @staticmethod
    def _sugerir_destino_a_partir_do_backup(caminho_backup: str) -> str:
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
        self.progresso.start(12)
        logger.info("=== Nova restauração iniciada pela interface ===")

        threading.Thread(
            target=self._worker_restaurar,
            args=(backup, destino, instalacao, usuario, senha),
            daemon=True,
        ).start()

    def _worker_restaurar(self, backup, destino, instalacao, usuario, senha) -> None:
        resultado = restore.executar_restauracao(
            caminho_backup=backup,
            caminho_destino=destino,
            instalacao=instalacao,
            usuario=usuario,
            senha=senha,
            fator_estimativa=self.settings.fator_estimativa_fdb_por_fbk,
            margem_seguranca=self.settings.margem_seguranca_espaco,
            on_status=lambda msg: self.after(0, self._atualizar_status, msg),
        )
        self.after(0, self._finalizar_restauracao, resultado)

    def _atualizar_status(self, texto: str) -> None:
        self.var_status.set(texto)

    def _finalizar_restauracao(self, resultado: restore.ResultadoRestauracao) -> None:
        self.restaurando = False
        self.ultimo_resultado = resultado
        self.progresso.stop()
        self.botao_restaurar.configure(state="normal", text="RESTAURAR BANCO")
        self.var_status.set(resultado.mensagem_usuario)

        if resultado.sucesso:
            messagebox.showinfo(
                "Restauração concluída",
                f"BANCO RESTAURADO COM SUCESSO.\n\nArquivo criado em:\n{resultado.caminho_destino}\n\n"
                "O backup original e o banco original (se houver) não foram alterados.",
            )
        else:
            resposta = messagebox.askyesno(
                "Erro na restauração",
                f"{resultado.mensagem_usuario}\n\nDeseja ver os detalhes técnicos (para suporte)?",
                icon="error",
            )
            if resposta:
                JanelaDetalhesTecnicos(self, resultado.detalhes_tecnicos)

    # ------------------------------------------------------------- Saída
    def _ao_fechar(self) -> None:
        config.salvar_configuracoes(self.settings)
        self.destroy()


def rodar() -> None:
    app = JanelaPrincipal()
    app.mainloop()
