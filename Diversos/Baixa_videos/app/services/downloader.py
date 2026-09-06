"""Utilidades genéricas de download, reaproveitáveis entre plataformas.

- criação de diretório de trabalho isolado por operação (ID único);
- resultado tipado do download;
- download HTTP em streaming com timeout e teto de tamanho (para futuras
  plataformas que exponham URL direta; o Instagram baixa via yt-dlp).

Nada aqui é específico de uma plataforma.
"""

from __future__ import annotations

import re
import shutil
from dataclasses import dataclass
from pathlib import Path
from uuid import uuid4

import httpx

from app.core.config import get_settings
from app.core.logger import get_logger
from app.core.security import safe_join, sanitize_filename
from app.services.errors import MSG_TOO_LARGE, ExtractionError, MediaTooLargeError

log = get_logger(__name__)

_MEDIA_TYPES = {
    "mp4": "video/mp4",
    "webm": "video/webm",
    "mkv": "video/x-matroska",
    "mov": "video/quicktime",
    "mp3": "audio/mpeg",
    "m4a": "audio/mp4",
    "aac": "audio/aac",
    "opus": "audio/opus",
}


@dataclass
class DownloadResult:
    path: Path            # arquivo no disco
    filename: str         # nome sugerido ao usuário (já sanitizado)
    size: int             # bytes
    media_type: str
    job_dir: Path         # diretório a limpar depois de entregar


def media_type_for(ext: str) -> str:
    return _MEDIA_TYPES.get(ext.lower().lstrip("."), "application/octet-stream")


def new_job_dir() -> tuple[str, Path]:
    """Cria storage/temp/<id>/ e devolve (id, caminho)."""
    settings = get_settings()
    job_id = uuid4().hex[:16]
    job_dir = safe_join(settings.temp_path, job_id)
    job_dir.mkdir(parents=True, exist_ok=False)
    return job_id, job_dir


def cleanup_job_dir(job_dir: Path) -> None:
    """Remove o diretório de trabalho. Nunca levanta."""
    try:
        shutil.rmtree(job_dir, ignore_errors=True)
    except Exception as exc:  # pragma: no cover - defensivo
        log.warning("falha ao limpar %s: %r", job_dir, exc)


def build_filename(stem: str, ext: str, *, fallback: str = "video") -> str:
    ext = ext.lower().lstrip(".") or "bin"
    safe_stem = sanitize_filename(stem, fallback=fallback)
    return f"{safe_stem}.{ext}"


def largest_file(directory: Path) -> Path | None:
    files = [p for p in directory.iterdir() if p.is_file()]
    if not files:
        return None
    return max(files, key=lambda p: p.stat().st_size)


def http_download(
    url: str,
    dest: Path,
    *,
    max_bytes: int | None = None,
    timeout: float | None = None,
) -> Path:
    """Baixa `url` para `dest` em streaming, abortando se passar de `max_bytes`.

    Uso previsto: plataformas com URL de mídia direta. Levanta MediaTooLargeError
    ou ExtractionError.
    """
    settings = get_settings()
    max_bytes = max_bytes or settings.max_media_size_bytes
    timeout = timeout or settings.request_timeout

    try:
        with httpx.stream("GET", url, timeout=timeout, follow_redirects=True) as resp:
            resp.raise_for_status()

            declared = resp.headers.get("content-length")
            if declared and declared.isdigit() and int(declared) > max_bytes:
                raise MediaTooLargeError(MSG_TOO_LARGE, reason="content_length")

            written = 0
            with dest.open("wb") as fh:
                for chunk in resp.iter_bytes(chunk_size=64 * 1024):
                    written += len(chunk)
                    if written > max_bytes:
                        fh.close()
                        dest.unlink(missing_ok=True)
                        raise MediaTooLargeError(MSG_TOO_LARGE, reason="stream_exceeded")
                    fh.write(chunk)
    except httpx.HTTPError as exc:
        raise ExtractionError("Não foi possível baixar o arquivo.", reason="http_error") from exc

    return dest


def format_selector(kind: str, quality: str | None, *, allow_merge: bool = False) -> str:
    """Traduz (kind, quality) para um seletor de formato do yt-dlp.

    Com `allow_merge` (há FFmpeg), permite `bv*+ba` — necessário no YouTube, onde
    não existem streams progressivos acima de 360p. Sem FFmpeg, restringe a um
    arquivo progressivo único (vídeo + áudio no mesmo container).
    """
    if kind == "audio":
        return "ba/bestaudio/best"

    match = re.match(r"(\d{3,4})", quality or "")
    height = match.group(1) if match else None

    if allow_merge:
        if height:
            return f"bv*[height<={height}]+ba/b[height<={height}]/bv*+ba/b/best"
        return "bv*+ba/b/best"

    if height:
        return (
            f"b[height<={height}][vcodec!=none][acodec!=none]/"
            f"b[height<={height}]/b[vcodec!=none][acodec!=none]/b/best"
        )
    return "b[vcodec!=none][acodec!=none]/b/best"
