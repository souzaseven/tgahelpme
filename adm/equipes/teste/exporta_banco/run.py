"""
Ponto de entrada do Inspetor de Banco Firebird.

Uso:
    python run.py

Abre automaticamente em http://127.0.0.1:8000 (ou na próxima porta livre,
se a 8000 já estiver em uso por outra instância da ferramenta).
"""
import socket
import sys
import threading
import webbrowser

import uvicorn

HOST = "127.0.0.1"
PORTA_PREFERIDA = 8000
MAX_TENTATIVAS = 10


def _porta_livre(porta: int) -> bool:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.settimeout(0.5)
        return s.connect_ex((HOST, porta)) != 0


def _escolher_porta() -> int:
    porta = PORTA_PREFERIDA
    for _ in range(MAX_TENTATIVAS):
        if _porta_livre(porta):
            return porta
        porta += 1

    print(
        f"Não foi possível encontrar uma porta livre entre {PORTA_PREFERIDA} e {porta - 1}.\n"
        "Feche outras instâncias do Inspetor de Banco Firebird e tente novamente."
    )
    sys.exit(1)


def _abrir_navegador(url: str) -> None:
    webbrowser.open(url)


if __name__ == "__main__":
    porta = _escolher_porta()
    url = f"http://{HOST}:{porta}"

    if porta != PORTA_PREFERIDA:
        print(
            f"⚠️  A porta {PORTA_PREFERIDA} já está em uso (provavelmente outra "
            f"instância desta ferramenta) — usando a porta {porta} nesta execução."
        )
    print(f"Inspetor de Banco Firebird disponível em {url}")

    threading.Timer(1.2, _abrir_navegador, args=(url,)).start()
    uvicorn.run("backend.main:app", host=HOST, port=porta, reload=False)
