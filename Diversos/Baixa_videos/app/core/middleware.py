"""Middlewares de segurança HTTP: cabeçalhos e limite de tamanho de corpo."""

from __future__ import annotations

from starlette.requests import Request
from starlette.responses import JSONResponse
from starlette.types import ASGIApp

from app.core.config import get_settings
from app.core.logger import get_logger

log = get_logger(__name__)

# CSP compatível com a página: CSS/JS locais, Google Fonts, e imagens
# (thumbnails do Instagram via https e placeholders via data:).
_CSP = "; ".join(
    [
        "default-src 'self'",
        "base-uri 'none'",
        "frame-ancestors 'none'",
        "form-action 'self'",
        "object-src 'none'",
        "img-src 'self' data: https:",
        "style-src 'self' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com",
        "script-src 'self'",
        "connect-src 'self'",
    ]
)

_STATIC_HEADERS = {
    "X-Content-Type-Options": "nosniff",
    "Referrer-Policy": "no-referrer",
    "X-Frame-Options": "DENY",
    "Cross-Origin-Opener-Policy": "same-origin",
}

# Swagger UI (/docs) precisa de CDN + inline; só existe em debug.
_CSP_EXEMPT_PREFIXES = ("/docs", "/redoc", "/openapi.json")


class SecurityHeadersMiddleware:
    def __init__(self, app: ASGIApp) -> None:
        self.app = app

    async def __call__(self, scope, receive, send):
        if scope["type"] != "http":
            await self.app(scope, receive, send)
            return

        request = Request(scope, receive=receive)

        # Guarda de DoS: rejeita corpo grande em /api/* pelo Content-Length.
        if request.url.path.startswith("/api/"):
            declared = request.headers.get("content-length")
            max_bytes = get_settings().max_request_bytes
            if declared and declared.isdigit() and int(declared) > max_bytes:
                log.info("corpo recusado em %s (%s bytes)", request.url.path, declared)
                response = JSONResponse(
                    status_code=413,
                    content={"success": False, "error": "Requisição muito grande."},
                )
                await response(scope, receive, send)
                return

        exempt = request.url.path.startswith(_CSP_EXEMPT_PREFIXES)

        async def send_with_headers(message):
            if message["type"] == "http.response.start":
                headers = message.setdefault("headers", [])
                existing = {k.decode().lower() for k, _ in headers}
                extra = dict(_STATIC_HEADERS)
                if not exempt:
                    extra["Content-Security-Policy"] = _CSP
                for name, value in extra.items():
                    if name.lower() not in existing:
                        headers.append((name.encode(), value.encode()))
            await send(message)

        await self.app(scope, receive, send_with_headers)
