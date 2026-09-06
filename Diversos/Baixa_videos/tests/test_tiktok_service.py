"""Testes do TikTokService com extrator injetado (sem rede / sem yt-dlp)."""

from __future__ import annotations

import asyncio

import pytest

from app.services.errors import ContentPrivateError, ContentUnavailableError, ExtractionError
from app.services.tiktok import TikTokService, _classify_and_raise
from app.services.validator import validate_url
from app.utils.cache import TTLCache

VIDEO_URL = validate_url(
    "https://www.tiktok.com/@user/video/7280000000000000000", dns_check=False
)

FAKE_INFO = {
    "title": "Um vídeo de teste no TikTok",
    "thumbnail": "https://example.com/t.jpg",
    "duration": 15,
    "ext": "mp4",
    "formats": [
        {"format_id": "hd", "vcodec": "h264", "acodec": "aac", "height": 1024,
         "ext": "mp4", "filesize": 3_000_000},
        {"format_id": "sd", "vcodec": "h264", "acodec": "aac", "height": 576,
         "ext": "mp4", "filesize": 1_400_000},
    ],
}


def run(coro):
    return asyncio.run(coro)


def service_with(info=None, exc=None):
    def extractor(_url):
        if exc:
            raise exc
        return info
    return TikTokService(extractor=extractor, cache=TTLCache(ttl_seconds=0))


def test_metadata_basico() -> None:
    content = run(service_with(FAKE_INFO).get_metadata(VIDEO_URL))
    assert content.platform == "tiktok"
    assert content.type == "video"
    assert content.title == "Um vídeo de teste no TikTok"
    assert content.duration == 15
    assert [f.quality for f in content.video_formats] == ["1024p", "576p"]
    assert [f.quality for f in content.audio_formats] == ["mp3"]


def test_privado() -> None:
    exc = _fake_error("ERROR: Video is private")
    with pytest.raises(ContentPrivateError):
        run(service_with(exc=exc).get_metadata(VIDEO_URL))


def test_indisponivel() -> None:
    exc = _fake_error("ERROR: Video not available (HTTP Error 404)")
    with pytest.raises(ContentUnavailableError):
        run(service_with(exc=exc).get_metadata(VIDEO_URL))


def test_erro_generico() -> None:
    with pytest.raises(ExtractionError):
        run(service_with(exc=RuntimeError("boom")).get_metadata(VIDEO_URL))


def _fake_error(message: str) -> Exception:
    try:
        _classify_and_raise(message)
    except Exception as exc:  # noqa: BLE001
        return exc
    raise AssertionError("deveria ter classificado")


def test_analyze_endpoint_roteia_para_tiktok(monkeypatch) -> None:
    """A URL do TikTok deve chegar ao TikTokService via /api/analyze."""
    from fastapi.testclient import TestClient

    import app.api.analyze as analyze_mod
    from app.main import app
    from app.services import ytdlp

    monkeypatch.setattr(ytdlp, "run_extract", lambda url, **kw: FAKE_INFO)
    real = analyze_mod.validate_url
    monkeypatch.setattr(analyze_mod, "validate_url", lambda raw: real(raw, dns_check=False))

    client = TestClient(app, raise_server_exceptions=False)
    r = client.post(
        "/api/analyze",
        json={"url": "https://www.tiktok.com/@user/video/7280000000000000000"},
    )
    assert r.status_code == 200
    body = r.json()
    assert body["platform"] == "tiktok"
    assert body["type"] == "video"
    assert any(f["kind"] == "audio" for f in body["formats"])
