"""Testes do JobStore (registro de downloads em andamento)."""

from __future__ import annotations

import time

from app.services.jobs import Job, JobStore


def test_create_get_update() -> None:
    store = JobStore()
    job = store.create()
    assert store.get(job.id) is job
    assert job.state == "queued"

    store.update(job.id, state="downloading", message="Baixando...", percent=42.0)
    got = store.get(job.id)
    assert got.state == "downloading"
    assert got.percent == 42.0

    store.update("inexistente", state="done")  # não explode


def test_snapshot_so_expõe_campos_seguros() -> None:
    store = JobStore()
    job = store.create()
    store.update(job.id, state="downloading", message="Baixando...", percent=12.345)
    snap = store.get(job.id).snapshot()
    assert snap == {"state": "downloading", "message": "Baixando...", "percent": 12.3}

    store.update(job.id, state="error", error="deu ruim")
    assert store.get(job.id).snapshot()["error"] == "deu ruim"


def test_terminal() -> None:
    assert Job(id="a", state="done").terminal is True
    assert Job(id="a", state="error").terminal is True
    assert Job(id="a", state="downloading").terminal is False


def test_discard() -> None:
    store = JobStore()
    job = store.create()
    store.discard(job.id)
    assert store.get(job.id) is None


def test_evict_ao_encher() -> None:
    store = JobStore(max_jobs=3)
    ids = [store.create().id for _ in range(5)]
    vivos = [i for i in ids if store.get(i) is not None]
    assert len(vivos) <= 3
    assert ids[-1] in vivos           # o mais novo sobrevive


def test_sweep_remove_antigos() -> None:
    store = JobStore(ttl_seconds=0.05)
    job = store.create()
    assert store.sweep() == 0         # ainda fresco
    time.sleep(0.08)
    assert store.sweep() == 1
    assert store.get(job.id) is None
