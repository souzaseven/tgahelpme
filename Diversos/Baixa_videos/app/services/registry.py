"""Resolução plataforma -> serviço.

O núcleo chama `get_service(platform)` e recebe a implementação correta sem
conhecer detalhes de cada plataforma. Novas plataformas entram só aqui.
"""

from __future__ import annotations

from app.services.base import PlatformService
from app.services.errors import UnsupportedPlatformError
from app.services.facebook import FacebookService
from app.services.instagram import InstagramService
from app.services.tiktok import TikTokService
from app.services.youtube import YouTubeService

_REGISTRY: dict[str, type[PlatformService]] = {
    "instagram": InstagramService,
    "tiktok": TikTokService,
    "facebook": FacebookService,
    "youtube": YouTubeService,
}


def get_service(platform: str) -> PlatformService:
    service_cls = _REGISTRY.get(platform)
    if service_cls is None:
        raise UnsupportedPlatformError(
            "Essa plataforma ainda não é suportada.", reason=f"no_service:{platform}"
        )
    return service_cls()


def supported_platforms() -> tuple[str, ...]:
    return tuple(_REGISTRY)
