"""
ui/janela_fila_restauracao.py
-------------------------------
Janela "Restaurar em lote": adicionar vários backups de uma vez e restaurá-los
em sequência, sem repetir manualmente o fluxo (selecionar, confirmar, esperar)
para cada um. Cada item da fila usa restore.executar_restauracao() — a mesma
função da restauração individual — com as mesmas configurações (Firebird,
usuário, senha, page size, charset, validação completa) para todos os itens.

A regra crítica do projeto (nunca sobrescrever um destino já existente)
continua garantida por executar_restauracao() em si; como não há como abrir
um diálogo interativo de conflito no meio de um lote sem supervisão, um
destino que já existir no momento de ADICIONAR à fila já recebe
automaticamente o sufixo "_RESTAURADO_<timestamp>" (mesma lógica usada na
restauração individual), evitando qualquer necessidade de decisão manual
durante o processamento.

Suporta cancelamento (mesmo padrão de restore.SinalCancelamento): interrompe
o item atual e não processa os seguintes.
"""
from __future__ import annotations

import re
import threading
import tkinter as tk
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from tkinter import filedialog, messagebox, scrolledtext, ttk

import firebird
import logger
import restore
import validator
from ui.dialogos import ItemHistorico, JanelaHistoricoSessao
from ui.estilos import CORES
from ui.widgets import adicionar_barra_busca, dica, salvar_texto_como_arquivo


@dataclass
class ItemFila:
    caminho_backup: str
    caminho_destino: str
    status: str = "Pendente"
    mensagem: str = ""
    duracao_formatada: str = ""
    resumo: restore.ResumoRestauracao | None = None


