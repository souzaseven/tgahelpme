"""Endpoints de download.

Fluxo com progresso (SSE):
  POST /api/download                 -> { "job_id": ... }   (202)
  GET  /api/download/{job_id}/events -> text/event-stream (estado + %)
  GET  /api/download/{job_id}/file   -> o arquivo pronto; apaga o job depois

Erros de "não dá nem para começar" (URL inválida, plataforma não suportada,
áudio sem FFmpeg) saem como status HTTP no POST. Erros durante o download
chegam pelo stream de eventos.
"""

from __future__ import annotations

import asyncio
import json
import time
from urllib.parse import quote

from fastapi import APIRouter, Request
from fastapi.responses import FileResponse, JSONResponse, StreamingResponse
from starlette.background import BackgroundTask

from app.api.schemas import DownloadRequest
from app.core.logger import get_logger
from app.core.ratelimit import download_limit, limiter
from app.services.downloader import cleanup_job_dir
from app.services.errors import MSG_GENERIC
from app.services.jobs import jobs
from app.services.registry import get_service
from app.services.validator import validate_url

router = APIRouter(prefix="/api", tags=["download"])
log = get_logger(__name__)

_EVENT_TIMEOUT = 900     # segundos que o stream de progresso fica aberto
_POLL_INTERVAL = 0.4
_HEARTBEAT_SECONDS = 15  # comentário keep-alive quando não há novidade


def _error(status: int, message: str) -> JSONResponse:
    return JSONResponse(status_code=status, content={"success": False, "error": message})


@router.post("/download", status_code=202)
@limiter.limit(download_limit)
async def start_download(request: Request, payload: DownloadRequest) -> JSONResponse:
    validated = validate_url(payload.url)
    service = get_service(validated.platform)
    job_id = await service.start_download(validated, payload.format, payload.quality)
    return JSONResponse(status_code=202, content={"job_id": job_id})


@router.get("/download/{job_id}/events")
async def download_events(job_id: str, request: Request) -> StreamingResponse:
    if jobs.get(job_id) is None:
        return _error(404, "Download expirado ou inexistente.")

    async def stream():
        last: str | None = None
        deadline = time.monotonic() + _EVENT_TIMEOUT
        quiet_since = time.monotonic()
        while True:
            job = jobs.get(job_id)
            if job is None:
                yield _sse({"state": "error", "error": MSG_GENERIC})
                return

            payload = json.dumps(job.snapshot(), ensure_ascii=False)
            now = time.monotonic()
            if payload != last:
                yield _sse_raw(payload)
                last = payload
                quiet_since = now
            elif now - quiet_since >= _HEARTBEAT_SECONDS:
                # Mantém a conexão viva durante fases longas sem novidade
                # (ex.: merge do FFmpeg). Clientes SSE ignoram linhas ": ...".
                yield ": keep-alive\n\n"
                quiet_since = now

            if job.terminal:
                return
            if now > deadline:
                return
            # Se o cliente desconectar, o Starlette cancela este gerador.
            await asyncio.sleep(_POLL_INTERVAL)

    return StreamingResponse(
        stream(),
        media_type="text/event-stream",
        headers={"Cache-Control": "no-cache", "X-Accel-Buffering": "no"},
    )


@router.get("/download/{job_id}/file")
async def download_file(job_id: str):
    job = jobs.get(job_id)
    if job is None:
        return _error(404, "Download expirado ou inexistente.")
    if job.state == "error":
        return _error(502, job.error or MSG_GENERIC)
    if job.state != "done" or job.result is None:
        return _error(409, "O download ainda não terminou.")

    result = job.result
    log.info("entrega do job %s: %s (%d bytes)", job_id, result.filename, result.size)

    ascii_name = result.filename.encode("ascii", "ignore").decode("ascii") or "download"
    disposition = (
        f"attachment; filename=\"{ascii_name}\"; "
        f"filename*=UTF-8''{quote(result.filename)}"
    )

    def _cleanup() -> None:
        cleanup_job_dir(result.job_dir)
        jobs.discard(job_id)

    return FileResponse(
        path=result.path,
        media_type=result.media_type,
        filename=result.filename,
        headers={"Content-Disposition": disposition},
        background=BackgroundTask(_cleanup),
    )


def _sse(obj: dict) -> str:
    return _sse_raw(json.dumps(obj, ensure_ascii=False))


def _sse_raw(data: str) -> str:
    return f"data: {data}\n\n"
