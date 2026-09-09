"""
main.py
-------
Ponto de entrada do Migrador Firebird. Cria a QApplication e abre a janela
principal.

    cd migrador
    python main.py
"""
from __future__ import annotations

import sys

from PySide6.QtGui import QColor, QPalette
from PySide6.QtWidgets import QApplication

from ui.janela_principal import JanelaPrincipal
from version import VERSAO


def _aplicar_tema_claro(app: QApplication) -> None:
    """Fixa um tema claro consistente (estilo Fusion + paleta explícita), para
    a aparência não depender do tema claro/escuro do Windows — sem isto, no
    modo escuro o texto some, as bordas ficam invisíveis e os campos viram
    blocos brancos."""
    app.setStyle("Fusion")
    pal = QPalette()
    branco = QColor("#ffffff")
    fundo = QColor("#f4f6f8")
    texto = QColor("#1f2328")
    pal.setColor(QPalette.Window, fundo)
    pal.setColor(QPalette.WindowText, texto)
    pal.setColor(QPalette.Base, branco)
    pal.setColor(QPalette.AlternateBase, QColor("#eef1f4"))
    pal.setColor(QPalette.Text, texto)
    pal.setColor(QPalette.Button, QColor("#eef1f4"))
    pal.setColor(QPalette.ButtonText, texto)
    pal.setColor(QPalette.ToolTipBase, branco)
    pal.setColor(QPalette.ToolTipText, texto)
    pal.setColor(QPalette.Highlight, QColor("#1f6feb"))
    pal.setColor(QPalette.HighlightedText, branco)
    pal.setColor(QPalette.PlaceholderText, QColor("#8b949e"))
    pal.setColor(QPalette.Disabled, QPalette.Text, QColor("#9aa4ae"))
    pal.setColor(QPalette.Disabled, QPalette.ButtonText, QColor("#9aa4ae"))
    pal.setColor(QPalette.Disabled, QPalette.WindowText, QColor("#9aa4ae"))
    app.setPalette(pal)


def main() -> int:
    app = QApplication(sys.argv)
    app.setApplicationName("Migrador Firebird")
    app.setApplicationVersion(VERSAO)
    _aplicar_tema_claro(app)
    janela = JanelaPrincipal()
    janela.show()
    return app.exec()


if __name__ == "__main__":
    raise SystemExit(main())