class JanelaFilaRestauracao(tk.Toplevel):
    def __init__(self, parent: tk.Tk, instalacoes: list[firebird.InstalacaoFirebird], config_padrao: dict):
        super().__init__(parent)
        self.title("Restaurar em lote")
        self.geometry("820x660")
        self.minsize(700, 500)
        self.transient(parent)

        self.instalacoes = instalacoes
        self.config_padrao = config_padrao
        self.itens: list[ItemFila] = []
        self.processando = False
        self.sinal_cancelamento: restore.SinalCancelamento | None = None
        self._fechar_apos_cancelar = False
        self.historico_resultado: list[ItemHistorico] = []

        tk.Label(self, text="📚 Restaurar em lote", font=("Segoe UI", 13, "bold")).pack(pady=(14, 2))
        tk.Label(
            self,
            text="Adicione vários backups e restaure todos em sequência, usando a mesma configuração "
                 "para todos. Cada um vira um NOVO banco — nenhum arquivo existente é sobrescrito.",
            fg="#555", wraplength=760, justify="left",
        ).pack(pady=(0, 8), padx=14)

        # --- Configuração aplicada a todos os itens ---------------------------
        frame_config = tk.LabelFrame(self, text="Configuração usada para todos os itens", padx=10, pady=6)
        frame_config.pack(fill="x", padx=14, pady=(0, 8))
        frame_config.columnconfigure(1, weight=1)
        frame_config.columnconfigure(3, weight=1)

        tk.Label(frame_config, text="Firebird:").grid(row=0, column=0, sticky="w")
        self.combo_firebird = ttk.Combobox(
            frame_config, values=[i.rotulo for i in instalacoes], state="readonly",
        )
        indice_padrao = config_padrao.get("instalacao_index", 0)
        if instalacoes:
            self.combo_firebird.current(indice_padrao if 0 <= indice_padrao < len(instalacoes) else 0)
        self.combo_firebird.grid(row=0, column=1, columnspan=3, sticky="ew", padx=(6, 0))

        tk.Label(frame_config, text="Usuário:").grid(row=1, column=0, sticky="w", pady=(4, 0))
        self.var_usuario = tk.StringVar(value=config_padrao.get("usuario", "SYSDBA"))
        tk.Entry(frame_config, textvariable=self.var_usuario, width=16).grid(
            row=1, column=1, sticky="w", padx=(6, 0), pady=(4, 0)
        )

        tk.Label(frame_config, text="Senha:").grid(row=1, column=2, sticky="w", padx=(12, 0), pady=(4, 0))
        self.var_senha = tk.StringVar(value=config_padrao.get("senha", ""))
        tk.Entry(frame_config, textvariable=self.var_senha, show="•", width=16).grid(
            row=1, column=3, sticky="w", padx=(6, 0), pady=(4, 0)
        )

        self.var_validacao_completa = tk.BooleanVar(value=config_padrao.get("validar_completo", False))
        check_validacao = tk.Checkbutton(
            frame_config, text="Validação completa após cada restauração (mais lento)",
            variable=self.var_validacao_completa,
        )
        check_validacao.grid(row=2, column=0, columnspan=4, sticky="w", pady=(4, 0))

        # --- Fila (Treeview) ----------------------------------------------------
        frame_lista = tk.LabelFrame(self, text="Fila", padx=8, pady=6)
        frame_lista.pack(fill="both", expand=True, padx=14, pady=(0, 8))

        cabecalho_lista = tk.Frame(frame_lista)
        cabecalho_lista.pack(fill="x")
        botao_adicionar = tk.Button(
            cabecalho_lista, text="+ Adicionar backup(s)...", command=self._adicionar_backups,
        )
        botao_adicionar.pack(side="left")
        dica(botao_adicionar, "Selecione um ou mais arquivos .fbk/.fbk.gz de uma vez — cada um vira um "
                               "item separado na fila, com destino sugerido automaticamente.")
        tk.Button(cabecalho_lista, text="🗑 Remover selecionado", command=self._remover_selecionado).pack(
            side="left", padx=(6, 0)
        )

        colunas = ("backup", "destino", "status")
        self.tree = ttk.Treeview(frame_lista, columns=colunas, show="headings", height=8)
        self.tree.heading("backup", text="Backup")
        self.tree.heading("destino", text="Destino")
        self.tree.heading("status", text="Status")
        self.tree.column("backup", width=220)
        self.tree.column("destino", width=300)
        self.tree.column("status", width=130, anchor="center")
        self.tree.pack(fill="both", expand=True, pady=(6, 0))

        # --- Status / progresso geral --------------------------------------------
        self.var_status = tk.StringVar(value="Pronto. Adicione backups à fila.")
        tk.Label(self, textvariable=self.var_status, fg="#555").pack(anchor="w", padx=14)
        frame_prog = tk.Frame(self)
        frame_prog.pack(fill="x", padx=14, pady=(2, 6))
        self.progresso_geral = ttk.Progressbar(frame_prog, mode="determinate", maximum=100)
        self.progresso_geral.pack(side="left", fill="x", expand=True)
        self.var_contagem = tk.StringVar(value="0/0")
        tk.Label(frame_prog, textvariable=self.var_contagem, width=8, anchor="e").pack(side="left", padx=(8, 0))

        # --- Botões de ação -------------------------------------------------------
        frame_acoes = tk.Frame(self)
        frame_acoes.pack(fill="x", padx=14, pady=(0, 8))
        self.botao_iniciar = tk.Button(
            frame_acoes, text="▶ INICIAR FILA", font=("Segoe UI", 10, "bold"),
            bg="#15803d", fg="white", command=self._iniciar,
        )
        self.botao_iniciar.pack(side="left", fill="x", expand=True)
        self.botao_cancelar = tk.Button(
            frame_acoes, text="❌ Cancelar", font=("Segoe UI", 10, "bold"),
            bg="#b91c1c", fg="white", command=self._cancelar, state="disabled",
        )
        self.botao_cancelar.pack(side="left", padx=(6, 0))

        # --- Log --------------------------------------------------------------
        cabecalho_log = tk.Frame(self)
        cabecalho_log.pack(fill="x", padx=14)
        tk.Label(cabecalho_log, text="Acompanhamento:", fg="#555").pack(side="left")
        tk.Button(
            cabecalho_log, text="💾 Salvar como .txt",
            command=lambda: salvar_texto_como_arquivo(
                self, self.area_log.get("1.0", "end"), f"fila_restauracao_{datetime.now():%Y%m%d_%H%M%S}.txt",
            ),
        ).pack(side="right")
        self.area_log = scrolledtext.ScrolledText(self, font=("Consolas", 9), height=8, state="disabled")
        for nivel, cor in CORES.items():
            self.area_log.tag_configure(nivel, foreground=cor)
        barra_busca = adicionar_barra_busca(self, self.area_log)
        barra_busca.pack(fill="x", padx=14, pady=(4, 4))
        self.area_log.pack(fill="both", expand=True, padx=14, pady=(0, 10))

        logger.registrar_listener(self._log_thread_safe)
        self.protocol("WM_DELETE_WINDOW", self._fechar)

    # -------------------------------------------------------------- Fila
    def _adicionar_backups(self) -> None:
        caminhos = filedialog.askopenfilenames(
            parent=self, title="Selecionar backups Firebird",
            filetypes=[("Backup Firebird", "*.fbk *.fbk.gz"), ("Todos os arquivos", "*.*")],
        )
        for caminho in caminhos:
            resultado = validator.validar_arquivo_backup(caminho)
            if not resultado.ok:
                messagebox.showwarning(
                    "Backup inválido", f"{Path(caminho).name}:\n{resultado.mensagem_usuario}\n\nIgnorado.",
                    parent=self,
                )
                continue
            destino = self._sugerir_destino(caminho, resultado.metadados)
            item = ItemFila(caminho_backup=caminho, caminho_destino=destino)
            self.itens.append(item)
            self.tree.insert("", "end", values=(Path(item.caminho_backup).name, item.caminho_destino, item.status))

    @staticmethod
    def _sugerir_destino(caminho_backup: str, meta: dict) -> str:
        nome_original = validator.extrair_nome_banco_original(caminho_backup, meta.get("comprimido", False))
        if nome_original:
            nome_base = Path(nome_original).stem
        else:
            nome = Path(caminho_backup).name
            for sufixo in (".fbk.gz", ".fbk"):
                if nome.lower().endswith(sufixo):
                    nome = nome[: -len(sufixo)]
                    break
            nome_base = re.sub(r"_\d{8}_\d{6}$", "", nome) or nome

        destino_sugerido = Path(caminho_backup).parent / f"{nome_base}.FDB"
        # Sem diálogo interativo de conflito durante o processamento em lote:
        # se já existir um arquivo com esse nome, já aplica o mesmo sufixo com
        # timestamp usado na restauração individual (nunca sobrescreve nada).
        if destino_sugerido.exists():
            return validator.sugerir_nome_restauracao_paralela(str(destino_sugerido))
        return str(destino_sugerido)

    def _remover_selecionado(self) -> None:
        if self.processando:
            return
        for iid in self.tree.selection():
            indice = self.tree.index(iid)
            del self.itens[indice]
            self.tree.delete(iid)

    def _atualizar_linha(self, item: ItemFila) -> None:
        indice = self.itens.index(item)
        iid = self.tree.get_children()[indice]
        self.tree.item(iid, values=(Path(item.caminho_backup).name, item.caminho_destino, item.status))

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

    # --------------------------------------------------------- Processamento
    def _iniciar(self) -> None:
        if self.processando:
            return
        pendentes = [it for it in self.itens if it.status == "Pendente"]
        if not pendentes:
            messagebox.showwarning(
                "Fila vazia", "Adicione ao menos um backup à fila antes de iniciar.", parent=self,
            )
            return
        instalacao = self._instalacao_selecionada()
        if instalacao is None:
            messagebox.showwarning(
                "Firebird não selecionado", "Selecione uma instalação do Firebird.", parent=self,
            )
            return
        if not messagebox.askyesno(
            "Confirmar restauração em lote",
            f"Restaurar {len(pendentes)} backup(s) em sequência, usando {instalacao.rotulo}?",
            parent=self,
        ):
            return

        self.processando = True
        self.sinal_cancelamento = restore.SinalCancelamento()
        self.historico_resultado = []
        self.botao_iniciar.configure(state="disabled", text="PROCESSANDO...")
        self.botao_cancelar.configure(state="normal")
        self.progresso_geral["value"] = 0
        self.var_contagem.set(f"0/{len(pendentes)}")

        threading.Thread(
            target=self._worker_fila,
            args=(pendentes, instalacao, self.var_usuario.get().strip(), self.var_senha.get(),
                  self.var_validacao_completa.get()),
            daemon=True,
        ).start()

    def _cancelar(self) -> None:
        if not self.processando or self.sinal_cancelamento is None:
            return
        if not messagebox.askyesno(
            "Cancelar fila",
            "Tem certeza que deseja interromper a restauração em lote?\n\n"
            "O item atual é interrompido; os itens seguintes não são processados.",
            parent=self,
        ):
            return
        self.sinal_cancelamento.set()
        self.botao_cancelar.configure(state="disabled")
        self.var_status.set("Cancelando... aguarde o item atual ser interrompido.")

    def _worker_fila(self, pendentes, instalacao, usuario, senha, validar_completo) -> None:
        total = len(pendentes)

        for i, item in enumerate(pendentes, start=1):
            if self.sinal_cancelamento.is_set():
                item.status = "⏹ Cancelado"
                self.after(0, self._atualizar_linha, item)
                continue

            item.status = "Restaurando..."
            self.after(0, self._atualizar_linha, item)
            self.after(0, self.var_status.set, f"Restaurando {i}/{total}: {Path(item.caminho_backup).name}")

            resultado = restore.executar_restauracao(
                caminho_backup=item.caminho_backup,
                caminho_destino=item.caminho_destino,
                instalacao=instalacao,
                usuario=usuario,
                senha=senha,
                fator_estimativa=self.config_padrao.get("fator_estimativa", 4.0),
                margem_seguranca=self.config_padrao.get("margem_seguranca", 1.3),
                on_status=lambda msg: self.after(0, self.var_status.set, msg),
                validar_integridade_completa=validar_completo,
                page_size=self.config_padrao.get("page_size"),
                charset=self.config_padrao.get("charset"),
                sinal_cancelamento=self.sinal_cancelamento,
            )

            item.mensagem = resultado.mensagem_usuario
            item.resumo = resultado.resumo
            item.duracao_formatada = resultado.resumo.duracao_formatada if resultado.resumo else ""
            if resultado.sucesso:
                item.status = "✔ Sucesso"
            elif "cancelada" in resultado.mensagem_usuario.lower():
                item.status = "⏹ Cancelado"
            else:
                item.status = "✖ Falha"

            self.historico_resultado.append(ItemHistorico(
                timestamp=datetime.now(),
                nome_arquivo_backup=Path(item.caminho_backup).name,
                nome_banco_destino=Path(item.caminho_destino).name,
                sucesso=resultado.sucesso,
                duracao_formatada=item.duracao_formatada or "—",
                mensagem_erro="" if resultado.sucesso else resultado.mensagem_usuario,
                firebird_versao_detalhada=resultado.resumo.versao_firebird_detalhada if resultado.resumo else "",
                page_size=resultado.resumo.page_size if resultado.resumo else None,
                tabelas_com_problema=resultado.resumo.tabelas_com_problema if resultado.resumo else [],
                validacao_avisos=resultado.resumo.validacao_avisos if resultado.resumo else 0,
                gbak_erros_count=resultado.resumo.gbak_erros_count if resultado.resumo else 0,
            ))

            self.after(0, self._atualizar_linha, item)
            self.after(0, self._atualizar_progresso_geral, i, total)

            if self.sinal_cancelamento.is_set():
                break

        # Itens que ainda não chegaram a ser processados (cancelamento no meio).
        for item in pendentes:
            if item.status == "Pendente":
                item.status = "⏹ Cancelado"
                self.after(0, self._atualizar_linha, item)

        self.after(0, self._finalizar_fila)

    def _atualizar_progresso_geral(self, indice_atual: int, total: int) -> None:
        self.progresso_geral["value"] = (indice_atual / total) * 100 if total else 0
        self.var_contagem.set(f"{indice_atual}/{total}")

    def _finalizar_fila(self) -> None:
        self.processando = False
        self.sinal_cancelamento = None
        self.botao_iniciar.configure(state="normal", text="▶ INICIAR FILA")
        self.botao_cancelar.configure(state="disabled")

        if self._fechar_apos_cancelar:
            logger.remover_listener(self._log_thread_safe)
            self.destroy()
            return

        sucessos = sum(1 for it in self.itens if it.status == "✔ Sucesso")
        falhas = sum(1 for it in self.itens if it.status == "✖ Falha")
        cancelados = sum(1 for it in self.itens if it.status == "⏹ Cancelado")
        partes = [f"{sucessos} sucesso(s)"]
        if falhas:
            partes.append(f"{falhas} falha(s)")
        if cancelados:
            partes.append(f"{cancelados} cancelado(s)")
        self.var_status.set("Fila concluída: " + ", ".join(partes) + ".")

        if self.historico_resultado:
            JanelaHistoricoSessao(self, self.historico_resultado)

    def _fechar(self) -> None:
        if self.processando:
            if not messagebox.askyesno(
                "Restauração em lote em andamento",
                "A fila ainda está sendo processada. Fechar esta janela vai CANCELAR a restauração "
                "em andamento (o item atual é interrompido). Cancelar e fechar?",
                parent=self,
            ):
                return
            self._fechar_apos_cancelar = True
            if self.sinal_cancelamento is not None:
                self.sinal_cancelamento.set("Fila cancelada (janela fechada pelo usuário).")
            self.withdraw()
            return
        logger.remover_listener(self._log_thread_safe)
        self.destroy()
