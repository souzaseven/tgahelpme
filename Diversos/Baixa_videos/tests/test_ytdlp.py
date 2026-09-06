"""Testes das opções genéricas do yt-dlp (cookies)."""

from __future__ import annotations

from pathlib import Path

import pytest

from app.services import ytdlp


@pytest.mark.parametrize(
    "spec,expected",
    [
        ("", None),
        ("  ", None),
        ("chrome", ("chrome", None, None, None)),
        ("Firefox", ("firefox", None, None, None)),
        ("chrome:Perfil 1", ("chrome", "Perfil 1", None, None)),
        ("edge: Default ", ("edge", "Default", None, None)),
    ],
)
def test_browser_spec(spec: str, expected) -> None:
    assert ytdlp._browser_spec(spec) == expected


def test_common_opts_cookiefile_existente(tmp_path: Path) -> None:
    f = tmp_path / "cookies.txt"
    f.write_text("# Netscape HTTP Cookie File\n")
    opts = ytdlp.common_opts(cookiefile=str(f), cookies_from_browser="chrome")
    assert opts["cookiefile"] == str(f)
    assert "cookiesfrombrowser" not in opts   # arquivo tem prioridade


def test_common_opts_cookiefile_inexistente_cai_para_browser(tmp_path: Path) -> None:
    opts = ytdlp.common_opts(
        cookiefile=str(tmp_path / "naoexiste.txt"), cookies_from_browser="firefox"
    )
    assert "cookiefile" not in opts
    assert opts["cookiesfrombrowser"] == ("firefox", None, None, None)


def test_common_opts_so_browser() -> None:
    opts = ytdlp.common_opts(cookies_from_browser="edge:Default")
    assert opts["cookiesfrombrowser"] == ("edge", "Default", None, None)


def test_common_opts_sem_cookies() -> None:
    opts = ytdlp.common_opts()
    assert "cookiefile" not in opts and "cookiesfrombrowser" not in opts


def test_reraise_navegador_travado() -> None:
    from app.services.errors import ExtractionError

    exc = Exception("ERROR: Could not copy Chrome cookie database. See ...")
    with pytest.raises(ExtractionError) as ei:
        ytdlp._reraise_ytdlp(exc)
    assert ei.value.reason == "browser_cookie_locked"
    assert "Feche o navegador" in ei.value.message


def test_reraise_generico_vira_ytdlperror() -> None:
    with pytest.raises(ytdlp.YtdlpError) as ei:
        ytdlp._reraise_ytdlp(Exception("ERROR: Video unavailable"))
    assert ei.value.raw == "ERROR: Video unavailable"
