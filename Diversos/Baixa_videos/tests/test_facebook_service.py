"""Testes do FacebookService com extrator injetado (sem rede / sem yt-dlp)."""

from __future__ import annotations

import asyncio

import pytest

from app.services.errors import ContentPrivateError, ContentUnavailableError, ExtractionError
from app.services.facebook import FacebookService, _classify_and_raise
from app.services.validator import validate_url
from app.utils.cache import TTLCache

WATCH_URL = validate_url(
    "https://www.facebook.com/watch/?v=1234567890", dns_check=False
)
REEL_URL = validate_url("https://www.facebook.com/reel/998877/", dns_check=False)

FAKE_INFO = {
    "title": "Vídeo público do Facebook",
    "thumbnail": "https://example.com/fb.jpg",
    "duration": 90,
    "ext": "mp4",
    "formats": [
        {"format_id": "hd", "vcodec": "h264", "acodec": "aac", "height": 720,
         "ext": "mp4", "filesize": 12_000_000},
        {"format_id": "sd", "vcodec": "h264", "acodec": "aac", "height": 360,
         "ext": "mp4", "filesize": 5_000_000},
    ],
}


def run(coro):
    return asyncio.run(coro)


def service_with(info=None, exc=None):
    def extractor(_url):
        if exc:
            raise exc
        return info
    return FacebookService(extractor=extractor, cache=TTLCache(ttl_seconds=0))


def test_metadata_watch() -> None:
    content = run(service_with(FAKE_INFO).get_metadata(WATCH_URL))
    assert content.platform == "facebook"
    assert content.type == "video"
    assert [f.quality for f in content.video_formats] == ["720p", "360p"]
    assert content.duration == 90


def test_tipo_reel_por_caminho() -> None:
    content = run(service_with(FAKE_INFO).get_metadata(REEL_URL))
    assert content.type == "reel"


def test_privado() -> None:
    with pytest.raises(ContentPrivateError):
        run(service_with(exc=_fake_error("ERROR: This video requires you to log in")).get_metadata(WATCH_URL))


def test_indisponivel() -> None:
    with pytest.raises(ContentUnavailableError):
        run(service_with(exc=_fake_error("ERROR: The content isn't available right now")).get_metadata(WATCH_URL))


def test_erro_generico() -> None:
    with pytest.raises(ExtractionError):
        run(service_with(exc=RuntimeError("boom")).get_metadata(WATCH_URL))


def _fake_error(message: str) -> Exception:
    try:
        _classify_and_raise(message)
    except Exception as exc:  # noqa: BLE001
        return exc
    raise AssertionError("deveria ter classificado")
