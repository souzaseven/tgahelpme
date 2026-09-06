"""Serviço base para plataformas atendidas via yt-dlp.

Instagram, TikTok (e futuramente Facebook/YouTube) só precisam declarar:
- `name`
- `_path_types` (segmento da URL -> tipo de conteúdo)
- `_cookiefile()` (opcional)
- `_classify(raw)` (traduz a mensagem crua do yt-dlp no ServiceError certo)

Todo o fluxo (cache, threads, formatos, download, áudio, limpeza) fica aqui.
"""

from __future__ import annotations

import asyncio
from pathlib import Path
from typing import Any, Callable
from urllib.parse import parse_qs, urlparse

from app.core.config import get_settings
from app.core.logger import get_logger
from app.services import media, ytdlp
from app.services.base import ContentInfo, MediaFormatInfo, PlatformService
from app.services.downloader import (
    DownloadResult,
    build_filename,
    cleanup_job_dir,
    format_selector,
    media_type_for,
    new_job_dir,
)
from app.services.errors import (
    MSG_GENERIC,
    MSG_NO_FFMPEG,
    MSG_TOO_LARGE,
    ExtractionError,
    FeatureUnavailableError,
    MediaTooLargeError,
    ServiceError,
)
from app.services.jobs import jobs
from app.services.validator import ValidatedURL
from app.utils.cache import TTLCache
from app.services.ytdlp import ProgressCB

log = get_logger(__name__)

# Injeções para teste (mesma assinatura usada nos serviços concretos).
Extractor = Callable[[str], dict[str, Any]]
Downloader = Callable[..., Path]   # (url, dest_dir, selector, progress_cb=None)
AudioExtractor = Callable[[Path, Path], Path]

_NOOP_PROGRESS: ProgressCB = lambda state, message, percent: None

# Mantém referência forte às tasks de download (o asyncio pode coletar tasks
# sem referência e cancelá-las no meio).
_running_jobs: set[asyncio.Task] = set()

# Cache de metadados compartilhado (chave = URL normalizada, que já inclui o host).
_metadata_cache: TTLCache[ContentInfo] = TTLCache(get_settings().metadata_cache_ttl)


