"""Testes do wrapper de FFmpeg (subprocess mockado — não precisa de FFmpeg real)."""

from __future__ import annotations

import subprocess
from pathlib import Path

import pytest

from app.services import media
from app.services.errors import FeatureUnavailableError, MediaProcessingError


class FakeProc:
    def __init__(self, returncode: int) -> None:
        self.returncode = returncode
        self.stderr = "erro simulado do ffmpeg"
        self.stdout = ""


def test_extract_audio_sem_ffmpeg(monkeypatch: pytest.MonkeyPatch, tmp_path: Path) -> None:
    monkeypatch.setattr(media, "resolve_ffmpeg", lambda: None)
    with pytest.raises(FeatureUnavailableError):
        media.extract_audio(tmp_path / "in.mp4", tmp_path / "out.mp3")


def test_extract_audio_sucesso(monkeypatch: pytest.MonkeyPatch, tmp_path: Path) -> None:
    monkeypatch.setattr(media, "resolve_ffmpeg", lambda: "/usr/bin/ffmpeg")
    captured = {}

    def fake_run(cmd, **kwargs):
        captured["cmd"] = cmd
        captured["kwargs"] = kwargs
        Path(cmd[-1]).write_bytes(b"ID3fake-mp3-bytes")
        return FakeProc(0)

    monkeypatch.setattr(subprocess, "run", fake_run)

    src = tmp_path / "in.mp4"
    src.write_bytes(b"video")
    out = media.extract_audio(src, tmp_path / "out.mp3", bitrate="192k")

    assert out.is_file() and out.stat().st_size > 0
    # comando é lista, sem shell
    assert isinstance(captured["cmd"], list)
    assert captured["kwargs"].get("shell") in (None, False)
    assert captured["cmd"][0] == "/usr/bin/ffmpeg"
    assert "-vn" in captured["cmd"] and "libmp3lame" in captured["cmd"]
    assert captured["cmd"][-1].endswith("out.mp3")


def test_extract_audio_ffmpeg_falha(monkeypatch: pytest.MonkeyPatch, tmp_path: Path) -> None:
    monkeypatch.setattr(media, "resolve_ffmpeg", lambda: "/usr/bin/ffmpeg")
    monkeypatch.setattr(subprocess, "run", lambda cmd, **kw: FakeProc(1))

    out = tmp_path / "out.mp3"
    with pytest.raises(MediaProcessingError):
        media.extract_audio(tmp_path / "in.mp4", out)
    assert not out.exists()


def test_extract_audio_timeout(monkeypatch: pytest.MonkeyPatch, tmp_path: Path) -> None:
    monkeypatch.setattr(media, "resolve_ffmpeg", lambda: "/usr/bin/ffmpeg")

    def boom(cmd, **kw):
        raise subprocess.TimeoutExpired(cmd, 1)

    monkeypatch.setattr(subprocess, "run", boom)
    with pytest.raises(MediaProcessingError):
        media.extract_audio(tmp_path / "in.mp4", tmp_path / "out.mp3")


def test_resolve_ffmpeg_usa_which(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setattr("app.services.media.get_settings", lambda: _Settings(""))
    monkeypatch.setattr("shutil.which", lambda name: "/opt/ffmpeg" if name == "ffmpeg" else None)
    media.resolve_ffmpeg.cache_clear()
    assert media.resolve_ffmpeg() == "/opt/ffmpeg"
    media.resolve_ffmpeg.cache_clear()


class _Settings:
    def __init__(self, ffmpeg_path: str) -> None:
        self.ffmpeg_path = ffmpeg_path
        self.audio_bitrate = "192k"
        self.request_timeout = 20
