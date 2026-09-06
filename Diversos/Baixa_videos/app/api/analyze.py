"""Endpoint POST /api/analyze.

Fino de propósito: valida a URL, resolve o serviço da plataforma e devolve os
metadados. Todo erro (URL inválida, plataforma não suportada, conteúdo
indisponível/privado, falha de extração) é convertido em resposta JSON
padronizada pelos exception handlers em app.main.
"""

from __future__ import annotations

from fastapi import APIRouter, Request

from app.api.schemas import AnalyzeRequest, AnalyzeResponse, MediaFormat
from app.core.config import get_settings
from app.core.logger import get_logger
from app.core.ratelimit import analyze_limit, limiter
from app.services.base import ContentInfo
from app.services.registry import get_service
from app.services.validator import validate_url

router = APIRouter(prefix="/api", tags=["analyze"])
log = get_logger(__name__)


def _to_response(content: ContentInfo) -> AnalyzeResponse:
    return AnalyzeResponse(
        platform=content.platform,
        type=content.type,
        title=content.title,
        thumbnail=content.thumbnail,
        duration=content.duration,
        formats=[
            MediaFormat(
                kind=f.kind,
                quality=f.quality,
                ext=f.ext,
                label=f.label,
                filesize=f.filesize,
            )
            for f in content.formats
        ],
        max_size_mb=get_settings().max_media_size_mb,
    )


@router.post("/analyze", response_model=AnalyzeResponse)
@limiter.limit(analyze_limit)
async def analyze(request: Request, payload: AnalyzeRequest) -> AnalyzeResponse:
    validated = validate_url(payload.url)
    service = get_service(validated.platform)
    content = await service.get_metadata(validated)
    log.info("analyze ok: %s/%s", content.platform, content.type)
    return _to_response(content)
