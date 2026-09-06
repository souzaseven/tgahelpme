"""Testes das utilidades genéricas de download."""

from __future__ import annotations

from pathlib import Path

import pytest

from app.services.downloader import (
    build_filename,
    format_selector,
    largest_file,
    media_type_for,
    new_job_dir,
)


@pytest.mark.parametrize(
    "kind,quality,needle",
    [
        ("video", None, "b[vcodec!=none][acodec!=none]/b/best"),
        ("video", "best", "b[vcodec!=none][acodec!=none]/b/best"),
        ("video", "Original", "b[vcodec!=none][acodec!=none]/b/best"),
        ("video", "720p", "height<=720"),
        ("video", "1080", "height<=1080"),
        ("audio", None, "ba/bestaudio/best"),
    ],
)
def test_format_selector(kind: str, quality: str | None, needle: str) -> None:
    assert needle in format_selector(kind, quality)


def test_format_selector_sem_ffmpeg_nao_faz_merge() -> None:
    for q in (None, "720p", "1080p", "best"):
        assert "+" not in format_selector("video", q)                # padrão
        assert "+" not in format_selector("video", q, allow_merge=False)


def test_format_selector_com_ffmpeg_faz_merge() -> None:
    assert format_selector("video", "720p", allow_merge=True).startswith("bv*[height<=720]+ba")
    assert "+ba" in format_selector("video", None, allow_merge=True)
    # áudio nunca muda
    assert format_selector("audio", None, allow_merge=True) == "ba/bestaudio/best"


@pytest.mark.parametrize(
    "stem,ext,expected",
    [
        ("instagram_reel_ABC123", ".mp4", "instagram_reel_ABC123.mp4"),
        ("../../etc/passwd", "mp4", "etc-passwd.mp4"),
        ("nome com espaço", "MP4", "nome-com-espaco.mp4"),
        ("", "", "video.bin"),
    ],
)
def test_build_filename(stem: str, ext: str, expected: str) -> None:
    assert build_filename(stem, ext) == expected


@pytest.mark.parametrize(
    "ext,expected",
    [("mp4", "video/mp4"), (".MP4", "video/mp4"), ("mp3", "audio/mpeg"),
     ("xyz", "application/octet-stream")],
)
def test_media_type_for(ext: str, expected: str) -> None:
    assert media_type_for(ext) == expected


def test_new_job_dir_cria_dentro_do_temp() -> None:
    from app.core.config import get_settings

    job_id, job_dir = new_job_dir()
    try:
        assert job_dir.is_dir()
        assert job_dir.parent == get_settings().temp_path
        assert job_id in str(job_dir)
    finally:
        job_dir.rmdir()


def test_largest_file(tmp_path: Path) -> None:
    (tmp_path / "small.bin").write_bytes(b"x")
    (tmp_path / "big.bin").write_bytes(b"x" * 100)
    assert largest_file(tmp_path).name == "big.bin"
    assert largest_file(tmp_path / "vazio" if False else tmp_path) is not None


def test_largest_file_vazio(tmp_path: Path) -> None:
    assert largest_file(tmp_path) is None
