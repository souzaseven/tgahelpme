"""
ui/aba_compatibilidade.py
-------------------------
Aba 3: lista os avisos de compatibilidade (core/compatibilidade.py). Se houver
qualquer aviso de nível "bloqueio", o botão de avançar fica desabilitado.
"""
from __future__ import annotations

from PySide6.QtCore import Signal
from PySide6.QtWidgets import (
    QHBoxLayout,
    QLabel,
    QPushButton,
    QScrollArea,
    QVBoxLayout,
    QWidget,
)

from core.compatibilidade import existe_bloqueio
from core.modelos import Aviso
from ui.estado import Estado
from ui.widgets import CartaoAviso


class AbaCompatibilidade(QWidget):
    avancar = Signal()

    def __init__(self, estado: Estado) -> None:
        super().__init__()
        self.estado = estado
        lay = QVBoxLayout(self)
        lay.setContentsMargins(28, 24, 28, 20)
        lay.setSpacing(14)

        titulo = QLabel("Compatibilidade  (Firebird 2.5 → 5.0)")
        titulo.setObjectName("titulo")
        lay.addWidget(titulo)

        self.lbl_resumo = QLabel()
        self.lbl_resumo.setWordWrap(True)
        lay.addWidget(self.lbl_resumo)

        area = QScrollArea()
        area.setWidgetResizable(True)
        self._container = QWidget()
        self._vbox = QVBoxLayout(self._container)
        self._vbox.addStretch(1)
        area.setWidget(self._container)
        lay.addWidget(area, 1)

        rodape = QHBoxLayout()
        rodape.addStretch(1)
        self.btn_avancar = QPushButton("Configurar migração  →")
        self.btn_avancar.setObjectName("primario")
        self.btn_avancar.clicked.connect(self.avancar.emit)
        rodape.addWidget(self.btn_avancar)
        lay.addLayout(rodape)

    def preencher(self, avisos: list[Aviso]) -> None:
        # limpa cartões antigos (mantém o stretch no fim)
        while self._vbox.count() > 1:
            item = self._vbox.takeAt(0)
            if item.widget():
                item.widget().deleteLater()

        for aviso in avisos:
            self._vbox.insertWidget(self._vbox.count() - 1, CartaoAviso(aviso))

        bloqueado = existe_bloqueio(avisos)
        self.btn_avancar.setEnabled(not bloqueado)

        n_atencao = sum(1 for a in avisos if a.nivel == "atencao")
        if bloqueado:
            self.lbl_resumo.setText(
                "⛔ <b>Migração bloqueada.</b> Há pelo menos um ponto que impede a "
                "migração segura nesta direção. Resolva o item de bloqueio abaixo "
                "e rode a análise de novo."
            )
        elif n_atencao:
            self.lbl_resumo.setText(
                f"⚠ {n_atencao} ponto(s) de atenção. A migração pode prosseguir, "
                "mas anote os ajustes necessários no Firebird 5.0 / na aplicação."
            )
        elif avisos:
            self.lbl_resumo.setText("Apenas itens informativos. Pode prosseguir.")
        else:
            self.lbl_resumo.setText(
                "✓ Nenhum ponto de atenção encontrado na análise automática. "
                "A confirmação final é o próprio restore do gbak."
            )
