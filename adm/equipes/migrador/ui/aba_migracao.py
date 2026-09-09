"""
ui/aba_migracao.py
------------------
Aba 4: define destino e opções, checa espaço em disco, dispara a migração e
mostra o log ao vivo com barra de progresso.
"""
from __future__ import annotations

from datetime import datetime
from pathlib import Path

from PySide6.QtCore import Signal
from PySide6.QtWidgets import (
    QCheckBox,
    QComboBox,
    QGroupBox,
    QHBoxLayout,
    QLabel,
    QLineEdit,
    QMessageBox,
    QPlainTextEdit,
    QProgressBar,
    QPushButton,
    QSpinBox,
    QVBoxLayout,
    QWidget,
)

import config
from core import espaco
from core.migracao import OpcoesMigracao, nome_backup, nome_destino
from ui.estado import Estado
from ui.trabalhadores import PacoteMigracao, TrabalhadorMigracao
from ui.widgets import SeletorArquivo, form_padrao


class AbaMigracao(QWidget):
    concluido = Signal(object)  # relatorio.Relatorio
    iniciou = Signal()

    def __init__(self, estado: Estado, settings: config.Settings) -> None:
        super().__init__()
        self.estado = estado
        self.settings = settings
        self._worker: TrabalhadorMigracao | None = None

        lay = QVBoxLayout(self)
        lay.setContentsMargins(28, 24, 28, 20)
        lay.setSpacing(14)
        titulo = QLabel("Migração")
        titulo.setObjectName("titulo")
        lay.addWidget(titulo)

        gb_dest = QGroupBox("Destino")
        form = form_padrao()
        gb_dest.setLayout(form)
        self.sel_pasta = SeletorArquivo(
            "Pasta onde gravar o .fbk e o .fdb migrado…",
            pasta=True,
            titulo_dialogo="Pasta de destino",
        )
        self.sel_pasta.mudou.connect(self._on_pasta_mudou)
        form.addRow("Pasta:", self.sel_pasta)
        self.ed_fbk = QLineEdit()
        self.ed_destino = QLineEdit()
        form.addRow("Nome do backup (.fbk):", self.ed_fbk)
        form.addRow("Nome do banco migrado (.fdb):", self.ed_destino)
        self.lbl_espaco = QLabel()
        self.lbl_espaco.setWordWrap(True)
        form.addRow("Espaço em disco:", self.lbl_espaco)
        lay.addWidget(gb_dest)

        gb_opc = QGroupBox("Opções de restauração (avançado)")
        fopc = form_padrao()
        gb_opc.setLayout(fopc)
        self.cmb_pagesize = QComboBox()
        self.cmb_pagesize.addItems(
            ["(preservar do backup)", "4096", "8192", "16384", "32768"]
        )
        self.cmb_pagesize.setCurrentText(self.settings.page_size_padrao)
        fopc.addRow("Page size:", self.cmb_pagesize)
        self.ed_fixfss = QLineEdit()
        self.ed_fixfss.setPlaceholderText("ex.: WIN1252 — só se a análise apontou charset NONE/ASCII")
        fopc.addRow("Corrigir charset (-fix_fss):", self.ed_fixfss)
        self.spin_par = QSpinBox()
        self.spin_par.setRange(1, 16)
        self.spin_par.setValue(1)
        fopc.addRow("Workers paralelos (gbak 5):", self.spin_par)
        self.chk_copia = QCheckBox("Fazer backup sobre uma cópia da origem (não toca no arquivo real)")
        self.chk_copia.setChecked(self.settings.trabalhar_sobre_copia_padrao)
        self.chk_um_por_vez = QCheckBox("Restaurar tabela por tabela (-o) — mais lento, ajuda com backup problemático")
        self.chk_validacao = QCheckBox("Validação completa (gfix -v -full) após restaurar")
        self.chk_validacao.setChecked(self.settings.validacao_completa_padrao)
        fopc.addRow(self.chk_copia)
        fopc.addRow(self.chk_um_por_vez)
        fopc.addRow(self.chk_validacao)
        lay.addWidget(gb_opc)

        linha_acao = QHBoxLayout()
        self.btn_migrar = QPushButton("Migrar banco")
        self.btn_migrar.setObjectName("primario")
        self.btn_migrar.clicked.connect(self._iniciar)
        self.btn_cancelar = QPushButton("Cancelar")
        self.btn_cancelar.setEnabled(False)
        self.btn_cancelar.clicked.connect(self._cancelar)
        linha_acao.addWidget(self.btn_migrar)
        linha_acao.addWidget(self.btn_cancelar)
        linha_acao.addStretch(1)
        lay.addLayout(linha_acao)

        self.lbl_fase = QLabel("Aguardando…")
        lay.addWidget(self.lbl_fase)
        self.barra = QProgressBar()
        self.barra.setRange(0, 100)
        lay.addWidget(self.barra)

        self.log = QPlainTextEdit()
        self.log.setReadOnly(True)
        self.log.setMaximumBlockCount(5000)
        lay.addWidget(self.log, 1)

    # ------------------------------------------------------------------ #
    def ao_entrar(self) -> None:
        """Chamado toda vez que a aba é exibida: recalcula nomes e espaço."""
        if self.estado.pasta_destino and not self.sel_pasta.texto():
            self.sel_pasta.definir(self.estado.pasta_destino)
        origem = self.estado.caminho_origem
        if origem:
            quando = datetime.now()
            if not self.ed_fbk.text().strip():
                self.ed_fbk.setText(nome_backup(origem, quando))
            if not self.ed_destino.text().strip():
                self.ed_destino.setText(nome_destino(origem, quando))
        self._checar_espaco()

    def _on_pasta_mudou(self, texto: str) -> None:
        self.estado.pasta_destino = texto.strip()
        self._checar_espaco()

    def _checar_espaco(self) -> None:
        origem = self.estado.caminho_origem
        pasta = self.sel_pasta.texto() or self.estado.pasta_destino
        if not (origem and pasta and Path(origem).is_file()):
            self.lbl_espaco.setText("—")
            return
        try:
            est = espaco.checar(
                origem, pasta,
                fator_fbk=self.settings.fator_fbk_por_fdb,
                fator_fdb=self.settings.fator_fdb_restaurado_por_fdb,
                margem=self.settings.margem_seguranca_espaco,
            )
        except OSError as e:
            self.lbl_espaco.setText(f"Não foi possível checar: {e}")
            return
        fb = espaco.formatar_bytes
        if est.suficiente:
            self.lbl_espaco.setText(
                f"✓ Livre: {fb(est.disponivel_no_destino)}  |  "
                f"necessário (estimado, com margem): {fb(est.necessario_com_margem)}"
            )
            self.lbl_espaco.setStyleSheet("color: #1a7f37;")
        else:
            self.lbl_espaco.setText(
                f"⛔ Espaço insuficiente. Livre: {fb(est.disponivel_no_destino)}  |  "
                f"necessário: {fb(est.necessario_com_margem)}  |  faltam ~{fb(est.faltando)}"
            )
            self.lbl_espaco.setStyleSheet("color: #b42318;")

    # ------------------------------------------------------------------ #
    def _montar_opcoes(self) -> OpcoesMigracao:
        return OpcoesMigracao(
            page_size=self.cmb_pagesize.currentText(),
            fix_fss_charset=self.ed_fixfss.text().strip(),
            par_workers=self.spin_par.value(),
            trabalhar_sobre_copia=self.chk_copia.isChecked(),
            restore_um_por_vez=self.chk_um_por_vez.isChecked(),
        )

    def _validar_antes(self) -> str:
        e = self.estado
        if not (e.inst_origem and e.inst_destino):
            return "Instalações de origem/destino não definidas."
        if not (e.caminho_origem and Path(e.caminho_origem).is_file()):
            return "Banco de origem inválido."
        if not e.analise or not e.analise.ok:
            return "Rode a análise antes de migrar."
        pasta = self.sel_pasta.texto()
        if not pasta or not Path(pasta).is_dir():
            return "Escolha uma pasta de destino válida."
        if not self.ed_fbk.text().strip() or not self.ed_destino.text().strip():
            return "Informe os nomes do .fbk e do .fdb de destino."
        destino = Path(pasta) / self.ed_destino.text().strip()
        fbk = Path(pasta) / self.ed_fbk.text().strip()
        if destino.exists():
            return f"O destino já existe:\n{destino}\nEscolha outro nome."
        if fbk.exists():
            return f"O backup já existe:\n{fbk}\nEscolha outro nome."
        try:
            est = espaco.checar(
                e.caminho_origem, pasta,
                fator_fbk=self.settings.fator_fbk_por_fdb,
                fator_fdb=self.settings.fator_fdb_restaurado_por_fdb,
                margem=self.settings.margem_seguranca_espaco,
            )
            if not est.suficiente:
                return "Espaço em disco insuficiente (veja o aviso acima)."
        except OSError:
            pass
        return ""

    def _iniciar(self) -> None:
        erro = self._validar_antes()
        if erro:
            QMessageBox.warning(self, "Não é possível migrar ainda", erro)
            return

        confirm = QMessageBox.question(
            self,
            "Confirmar migração",
            "A migração vai:\n"
            f"• fazer backup de:\n   {self.estado.caminho_origem}\n"
            f"• gerar:\n   {self.ed_fbk.text().strip()}\n   {self.ed_destino.text().strip()}\n\n"
            "O banco original NÃO é alterado. Continuar?",
            QMessageBox.Yes | QMessageBox.No,
            QMessageBox.No,
        )
        if confirm != QMessageBox.Yes:
            return

        pasta = Path(self.sel_pasta.texto())
        pacote = PacoteMigracao(
            inst_origem=self.estado.inst_origem,
            inst_destino=self.estado.inst_destino,
            origem_fdb=self.estado.caminho_origem,
            destino_fdb=str(pasta / self.ed_destino.text().strip()),
            fbk_path=str(pasta / self.ed_fbk.text().strip()),
            usuario=self.estado.usuario,
            senha=self.estado.senha,
            opcoes=self._montar_opcoes(),
            analise=self.estado.analise,
            validacao_completa=self.chk_validacao.isChecked(),
            pasta_relatorio=str(pasta),
        )

        self.log.clear()
        self.barra.setValue(0)
        self._set_rodando(True)
        self.iniciou.emit()

        self._worker = TrabalhadorMigracao(pacote)
        self._worker.linha.connect(self._append_log)
        self._worker.fase.connect(self.lbl_fase.setText)
        self._worker.progresso.connect(self.barra.setValue)
        self._worker.concluido.connect(self._on_concluido)
        self._worker.start()

    def _cancelar(self) -> None:
        if self._worker and self._worker.isRunning():
            self._worker.cancelar()
            self.btn_cancelar.setEnabled(False)

    def _on_concluido(self, rel) -> None:
        self._set_rodando(False)
        self.lbl_fase.setText(f"Resultado: {rel.resultado}")
        self.concluido.emit(rel)

    def _set_rodando(self, rodando: bool) -> None:
        self.btn_migrar.setEnabled(not rodando)
        self.btn_cancelar.setEnabled(rodando)

    def _append_log(self, texto: str) -> None:
        self.log.appendPlainText(texto)
        self.log.verticalScrollBar().setValue(self.log.verticalScrollBar().maximum())
