"""
ui/aba_analise.py
-----------------
Aba 2: mostra o retrato do banco de origem produzido por core/analise.py.
"""
from __future__ import annotations

from PySide6.QtCore import Signal
from PySide6.QtWidgets import (
    QFormLayout,
    QGroupBox,
    QHBoxLayout,
    QLabel,
    QPushButton,
    QVBoxLayout,
    QWidget,
)

from core.espaco import formatar_bytes
from core.modelos import ResultadoAnalise
from ui.estado import Estado


class AbaAnalise(QWidget):
    avancar = Signal()

    def __init__(self, estado: Estado) -> None:
        super().__init__()
        self.estado = estado
        lay = QVBoxLayout(self)
        lay.setContentsMargins(28, 24, 28, 20)
        lay.setSpacing(14)

        titulo = QLabel("Análise do banco de origem")
        titulo.setObjectName("titulo")
        lay.addWidget(titulo)

        self.lbl_status = QLabel("Rode a análise na aba anterior.")
        self.lbl_status.setWordWrap(True)
        lay.addWidget(self.lbl_status)

        cols = QHBoxLayout()
        gb_cab = QGroupBox("Cabeçalho / formato")
        self.form_cab = QFormLayout(gb_cab)
        gb_obj = QGroupBox("Objetos")
        self.form_obj = QFormLayout(gb_obj)
        cols.addWidget(gb_cab)
        cols.addWidget(gb_obj)
        lay.addLayout(cols)

        self.lbl_observacoes = QLabel()
        self.lbl_observacoes.setWordWrap(True)
        self.lbl_observacoes.setStyleSheet("color: #9a6700;")
        lay.addWidget(self.lbl_observacoes)

        lay.addStretch(1)
        rodape = QHBoxLayout()
        rodape.addStretch(1)
        self.btn_avancar = QPushButton("Ver compatibilidade  →")
        self.btn_avancar.setObjectName("primario")
        self.btn_avancar.setEnabled(False)
        self.btn_avancar.clicked.connect(self.avancar.emit)
        rodape.addWidget(self.btn_avancar)
        lay.addLayout(rodape)

    def mostrar_carregando(self) -> None:
        self.lbl_status.setText("Analisando o banco de origem… (gstat + isql)")
        self.btn_avancar.setEnabled(False)

    def mostrar_erro(self, mensagem: str) -> None:
        self.lbl_status.setText(f"❌ A análise falhou: {mensagem}")
        self.btn_avancar.setEnabled(False)

    def preencher(self, r: ResultadoAnalise) -> None:
        _limpar_form(self.form_cab)
        _limpar_form(self.form_obj)

        if not r.ok:
            self.mostrar_erro("; ".join(r.erros))
            return

        self.lbl_status.setText(
            f"Banco lido. Família provável pela ODS: <b>{r.familia_provavel}</b>."
        )
        add = self.form_cab.addRow
        add("Arquivo:", QLabel(r.caminho))
        add("Tamanho:", QLabel(formatar_bytes(r.tamanho_bytes)))
        add("ODS:", QLabel(r.ods))
        add("Page size:", QLabel(str(r.page_size)))
        add("SQL Dialect:", QLabel(str(r.sql_dialect or "?")))
        add("Charset padrão:", QLabel(r.charset_padrao))
        add("Forced writes:", QLabel(r.forced_writes))
        add("Sweep interval:", QLabel(str(r.sweep_interval)))
        if r.atributos:
            add("Atributos:", QLabel(", ".join(r.atributos)))

        addo = self.form_obj.addRow
        addo("Tabelas:", QLabel(str(r.qtd_tabelas)))
        addo("Views:", QLabel(str(r.qtd_views)))
        addo("Procedures:", QLabel(str(r.qtd_procedures)))
        addo("Triggers:", QLabel(str(r.qtd_triggers)))
        addo("Generators:", QLabel(str(r.qtd_generators)))
        addo("Domains:", QLabel(str(r.qtd_domains)))
        addo("Índices:", QLabel(str(r.qtd_indices)))
        addo("Foreign keys:", QLabel(str(r.qtd_foreign_keys)))
        addo("UDFs:", QLabel(str(len(r.udfs))))
        addo("Tabelas externas:", QLabel(str(len(r.tabelas_externas))))

        self.lbl_observacoes.setText("\n".join(r.observacoes))
        self.btn_avancar.setEnabled(True)


def _limpar_form(form: QFormLayout) -> None:
    while form.rowCount():
        form.removeRow(0)
