"""
ui/widgets.py
-------------
Pequenos utilitários de interface reaproveitados em várias janelas: tooltip
("dica"), salvar um Text/ScrolledText como .txt, abrir o Explorer numa pasta,
e a barra de busca/filtro usada em toda área de log. Nenhum deles conhece o
fluxo de restauração — só recebem um widget e operam sobre ele.
"""
from __future__ import annotations

import subprocess
import tkinter as tk
from pathlib import Path
from tkinter import filedialog, messagebox

_CREATION_FLAGS = subprocess.CREATE_NO_WINDOW if hasattr(subprocess, "CREATE_NO_WINDOW") else 0


class DicaFerramenta:
    """Tooltip simples: mostra um texto de ajuda ao passar o mouse sobre um
    widget, sem depender de nenhuma biblioteca externa. Usado para explicar
    cada campo/opção da tela a um usuário com pouca experiência técnica."""

    def __init__(self, widget: tk.Widget, texto: str, wraplength: int = 320):
        self.widget = widget
        self.texto = texto
        self.wraplength = wraplength
        self.janela: tk.Toplevel | None = None
        widget.bind("<Enter>", self._mostrar, add="+")
        widget.bind("<Leave>", self._esconder, add="+")
        widget.bind("<ButtonPress>", self._esconder, add="+")

    def _mostrar(self, _evento=None) -> None:
        if self.janela is not None or not self.texto:
            return
        x = self.widget.winfo_rootx() + 12
        y = self.widget.winfo_rooty() + self.widget.winfo_height() + 6
        self.janela = tk.Toplevel(self.widget)
        self.janela.wm_overrideredirect(True)
        self.janela.wm_geometry(f"+{x}+{y}")
        try:
            self.janela.attributes("-topmost", True)
        except tk.TclError:
            pass
        tk.Label(
            self.janela, text=self.texto, justify="left", background="#fffbe6",
            foreground="#333", relief="solid", borderwidth=1,
            wraplength=self.wraplength, font=("Segoe UI", 8), padx=6, pady=4,
        ).pack()

    def _esconder(self, _evento=None) -> None:
        if self.janela is not None:
            self.janela.destroy()
            self.janela = None


def dica(widget: tk.Widget, texto: str) -> None:
    """Atalho para aplicar uma DicaFerramenta a um widget."""
    DicaFerramenta(widget, texto)


def salvar_texto_como_arquivo(parent: tk.Widget, texto: str, nome_sugerido: str) -> None:
    """Salva o conteúdo de uma área de log/detalhes como .txt — usado para
    levar o acompanhamento da restauração para outro lugar (chamado, e-mail,
    outra máquina)."""
    caminho = filedialog.asksaveasfilename(
        parent=parent, title="Salvar detalhes como texto",
        defaultextension=".txt", initialfile=nome_sugerido,
        filetypes=[("Arquivo de texto", "*.txt"), ("Todos os arquivos", "*.*")],
    )
    if not caminho:
        return
    try:
        Path(caminho).write_text(texto, encoding="utf-8")
    except OSError as exc:
        messagebox.showerror("Erro ao salvar", f"Não foi possível salvar o arquivo:\n{exc}", parent=parent)
        return
    messagebox.showinfo("Salvo", f"Detalhes salvos em:\n{caminho}", parent=parent)


def abrir_pasta_com_arquivo_selecionado(parent: tk.Widget, caminho_arquivo: str) -> None:
    """Abre o Explorer do Windows já na pasta do arquivo indicado, com ele
    selecionado — atalho para não precisar navegar manualmente até o banco
    recém-restaurado. Nunca trava o app: se o Explorer não puder ser aberto
    (arquivo movido/apagado nesse meio-tempo, por exemplo), mostra um aviso."""
    caminho = Path(caminho_arquivo)
    try:
        if caminho.exists():
            subprocess.run(["explorer", "/select,", str(caminho)], creationflags=_CREATION_FLAGS)
        else:
            subprocess.run(["explorer", str(caminho.parent)], creationflags=_CREATION_FLAGS)
    except OSError as exc:
        messagebox.showwarning(
            "Não foi possível abrir a pasta",
            f"Não foi possível abrir o Explorer nesta pasta:\n{caminho.parent}\n\nDetalhe: {exc}",
            parent=parent,
        )


def adicionar_barra_busca(parent_frame: tk.Widget, text_widget: tk.Text) -> tk.Frame:
    """Cria uma barra de busca/filtro para um Text (ou ScrolledText): destaca
    todas as ocorrências do termo digitado e permite navegar entre elas com
    Enter / os botões ◀ ▶. Não esconde linhas — apenas realça, para não
    perder o contexto ao redor de cada ocorrência."""
    frame = tk.Frame(parent_frame)

    text_widget.tag_configure("busca_destaque", background="#fde047")
    text_widget.tag_configure("busca_atual", background="#f97316", foreground="white")

    var_busca = tk.StringVar()
    var_contagem = tk.StringVar(value="")
    ocorrencias: list[str] = []
    indice_atual = {"i": -1}

    def _limpar_destaques() -> None:
        text_widget.tag_remove("busca_destaque", "1.0", "end")
        text_widget.tag_remove("busca_atual", "1.0", "end")

    def _destacar_atual() -> None:
        text_widget.tag_remove("busca_atual", "1.0", "end")
        if 0 <= indice_atual["i"] < len(ocorrencias):
            pos = ocorrencias[indice_atual["i"]]
            fim = f"{pos}+{len(var_busca.get())}c"
            text_widget.tag_add("busca_atual", pos, fim)
            text_widget.see(pos)
            var_contagem.set(f"{indice_atual['i'] + 1}/{len(ocorrencias)}")

    def executar_busca(_evento=None) -> None:
        _limpar_destaques()
        ocorrencias.clear()
        termo = var_busca.get()
        if not termo:
            var_contagem.set("")
            indice_atual["i"] = -1
            return
        inicio = "1.0"
        while True:
            pos = text_widget.search(termo, inicio, stopindex="end", nocase=True)
            if not pos:
                break
            fim = f"{pos}+{len(termo)}c"
            text_widget.tag_add("busca_destaque", pos, fim)
            ocorrencias.append(pos)
            inicio = fim
        if ocorrencias:
            indice_atual["i"] = 0
            _destacar_atual()
        else:
            indice_atual["i"] = -1
            var_contagem.set("0 encontrados")

    def proximo(_evento=None) -> None:
        if not ocorrencias:
            return
        indice_atual["i"] = (indice_atual["i"] + 1) % len(ocorrencias)
        _destacar_atual()

    def anterior() -> None:
        if not ocorrencias:
            return
        indice_atual["i"] = (indice_atual["i"] - 1) % len(ocorrencias)
        _destacar_atual()

    tk.Label(frame, text="🔎 Buscar:").pack(side="left")
    entry_busca = tk.Entry(frame, textvariable=var_busca, width=22)
    entry_busca.pack(side="left", padx=(4, 4))
    entry_busca.bind("<KeyRelease>", executar_busca)
    entry_busca.bind("<Return>", proximo)
    tk.Button(frame, text="◀", width=2, command=anterior).pack(side="left")
    tk.Button(frame, text="▶", width=2, command=proximo).pack(side="left", padx=(2, 6))
    tk.Label(frame, textvariable=var_contagem, fg="#555", width=8, anchor="w").pack(side="left")
    dica(entry_busca, "Filtra e destaca (em amarelo) todas as ocorrências do texto digitado. "
                       "Use Enter ou ▶/◀ para navegar entre elas. Aperte de novo se novas linhas "
                       "chegarem enquanto a restauração está em andamento.")

    return frame
