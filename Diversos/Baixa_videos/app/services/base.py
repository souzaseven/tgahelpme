"""Contrato comum a todas as plataformas.

Cada plataforma (Instagram agora; TikTok/Facebook/YouTube depois) implementa
`PlatformService` sem que o núcleo precise conhecer seus detalhes.
"""

from __future__ import annotations

from abc import ABC, abstractmethod
from dataclasses import dataclass, field

from app.services.downloader import DownloadResult
from app.services.validator import ValidatedURL


@dataclass(frozen=True)
class MediaFormatInfo:
    kind: str          # "video" | "audio"
    quality: str       # chave estável: "1080p", "720p", "mp3"
    ext: str           # "mp4", "mp3"
    label: str         # texto exibido ao usuário
    filesize: int | None = None
    # Identificador interno da fonte (ex.: format_id do yt-dlp). Nunca vai ao
    # frontend; usado só na fase de download.
    source_id: str | None = None


@dataclass(frozen=True)
class ContentInfo:
    platform: str
    type: str          # "reel" | "post" | "video"
    title: str | None
    thumbnail: str | None
    duration: int | None
    formats: list[MediaFormatInfo] = field(default_factory=list)

    @property
    def video_formats(self) -> list[MediaFormatInfo]:
        return [f for f in self.formats if f.kind == "video"]

    @property
    def audio_formats(self) -> list[MediaFormatInfo]:
        return [f for f in self.formats if f.kind == "audio"]


class PlatformService(ABC):
    #: nome da plataforma, igual ao usado em validator.PLATFORM_HOSTS
    name: str = ""

    @abstractmethod
    async def get_metadata(self, url: ValidatedURL) -> ContentInfo:
        """Extrai título, thumbnail, duração e formatos disponíveis."""
        raise NotImplementedError

    @abstractmethod
    async def download(self, url: ValidatedURL, kind: str, quality: str | None) -> DownloadResult:
        """Baixa o conteúdo para um diretório temporário e devolve o arquivo."""
        raise NotImplementedError
