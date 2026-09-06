"""Configuração central da aplicação, carregada de variáveis de ambiente / .env."""

from __future__ import annotations

from functools import lru_cache
from pathlib import Path

from pydantic_settings import BaseSettings, SettingsConfigDict

# Raiz do projeto (dois níveis acima deste arquivo: app/core/config.py -> app -> raiz)
BASE_DIR = Path(__file__).resolve().parent.parent.parent


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=BASE_DIR / ".env",
        env_file_encoding="utf-8",
        extra="ignore",
    )

    app_env: str = "development"
    app_debug: bool = True

    host: str = "127.0.0.1"
    port: int = 8000

    temp_dir: str = "storage/temp"
    download_ttl: int = 600
    max_media_size_mb: int = 300
    request_timeout: int = 20

    # Varredura periódica de sobras em storage/temp.
    cleanup_interval: int = 300
    max_temp_total_mb: int = 2048

    rate_limit_enabled: bool = True
    rate_limit_analyze: str = "20/minute"
    rate_limit_download: str = "5/minute"

    # Tamanho máximo do corpo aceito em /api/* (bytes).
    max_request_bytes: int = 32_768

    # Vazio = mesma origem apenas (sem CORS). Lista separada por vírgula.
    cors_origins: str = ""
    # "*" = qualquer Host. Em produção, liste os domínios reais.
    allowed_hosts: str = "*"

    ffmpeg_path: str = ""

    # Bitrate do MP3 gerado na extração de áudio.
    audio_bitrate: str = "192k"

    # Caminho para um arquivo de cookies (formato Netscape) da SUA conta, usado
    # apenas para acessar conteúdo público/próprio que você tem autorização de
    # baixar. Vazio = acesso anônimo.
    instagram_cookies_file: str = ""
    tiktok_cookies_file: str = ""
    facebook_cookies_file: str = ""
    youtube_cookies_file: str = ""

    # Alternativa ao arquivo: lê os cookies direto do navegador logado.
    # Ex.: "chrome", "firefox", "edge", "brave" — ou "chrome:Perfil 1".
    # Ignorado se o *_COOKIES_FILE correspondente estiver definido.
    instagram_cookies_from_browser: str = ""
    tiktok_cookies_from_browser: str = ""
    facebook_cookies_from_browser: str = ""
    youtube_cookies_from_browser: str = ""

    # Cache em memória dos metadados por URL (segundos). 0 desativa.
    metadata_cache_ttl: int = 60

    enabled_platforms: tuple[str, ...] = ("instagram", "tiktok", "facebook", "youtube")

    @property
    def temp_path(self) -> Path:
        """Diretório temporário resolvido de forma absoluta."""
        p = Path(self.temp_dir)
        if not p.is_absolute():
            p = BASE_DIR / p
        return p.resolve()

    @property
    def max_media_size_bytes(self) -> int:
        return self.max_media_size_mb * 1024 * 1024

    @property
    def max_temp_total_bytes(self) -> int:
        return self.max_temp_total_mb * 1024 * 1024

    @property
    def is_production(self) -> bool:
        return self.app_env.lower() == "production"

    @property
    def cors_origin_list(self) -> list[str]:
        return [o.strip() for o in self.cors_origins.split(",") if o.strip()]

    @property
    def allowed_host_list(self) -> list[str]:
        return [h.strip() for h in self.allowed_hosts.split(",") if h.strip()] or ["*"]


@lru_cache
def get_settings() -> Settings:
    settings = Settings()
    settings.temp_path.mkdir(parents=True, exist_ok=True)
    return settings
