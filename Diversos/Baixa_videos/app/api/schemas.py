"""Modelos de entrada e saída da API (contratos entre backend e frontend)."""

from __future__ import annotations

from typing import Literal

from pydantic import BaseModel, Field


class AnalyzeRequest(BaseModel):
    url: str = Field(min_length=8, max_length=2048)


class MediaFormat(BaseModel):
    kind: Literal["video", "audio"]
    quality: str            # ex.: "1080p", "720p", "mp3"
    ext: str                # ex.: "mp4", "mp3"
    label: str              # texto exibido ao usuário
    filesize: int | None = None


class AnalyzeResponse(BaseModel):
    success: bool = True
    platform: str
    type: str               # ex.: "reel", "video", "post"
    title: str | None = None
    thumbnail: str | None = None
    duration: int | None = None   # segundos
    formats: list[MediaFormat] = []
    max_size_mb: int              # limite de tamanho por download (MAX_MEDIA_SIZE_MB)


class DownloadRequest(BaseModel):
    url: str = Field(min_length=8, max_length=2048)
    format: Literal["video", "audio"]
    quality: str | None = None


class ErrorResponse(BaseModel):
    success: bool = False
    error: str              # mensagem amigável, sem detalhes internos
