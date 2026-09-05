"""
ui/estilos.py
-------------
Paletas de cores usadas na área de acompanhamento em tempo real — o visual
padrão claro e o tema alternativo "hacker" (terminal preto/verde). Separado
de main_window.py só para não misturar "o que a tela mostra" com "como ela é
montada"; nenhuma lógica de widget mora aqui.
"""
from __future__ import annotations

CORES = {
    "INFO": "#1f2937",
    "AVISO": "#b45309",
    "ERRO": "#b91c1c",
    "OK": "#15803d",
}

# Tema alternativo puramente estético para a área de acompanhamento em tempo
# real ("Detalhes") — terminal preto/verde no lugar do fundo claro padrão.
# Não muda nenhum comportamento, só a aparência (fundo, texto e cores por
# nível de log).
CORES_HACKER = {
    "INFO": "#00ff41",
    "AVISO": "#ffee00",
    "ERRO": "#ff3131",
    "OK": "#39ff14",
}
FUNDO_LOG_NORMAL = "white"
FUNDO_LOG_HACKER = "#000000"


def paleta_log(tema_hacker: bool) -> dict[str, str]:
    return CORES_HACKER if tema_hacker else CORES


def fundo_log(tema_hacker: bool) -> str:
    return FUNDO_LOG_HACKER if tema_hacker else FUNDO_LOG_NORMAL
