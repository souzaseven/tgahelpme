"""
ui/aba_resultado.py
-------------------
Aba 5: mostra o desfecho (CONCLUÍDA / CONCLUÍDA COM AVISOS / FALHOU), o texto do
relatório e ações (abrir pasta, ver log, salvar relatório).
"""
from __future__ import annotations

import os
import subprocess
from pathlib import Path

from PySide6.QtWidgets import (
    QFileDialog,
    QHBoxLayout,
    QLabel,
    QMessageBox,
    QPlainTextEdit,
    QPushButton,
    QVBoxLayout,
    QWidget,
)

import logger
from core.relatorio import CONCLUIDA, CONCLUIDA_COM_AVISOS, Relatorio


class AbaResultado(QWidget):
    def __init__(self) -> None:
        super().__init__()
        self._relatorio: Relatorio | None = None
        self._pasta_destino = ""
        self._nome_base = "migracao"

        lay = QVBoxLayout(self)
        lay.setContentsMargins(28, 24, 28, 20)
        lay.setSpacing(14)
        self.lbl_resultado = QLabel("Aguardando a migração…")
        self.lbl_resultado.setObjectName("resultadoWarn")
        lay.addWidget(self.lbl_resultado)

        self.texto = QPlainTextEdit()
        self.texto.setReadOnly(True)
        self.texto.setStyleSheet("background:#0d1117; color:#c9d1d9;")
        lay.addWidget(self.texto, 1)

        linha = QHBoxLayout()
        self.btn_pasta = QPushButton("📂 Abrir pasta do banco migrado")
        self.btn_pasta.clicked.connect(self._abrir_pasta)
        self.btn_log = QPushButton("📄 Ver log")
        self.btn_log.clicked.connect(self._abrir_log)
        self.btn_salvar = QPushButton("💾 Salvar relatório…")
        self.btn_salvar.clicked.connect(self._salvar)
        for b in (self.btn_pasta, self.btn_log, self.btn_salvar):
            b.setEnabled(False)
            linha.addWidget(b)
        linha.addStretch(1)
        lay.addLayout(linha)

    def mostrar(self, rel: Relatorio, pasta_destino: str, nome_base: str) -> None:
        self._relatorio = rel
        self._pasta_destino = pasta_destino
        self._nome_base = nome_base or "migracao"
        self.texto.setPlainText(rel.texto)

        if rel.resultado == CONCLUIDA:
            self.lbl_resultado.setObjectName("resultadoOK")
            self.lbl_resultado.setText("✓ MIGRAÇÃO CONCLUÍDA")
        elif rel.resultado == CONCLUIDA_COM_AVISOS:
            self.lbl_resultado.setObjectName("resultadoWarn")
            self.lbl_resultado.setText("⚠ MIGRAÇÃO CONCLUÍDA COM AVISOS")
        else:
            self.lbl_resultado.setObjectName("resultadoFail")
            self.lbl_resultado.setText("✗ MIGRAÇÃO FALHOU")
        self.lbl_resultado.style().unpolish(self.lbl_resultado)
        self.lbl_resultado.style().polish(self.lbl_resultado)

        tem_pasta = bool(pasta_destino and Path(pasta_destino).is_dir())
        self.btn_pasta.setEnabled(tem_pasta)
        self.btn_salvar.setEnabled(True)
        self.btn_log.setEnabled(logger.arquivo_log_atual() is not None)

        # Salva uma cópia do relatório automaticamente ao lado do banco migrado.
        if tem_pasta:
            try:
                caminho = rel.salvar(pasta_destino, self._nome_base)
                logger.info(f"Relatório salvo em {caminho}")
            except OSError as e:
                logger.aviso(f"Não foi possível salvar o relatório automaticamente: {e}")

    def _abrir_pasta(self) -> None:
        if self._pasta_destino and Path(self._pasta_destino).is_dir():
            os.startfile(self._pasta_destino)  # noqa: S606 (Windows Explorer)

    def _abrir_log(self) -> None:
        arq = logger.arquivo_log_atual()
        if arq and Path(arq).is_file():
            os.startfile(str(arq))

    def _salvar(self) -> None:
        if not self._relatorio:
            return
        sugestao = str(Path(self._pasta_destino or ".") / f"{self._nome_base}_relatorio.txt")
        caminho, _ = QFileDialog.getSaveFileName(
            self, "Salvar relatório", sugestao, "Texto (*.txt)"
        )
        if not caminho:
            return
        try:
            Path(caminho).write_text(self._relatorio.texto, encoding="utf-8")
            QMessageBox.information(self, "Relatório salvo", caminho)
        except OSError as e:
            QMessageBox.warning(self, "Erro ao salvar", str(e))
