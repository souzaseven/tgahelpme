"""Erros das camadas de serviço, com mensagem segura para o usuário.

`message` é exibível; `reason` é um código curto só para log/telemetria.
"""

from __future__ import annotations


class ServiceError(Exception):
    def __init__(self, message: str, *, reason: str) -> None:
        super().__init__(reason)
        self.message = message
        self.reason = reason


class UnsupportedPlatformError(ServiceError):
    """A plataforma da URL ainda não tem serviço implementado."""


class ContentPrivateError(ServiceError):
    """A conta/publicação é privada — não há acesso público a esse conteúdo."""


class ContentAuthRequiredError(ServiceError):
    """A plataforma exige uma sessão autenticada para entregar o conteúdo.

    Diferente de `ContentPrivateError`: o conteúdo pode ser público, mas o
    Instagram não o serve a um cliente deslogado.
    """


class ContentUnavailableError(ServiceError):
    """O conteúdo foi removido, não existe ou a plataforma não o entregou."""


class ExtractionError(ServiceError):
    """Falha genérica ao extrair informações (rede, mudança da plataforma...)."""


class MediaTooLargeError(ServiceError):
    """O arquivo excede MAX_MEDIA_SIZE_MB."""


class FeatureUnavailableError(ServiceError):
    """Funcionalidade ainda não implementada / indisponível no ambiente (ex.: FFmpeg)."""


class MediaProcessingError(ServiceError):
    """Falha ao processar a mídia (FFmpeg retornou erro)."""


# Mensagens padronizadas (ver "FEEDBACK" no prompt do projeto).
MSG_PRIVATE = "Esse conteúdo não está disponível publicamente."
MSG_UNAVAILABLE = "Não foi possível acessar esse conteúdo."
MSG_GENERIC = "Não foi possível processar o link."
# Quando NÃO há cookies configurados no servidor.
MSG_AUTH_REQUIRED = (
    "O Instagram está exigindo login para acessar este conteúdo. "
    "Se ele for seu ou público, configure os cookies da sua conta no servidor."
)
# Quando HÁ cookies configurados, mas ainda assim falhou (provável expiração).
MSG_AUTH_EXPIRED = (
    "Não foi possível acessar esse conteúdo. A sessão configurada no servidor "
    "pode ter expirado."
)
MSG_TOO_LARGE = "O arquivo é maior que o limite permitido."
MSG_AUDIO_SOON = "O download de áudio ainda não está disponível."
MSG_NO_FFMPEG = "A extração de áudio não está disponível no momento."
MSG_MEDIA_FAIL = "Não foi possível gerar o áudio."
