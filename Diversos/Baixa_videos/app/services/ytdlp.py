"""Camada genérica sobre o yt-dlp.

Nenhuma plataforma específica aqui: só o que é comum a qualquer extração via
yt-dlp (opções, execução, normalização de metadados). A classificação de erro
(privado / login / indisponível) fica em cada serviço, que recebe a mensagem
crua via `YtdlpError`.
"""

from __future__ import annotations

from pathlib import Path
from typing import Any, Callable

from app.core.config import get_settings
from app.core.logger import get_logger
from app.services.base import MediaFormatInfo
from app.services.errors import (
    MSG_GENERIC,
    MSG_TOO_LARGE,
    MSG_UNAVAILABLE,
    ContentUnavailableError,
    ExtractionError,
    MediaTooLargeError,
)
from app.services.downloader import largest_file

log = get_logger(__name__)

_TOO_LARGE_MARKERS = ("max-filesize", "larger than", "file is larger")

# (state, message, percent|None) — state em {"downloading", "processing"}.
ProgressCB = Callable[[str, str, "float | None"], None]


def _make_progress_hooks(cb: ProgressCB) -> tuple[list, list]:
    def on_download(d: dict[str, Any]) -> None:
        status = d.get("status")
        if status == "downloading":
            total = d.get("total_bytes") or d.get("total_bytes_estimate")
            done = d.get("downloaded_bytes") or 0
            pct = (done / total * 100) if total else None
            cb("downloading", "Baixando...", pct)
        elif status == "finished":
            cb("processing", "Processando...", None)

    def on_postprocess(d: dict[str, Any]) -> None:
        if d.get("status") in ("started", "processing"):
            cb("processing", "Processando...", None)

    return [on_download], [on_postprocess]


class YtdlpError(Exception):
    """Erro cru vindo do yt-dlp, para o serviço classificar."""

    def __init__(self, raw: str) -> None:
        super().__init__(raw)
        self.raw = raw


def _reraise_ytdlp(exc: Exception) -> None:
    """Converte o erro do yt-dlp: casos genéricos viram YtdlpError (o serviço
    classifica); problemas de ambiente ganham mensagem própria."""
    msg = str(exc)
    low = msg.lower()
    if "cookie database" in low and ("could not copy" in low or "unable to" in low):
        raise ExtractionError(
            "Não foi possível ler os cookies do navegador. Feche o navegador e "
            "tente de novo, ou configure um arquivo de cookies (COOKIES_FILE).",
            reason="browser_cookie_locked",
        ) from None
    if "could not find" in low and "cookies database" in low:
        raise ExtractionError(
            "Navegador ou perfil de cookies não encontrado. Confira o valor de "
            "COOKIES_FROM_BROWSER.",
            reason="browser_not_found",
        ) from None
    raise YtdlpError(msg) from None


def _browser_spec(spec: str) -> tuple | None:
    """"chrome" ou "chrome:Perfil 1" -> tupla do yt-dlp (browser, profile, ...)."""
    spec = (spec or "").strip()
    if not spec:
        return None
    name, _, profile = spec.partition(":")
    return (name.strip().lower(), profile.strip() or None, None, None)


def common_opts(
    *, cookiefile: str | None = None, cookies_from_browser: str | None = None
) -> dict[str, Any]:
    settings = get_settings()
    opts: dict[str, Any] = {
        "quiet": True,
        "no_warnings": True,
        "socket_timeout": settings.request_timeout,
        "extractor_retries": 1,
        "nocheckcertificate": False,
        "noprogress": True,
    }
    # Um arquivo de cookies válido tem prioridade; se não existir, cai para o
    # navegador (config quebrada não deve silenciar a alternativa).
    if cookiefile and Path(cookiefile).is_file():
        opts["cookiefile"] = cookiefile
    else:
        if cookiefile:
            log.warning("arquivo de cookies não encontrado: %s", cookiefile)
        browser = _browser_spec(cookies_from_browser or "")
        if browser:
            opts["cookiesfrombrowser"] = browser
    if settings.ffmpeg_path.strip():
        opts["ffmpeg_location"] = settings.ffmpeg_path.strip()
    return opts


def run_extract(
    url: str, *, cookiefile: str | None = None, cookies_from_browser: str | None = None
) -> dict[str, Any]:
    """Extrai metadados (sem baixar). Levanta YtdlpError / ExtractionError."""
    from yt_dlp import YoutubeDL
    from yt_dlp.utils import DownloadError, ExtractorError

    opts = common_opts(cookiefile=cookiefile, cookies_from_browser=cookies_from_browser)
    opts["noplaylist"] = False   # carrossel: entradas tratadas manualmente
    opts["skip_download"] = True

    try:
        with YoutubeDL(opts) as ydl:
            info = ydl.extract_info(url, download=False)
    except (DownloadError, ExtractorError) as exc:
        _reraise_ytdlp(exc)
    except Exception as exc:
        log.warning("yt-dlp extract erro inesperado: %r", exc)
        raise ExtractionError(MSG_GENERIC, reason="extractor_crash") from exc

    if not info:
        raise ExtractionError(MSG_GENERIC, reason="empty_info")
    return info


