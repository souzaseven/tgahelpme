"""
ui/dialogos.py
---------------
As janelas secundárias (Toplevel) do Restaurador Firebird: o diálogo de
conflito de destino, a lista de erros, o resumo final, os detalhes técnicos
de uma falha e o histórico consolidado da sessão — junto com o modelo de
dados (ItemHistorico) e a formatação de texto (montar_texto_historico) que
alimentam esse histórico. Separado de main_window.py (que fica só com a
janela principal) para cada arquivo caber numa leitura só.
"""
from __future__ import annotations

import tkinter as tk
from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path
from tkinter import filedialog, scrolledtext, ttk

import restore
from ui.estilos import fundo_log, paleta_log
from ui.widgets import abrir_pasta_com_arquivo_selecionado, adicionar_barra_busca, dica, salvar_texto_como_arquivo


@dataclass
class ItemHistorico:
    """Um item do histórico de restaurações desta sessão (não persiste entre
    execuções do programa) — base do relatório consolidado mostrado em
    JanelaHistoricoSessao."""
    timestamp: datetime
    nome_arquivo_backup: str
    nome_banco_destino: str
    sucesso: bool
    duracao_formatada: str
    mensagem_erro: str = ""
    firebird_versao_detalhada: str = ""
    page_size: int | None = None
    tabelas_com_problema: list = field(default_factory=list)
    validacao_avisos: int = 0
    gbak_erros_count: int = 0


def montar_texto_historico(itens: list[ItemHistorico]) -> str:
    """Agrupa por arquivo de backup de origem e monta um relatório de texto
    no formato: origem -> lista numerada de bancos criados -> resumo
    consolidado (Firebird usado, Page Size, falhas e avisos)."""
    if not itens:
        return "Nenhuma restauração foi feita ainda nesta sessão."

    grupos: dict[str, list[ItemHistorico]] = {}
    for item in itens:
        grupos.setdefault(item.nome_arquivo_backup, []).append(item)

    blocos = []
    for origem, grupo in grupos.items():
        linhas = [f"Origem: {origem}", ""]
        for i, item in enumerate(grupo, start=1):
            resultado = "sucesso" if item.sucesso else f"FALHA — {item.mensagem_erro}"
            linhas.append(f"{i}º banco criado: {item.nome_banco_destino}")
            linhas.append(f"   Duração: {item.duracao_formatada}")
            linhas.append(f"   Resultado: {resultado}")
            linhas.append("")

        referencia = next((it for it in reversed(grupo) if it.sucesso), grupo[-1])
        linhas.append(f"Firebird usado: {referencia.firebird_versao_detalhada or 'desconhecido'}")
        linhas.append(f"Page Size: {referencia.page_size} bytes" if referencia.page_size else "Page Size: (do backup)")
        linhas.append("")

        todas_sucesso = all(it.sucesso for it in grupo)
        falhas = [it for it in grupo if not it.sucesso]
        avisos = [
            it for it in grupo
            if it.tabelas_com_problema or it.validacao_avisos > 0 or it.gbak_erros_count > 0
        ]

        linhas.append(f"Resultado das restaurações: {'sucesso' if todas_sucesso else 'com falha(s)'}")
        linhas.append(
            "Falha fatal encontrada: " + ("nenhuma" if not falhas else "; ".join(f.mensagem_erro for f in falhas))
        )
        if avisos:
            descricoes = []
            for a in avisos:
                if a.tabelas_com_problema:
                    descricoes.append(f"{a.nome_banco_destino}: tabelas {', '.join(a.tabelas_com_problema)}")
                elif a.gbak_erros_count > 0:
                    descricoes.append(
                        f"{a.nome_banco_destino}: {a.gbak_erros_count} erro(s) do gbak "
                        "(provavelmente índice/chave estrangeira)"
                    )
                else:
                    descricoes.append(f"{a.nome_banco_destino}: {a.validacao_avisos} aviso(s)")
            linhas.append("Aviso crítico encontrado: " + "; ".join(descricoes))
        else:
            linhas.append("Aviso crítico encontrado: nenhum")

        blocos.append("\n".join(linhas))

    return ("\n\n" + "-" * 50 + "\n\n").join(blocos)


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


