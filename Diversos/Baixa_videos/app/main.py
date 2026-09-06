"""Ponto de entrada da aplicação FastAPI.

Executar em desenvolvimento:
    uvicorn app.main:app --reload
"""

from __future__ import annotations

from contextlib import asynccontextmanager

from fastapi import FastAPI, Request
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from slowapi.errors import RateLimitExceeded
from slowapi.middleware import SlowAPIMiddleware
from starlette.middleware.cors import CORSMiddleware
from starlette.middleware.trustedhost import TrustedHostMiddleware

from app.api import analyze, download
from app.core.cleanup import CleanupScheduler, sweep_temp
from app.core.config import BASE_DIR, get_settings
from app.core.logger import get_logger, setup_logging
from app.core.middleware import SecurityHeadersMiddleware
from app.core.ratelimit import limiter
from app.services.errors import (
    MSG_GENERIC,
    FeatureUnavailableError,
    MediaTooLargeError,
    ServiceError,
    UnsupportedPlatformError,
)
from app.services.validator import UrlValidationError

setup_logging()
log = get_logger(__name__)
settings = get_settings()

templates = Jinja2Templates(directory=str(BASE_DIR / "templates"))
cleanup = CleanupScheduler()


@asynccontextmanager
async def lifespan(app: FastAPI):
    log.info("Iniciando aplicação (env=%s, temp=%s)", settings.app_env, settings.temp_path)
    sweep_temp()          # varre sobras deixadas por execuções anteriores
    cleanup.start()       # e agenda a varredura periódica
    yield
    await cleanup.stop()
    log.info("Encerrando aplicação")


app = FastAPI(
    title="Downloader de Vídeos e Áudios",
    description="Baixe vídeos e áudios de conteúdos públicos. Fase inicial: Instagram.",
    version="0.1.0",
    lifespan=lifespan,
    docs_url="/docs" if settings.app_debug else None,
    redoc_url=None,
)

app.state.limiter = limiter

# Middlewares (o último adicionado roda primeiro na requisição).
app.add_middleware(SecurityHeadersMiddleware)
app.add_middleware(SlowAPIMiddleware)

if settings.allowed_host_list != ["*"]:
    app.add_middleware(TrustedHostMiddleware, allowed_hosts=settings.allowed_host_list)

if settings.cors_origin_list:
    app.add_middleware(
        CORSMiddleware,
        allow_origins=settings.cors_origin_list,
        allow_methods=["GET", "POST"],
        allow_headers=["Content-Type"],
        max_age=600,
    )

app.mount("/static", StaticFiles(directory=str(BASE_DIR / "static")), name="static")

app.include_router(analyze.router)
app.include_router(download.router)


@app.exception_handler(RateLimitExceeded)
async def on_rate_limit(request: Request, exc: RateLimitExceeded) -> JSONResponse:
    log.info("rate limit atingido em %s por %s", request.url.path, request.client.host if request.client else "?")
    return JSONResponse(
        status_code=429,
        content={"success": False, "error": "Muitas requisições. Aguarde alguns instantes e tente de novo."},
    )


# Status HTTP por tipo de erro de serviço; o que não estiver aqui vira 502.
_SERVICE_ERROR_STATUS: tuple[tuple[type[ServiceError], int], ...] = (
    (UnsupportedPlatformError, 400),
    (FeatureUnavailableError, 501),
    (MediaTooLargeError, 413),
)


def _json_error(status: int, message: str) -> JSONResponse:
    return JSONResponse(status_code=status, content={"success": False, "error": message})


@app.exception_handler(RequestValidationError)
async def on_request_validation_error(request: Request, exc: RequestValidationError) -> JSONResponse:
    # Não expõe o corpo do erro do Pydantic ao usuário final.
    log.info("Requisição malformada em %s", request.url.path)
    return _json_error(422, "Requisição inválida.")


@app.exception_handler(UrlValidationError)
async def on_url_validation_error(request: Request, exc: UrlValidationError) -> JSONResponse:
    log.info("URL rejeitada em %s: %s", request.url.path, exc.reason)
    return _json_error(400, exc.message)


@app.exception_handler(ServiceError)
async def on_service_error(request: Request, exc: ServiceError) -> JSONResponse:
    status = next((s for cls, s in _SERVICE_ERROR_STATUS if isinstance(exc, cls)), 502)
    log.info(
        "Falha de serviço em %s: %s (%s -> %d)",
        request.url.path, exc.reason, type(exc).__name__, status,
    )
    return _json_error(status, exc.message)


@app.exception_handler(Exception)
async def on_unhandled_error(request: Request, exc: Exception) -> JSONResponse:
    log.exception("Erro não tratado em %s", request.url.path)
    return _json_error(500, MSG_GENERIC)


@app.get("/health", include_in_schema=False)
async def health() -> dict[str, str]:
    return {"status": "ok"}


@app.get("/", include_in_schema=False)
async def index(request: Request):
    return templates.TemplateResponse(request, "index.html")
