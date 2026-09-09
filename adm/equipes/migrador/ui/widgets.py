"""
ui/widgets.py
-------------
Widgets auxiliares reutilizados pelas abas: linha "rótulo + campo + botão de
seleção de arquivo" e um cartão de aviso de compatibilidade.
"""
from __future__ import annotations

from PySide6.QtCore import Qt, Signal
from PySide6.QtWidgets import (
    QComboBox,
    QFileDialog,
    QFormLayout,
    QFrame,
    QHBoxLayout,
    QLabel,
    QLineEdit,
    QPushButton,
    QSizePolicy,
    QVBoxLayout,
    QWidget,
)

from core.modelos import Aviso

# Largura máxima do bloco de conteúdo das abas, para os campos não esticarem
# de ponta a ponta numa janela larga.
FAIXA_MAX = 900


def form_padrao() -> QFormLayout:
    """QFormLayout com espaçamento e alinhamento consistentes entre as abas."""
    f = QFormLayout()
    f.setLabelAlignment(Qt.AlignRight | Qt.AlignVCenter)
    f.setFormAlignment(Qt.AlignTop)
    f.setHorizontalSpacing(14)
    f.setVerticalSpacing(12)
    f.setFieldGrowthPolicy(QFormLayout.AllNonFixedFieldsGrow)
    return f


def faixa_central(conteudo: QWidget) -> QHBoxLayout:
    """Envolve `conteudo` numa faixa horizontal centrada com largura máxima."""
    linha = QHBoxLayout()
    linha.setContentsMargins(0, 0, 0, 0)
    linha.addStretch(1)
    conteudo.setMaximumWidth(FAIXA_MAX)
    linha.addWidget(conteudo, 10)
    linha.addStretch(1)
    return linha


def combo_instalacoes() -> QComboBox:
    """QComboBox que não exige a largura inteira do texto mais longo dos itens
    (os rótulos de instalação do Firebird são compridos)."""
    cmb = QComboBox()
    cmb.setSizeAdjustPolicy(QComboBox.AdjustToMinimumContentsLengthWithIcon)
    cmb.setMinimumContentsLength(12)
    cmb.setSizePolicy(QSizePolicy.Expanding, QSizePolicy.Fixed)
    return cmb


class SeletorArquivo(QWidget):
    """Campo de texto + botão "..." que abre um diálogo de arquivo ou pasta."""

    mudou = Signal(str)

    def __init__(
        self,
        placeholder: str = "",
        *,
        pasta: bool = False,
        filtro: str = "Todos os arquivos (*.*)",
        titulo_dialogo: str = "Selecionar",
    ) -> None:
        super().__init__()
        self._pasta = pasta
        self._filtro = filtro
        self._titulo = titulo_dialogo

        lay = QHBoxLayout(self)
        lay.setContentsMargins(0, 0, 0, 0)
        self.campo = QLineEdit()
        self.campo.setPlaceholderText(placeholder)
        self.campo.textChanged.connect(self.mudou.emit)
        botao = QPushButton("Procurar…")
        botao.clicked.connect(self._escolher)
        lay.addWidget(self.campo, 1)
        lay.addWidget(botao)

    def _escolher(self) -> None:
        inicio = self.campo.text().strip()
        if self._pasta:
            caminho = QFileDialog.getExistingDirectory(self, self._titulo, inicio)
        else:
            caminho, _ = QFileDialog.getOpenFileName(self, self._titulo, inicio, self._filtro)
        if caminho:
            self.campo.setText(caminho)

    def texto(self) -> str:
        return self.campo.text().strip()

    def definir(self, valor: str) -> None:
        self.campo.setText(valor or "")


class CartaoAviso(QFrame):
    """Exibe um core.modelos.Aviso com cor conforme o nível."""

    def __init__(self, aviso: Aviso) -> None:
        super().__init__()
        nome_objeto = {
            "info": "avisoInfo",
            "atencao": "avisoAtencao",
            "bloqueio": "avisoBloqueio",
        }.get(aviso.nivel, "avisoInfo")
        self.setObjectName(nome_objeto)

        lay = QVBoxLayout(self)
        rotulo_nivel = {"info": "INFORMATIVO", "atencao": "ATENÇÃO", "bloqueio": "BLOQUEIO"}
        titulo = QLabel(f"[{rotulo_nivel.get(aviso.nivel, '')}] {aviso.categoria} — {aviso.titulo}")
        titulo.setStyleSheet("font-weight: 700;")
        titulo.setWordWrap(True)
        lay.addWidget(titulo)

        detalhe = QLabel(aviso.detalhe)
        detalhe.setWordWrap(True)
        lay.addWidget(detalhe)

        if aviso.itens:
            amostra = aviso.itens[:20]
            resto = len(aviso.itens) - len(amostra)
            texto = "\n".join(f"  • {i}" for i in amostra)
            if resto > 0:
                texto += f"\n  … e mais {resto}"
            itens = QLabel(texto)
            itens.setTextInteractionFlags(Qt.TextSelectableByMouse)
            itens.setStyleSheet("color: #444; font-family: Consolas, monospace;")
            lay.addWidget(itens)
