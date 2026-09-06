"""Registro em memória dos downloads em andamento (para o progresso via SSE).

Simples de propósito: um dicionário protegido por lock, com expiração. Não é
um sistema de filas — se o volume crescer, trocar por Redis/RQ.
"""

from __future__ import annotations

import threading
import time
import uuid
from dataclasses import dataclass, field
from typing import Literal

from app.services.downloader import DownloadResult, cleanup_job_dir

JobState = Literal["queued", "downloading", "processing", "done", "error"]


@dataclass
class Job:
    id: str
    state: JobState = "queued"
    message: str = "Na fila..."
    percent: float | None = None
    result: DownloadResult | None = None
    error: str | None = None
    created: float = field(default_factory=time.monotonic)
    updated: float = field(default_factory=time.monotonic)

    @property
    def terminal(self) -> bool:
        return self.state in ("done", "error")

    def snapshot(self) -> dict[str, object]:
        """Só campos seguros para o cliente."""
        data: dict[str, object] = {"state": self.state, "message": self.message}
        if self.percent is not None:
            data["percent"] = round(self.percent, 1)
        if self.state == "done" and self.result is not None:
            data["filename"] = self.result.filename
            data["size"] = self.result.size
        if self.state == "error":
            data["error"] = self.error
        return data


class JobStore:
    def __init__(self, *, max_jobs: int = 128, ttl_seconds: float = 900) -> None:
        self._jobs: dict[str, Job] = {}
        self._lock = threading.Lock()
        self._max = max_jobs
        self._ttl = ttl_seconds

    def create(self) -> Job:
        job = Job(id=uuid.uuid4().hex[:16])
        with self._lock:
            self._evict_locked()
            self._jobs[job.id] = job
        return job

    def get(self, job_id: str) -> Job | None:
        with self._lock:
            return self._jobs.get(job_id)

    def update(self, job_id: str, **fields: object) -> None:
        with self._lock:
            job = self._jobs.get(job_id)
            if job is None:
                return
            for key, value in fields.items():
                setattr(job, key, value)
            job.updated = time.monotonic()

    def discard(self, job_id: str) -> None:
        with self._lock:
            self._jobs.pop(job_id, None)

    def sweep(self) -> int:
        """Remove jobs antigos; limpa o diretório de sobras. Devolve a contagem."""
        now = time.monotonic()
        removed = 0
        with self._lock:
            stale = [
                jid for jid, job in self._jobs.items()
                if now - job.updated > self._ttl
            ]
            for jid in stale:
                job = self._jobs.pop(jid)
                if job.result is not None:
                    cleanup_job_dir(job.result.job_dir)
                removed += 1
        return removed

    def _evict_locked(self) -> None:
        if len(self._jobs) < self._max:
            return
        oldest = min(self._jobs, key=lambda k: self._jobs[k].updated)
        job = self._jobs.pop(oldest)
        if job.result is not None:
            cleanup_job_dir(job.result.job_dir)


jobs = JobStore()
