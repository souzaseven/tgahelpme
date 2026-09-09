"""
ui/estilos.py
-------------
Folha de estilo (QSS) do Migrador Firebird. Usada junto com o estilo Fusion e
a paleta clara fixada em main.py — assim a aparência é a mesma no Windows
claro ou escuro.
"""

AZUL = "#1f6feb"
AZUL_ESCURO = "#1a5fd0"
VERDE = "#1a7f37"
AMARELO = "#9a6700"
VERMELHO = "#b42318"
BORDA = "#d0d7de"
FUNDO = "#f4f6f8"
FUNDO_SUAVE = "#f6f8fa"
TEXTO = "#1f2328"
TEXTO_FRACO = "#57606a"

QSS = f"""
* {{
    font-size: 13px;
    color: {TEXTO};
}}
QMainWindow {{
    background: {FUNDO};
}}
QTabWidget::pane {{
    border: 1px solid {BORDA};
    border-radius: 8px;
    background: white;
    top: -1px;
}}
QTabBar {{
    qproperty-drawBase: 0;
}}
QTabBar::tab {{
    padding: 9px 20px;
    margin-right: 4px;
    border: 1px solid {BORDA};
    border-top-left-radius: 7px;
    border-top-right-radius: 7px;
    background: {FUNDO_SUAVE};
    color: {TEXTO_FRACO};
}}
QTabBar::tab:selected {{
    background: white;
    color: {AZUL};
    font-weight: 600;
    border-bottom-color: white;
}}
QTabBar::tab:disabled {{
    color: #b6bec6;
    background: {FUNDO};
}}

QLabel#titulo {{
    font-size: 17px;
    font-weight: 700;
    color: {TEXTO};
}}
QLabel#subtitulo {{
    color: {TEXTO_FRACO};
}}
QLabel#dica {{
    color: {TEXTO_FRACO};
    font-size: 12px;
}}

QGroupBox {{
    border: 1px solid {BORDA};
    border-radius: 8px;
    margin-top: 16px;
    padding: 16px 16px 14px 16px;
    background: white;
}}
QGroupBox::title {{
    subcontrol-origin: margin;
    subcontrol-position: top left;
    left: 12px;
    padding: 2px 8px;
    color: {TEXTO_FRACO};
    font-weight: 600;
    background: white;
}}

QLineEdit, QComboBox, QSpinBox {{
    min-height: 22px;
    padding: 6px 10px;
    border: 1px solid {BORDA};
    border-radius: 6px;
    background: white;
    selection-background-color: {AZUL};
    selection-color: white;
}}
QLineEdit:focus, QComboBox:focus, QSpinBox:focus {{
    border: 1px solid {AZUL};
}}
QLineEdit:disabled, QComboBox:disabled {{
    background: {FUNDO_SUAVE};
    color: #9aa4ae;
}}
QComboBox::drop-down {{
    width: 22px;
    border-left: 1px solid {BORDA};
}}
QComboBox QAbstractItemView {{
    border: 1px solid {BORDA};
    background: white;
    selection-background-color: {AZUL};
    selection-color: white;
}}

QPushButton {{
    padding: 7px 16px;
    border: 1px solid {BORDA};
    border-radius: 6px;
    background: white;
    color: {TEXTO};
}}
QPushButton:hover {{
    background: {FUNDO_SUAVE};
    border-color: #b8c0c8;
}}
QPushButton:pressed {{
    background: #eaeef2;
}}
QPushButton:disabled {{
    color: #a8b0b8;
    background: {FUNDO_SUAVE};
    border-color: #e2e6ea;
}}
QPushButton#primario {{
    background: {AZUL};
    color: white;
    border: 1px solid {AZUL};
    font-weight: 600;
    padding: 8px 22px;
}}
QPushButton#primario:hover {{
    background: {AZUL_ESCURO};
    border-color: {AZUL_ESCURO};
}}
QPushButton#primario:disabled {{
    background: #a9c7f5;
    border-color: #a9c7f5;
    color: #f0f5ff;
}}
QPushButton#perigo {{
    color: {VERMELHO};
    border-color: #e6b8b2;
}}
QPushButton#perigo:hover {{
    background: #fdeceb;
}}

QCheckBox {{
    spacing: 8px;
    padding: 3px 0;
}}
QCheckBox::indicator {{
    width: 16px;
    height: 16px;
    border: 1px solid #b8c0c8;
    border-radius: 4px;
    background: white;
}}
QCheckBox::indicator:checked {{
    background: {AZUL};
    border-color: {AZUL};
    image: none;
}}

QProgressBar {{
    border: 1px solid {BORDA};
    border-radius: 6px;
    text-align: center;
    background: {FUNDO_SUAVE};
    height: 22px;
}}
QProgressBar::chunk {{
    background: {AZUL};
    border-radius: 5px;
}}

QPlainTextEdit, QTextEdit {{
    border: 1px solid {BORDA};
    border-radius: 6px;
    background: #0d1117;
    color: #d5dbe2;
    font-family: Consolas, "Cascadia Mono", monospace;
    font-size: 12px;
    padding: 6px;
}}

QScrollArea {{
    border: none;
    background: transparent;
}}

QLabel#resultadoOK   {{ font-size: 19px; font-weight: 800; color: {VERDE}; }}
QLabel#resultadoWarn {{ font-size: 19px; font-weight: 800; color: {AMARELO}; }}
QLabel#resultadoFail {{ font-size: 19px; font-weight: 800; color: {VERMELHO}; }}

QLabel#avisoTexto {{ color: {AMARELO}; }}

QFrame#avisoInfo     {{ border: 1px solid #cfe0ff; border-left: 4px solid {AZUL};    background: #eef4ff; border-radius: 6px; }}
QFrame#avisoAtencao  {{ border: 1px solid #f2e2b8; border-left: 4px solid {AMARELO}; background: #fff8e6; border-radius: 6px; }}
QFrame#avisoBloqueio {{ border: 1px solid #f0c8c3; border-left: 4px solid {VERMELHO};background: #fdeceb; border-radius: 6px; }}
"""
