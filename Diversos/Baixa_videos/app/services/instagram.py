"""Serviço do Instagram.

Só o que é específico do Instagram: hosts (no validator), tipos de conteúdo,
uso de cookies e a classificação das mensagens de erro. Todo o resto vem de
`YtdlpPlatformService`.
"""

from __future__ import annotations

from pathlib import Path

from app.core.config import get_settings
from app.services import ytdlp
from app.services.errors import (
    MSG_AUTH_EXPIRED,
    MSG_AUTH_REQUIRED,
    MSG_GENERIC,
    MSG_PRIVATE,
    MSG_UNAVAILABLE,
    ContentAuthRequiredError,
    ContentPrivateError,
    ContentUnavailableError,
    ExtractionError,
)
from app.services.ytdlp_service import Downloader, Extractor, YtdlpPlatformService

# A conta/post é realmente privada.
_PRIVATE_PATTERNS = (
    "this account is private",
    "private account",
    "the post is private",
    "is private",
)
# Instagram exige sessão autenticada (o conteúdo pode até ser público).
_AUTH_PATTERNS = (
    "login required",
    "requires authentication",
    "you need to log in",
    "sign in to confirm",
    "use --cookies",
    "cookies-from-browser",
    "empty media response",
    "rate-limit reached",
    "requested content is not available",
    "login to access",
)
# O conteúdo foi removido / não existe.
_UNAVAILABLE_PATTERNS = (
    "not available",
    "video unavailable",
    "page not found",
    "http error 404",
    "content isn't available",
    "removed",
    "does not exist",
    "no video",
    "unable to extract shared data",
)


def _cookiefile() -> str | None:
    value = get_settings().instagram_cookies_file.strip()
    return value or None


def _cookies_from_browser() -> str | None:
    value = get_settings().instagram_cookies_from_browser.strip()
    return value or None


def _has_cookies() -> bool:
    return _cookiefile() is not None or _cookies_from_browser() is not None


def _classify_and_raise(message: str) -> None:
    low = message.lower()
    if any(p in low for p in _PRIVATE_PATTERNS):
        raise ContentPrivateError(MSG_PRIVATE, reason="private")
    if any(p in low for p in _AUTH_PATTERNS):
        has_cookies = _has_cookies()
        raise ContentAuthRequiredError(
            MSG_AUTH_EXPIRED if has_cookies else MSG_AUTH_REQUIRED,
            reason="auth_expired" if has_cookies else "auth_required",
        )
    if any(p in low for p in _UNAVAILABLE_PATTERNS):
        raise ContentUnavailableError(MSG_UNAVAILABLE, reason="unavailable")
    raise ExtractionError(MSG_GENERIC, reason="extract_failed")


def _default_extractor(url: str) -> dict:
    try:
        return ytdlp.run_extract(
            url, cookiefile=_cookiefile(), cookies_from_browser=_cookies_from_browser()
        )
    except ytdlp.YtdlpError as exc:
        _classify_and_raise(exc.raw)
        raise  # pragma: no cover


def _default_downloader(
    url: str, dest_dir: Path, selector: str,
    progress_cb: ytdlp.ProgressCB | None = None,
) -> Path:
    try:
        return ytdlp.run_download(
            url, dest_dir, selector,
            cookiefile=_cookiefile(),
            cookies_from_browser=_cookies_from_browser(),
            max_bytes=get_settings().max_media_size_bytes,
            progress_cb=progress_cb,
        )
    except ytdlp.YtdlpError as exc:
        _classify_and_raise(exc.raw)
        raise  # pragma: no cover


class InstagramService(YtdlpPlatformService):
    name = "instagram"
    _path_types = {"reel": "reel", "reels": "reel", "p": "post", "tv": "video"}

    def _classify(self, raw: str) -> None:
        _classify_and_raise(raw)

    def _build_extractor(self) -> Extractor:
        return _default_extractor

    def _build_downloader(self) -> Downloader:
        return _default_downloader
