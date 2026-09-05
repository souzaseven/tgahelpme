"""
notificacoes.py
----------------
Notificação nativa do Windows (toast) ao concluir uma restauração — usa só
`powershell.exe` (sempre presente no Windows) e a API WinRT de toasts, sem
nenhuma dependência Python externa. Complementa o som (winsound): útil
quando a janela está minimizada numa restauração longa.

Puramente informativo: se falhar por qualquer motivo (Windows mais antigo,
PowerShell bloqueado por política de grupo, notificações desativadas...), o
programa continua normalmente — só não mostra o aviso. Nunca levanta exceção.
"""
from __future__ import annotations

import base64
import subprocess

_CREATION_FLAGS = subprocess.CREATE_NO_WINDOW if hasattr(subprocess, "CREATE_NO_WINDOW") else 0

# AppID conhecido do próprio Windows PowerShell — usá-lo (em vez de um nome
# arbitrário não registrado) é o que garante que o toast realmente apareça
# na tela em qualquer versão do Windows 10/11, mesmo sem o app estar
# "instalado" como pacote UWP. O toast aparece creditado a "Windows
# PowerShell" no Central de Ações, não ao nome do programa — é uma limitação
# conhecida dessa abordagem sem dependências externas, não um bug.
_APP_ID_TOAST = r"{1AC14E77-02E7-4E5D-B744-2EB1AE5198B7}\WindowsPowerShell\v1.0\powershell.exe"


def _escapar(texto: str) -> str:
    """Deixa o texto seguro tanto dentro de XML quanto dentro de uma string
    delimitada por aspas simples do PowerShell."""
    texto = texto.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")
    return texto.replace("'", "''")


def notificar_windows(titulo: str, mensagem: str) -> None:
    """Dispara uma notificação toast nativa do Windows. Não bloqueia (o
    processo do PowerShell é iniciado e não aguardado) e nunca levanta
    exceção — qualquer falha é silenciosamente ignorada."""
    titulo_seguro = _escapar(titulo)
    mensagem_segura = _escapar(mensagem)
    script = (
        '[Windows.UI.Notifications.ToastNotificationManager, Windows.UI.Notifications, '
        'ContentType = WindowsRuntime] | Out-Null; '
        '[Windows.Data.Xml.Dom.XmlDocument, Windows.Data.Xml.Dom.XmlDocument, '
        'ContentType = WindowsRuntime] | Out-Null; '
        '$xml = New-Object Windows.Data.Xml.Dom.XmlDocument; '
        f'$xml.LoadXml(\'<toast><visual><binding template="ToastGeneric">'
        f'<text>{titulo_seguro}</text><text>{mensagem_segura}</text>'
        f'</binding></visual></toast>\'); '
        '$toast = New-Object Windows.UI.Notifications.ToastNotification $xml; '
        f"[Windows.UI.Notifications.ToastNotificationManager]::CreateToastNotifier('{_APP_ID_TOAST}')"
        '.Show($toast)'
    )
    try:
        # -EncodedCommand (base64 UTF-16LE) evita qualquer problema de
        # escaping entre Python -> subprocess -> PowerShell, já que o texto
        # do título/mensagem nunca precisa cruzar a linha de comando cru.
        codificado = base64.b64encode(script.encode("utf-16-le")).decode("ascii")
        subprocess.Popen(
            [
                "powershell", "-NoProfile", "-NonInteractive", "-WindowStyle", "Hidden",
                "-EncodedCommand", codificado,
            ],
            creationflags=_CREATION_FLAGS,
        )
    except OSError:
        pass
