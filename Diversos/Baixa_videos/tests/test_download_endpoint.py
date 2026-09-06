"""Testes do fluxo de download com progresso (SSE), sem rede / sem yt-dlp."""

from __future__ import annotations

import json
import time
from pathlib import Path

import pytest
from fastapi.testclient import TestClient

import app.api.download as download_mod
import app.services.instagram as instagram_mod
from app.main import app
from app.services.jobs import jobs

REEL = "https://www.instagram.com/reel/Dc6Q0IuAVCa/"


@pytest.fixture
def client():
    # Context manager: um único event loop persiste entre as requisições,
    # necessário para o job em segundo plano (asyncio.create_task) avançar.
    with TestClient(app, raise_server_exceptions=False) as c:
        yield c


@pytest.fixture(autouse=True)
def _fast_poll(monkeypatch: pytest.MonkeyPatch):
    monkeypatch.setattr(download_mod, "_POLL_INTERVAL", 0.02)
    real = download_mod.validate_url
    monkeypatch.setattr(download_mod, "validate_url", lambda raw: real(raw, dns_check=False))
    yield
    for jid in list(jobs._jobs):
        jobs.discard(jid)


@pytest.fixture
def fake_video(monkeypatch: pytest.MonkeyPatch):
    dirs: list[Path] = []

    def _fake(url, dest_dir: Path, selector, progress_cb=None):
        if progress_cb:
            progress_cb("downloading", "Baixando...", 50.0)
        time.sleep(0.08)   # janela para o SSE observar o estado "downloading"
        out = dest_dir / "video.mp4"
        out.write_bytes(b"FAKEMP4DATA" * 20)
        dirs.append(dest_dir)
        return out

    monkeypatch.setattr(instagram_mod, "_default_downloader", _fake)
    return dirs


def _run_job(client: TestClient, payload: dict) -> tuple[list[dict], str]:
    """POST /download, consome o SSE até o estado terminal, devolve (eventos, job_id)."""
    r = client.post("/api/download", json=payload)
    assert r.status_code == 202, r.text
    job_id = r.json()["job_id"]

    events: list[dict] = []
    with client.stream("GET", f"/api/download/{job_id}/events") as resp:
        assert resp.headers["content-type"].startswith("text/event-stream")
        for line in resp.iter_lines():
            text = line if isinstance(line, str) else (line or b"").decode()
            if text.startswith("data: "):
                snap = json.loads(text[6:])
                events.append(snap)
                if snap["state"] in ("done", "error"):
                    break
    return events, job_id


def test_video_fluxo_completo(client, fake_video) -> None:
    events, job_id = _run_job(client, {"url": REEL, "format": "video", "quality": "720p"})
    states = [e["state"] for e in events]
    assert states[-1] == "done"
    assert any(s == "downloading" for s in states)
    assert events[-1]["filename"].endswith(".mp4")

    r = client.get(f"/api/download/{job_id}/file")
    assert r.status_code == 200
    assert r.headers["content-type"].startswith("video/mp4")
    assert "instagram_reel_Dc6Q0IuAVCa.mp4" in r.headers["content-disposition"]
    assert r.content.startswith(b"FAKEMP4DATA")

    assert fake_video and not fake_video[0].exists()          # job dir limpo
    assert client.get(f"/api/download/{job_id}/file").status_code == 404  # job descartado


def test_audio_sem_ffmpeg_recusa_no_post(client, fake_video, monkeypatch: pytest.MonkeyPatch) -> None:
    from app.services import media
    monkeypatch.setattr(media, "ffmpeg_available", lambda: False)
    r = client.post("/api/download", json={"url": REEL, "format": "audio"})
    assert r.status_code == 501


def test_audio_fluxo_completo(client, fake_video, monkeypatch: pytest.MonkeyPatch) -> None:
    from app.services import media
    monkeypatch.setattr(media, "ffmpeg_available", lambda: True)

    def _fake_extract(src, dest, **kw):
        time.sleep(0.08)   # janela para o SSE observar o estado "processing"
        dest.write_bytes(b"ID3\x00" * 16)
        return dest

    monkeypatch.setattr(media, "extract_audio", _fake_extract)

    events, job_id = _run_job(client, {"url": REEL, "format": "audio"})
    assert events[-1]["state"] == "done"
    assert any(e["state"] == "processing" for e in events)

    r = client.get(f"/api/download/{job_id}/file")
    assert r.status_code == 200
    assert r.headers["content-type"].startswith("audio/mpeg")
    assert r.content.startswith(b"ID3")


def test_url_invalida_recusa_no_post(client, fake_video) -> None:
    r = client.post("/api/download", json={"url": "https://vimeo.com/1", "format": "video"})
    assert r.status_code == 400


def test_erro_durante_download_vai_pro_stream(client, monkeypatch: pytest.MonkeyPatch) -> None:
    from app.services.errors import MSG_TOO_LARGE, MediaTooLargeError

    def _boom(url, dest_dir, selector, progress_cb=None):
        raise MediaTooLargeError(MSG_TOO_LARGE, reason="test")

    monkeypatch.setattr(instagram_mod, "_default_downloader", _boom)

    events, job_id = _run_job(client, {"url": REEL, "format": "video", "quality": "1080p"})
    assert events[-1]["state"] == "error"
    assert events[-1]["error"] == MSG_TOO_LARGE

    r = client.get(f"/api/download/{job_id}/file")
    assert r.status_code == 502


def test_events_job_inexistente(client) -> None:
    assert client.get("/api/download/naoexiste/events").status_code == 404
    assert client.get("/api/download/naoexiste/file").status_code == 404


def test_sse_manda_keepalive_em_fase_longa(
    client, monkeypatch: pytest.MonkeyPatch
) -> None:
    monkeypatch.setattr(download_mod, "_HEARTBEAT_SECONDS", 0.05)

    def _slow(url, dest_dir: Path, selector, progress_cb=None):
        if progress_cb:
            progress_cb("downloading", "Baixando...", 30.0)
        time.sleep(0.35)   # janela sem novidade -> deve sair keep-alive
        out = dest_dir / "video.mp4"
        out.write_bytes(b"x" * 32)
        return out

    monkeypatch.setattr(instagram_mod, "_default_downloader", _slow)

    r = client.post("/api/download", json={"url": REEL, "format": "video", "quality": "720p"})
    job_id = r.json()["job_id"]

    saw_keepalive = False
    with client.stream("GET", f"/api/download/{job_id}/events") as resp:
        for line in resp.iter_lines():
            text = line if isinstance(line, str) else (line or b"").decode()
            if text.startswith(": "):
                saw_keepalive = True
            if text.startswith("data: ") and '"state": "done"' in text:
                break
    assert saw_keepalive
