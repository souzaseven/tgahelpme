"""Testes da varredura de sobras em storage/temp."""

from __future__ import annotations

import asyncio
import os
import time
from pathlib import Path

import pytest

from app.core import cleanup
from app.core.config import Settings, get_settings


@pytest.fixture
def temp_root(tmp_path: Path, monkeypatch: pytest.MonkeyPatch) -> Path:
    """Aponta settings.temp_path para um diretório de teste isolado."""
    monkeypatch.setattr(Settings, "temp_path", property(lambda self: tmp_path))
    (tmp_path / ".gitkeep").write_text("")
    return tmp_path


def _set(monkeypatch: pytest.MonkeyPatch, **fields) -> None:
    settings = get_settings()
    for key, value in fields.items():
        monkeypatch.setattr(settings, key, value)


def _aged_dir(base: Path, name: str, age_seconds: float) -> Path:
    d = base / name
    d.mkdir()
    (d / "source.mp4").write_bytes(b"x" * 1024)
    old = time.time() - age_seconds
    os.utime(d / "source.mp4", (old, old))
    os.utime(d, (old, old))
    return d


def test_remove_apenas_expirados(temp_root: Path, monkeypatch: pytest.MonkeyPatch) -> None:
    _set(monkeypatch, download_ttl=600)

    velho = _aged_dir(temp_root, "velho", 1200)
    novo = _aged_dir(temp_root, "novo", 60)

    removed = cleanup.sweep_temp()

    assert removed == 1
    assert not velho.exists()
    assert novo.exists()
    assert (temp_root / ".gitkeep").exists()


def test_preserva_gitkeep(temp_root: Path, monkeypatch: pytest.MonkeyPatch) -> None:
    _set(monkeypatch, download_ttl=600)
    _aged_dir(temp_root, "antigo", 99999)
    cleanup.sweep_temp()
    assert (temp_root / ".gitkeep").exists()


def test_teto_de_espaco(temp_root: Path, monkeypatch: pytest.MonkeyPatch) -> None:
    # Nada expira por idade; teto de ~2 KB força remoção do mais antigo.
    _set(monkeypatch, download_ttl=100_000, max_temp_total_mb=0.002)

    a = _aged_dir(temp_root, "a", 300)   # mais antigo
    _aged_dir(temp_root, "b", 200)
    c = _aged_dir(temp_root, "c", 100)   # mais novo

    cleanup.sweep_temp()

    assert not a.exists()
    assert c.exists()


def test_sweep_sem_diretorio(monkeypatch: pytest.MonkeyPatch, tmp_path: Path) -> None:
    ausente = tmp_path / "nao-existe"
    monkeypatch.setattr(Settings, "temp_path", property(lambda self: ausente))
    assert cleanup.sweep_temp() == 0


def test_scheduler_start_stop() -> None:
    async def scenario() -> None:
        sched = cleanup.CleanupScheduler()
        sched.start()
        assert sched._task is not None
        sched.start()  # idempotente
        await sched.stop()
        assert sched._task is None
        await sched.stop()  # não explode se já parado

    asyncio.run(scenario())
