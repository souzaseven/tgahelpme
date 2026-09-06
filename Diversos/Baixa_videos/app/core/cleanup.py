"""Limpeza de arquivos temporários.

Cada download já apaga o próprio diretório de trabalho após a entrega. Aqui
tratamos as SOBRAS: operações abortadas no meio (conexão caiu, processo
morto) que deixaram diretórios em storage/temp.

- `sweep_temp()`: remove entradas mais antigas que DOWNLOAD_TTL e, se ainda
  passar de MAX_TEMP_TOTAL_MB, remove as mais antigas até caber.
- `CleanupScheduler`: roda `sweep_temp()` periodicamente enquanto o app vive.
"""

from __future__ import annotations

import asyncio
import shutil
import time
from pathlib import Path

from app.core.config import get_settings
from app.core.logger import get_logger

log = get_logger(__name__)

# Nunca remover estas entradas diretas de storage/temp.
_KEEP = {".gitkeep", ".gitignore"}


def _entry_size(path: Path) -> int:
    if path.is_file():
        try:
            return path.stat().st_size
        except OSError:
            return 0
    total = 0
    for child in path.rglob("*"):
        if child.is_file():
            try:
                total += child.stat().st_size
            except OSError:
                pass
    return total


def _remove(path: Path) -> bool:
    try:
        if path.is_dir():
            shutil.rmtree(path, ignore_errors=True)
        else:
            path.unlink(missing_ok=True)
        return True
    except OSError as exc:  # pragma: no cover - defensivo
        log.warning("não foi possível remover %s: %r", path, exc)
        return False


def sweep_temp(*, now: float | None = None) -> int:
    """Remove sobras. Devolve quantas entradas foram apagadas."""
    settings = get_settings()
    root = settings.temp_path
    if not root.is_dir():
        return 0

    now = now if now is not None else time.time()
    ttl = settings.download_ttl
    removed = 0

    # Jobs de download expirados (registro em memória + diretório).
    from app.services.jobs import jobs as _job_store
    removed += _job_store.sweep()

    entries: list[tuple[float, Path]] = []
    for entry in root.iterdir():
        if entry.name in _KEEP:
            continue
        try:
            mtime = entry.stat().st_mtime
        except OSError:
            continue
        entries.append((mtime, entry))

    # 1) Expirados por idade.
    survivors: list[tuple[float, Path]] = []
    for mtime, entry in entries:
        if now - mtime > ttl:
            if _remove(entry):
                removed += 1
        else:
            survivors.append((mtime, entry))

    # 2) Teto de espaço total: apaga do mais antigo para o mais novo.
    cap = settings.max_temp_total_bytes
    if cap > 0 and survivors:
        sizes = {entry: _entry_size(entry) for _, entry in survivors}
        total = sum(sizes.values())
        for mtime, entry in sorted(survivors, key=lambda t: t[0]):
            if total <= cap:
                break
            if _remove(entry):
                removed += 1
                total -= sizes[entry]

    if removed:
        log.info("cleanup: %d entrada(s) removida(s) de %s", removed, root)
    return removed


class CleanupScheduler:
    """Loop assíncrono que chama `sweep_temp()` a cada `cleanup_interval` s."""

    def __init__(self) -> None:
        self._task: asyncio.Task[None] | None = None

    async def _run(self) -> None:
        interval = max(get_settings().cleanup_interval, 10)
        while True:
            try:
                await asyncio.sleep(interval)
                await asyncio.to_thread(sweep_temp)
            except asyncio.CancelledError:
                raise
            except Exception as exc:  # nunca deixa o loop morrer
                log.warning("cleanup: falha na varredura: %r", exc)

    def start(self) -> None:
        if self._task is None or self._task.done():
            self._task = asyncio.create_task(self._run(), name="cleanup-scheduler")

    async def stop(self) -> None:
        if self._task is None:
            return
        self._task.cancel()
        try:
            await self._task
        except asyncio.CancelledError:
            pass
        self._task = None