class JanelaSomenteErros(tk.Toplevel):
    """Mostra só as linhas de nível ERRO já registradas no acompanhamento —
    atalho para não precisar garimpar uma restauração longa (com centenas de
    linhas de progresso) atrás das poucas linhas que realmente importam.
    Recebe as mesmas mensagens em tempo real enquanto a restauração continua."""

    def __init__(self, parent: tk.Tk, linhas_iniciais: list[str], tema_hacker: bool = False):
        super().__init__(parent)
        self.title("Somente erros")
        cor_erro = paleta_log(tema_hacker)["ERRO"]
        fundo = fundo_log(tema_hacker)

        parent.update_idletasks()
        largura, altura = 620, max(parent.winfo_height(), 400)
        x = parent.winfo_x() + parent.winfo_width() + 8
        y = parent.winfo_y()
        self.geometry(f"{largura}x{altura}+{x}+{y}")

        cabecalho = tk.Frame(self)
        cabecalho.pack(fill="x", padx=8, pady=(8, 0))
        self.var_contagem = tk.StringVar()
        tk.Label(cabecalho, textvariable=self.var_contagem, fg="#b91c1c", font=("Segoe UI", 9, "bold")).pack(
            side="left"
        )
        botao_salvar = tk.Button(
            cabecalho, text="💾 Salvar como .txt",
            command=lambda: salvar_texto_como_arquivo(
                self, self.area.get("1.0", "end"),
                f"erros_restauracao_{datetime.now():%Y%m%d_%H%M%S}.txt",
            ),
        )
        botao_salvar.pack(side="right")
        dica(botao_salvar, "Salva a lista de erros exibida nesta janela em um arquivo .txt.")

        self.area = scrolledtext.ScrolledText(
            self, wrap="word", font=("Consolas", 10), fg=cor_erro, bg=fundo, insertbackground=cor_erro,
        )
        self.area.pack(fill="both", expand=True, padx=8, pady=8)

        barra_busca = adicionar_barra_busca(self, self.area)
        barra_busca.pack(fill="x", padx=8, pady=(0, 4), before=self.area)

        for linha in linhas_iniciais:
            self.area.insert("end", linha + "\n")
        self._atualizar_contagem(len(linhas_iniciais))
        self.area.see("end")

    def _atualizar_contagem(self, total: int) -> None:
        self.var_contagem.set("Nenhum erro registrado até agora." if total == 0 else f"{total} erro(s) registrado(s).")

    def adicionar_linha(self, linha: str, total: int) -> None:
        self.area.insert("end", linha + "\n")
        self._atualizar_contagem(total)
        self.area.see("end")