class YtdlpPlatformService(PlatformService):
    name: str = ""
    _path_types: dict[str, str] = {}
    _default_type: str = "video"

    def __init__(
        self,
        extractor: Extractor | None = None,
        cache: TTLCache[ContentInfo] | None = None,
        downloader: Downloader | None = None,
        audio_extractor: AudioExtractor | None = None,
    ) -> None:
        self._extract = extractor or self._build_extractor()
        self._download_impl = downloader or self._build_downloader()
        self._cache = cache if cache is not None else _metadata_cache
        self._audio_extractor = audio_extractor or media.extract_audio

    # ---- hooks que cada plataforma pode sobrescrever --------------------

    def _cookiefile(self) -> str | None:
        value = getattr(get_settings(), f"{self.name}_cookies_file", "").strip()
        return value or None

    def _cookies_from_browser(self) -> str | None:
        value = getattr(get_settings(), f"{self.name}_cookies_from_browser", "").strip()
        return value or None

    def _classify(self, raw: str) -> None:
        """Traduz a mensagem crua do yt-dlp. Deve levantar um ServiceError."""
        raise ExtractionError(MSG_GENERIC, reason="extract_failed")

    # ---- implementações padrão (yt-dlp) -------------------------------

    def _build_extractor(self) -> Extractor:
        def _extract(url: str) -> dict[str, Any]:
            try:
                return ytdlp.run_extract(
                    url,
                    cookiefile=self._cookiefile(),
                    cookies_from_browser=self._cookies_from_browser(),
                )
            except ytdlp.YtdlpError as exc:
                self._classify(exc.raw)
                raise ExtractionError(MSG_GENERIC, reason="unclassified")  # pragma: no cover
        return _extract

    def _build_downloader(self) -> Downloader:
        def _download(
            url: str, dest_dir: Path, selector: str,
            progress_cb: ProgressCB | None = None,
        ) -> Path:
            try:
                return ytdlp.run_download(
                    url, dest_dir, selector,
                    cookiefile=self._cookiefile(),
                    cookies_from_browser=self._cookies_from_browser(),
                    max_bytes=get_settings().max_media_size_bytes,
                    progress_cb=progress_cb,
                )
            except ytdlp.YtdlpError as exc:
                self._classify(exc.raw)
                raise ExtractionError(MSG_GENERIC, reason="unclassified")  # pragma: no cover
        return _download

    # ---- helpers ------------------------------------------------------

    def _resolve_type(self, url: ValidatedURL) -> str:
        for segment in url.path.strip("/").split("/"):
            if segment in self._path_types:
                return self._path_types[segment]
        return self._default_type

    @staticmethod
    def _shortcode(url: ValidatedURL) -> str:
        parsed = urlparse(url.normalized)
        parts = [p for p in parsed.path.split("/") if p]
        last = parts[-1] if parts else ""
        if last and not last.endswith(".php") and last != "watch":
            return last
        q = parse_qs(parsed.query)
        for key in ("v", "story_fbid", "id"):
            if q.get(key):
                return q[key][0]
        return last or "video"

    # ---- API do contrato -------------------------------------------

    async def get_metadata(self, url: ValidatedURL) -> ContentInfo:
        cached = self._cache.get(url.normalized)
        if cached is not None:
            log.info("metadata %s: cache hit", self.name)
            return cached

        try:
            raw_info = await asyncio.to_thread(self._extract, url.normalized)
        except ServiceError:
            raise
        except Exception as exc:
            log.warning("extrator %s falhou: %r", self.name, exc)
            raise ExtractionError(MSG_GENERIC, reason="extractor_crash") from exc

        if not raw_info:
            raise ExtractionError(MSG_GENERIC, reason="empty_info")

        info = ytdlp.pick_video_entry(raw_info)
        formats = ytdlp.build_video_formats(info) + [
            MediaFormatInfo(kind="audio", quality="mp3", ext="mp3",
                            label="MP3", source_id="audio")
        ]

        content = ContentInfo(
            platform=self.name,
            type=self._resolve_type(url),
            title=ytdlp.clean_title(info),
            thumbnail=info.get("thumbnail"),
            duration=ytdlp.int_or_none(info.get("duration")),
            formats=formats,
        )
        self._cache.set(url.normalized, content)
        log.info(
            "metadata %s: type=%s formats=%d dur=%s",
            self.name, content.type, len(content.formats), content.duration,
        )
        return content

    def _precheck(self, kind: str) -> None:
        """Falhas que devem virar status HTTP na hora (antes do job assíncrono)."""
        if kind not in ("video", "audio"):
            raise ExtractionError(MSG_GENERIC, reason=f"bad_kind:{kind}")
        if kind == "audio" and not media.ffmpeg_available():
            raise FeatureUnavailableError(MSG_NO_FFMPEG, reason="ffmpeg_missing")

    async def start_download(
        self, url: ValidatedURL, kind: str, quality: str | None
    ) -> str:
        """Cria um job, dispara o download em segundo plano e devolve o job_id."""
        self._precheck(kind)
        job = jobs.create()
        task = asyncio.create_task(self._run_job(job.id, url, kind, quality))
        _running_jobs.add(task)
        task.add_done_callback(_running_jobs.discard)
        return job.id

    async def _run_job(
        self, job_id: str, url: ValidatedURL, kind: str, quality: str | None
    ) -> None:
        def report(state: str, message: str, percent: float | None) -> None:
            jobs.update(job_id, state=state, message=message, percent=percent)

        try:
            jobs.update(job_id, state="downloading", message="Preparando...", percent=None)
            result = await self._produce(url, kind, quality, report)
            jobs.update(
                job_id, state="done", message="Download pronto.",
                percent=100.0, result=result,
            )
        except ServiceError as exc:
            log.info("job %s falhou: %s", job_id, exc.reason)
            jobs.update(job_id, state="error", message=exc.message, error=exc.message)
        except Exception:
            log.exception("job %s: erro não tratado", job_id)
            jobs.update(job_id, state="error", message=MSG_GENERIC, error=MSG_GENERIC)

    async def download(
        self, url: ValidatedURL, kind: str, quality: str | None
    ) -> DownloadResult:
        """Versão bloqueante (sem progresso). Usada por chamadas diretas/testes."""
        self._precheck(kind)
        return await self._produce(url, kind, quality, _NOOP_PROGRESS)

    async def _produce(
        self,
        url: ValidatedURL,
        kind: str,
        quality: str | None,
        progress_cb: ProgressCB,
    ) -> DownloadResult:
        selector = format_selector(kind, quality, allow_merge=media.ffmpeg_available())
        job_id, job_dir = new_job_dir()
        max_bytes = get_settings().max_media_size_bytes
        log.info("download %s: job=%s kind=%s selector=%s", self.name, job_id, kind, selector)

        try:
            source = await asyncio.to_thread(
                self._download_impl, url.normalized, job_dir, selector, progress_cb
            )
            if source.stat().st_size > max_bytes:
                raise MediaTooLargeError(MSG_TOO_LARGE, reason="post_check")

            if kind == "audio":
                progress_cb("processing", "Processando áudio...", None)
                out = job_dir / "audio.mp3"
                await asyncio.to_thread(self._audio_extractor, source, out)
                source.unlink(missing_ok=True)
                suffix_kind = "audio"
            else:
                out = source
                suffix_kind = "video"

            stem = f"{self.name}_{self._resolve_type(url)}_{self._shortcode(url)}"
            return DownloadResult(
                path=out,
                filename=build_filename(stem, out.suffix, fallback=f"{self.name}_{suffix_kind}"),
                size=out.stat().st_size,
                media_type=media_type_for(out.suffix),
                job_dir=job_dir,
            )
        except ServiceError:
            cleanup_job_dir(job_dir)
            raise
        except Exception as exc:
            cleanup_job_dir(job_dir)
            log.warning("download %s falhou: %r", self.name, exc)
            raise ExtractionError(MSG_GENERIC, reason="download_wrapper") from exc
