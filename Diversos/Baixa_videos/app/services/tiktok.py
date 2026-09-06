"""Serviço do TikTok.

Vídeos públicos do TikTok costumam ser acessíveis sem login. Cookies opcionais
via TIKTOK_COOKIES_FILE. Todo o fluxo vem de `YtdlpPlatformService`.
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
    "this post is private",
    "login required",
    "requires authentication",
    "sign in to confirm",
    "use --cookies",
)
_UNAVAILABLE_PATTERNS = (
    "video not available",
    "not available",
    "http error 404",
    "page not found",
    "video is unavailable",
    "removed",
    "does not exist",
    "no video could be found",
    "unable to extract",
)


def _classify_and_raise(message: str) -> None:
    low = message.lower()
    if any(p in low for p in _PRIVATE_PATTERNS):
        raise ContentPrivateError(MSG_PRIVATE, reason="private")
    if any(p in low for p in _UNAVAILABLE_PATTERNS):
        raise ContentUnavailableError(MSG_UNAVAILABLE, reason="unavailable")
    raise ExtractionError(MSG_GENERIC, reason="extract_failed")


class TikTokService(YtdlpPlatformService):
    name = "tiktok"
    _path_types = {"video": "video", "photo": "photo"}

    def _classify(self, raw: str) -> None:
        _classify_and_raise(raw)
