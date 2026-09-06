"""Camada de processamento de mídia (FFmpeg).

Genérico, sem conhecer plataformas. Sempre invoca o FFmpeg com lista de
argumentos via subprocess.run (nunca `shell=True`, nunca concatenação de
strings) — ver "COMMAND INJECTION" no prompt do projeto.
"""

from __future__ import annotations

import shutil
import subprocess
from functools import lru_cache
from pathlib import Path

from app.core.config import get_settings
from app.core.logger import get_logger
from app.services.errors import (
    MSG_MEDIA_FAIL,
    MSG_NO_FFMPEG,
    FeatureUnavailableError,
    MediaProcessingError,
)

log = get_logger(__name__)


@lru_cache
def resolve_ffmpeg() -> str | None:
    """Caminho do executável do FFmpeg, ou None se não encontrado."""
    configured = get_settings().ffmpeg_path.strip()
    if configured:
        p = Path(configured)
        if p.is_file():
            return str(p)
        found = shutil.which(configured)
        if found:
            return found
        log.warning("FFMPEG_PATH definido mas inválido: %s", configured)
        return None
    return shutil.which("ffmpeg")


def ffmpeg_available() -> bool:
    return resolve_ffmpeg() is not None


def extract_audio(src: Path, dest: Path, *, bitrate: str | None = None) -> Path:
    """Extrai a trilha de áudio de `src` para um MP3 em `dest`.

    Levanta FeatureUnavailableError se não houver FFmpeg, MediaProcessingError
    se a conversão falhar.
    """
    ffmpeg = resolve_ffmpeg()
    if ffmpeg is None:
        raise FeatureUnavailableError(MSG_NO_FFMPEG, reason="ffmpeg_missing")

    bitrate = bitrate or get_settings().audio_bitrate
    timeout = max(get_settings().request_timeout * 3, 60)

    cmd = [
        ffmpeg,
        "-nostdin",
        "-hide_banner",
        "-loglevel", "error",
        "-y",
        "-i", str(src),
        "-vn",
        "-acodec", "libmp3lame",
        "-b:a", bitrate,
        str(dest),
    ]

    try:
        proc = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            timeout=timeout,
            check=False,
        )
    except subprocess.TimeoutExpired as exc:
        raise MediaProcessingError(MSG_MEDIA_FAIL, reason="ffmpeg_timeout") from exc
    except OSError as exc:
        raise MediaProcessingError(MSG_MEDIA_FAIL, reason="ffmpeg_spawn") from exc

    if proc.returncode != 0 or not dest.is_file() or dest.stat().st_size == 0:
        # stderr do ffmpeg vai só para o log, nunca para o usuário.
        log.warning("ffmpeg falhou (rc=%s): %s", proc.returncode, proc.stderr[:500])
        dest.unlink(missing_ok=True)
        raise MediaProcessingError(MSG_MEDIA_FAIL, reason="ffmpeg_returncode")

    return dest