def run_download(
    url: str,
    dest_dir: Path,
    selector: str,
    *,
    cookiefile: str | None = None,
    cookies_from_browser: str | None = None,
    max_bytes: int | None = None,
    progress_cb: ProgressCB | None = None,
) -> Path:
    """Baixa a mídia para `dest_dir`. Levanta MediaTooLargeError / YtdlpError."""
    from yt_dlp import YoutubeDL
    from yt_dlp.utils import DownloadError, ExtractorError

    max_bytes = max_bytes or get_settings().max_media_size_bytes
    opts = common_opts(cookiefile=cookiefile, cookies_from_browser=cookies_from_browser)
    opts.update(
        {
            "noplaylist": True,
            "skip_download": False,
            "format": selector,
            "outtmpl": str(dest_dir / "%(id)s.%(ext)s"),
            "max_filesize": max_bytes,
            "merge_output_format": "mp4",
            "restrictfilenames": True,
            "overwrites": True,
        }
    )
    if progress_cb is not None:
        dl_hooks, pp_hooks = _make_progress_hooks(progress_cb)
        opts["progress_hooks"] = dl_hooks
        opts["postprocessor_hooks"] = pp_hooks

    try:
        with YoutubeDL(opts) as ydl:
            ydl.extract_info(url, download=True)
    except (DownloadError, ExtractorError) as exc:
        low = str(exc).lower()
        if any(m in low for m in _TOO_LARGE_MARKERS):
            raise MediaTooLargeError(MSG_TOO_LARGE, reason="ytdlp_max_filesize") from None
        _reraise_ytdlp(exc)
    except Exception as exc:
        log.warning("yt-dlp download erro inesperado: %r", exc)
        raise ExtractionError(MSG_GENERIC, reason="download_crash") from exc

    file = largest_file(dest_dir)
    if file is None:
        raise ExtractionError(MSG_GENERIC, reason="no_output_file")
    return file


# ---------------------------------------------------------------------------
# Normalização de metadados (comum a todas as plataformas)
# ---------------------------------------------------------------------------

def int_or_none(value: Any) -> int | None:
    try:
        return int(value) if value is not None else None
    except (TypeError, ValueError):
        return None


def pick_video_entry(info: dict[str, Any]) -> dict[str, Any]:
    """Carrossel/playlist: devolve a primeira entrada com vídeo."""
    entries = [e for e in (info.get("entries") or []) if e]
    if not entries:
        return info
    for entry in entries:
        if entry.get("duration") is not None or entry.get("formats"):
            return entry
    raise ContentUnavailableError(MSG_UNAVAILABLE, reason="carousel_no_video")


def clean_title(info: dict[str, Any], *, max_len: int = 120) -> str | None:
    title = (info.get("title") or info.get("description") or "").strip() or None
    if title and len(title) > max_len:
        title = title[: max_len - 3].rstrip() + "..."
    return title


def build_video_formats(info: dict[str, Any]) -> list[MediaFormatInfo]:
    by_height: dict[int, MediaFormatInfo] = {}

    for fmt in info.get("formats") or []:
        if fmt.get("vcodec") in (None, "none"):
            continue
        height = int_or_none(fmt.get("height"))
        if not height:
            continue
        size = int_or_none(fmt.get("filesize") or fmt.get("filesize_approx"))
        candidate = MediaFormatInfo(
            kind="video",
            quality=f"{height}p",
            ext=fmt.get("ext") or "mp4",
            label=f"{height}p",
            filesize=size,
            source_id=str(fmt.get("format_id")) if fmt.get("format_id") else None,
        )
        prev = by_height.get(height)
        if prev is None or (candidate.filesize and not prev.filesize):
            by_height[height] = candidate

    if by_height:
        return [by_height[h] for h in sorted(by_height, reverse=True)]

    # Sem lista de formatos utilizável: uma opção "melhor disponível".
    height = int_or_none(info.get("height"))
    return [
        MediaFormatInfo(
            kind="video",
            quality=f"{height}p" if height else "best",
            ext=info.get("ext") or "mp4",
            label=f"{height}p" if height else "Original",
            filesize=int_or_none(info.get("filesize") or info.get("filesize_approx")),
            source_id="best",
        )
    ]