class JanelaResumo(tk.Toplevel):
    """Resumo completo exibido ao final de uma restauração bem-sucedida."""

    def __init__(self, parent: tk.Tk, resumo: restore.ResumoRestauracao):
        super().__init__(parent)
        self.title("Resumo da restauração")
        self.geometry("560x640")
        self.transient(parent)
        self.grab_set()

        tk.Label(
            self, text="✔ BANCO RESTAURADO COM SUCESSO",
            font=("Segoe UI", 12, "bold"), fg="#15803d",
        ).pack(pady=(14, 6))

        # Card de status de integridade: fixo no topo (fora da área rolável),
        # para responder de forma inequívoca e sempre visível "teve erro ou
        # não" — sem depender de rolar até o fim do resumo para descobrir.
        # Erros que o próprio gbak reportou DURANTE o restore (ex.: violação
        # de FOREIGN KEY ao ativar um índice) — sempre disponível, mesmo sem
        # marcar "Validação completa". Tem prioridade sobre o resultado dessa
        # validação opcional: se o gbak já disse que algo ficou errado, o
        # resumo nunca deve ficar em silêncio sobre isso.
        if resumo.gbak_erros_count > 0:
            cor_fundo, cor_texto = "#fee2e2", "#b91c1c"
            texto_status = (
                f"⚠ {resumo.gbak_erros_count} ERRO(S) REPORTADO(S) PELO GBAK DURANTE A RESTAURAÇÃO — "
                "provavelmente um ou mais índices (chave estrangeira) não puderam ser recriados por "
                "dados inconsistentes no backup. Os demais dados foram restaurados normalmente; "
                "veja a lista completa mais abaixo."
            )
        elif not resumo.validacao_executada:
            cor_fundo, cor_texto = "#dcfce7", "#15803d"
            texto_status = (
                "✔ NENHUM ERRO REPORTADO PELO GBAK durante a restauração. Isto não substitui uma "
                "verificação completa: marque \"Validação completa\" antes de restaurar para também "
                "detectar registros corrompidos que o gbak não acusaria."
            )
        elif resumo.validacao_erros == 0 and resumo.validacao_avisos == 0:
            cor_fundo, cor_texto = "#dcfce7", "#15803d"
            texto_status = "✔ NENHUM ERRO ENCONTRADO — a validação completa (gfix -v -full) não detectou " \
                            "nenhuma tabela ou registro corrompido."
        else:
            cor_fundo, cor_texto = "#fee2e2", "#b91c1c"
            if resumo.tabelas_com_problema:
                texto_status = (
                    f"⚠ {resumo.validacao_erros} ERRO(S) e {resumo.validacao_avisos} AVISO(S) ENCONTRADOS — "
                    f"tabela(s) afetada(s): {', '.join(resumo.tabelas_com_problema)}."
                )
            else:
                texto_status = (
                    f"⚠ {resumo.validacao_erros} ERRO(S) e {resumo.validacao_avisos} AVISO(S) ENCONTRADOS — "
                    "não foi possível identificar automaticamente quais tabelas; consulte os detalhes técnicos."
                )
        tk.Label(
            self, text=texto_status, bg=cor_fundo, fg=cor_texto, font=("Segoe UI", 9, "bold"),
            wraplength=520, justify="left", padx=12, pady=8,
        ).pack(fill="x", padx=14, pady=(0, 8))

        # Conteúdo rolável: com todos os campos + avisos, facilmente passa da
        # altura de uma tela menor.
        canvas = tk.Canvas(self, highlightthickness=0)
        scrollbar = ttk.Scrollbar(self, orient="vertical", command=canvas.yview)
        container = tk.Frame(canvas, padx=18, pady=4)
        container.bind("<Configure>", lambda e: canvas.configure(scrollregion=canvas.bbox("all")))
        canvas.create_window((0, 0), window=container, anchor="nw")
        canvas.configure(yscrollcommand=scrollbar.set)
        canvas.pack(side="left", fill="both", expand=True)
        scrollbar.pack(side="right", fill="y")

        # Scroll com a roda do mouse, ativo só enquanto o cursor está sobre
        # esta janela (para não interferir em outras áreas roláveis da app).
        def _rolar(evento):
            canvas.yview_scroll(int(-1 * (evento.delta / 120)), "units")

        canvas.bind("<Enter>", lambda e: canvas.bind_all("<MouseWheel>", _rolar))
        canvas.bind("<Leave>", lambda e: canvas.unbind_all("<MouseWheel>"))
        self.protocol("WM_DELETE_WINDOW", lambda: (canvas.unbind_all("<MouseWheel>"), self.destroy()))

        campos = [
            ("Origem (arquivo de backup)", resumo.nome_arquivo_backup),
            ("Banco original (do backup)", resumo.nome_banco_antigo),
            ("Banco novo (restaurado)", resumo.nome_banco_novo),
            ("Local do novo banco", resumo.caminho_destino),
            ("Firebird utilizado na restauração",
             f"{resumo.firebird_usado} (build {resumo.versao_firebird_detalhada})"
             if resumo.versao_firebird_detalhada else resumo.firebird_usado),
            ("Versão do formato do backup (gbak)", resumo.formato_backup_versao or "não identificada"),
            ("ODS version (banco restaurado)", resumo.ods_version or "não identificada"),
            ("Page Size", f"{resumo.page_size} bytes" if resumo.page_size else "não identificado"),
            ("Dialeto do banco", str(resumo.dialeto) if resumo.dialeto is not None else "não identificado"),
            ("Registros restaurados (total)",
             f"{resumo.total_registros_restaurados} registro(s) em {resumo.quantidade_tabelas_com_dados} tabela(s)"),
            ("Tamanho do backup", resumo.tamanho_backup_formatado),
            ("Tamanho do banco restaurado", resumo.tamanho_destino_formatado),
            ("Início", resumo.hora_inicio),
            ("Fim", resumo.hora_fim),
            ("Tempo total (aproximado)", resumo.duracao_formatada),
            (
                "Validação completa (gfix -v -full)",
                resumo.validacao_mensagem if resumo.validacao_executada else "Não executada (opção não marcada).",
            ),
        ]
        # Inserções por posição relativa a um rótulo de referência (em vez de
        # índice fixo) — evita que um bloco condicional desalinhe o outro
        # quando os dois aparecem juntos (ex.: banco TGA com versão mobile
        # E comparação de tamanho com o banco original, ao mesmo tempo).
        def _inserir_apos(rotulo_referencia: str, *novos_campos: tuple) -> None:
            indice = next((i for i, (r, _) in enumerate(campos) if r == rotulo_referencia), len(campos) - 1)
            for offset, campo in enumerate(novos_campos, start=1):
                campos.insert(indice + offset, campo)

        if resumo.tga_versao_base is not None:
            versao_tga = resumo.tga_versao_base
            if resumo.tga_data_atualizacao:
                versao_tga += f" (atualizado em {resumo.tga_data_atualizacao})"
            novos = [("Versão do sistema TGA (GDIVERSOS)", versao_tga)]
            if resumo.tga_versao_mobile:
                novos.append(("Versão mobile (GDIVERSOS)", resumo.tga_versao_mobile))
            _inserir_apos("Local do novo banco", *novos)
        if resumo.tamanho_original_formatado is not None:
            _inserir_apos(
                "Tamanho do banco restaurado",
                ("Tamanho do banco original (informado)", resumo.tamanho_original_formatado),
                ("Diferença de tamanho", resumo.diferenca_tamanho_formatada),
            )

        tabela = tk.Frame(container)
        tabela.pack(fill="both", expand=True)
        for i, (rotulo, valor) in enumerate(campos):
            tk.Label(tabela, text=rotulo + ":", anchor="w", font=("Segoe UI", 9, "bold")).grid(
                row=i, column=0, sticky="nw", pady=2
            )
            tk.Label(tabela, text=str(valor), anchor="w", wraplength=280, justify="left").grid(
                row=i, column=1, sticky="w", padx=(10, 0), pady=2
            )

        # (O status de integridade — se teve erro ou não — já aparece bem
        # destacado no card fixo no topo desta janela, sempre visível.)

        if resumo.gbak_linhas_erro:
            tk.Label(
                container, text="Linhas de erro reportadas pelo gbak:",
                font=("Segoe UI", 9, "bold"), fg="#b91c1c",
            ).pack(anchor="w", pady=(14, 2))
            area_erros = scrolledtext.ScrolledText(
                container, wrap="word", font=("Consolas", 8), height=6, fg="#b91c1c",
            )
            area_erros.pack(fill="x", pady=(0, 4))
            area_erros.insert("1.0", "\n".join(resumo.gbak_linhas_erro))
            area_erros.configure(state="disabled")

        # Explicação sobre tamanho: o pedido mais comum de suporte é "por que
        # o banco restaurado ficou maior que o original?" — a resposta é
        # sempre a mesma, então mostramos por padrão, e reforçamos com o
        # número real quando o usuário informa o banco original.
        texto_tamanho = (
            "O tamanho do banco restaurado pode ficar MAIOR (ou menor) do que o banco original antes do "
            "backup. Isso é normal e não indica perda de dados: o backup guarda apenas os dados lógicos "
            "(registros), sem o espaço livre, versões antigas (MVCC) e fragmentação do arquivo físico; ao "
            "restaurar, o Firebird recria as páginas e reconstrói todos os índices do zero, o que muda o "
            "tamanho final."
        )
        if resumo.diferenca_tamanho_formatada and resumo.diferenca_tamanho_formatada.startswith("+"):
            texto_tamanho = f"Diferença observada: {resumo.diferenca_tamanho_formatada}. " + texto_tamanho
        tk.Label(container, text=texto_tamanho, fg="#555", wraplength=460, justify="left").pack(
            anchor="w", pady=(10, 0)
        )

        tk.Label(
            container,
            text="O backup original e o banco original (se houver) não foram alterados.",
            fg="#555", wraplength=460, justify="left",
        ).pack(anchor="w", pady=(10, 0))

        frame_botoes_finais = tk.Frame(container)
        frame_botoes_finais.pack(pady=(14, 14))
        botao_abrir_pasta = tk.Button(
            frame_botoes_finais, text="📂 Abrir pasta", width=14,
            command=lambda: abrir_pasta_com_arquivo_selecionado(self, resumo.caminho_destino),
        )
        botao_abrir_pasta.grid(row=0, column=0, padx=6)
        dica(botao_abrir_pasta, "Abre o Explorer na pasta do banco restaurado, com o arquivo já selecionado.")
        tk.Button(frame_botoes_finais, text="Fechar", width=14, command=self.destroy).grid(row=0, column=1, padx=6)


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


