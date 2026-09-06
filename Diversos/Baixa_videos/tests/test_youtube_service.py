"""Testes do YouTubeService com extrator injetado (sem rede / sem yt-dlp)."""

from __future__ import annotations

import asyncio

import pytest

from app.services.errors import ContentPrivateError, ContentUnavailableError, ExtractionError
from app.services.validator import validate_url
from app.services.youtube import YouTubeService, _classify_and_raise
from app.utils.cache import TTLCache

WATCH_URL = validate_url("https://www.youtube.com/watch?v=dQw4w9WgXcQ", dns_check=False)
SHORT_URL = validate_url("https://www.youtube.com/shorts/abc123DEF", dns_check=False)

FAKE_INFO = {
    "title": "Vídeo público do YouTube",
    "thumbnail": "https://example.com/yt.jpg",
    "duration": 212,
    "ext": "mp4",
    "formats": [
        {"format_id": "137", "vcodec": "h264", "acodec": "none", "height": 1080,
         "ext": "mp4", "filesize": 40_000_000},
        {"format_id": "18", "vcodec": "h264", "acodec": "aac", "height": 360,
         "ext": "mp4", "filesize": 12_000_000},
    ],
}


def run(coro):
    return asyncio.run(coro)


def service_with(info=None, exc=None):
    def extractor(_url):
        if exc:
            raise exc
        return info
    return YouTubeService(extractor=extractor, cache=TTLCache(ttl_seconds=0))


def test_metadata_watch() -> None:
    content = run(service_with(FAKE_INFO).get_metadata(WATCH_URL))
    assert content.platform == "youtube"
    assert content.type == "video"
    assert [f.quality for f in content.video_formats] == ["1080p", "360p"]
    assert content.duration == 212


def test_tipo_short_por_caminho() -> None:
    content = run(service_with(FAKE_INFO).get_metadata(SHORT_URL))
    assert content.type == "short"


def test_privado_ou_age_restricted() -> None:
    with pytest.raises(ContentPrivateError):
        run(service_with(exc=_fake_error(
            "ERROR: Sign in to confirm your age. This video may be inappropriate for some users."
        )).get_metadata(WATCH_URL))


def test_indisponivel() -> None:
    with pytest.raises(ContentUnavailableError):
        run(service_with(exc=_fake_error("ERROR: Video unavailable. This video has been removed")).get_metadata(WATCH_URL))


def test_erro_generico() -> None:
    with pytest.raises(ExtractionError):
        run(service_with(exc=RuntimeError("boom")).get_metadata(WATCH_URL))


def _fake_error(message: str) -> Exception:
    try:
        _classify_and_raise(message)
    except Exception as exc:  # noqa: BLE001
        return exc
    raise AssertionError("deveria ter classificado")
