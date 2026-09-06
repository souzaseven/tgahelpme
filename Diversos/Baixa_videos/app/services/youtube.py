"""Serviço do YouTube.

Só acessa vídeos que o yt-dlp obtém normalmente, respeitando os Termos do
YouTube e direitos autorais. NÃO implementa contorno de DRM, de restrição de
idade sem autenticação, nem de conteúdo "members-only".
"""

from __future__ import annotations

from app.services.errors import (
    MSG_GENERIC,
    MSG_PRIVATE,
    MSG_UNAVAILABLE,
    ContentPrivateError,
    ContentUnavailableError,
    ExtractionError,
)
from app.services.ytdlp_service import YtdlpPlatformService

# Exige sessão / não é público (inclui restrição de idade e conteúdo de membros).
_PRIVATE_PATTERNS = (
    "private video",
    "sign in to confirm your age",
    "age-restricted",
    "members-only",
    "join this channel",
    "this video is available to this channel's members",
    "login required",
    "sign in to confirm you're not a bot",
)
# Removido / inexistente / bloqueado.
_UNAVAILABLE_PATTERNS = (
    "video unavailable",
    "is not available",
    "has been removed",
    "no longer available",
    "http error 404",
    "this video is unavailable",
    "who has blocked it",
    "not available in your country",
    "unable to extract",
    "removed for violating",
)


def _classify_and_raise(message: str) -> None:
    low = message.lower()
    if any(p in low for p in _PRIVATE_PATTERNS):
        raise ContentPrivateError(MSG_PRIVATE, reason="private")
    if any(p in low for p in _UNAVAILABLE_PATTERNS):
        raise ContentUnavailableError(MSG_UNAVAILABLE, reason="unavailable")
    raise ExtractionError(MSG_GENERIC, reason="extract_failed")


class YouTubeService(YtdlpPlatformService):
    name = "youtube"
    _path_types = {"shorts": "short", "live": "video", "embed": "video", "watch": "video"}

    def _classify(self, raw: str) -> None:
        _classify_and_raise(raw)
