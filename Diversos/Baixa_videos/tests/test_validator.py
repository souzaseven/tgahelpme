"""Testes do validador de URL.

`dns_check=False` isola o teste da rede — a checagem anti-SSRF tem cobertura
própria em test_security.py.
"""

from __future__ import annotations

import pytest

from app.services.validator import (
    UrlValidationError,
    validate_instagram_url,
    validate_url,
)

REEL = "https://www.instagram.com/reel/Dc6Q0IuAVCa/"


def v(url: str):
    return validate_url(url, dns_check=False)


# ---------- casos válidos ----------

@pytest.mark.parametrize(
    "url",
    [
        "https://www.instagram.com/reel/Dc6Q0IuAVCa/",
        "https://instagram.com/p/ABC123def/",
        "https://www.instagram.com/reels/Dc6Q0IuAVCa",
        "https://www.instagram.com/tv/XYZ_987-a/",
        "https://www.instagram.com/algum.perfil/reel/Dc6Q0IuAVCa/",
        "https://www.instagram.com/reel/Dc6Q0IuAVCa/?igsh=MXFjNmx",  # query removida
    ],
)
def test_urls_validas(url: str) -> None:
    result = v(url)
    assert result.platform == "instagram"
    assert result.normalized.startswith("https://")
    assert "?" not in result.normalized


def test_normaliza_para_canonica() -> None:
    result = v("https://www.instagram.com/reel/Dc6Q0IuAVCa/?igsh=abc#frag")
    assert result.normalized == "https://www.instagram.com/reel/Dc6Q0IuAVCa"


# ---------- formato inválido ----------

@pytest.mark.parametrize(
    "url",
    [
        "",
        "   ",
        "instagram.com/reel/abc",             # sem esquema
        "http://www.instagram.com/reel/abc/",  # http
        "ftp://www.instagram.com/reel/abc/",
        "file:///etc/passwd",
        "https://user:pass@www.instagram.com/reel/abc/",
        "https://www.instagram.com:8080/reel/abc/",
        "https://www.instagram.com/reel/abc\n/",
        "https://www.instagram.com/ instagram/reel/abc/",
    ],
)
def test_urls_malformadas(url: str) -> None:
    with pytest.raises(UrlValidationError):
        v(url)


# ---------- host / plataforma ----------

def test_host_precisa_ser_exato_nao_substring() -> None:
    with pytest.raises(UrlValidationError):
        v("https://instagram.com.evil.com/reel/abc/")
    with pytest.raises(UrlValidationError):
        v("https://evilinstagram.com/reel/abc/")


@pytest.mark.parametrize(
    "url",
    [
        "https://www.tiktok.com/@user.name/video/7280000000000000000",
        "https://www.tiktok.com/@user/photo/7280000000000000000",
        "https://vm.tiktok.com/ZMabc123/",
        "https://www.tiktok.com/t/ZMabc123/",
        "https://m.tiktok.com/@user/video/7280000000000000000?is_from_webapp=1",
    ],
)
def test_tiktok_valido(url: str) -> None:
    result = v(url)
    assert result.platform == "tiktok"
    assert "?" not in result.normalized


def test_tiktok_caminho_invalido() -> None:
    with pytest.raises(UrlValidationError):
        v("https://www.tiktok.com/@user/")


@pytest.mark.parametrize(
    "url,expected_norm",
    [
        ("https://www.facebook.com/watch/?v=1234567890",
         "https://www.facebook.com/watch?v=1234567890"),
        ("https://www.facebook.com/watch?v=123&extra=x",
         "https://www.facebook.com/watch?v=123"),
        ("https://www.facebook.com/reel/998877/", None),
        ("https://web.facebook.com/PageName/videos/1029384756/", None),
        ("https://www.facebook.com/share/v/abcDEF123/", None),
        ("https://fb.watch/abc-123XYZ/", None),
    ],
)
def test_facebook_valido(url: str, expected_norm: str | None) -> None:
    result = v(url)
    assert result.platform == "facebook"
    if expected_norm:
        assert result.normalized == expected_norm


@pytest.mark.parametrize(
    "url",
    [
        "https://www.facebook.com/watch/",          # sem ?v=
        "https://www.facebook.com/PageName/",        # perfil, não vídeo
        "https://www.facebook.com/",
        "https://fb.watch/",
    ],
)
def test_facebook_caminho_invalido(url: str) -> None:
    with pytest.raises(UrlValidationError):
        v(url)


@pytest.mark.parametrize(
    "url,expected_norm",
    [
        ("https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=abc&t=10",
         "https://www.youtube.com/watch?v=dQw4w9WgXcQ"),
        ("https://youtu.be/dQw4w9WgXcQ", None),
        ("https://www.youtube.com/shorts/abc123DEF/", None),
        ("https://m.youtube.com/watch?v=dQw4w9WgXcQ", None),
        ("https://music.youtube.com/watch?v=dQw4w9WgXcQ", None),
    ],
)
def test_youtube_valido(url: str, expected_norm: str | None) -> None:
    result = v(url)
    assert result.platform == "youtube"
    if expected_norm:
        assert result.normalized == expected_norm


@pytest.mark.parametrize(
    "url",
    [
        "https://www.youtube.com/watch",          # sem ?v=
        "https://www.youtube.com/@channel",        # canal
        "https://www.youtube.com/",
        "https://youtu.be/",
    ],
)
def test_youtube_caminho_invalido(url: str) -> None:
    with pytest.raises(UrlValidationError):
        v(url)


@pytest.mark.parametrize(
    "url,name",
    [
        ("https://x.com/user/status/123", "X (Twitter)"),
        ("https://vimeo.com/123456", "Vimeo"),
    ],
)
def test_plataforma_conhecida_nao_suportada(url: str, name: str) -> None:
    with pytest.raises(UrlValidationError) as ei:
        v(url)
    assert name in ei.value.message


def test_host_desconhecido() -> None:
    with pytest.raises(UrlValidationError):
        v("https://example.com/reel/abc/")


# ---------- caminho do Instagram ----------

@pytest.mark.parametrize(
    "url",
    [
        "https://www.instagram.com/",
        "https://www.instagram.com/algum.perfil/",
        "https://www.instagram.com/stories/user/123/",
        "https://www.instagram.com/reel/",
    ],
)
def test_caminho_instagram_invalido(url: str) -> None:
    with pytest.raises(UrlValidationError):
        v(url)


# ---------- helper de plataforma ----------

def test_validate_instagram_url_rejeita_outra_plataforma() -> None:
    with pytest.raises(UrlValidationError):
        validate_instagram_url("https://www.facebook.com/watch/?v=123", dns_check=False)


def test_validate_instagram_url_ok() -> None:
    assert validate_instagram_url(REEL, dns_check=False).platform == "instagram"