class JanelaHistoricoSessao(tk.Toplevel):
    """Relatório consolidado de todas as restaurações feitas nesta sessão —
    útil quando o mesmo backup é restaurado mais de uma vez (ex.: um conflito
    de nome levou a criar um segundo banco com sufixo _RESTAURADO)."""

    def __init__(self, parent: tk.Tk, itens: list[ItemHistorico]):
        super().__init__(parent)
        self.title("Histórico desta sessão")
        self.geometry("560x520")

        cabecalho = tk.Frame(self)
        cabecalho.pack(fill="x", padx=8, pady=(8, 0))
        tk.Label(cabecalho, text=f"{len(itens)} restauração(ões) nesta sessão", fg="#555").pack(side="left")
        botao_salvar = tk.Button(
            cabecalho, text="💾 Salvar como .txt",
            command=lambda: salvar_texto_como_arquivo(
                self, self.area.get("1.0", "end"),
                f"historico_restauracoes_{datetime.now():%Y%m%d_%H%M%S}.txt",
            ),
        )
        botao_salvar.pack(side="right")

        self.area = scrolledtext.ScrolledText(self, wrap="word", font=("Consolas", 9))
        self.area.pack(fill="both", expand=True, padx=8, pady=8)
        self.area.insert("1.0", montar_texto_historico(itens))
        self.area.configure(state="disabled")

        barra_busca = adicionar_barra_busca(self, self.area)
        barra_busca.pack(fill="x", padx=8, before=self.area)

        tk.Button(self, text="Fechar", command=self.destroy).pack(pady=(0, 8))
