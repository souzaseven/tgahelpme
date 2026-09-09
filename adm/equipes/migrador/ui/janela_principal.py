"""
ui/janela_principal.py
----------------------
Janela principal: um QTabWidget com as 5 etapas (Origem, Análise,
Compatibilidade, Migração, Resultado). As abas só ficam habilitadas conforme o
fluxo avança. A janela detém o Estado compartilhado e coordena os workers.
"""
from __future__ import annotations

from pathlib import Path

from PySide6.QtCore import Qt
from PySide6.QtWidgets import QMainWindow, QMessageBox, QTabWidget

import config
import logger
from core.compatibilidade import avaliar, existe_bloqueio
from ui.aba_analise import AbaAnalise
from ui.aba_compatibilidade import AbaCompatibilidade
from ui.aba_migracao import AbaMigracao
from ui.aba_origem import AbaOrigem
from ui.aba_resultado import AbaResultado
from ui.estado import Estado
from ui.estilos import QSS
from version import VERSAO

ABA_ORIGEM, ABA_ANALISE, ABA_COMPAT, ABA_MIGRACAO, ABA_RESULTADO = range(5)


class JanelaPrincipal(QMainWindow):
    def __init__(self) -> None:
        super().__init__()
        self.setWindowTitle(f"Migrador Firebird  v{VERSAO}   —   Firebird 2.5 → 5.0")
        self.resize(1000, 760)
        self.setMinimumSize(820, 620)
        self.setStyleSheet(QSS)

        logger.iniciar_arquivo_log()
        _removidos = logger.limpar_logs_antigos()
        logger.info(f"Migrador Firebird v{VERSAO} iniciado. Logs antigos removidos: {_removidos}.")

        self.settings = config.carregar_configuracoes()
        self.estado = Estado(
            usuario=self.settings.usuario_padrao,
            validacao_completa=self.settings.validacao_completa_padrao,
        )

        self.tabs = QTabWidget()
        self.setCentralWidget(self.tabs)

        self.aba_origem = AbaOrigem(self.estado)
        self.aba_analise = AbaAnalise(self.estado)
        self.aba_compat = AbaCompatibilidade(self.estado)
        self.aba_migracao = AbaMigracao(self.estado, self.settings)
        self.aba_resultado = AbaResultado()

        self.tabs.addTab(self.aba_origem, "1 · Origem")
        self.tabs.addTab(self.aba_analise, "2 · Análise")
        self.tabs.addTab(self.aba_compat, "3 · Compatibilidade")
        self.tabs.addTab(self.aba_migracao, "4 · Migração")
        self.tabs.addTab(self.aba_resultado, "5 · Resultado")

        for i in (ABA_ANALISE, ABA_COMPAT, ABA_MIGRACAO, ABA_RESULTADO):
            self.tabs.setTabEnabled(i, False)

        self.aba_origem.pedir_analise.connect(self._rodar_analise)
        self.aba_analise.avancar.connect(lambda: self._ir_para(ABA_COMPAT))
        self.aba_compat.avancar.connect(lambda: self._ir_para(ABA_MIGRACAO))
        self.aba_migracao.iniciou.connect(self._migracao_iniciou)
        self.aba_migracao.concluido.connect(self._migracao_concluida)
        self.tabs.currentChanged.connect(self._troca_aba)

        self._worker_analise = None

    # ------------------------------------------------------------------ #
    def _ir_para(self, indice: int) -> None:
        self.tabs.setTabEnabled(indice, True)
        self.tabs.setCurrentIndex(indice)

    def _troca_aba(self, indice: int) -> None:
        if indice == ABA_MIGRACAO:
            self.aba_migracao.ao_entrar()

    # ---- análise ----------------------------------------------------- #
    def _rodar_analise(self) -> None:
        e = self.estado
        if not e.pronto_para_analisar:
            QMessageBox.warning(self, "Faltam dados", "Selecione o banco e as instalações do Firebird.")
            return
        from ui.trabalhadores import TrabalhadorAnalise

        self._ir_para(ABA_ANALISE)
        self.aba_analise.mostrar_carregando()
        self.aba_origem.btn_analisar.setEnabled(False)

        self._worker_analise = TrabalhadorAnalise(
            e.inst_origem, e.caminho_origem, e.usuario, e.senha
        )
        self._worker_analise.concluido.connect(self._analise_ok)
        self._worker_analise.falhou.connect(self._analise_falhou)
        self._worker_analise.start()

    def _analise_ok(self, resultado) -> None:
        self.aba_origem.btn_analisar.setEnabled(True)
        self.estado.analise = resultado
        self.aba_analise.preencher(resultado)
        if not resultado.ok:
            return
        # Já calcula os avisos de compatibilidade para a aba 3.
        self.estado.avisos = avaliar(resultado)
        self.aba_compat.preencher(self.estado.avisos)
        self.tabs.setTabEnabled(ABA_COMPAT, True)
        if existe_bloqueio(self.estado.avisos):
            self.tabs.setTabEnabled(ABA_MIGRACAO, False)

    def _analise_falhou(self, mensagem: str) -> None:
        self.aba_origem.btn_analisar.setEnabled(True)
        self.aba_analise.mostrar_erro(mensagem)

    # ---- migração -------------------------------------------------- #
    def _migracao_iniciou(self) -> None:
        # Trava a navegação enquanto migra.
        for i in (ABA_ORIGEM, ABA_ANALISE, ABA_COMPAT):
            self.tabs.setTabEnabled(i, False)

    def _migracao_concluida(self, rel) -> None:
        for i in (ABA_ORIGEM, ABA_ANALISE, ABA_COMPAT):
            self.tabs.setTabEnabled(i, True)
        pasta = self.aba_migracao.sel_pasta.texto()
        nome_base = Path(self.estado.caminho_origem).stem or "migracao"
        self.aba_resultado.mostrar(rel, pasta, nome_base)
        self._ir_para(ABA_RESULTADO)

    # ---- ciclo de vida ------------------------------------------------ #
    def closeEvent(self, event) -> None:  # noqa: N802 (Qt)
        w = getattr(self.aba_migracao, "_worker", None)
        if w and w.isRunning():
            resp = QMessageBox.question(
                self,
                "Migração em andamento",
                "Uma migração está rodando. Cancelar e sair?",
                QMessageBox.Yes | QMessageBox.No,
                QMessageBox.No,
            )
            if resp != QMessageBox.Yes:
                event.ignore()
                return
            w.cancelar()
            w.wait(5000)
        self._salvar_preferencias()
        event.accept()

    def _salvar_preferencias(self) -> None:
        s = self.settings
        s.usuario_padrao = self.estado.usuario or "SYSDBA"
        if self.estado.caminho_origem:
            s.pasta_padrao_origem = str(Path(self.estado.caminho_origem).parent)
        if self.aba_migracao.sel_pasta.texto():
            s.pasta_padrao_destino = self.aba_migracao.sel_pasta.texto()
        s.page_size_padrao = self.aba_migracao.cmb_pagesize.currentText()
        s.validacao_completa_padrao = self.aba_migracao.chk_validacao.isChecked()
        s.trabalhar_sobre_copia_padrao = self.aba_migracao.chk_copia.isChecked()
        try:
            config.salvar_configuracoes(s)
        except OSError:
            pass
