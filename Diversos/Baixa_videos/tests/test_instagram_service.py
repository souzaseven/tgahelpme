"""Testes do InstagramService com extrator injetado (sem rede / sem yt-dlp)."""

from __future__ import annotations

import asyncio

import pytest

from app.services.errors import (
    ContentAuthRequiredError,
    ContentPrivateError,
    ContentUnavailableError,
    ExtractionError,
)
from app.services.instagram import InstagramService
from app.services.validator import validate_instagram_url
from app.utils.cache import TTLCache

REEL_URL = validate_instagram_url(
    "https://www.instagram.com/reel/Dc6Q0IuAVCa/", dns_check=False
)
POST_URL = validate_instagram_url(
    "https://www.instagram.com/p/ABC123def/", dns_check=False
)

FAKE_INFO = {
    "title": "Um reel de teste",
    "thumbnail": "https://example.com/thumb.jpg",
    "duration": 42,
    "ext": "mp4",
    "formats": [
        {"format_id": "hd", "vcodec": "h264", "acodec": "aac", "height": 1080,
         "ext": "mp4", "filesize": 8_400_000},
        {"format_id": "sd", "vcodec": "h264", "acodec": "aac", "height": 720,
         "ext": "mp4", "filesize_approx": 4_600_000},
        {"format_id": "audio", "vcodec": "none", "acodec": "aac", "ext": "m4a"},
    ],
}


def run(coro):
    return asyncio.run(coro)


def service_with(info=None, exc=None):
    def extractor(_url):
        if exc:
            raise exc
        return info
    # cache isolado (desativado) para não vazar estado entre os testes
    return InstagramService(extractor=extractor, cache=TTLCache(ttl_seconds=0))


def test_metadata_basico() -> None:
    svc = service_with(FAKE_INFO)
    content = run(svc.get_metadata(REEL_URL))

    assert content.platform == "instagram"
    assert content.type == "reel"
    assert content.title == "Um reel de teste"
    assert content.thumbnail == "https://example.com/thumb.jpg"
    assert content.duration == 42


def test_formats_ordenados_e_com_audio() -> None:
    content = run(service_with(FAKE_INFO).get_metadata(REEL_URL))
    videos = content.video_formats

    assert [f.quality for f in videos] == ["1080p", "720p"]
    assert videos[0].filesize == 8_400_000
    assert videos[1].filesize == 4_600_000  # veio de filesize_approx
    assert [f.quality for f in content.audio_formats] == ["mp3"]
    # source_id nunca deve vazar para o schema, mas existe internamente
    assert videos[0].source_id == "hd"


def test_tipo_por_caminho() -> None:
    content = run(service_with(FAKE_INFO).get_metadata(POST_URL))
    assert content.type == "post"


def test_sem_formatos_usa_melhor_disponivel() -> None:
    info = {"title": "x", "duration": 10, "height": 640, "ext": "mp4"}
    content = run(service_with(info).get_metadata(REEL_URL))
    assert len(content.video_formats) == 1
    assert content.video_formats[0].quality == "640p"
    assert content.video_formats[0].source_id == "best"


def test_titulo_longo_truncado() -> None:
    info = {**FAKE_INFO, "title": "a" * 200}
    content = run(service_with(info).get_metadata(REEL_URL))
    assert len(content.title) <= 120
    assert content.title.endswith("...")


def test_carrossel_pega_primeira_entrada_com_video() -> None:
    info = {
        "entries": [
            {"title": "foto", "formats": []},
            {"title": "video", "duration": 12,
             "formats": [{"format_id": "v", "vcodec": "h264", "height": 720, "ext": "mp4"}]},
        ]
    }
    content = run(service_with(info).get_metadata(REEL_URL))
    assert content.title == "video"
    assert content.video_formats[0].quality == "720p"


def test_login_exigido_vira_auth_required() -> None:
    # Sem cookies configurados -> mensagem de "exige login" (não "é privado").
    exc = _fake_download_error(
        "ERROR: [Instagram] Instagram sent an empty media response. use --cookies"
    )
    with pytest.raises(ContentAuthRequiredError) as ei:
        run(service_with(exc=exc).get_metadata(REEL_URL))
    assert "exigindo login" in ei.value.message


def test_conta_realmente_privada() -> None:
    exc = _fake_download_error("ERROR: This account is private")
    with pytest.raises(ContentPrivateError):
        run(service_with(exc=exc).get_metadata(REEL_URL))


def test_conteudo_indisponivel() -> None:
    exc = _fake_download_error("ERROR: Video unavailable. This page is not found")
    with pytest.raises(ContentUnavailableError):
        run(service_with(exc=exc).get_metadata(REEL_URL))


def test_erro_generico() -> None:
    with pytest.raises(ExtractionError):
        run(service_with(exc=RuntimeError("boom")).get_metadata(REEL_URL))


def test_info_vazia() -> None:
    with pytest.raises(ExtractionError):
        run(service_with(info=None).get_metadata(REEL_URL))


def test_cache_evita_segunda_extracao() -> None:
    calls = {"n": 0}

    def extractor(_url):
        calls["n"] += 1
        return FAKE_INFO

    cache: TTLCache = TTLCache(ttl_seconds=30)
    svc = InstagramService(extractor=extractor, cache=cache)

    run(svc.get_metadata(REEL_URL))
    run(svc.get_metadata(REEL_URL))

    assert calls["n"] == 1


def test_cache_desativado_sempre_extrai() -> None:
    calls = {"n": 0}

    def extractor(_url):
        calls["n"] += 1
        return FAKE_INFO

    svc = InstagramService(extractor=extractor, cache=TTLCache(ttl_seconds=0))
    run(svc.get_metadata(REEL_URL))
    run(svc.get_metadata(REEL_URL))

    assert calls["n"] == 2


# --- helpers ---------------------------------------------------------------

def _fake_download_error(message: str) -> Exception:
    """Reproduz a classificação feita em _default_extractor sem depender de yt-dlp."""
    from app.services.instagram import _classify_and_raise

    try:
        _classify_and_raise(message)
    except Exception as exc:  # noqa: BLE001 - queremos justamente a exceção classificada
        return exc
    raise AssertionError("deveria ter classificado")
