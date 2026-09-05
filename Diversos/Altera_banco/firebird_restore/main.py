"""
main.py
-------
Ponto de entrada do Restaurador Firebird.

Uso:
    python main.py

Depois de empacotado com PyInstaller (fase futura), o usuário final apenas
dá duplo clique no .exe — não precisa abrir terminal nenhum.
"""
import sys
import tkinter.messagebox as messagebox

import logger
from ui.main_window import rodar


def main() -> None:
    try:
        logger.limpar_logs_antigos()
    except Exception:
        pass  # limpeza de log é conveniência, nunca deve impedir o programa de abrir
    try:
        rodar()
    except Exception as exc:  # último recurso: nunca fechar sem explicar o que houve
        logger.erro(f"Erro fatal não tratado: {exc}")
        try:
            messagebox.showerror("Erro inesperado", f"Ocorreu um erro inesperado e o programa será fechado:\n\n{exc}")
        except Exception:
            print(f"Erro inesperado: {exc}", file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
