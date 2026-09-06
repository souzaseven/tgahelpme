"""Serviço do Facebook.

Vídeos, Reels e posts públicos costumam funcionar sem login. Cookies opcionais
via FACEBOOK_COOKIES_FILE. Todo o fluxo vem de `YtdlpPlatformService`.
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

_PRIVATE_PATTERNS = (
    "private",
    "log in",
    "login required",
    "requires authentication",
    "you must be logged in",
    "cannot parse data",     # geralmente vídeo restrito / exige sessão
    "no video formats found",
)
_UNAVAILABLE_PATTERNS = (
    "not available",
    "content isn't available",
    "http error 404",
    "page not found",
    "unable to extract",
    "video unavailable",
    "removed",
    "does not exist",
)


def _classify_and_raise(message: str) -> None:
    low = message.lower()
    if any(p in low for p in _PRIVATE_PATTERNS):
        raise ContentPrivateError(MSG_PRIVATE, reason="private")
    if any(p in low for p in _UNAVAILABLE_PATTERNS):
        raise ContentUnavailableError(MSG_UNAVAILABLE, reason="unavailable")
    raise ExtractionError(MSG_GENERIC, reason="extract_failed")


class FacebookService(YtdlpPlatformService):
    name = "facebook"
    _path_types = {"reel": "reel", "videos": "video", "watch": "video", "posts": "post"}

    def _classify(self, raw: str) -> None:
        _classify_and_raise(raw)
