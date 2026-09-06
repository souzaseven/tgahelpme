"""Validação e normalização de URLs de entrada.

Responsabilidades:
- rejeitar entrada malformada (esquema, tamanho, caracteres, host);
- identificar a plataforma a partir do host (allowlist, nunca "contém X");
- aplicar a checagem anti-SSRF (core.security);
- devolver uma URL canônica (sem query de rastreamento / fragmento).

Não faz requisições HTTP nem conhece a lógica de extração de cada plataforma.
"""

from __future__ import annotations

import re
from dataclasses import dataclass
from urllib.parse import parse_qsl, urlencode, urlparse, urlunparse

from app.core.logger import get_logger
from app.core.security import (
    HostResolutionError,
    UnsafeHostError,
    assert_public_host,
)

log = get_logger(__name__)

MAX_URL_LENGTH = 2048

# Allowlist de hosts por plataforma. Comparação exata (após lower()).
PLATFORM_HOSTS: dict[str, frozenset[str]] = {
    "instagram": frozenset({"instagram.com", "www.instagram.com", "m.instagram.com"}),
    "tiktok": frozenset({
        "tiktok.com", "www.tiktok.com", "m.tiktok.com",
        "vm.tiktok.com", "vt.tiktok.com",
    }),
    "facebook": frozenset({
        "facebook.com", "www.facebook.com", "web.facebook.com",
        "m.facebook.com", "mobile.facebook.com", "fb.watch",
    }),
    "youtube": frozenset({
        "youtube.com", "www.youtube.com", "m.youtube.com",
        "music.youtube.com", "youtu.be", "www.youtube-nocookie.com",
    }),
}

# Hosts conhecidos de plataformas ainda não suportadas — para mensagem específica.
KNOWN_UNSUPPORTED_HOSTS: dict[str, str] = {
    "twitter.com": "X (Twitter)",
    "x.com": "X (Twitter)",
    "vimeo.com": "Vimeo",
    "www.vimeo.com": "Vimeo",
    "dailymotion.com": "Dailymotion",
    "www.dailymotion.com": "Dailymotion",
}

# Parâmetros de query que carregam o ID do conteúdo (preservados na URL canônica).
KEPT_QUERY_KEYS: dict[str, frozenset[str]] = {
    "facebook": frozenset({"v", "story_fbid", "id"}),
    "youtube": frozenset({"v"}),
}

# Caminho de um Reel / post / IGTV público.
INSTAGRAM_PATH_RE = re.compile(
    r"^/(?:[A-Za-z0-9_.]+/)?(?:reel|reels|p|tv)/[A-Za-z0-9_-]+/?$"
)
# TikTok: /@usuario/video/123..., /@usuario/photo/123... ou link curto /XXXX, /t/XXXX.
TIKTOK_PATH_RE = re.compile(
    r"^/(?:@[\w.-]+/(?:video|photo)/\d+|t/[A-Za-z0-9]+|[A-Za-z0-9]{5,})/?$"
)
# Facebook: reel, vídeos de página, posts, links /share/. /watch e /story.php
# validam o ID pela query (v / story_fbid), tratados à parte.
FACEBOOK_PATH_RE = re.compile(
    r"^/(?:reel/\d+|[\w.-]+/videos/\d+|videos/\d+|"
    r"[\w.-]+/posts/[\w.-]+|share/[vr]/[\w-]+)/?$"
)
_FB_QUERY_PATHS = ("/watch", "/watch/", "/story.php", "/story.php/",
                   "/permalink.php", "/permalink.php/")
_FB_WATCH_SHORT_RE = re.compile(r"^/[\w-]+/?$")   # apenas para o host fb.watch

# YouTube: /shorts/ID, /live/ID, /embed/ID, /v/ID; /watch valida o ID por ?v=;
# youtu.be/ID tem o ID no próprio path.
YOUTUBE_PATH_RE = re.compile(r"^/(?:shorts|live|embed|v)/[\w-]{6,}/?$")
_YT_QUERY_PATHS = ("/watch", "/watch/")
_YOUTU_BE_RE = re.compile(r"^/[\w-]{6,}/?$")   # apenas para o host youtu.be

_CONTROL_CHARS_RE = re.compile(r"[\x00-\x1f\x7f]")


class UrlValidationError(Exception):
    """Erro de validação com mensagem segura para exibir ao usuário.

    `reason` é um código curto para log/telemetria, nunca exibido.
    """

    def __init__(self, message: str, *, reason: str) -> None:
        super().__init__(reason)
        self.message = message
        self.reason = reason


@dataclass(frozen=True)
class ValidatedURL:
    normalized: str
    platform: str
    host: str
    path: str


def _canonical(scheme: str, host: str, path: str, query: str = "") -> str:
    return urlunparse((scheme, host, path.rstrip("/") or "/", "", query, ""))


def validate_url(raw: str, *, dns_check: bool = True) -> ValidatedURL:
    """Valida `raw` e devolve uma URL canônica de plataforma suportada.

    Levanta UrlValidationError em qualquer condição inválida.
    """
    if not raw or not raw.strip():
        raise UrlValidationError("Cole um link para continuar.", reason="empty")

    candidate = raw.strip()

    if len(candidate) > MAX_URL_LENGTH:
        raise UrlValidationError("Link inválido.", reason="too_long")

    if _CONTROL_CHARS_RE.search(candidate) or " " in candidate:
        raise UrlValidationError("Link inválido.", reason="bad_chars")

    try:
        parsed = urlparse(candidate)
    except ValueError:
        raise UrlValidationError("Link inválido.", reason="unparseable") from None

    if parsed.scheme.lower() != "https":
        # Bloqueia http://, file://, ftp://, data:, etc.
        raise UrlValidationError("Link inválido.", reason=f"scheme:{parsed.scheme or 'none'}")

    if "@" in parsed.netloc:
        raise UrlValidationError("Link inválido.", reason="userinfo")

    if parsed.port not in (None, 443):
        raise UrlValidationError("Link inválido.", reason=f"port:{parsed.port}")

    host = (parsed.hostname or "").lower().strip(".")
    if not host:
        raise UrlValidationError("Link inválido.", reason="no_host")

    platform = _identify_platform(host)

    # Anti-SSRF: o host precisa resolver só para IPs públicos.
    if dns_check:
        try:
            assert_public_host(host)
        except UnsafeHostError as exc:
            log.warning("host bloqueado por SSRF: %s", exc)
            raise UrlValidationError("Link inválido.", reason="ssrf_blocked") from None
        except HostResolutionError:
            raise UrlValidationError(
                "Não foi possível acessar esse conteúdo.", reason="dns_failed"
            ) from None

    path = parsed.path or "/"

    allowed_keys = KEPT_QUERY_KEYS.get(platform, frozenset())
    kept = sorted(
        (k, v) for k, v in parse_qsl(parsed.query) if k in allowed_keys and v
    )
    query_dict = dict(kept)

    _validate_platform_path(platform, host, path, query_dict)

    return ValidatedURL(
        normalized=_canonical("https", host, path, urlencode(kept)),
        platform=platform,
        host=host,
        path=path,
    )


def validate_instagram_url(raw: str, *, dns_check: bool = True) -> ValidatedURL:
    result = validate_url(raw, dns_check=dns_check)
    if result.platform != "instagram":
        raise UrlValidationError(
            "Essa plataforma ainda não é suportada.", reason="not_instagram"
        )
    return result


def _identify_platform(host: str) -> str:
    for platform, hosts in PLATFORM_HOSTS.items():
        if host in hosts:
            return platform

    if host in KNOWN_UNSUPPORTED_HOSTS:
        name = KNOWN_UNSUPPORTED_HOSTS[host]
        raise UrlValidationError(
            f"{name} ainda não é suportado.", reason=f"unsupported:{name.lower()}"
        )

    raise UrlValidationError("Link inválido.", reason="unknown_host")


def _validate_platform_path(
    platform: str, host: str, path: str, query: dict[str, str]
) -> None:
    if platform == "instagram" and not INSTAGRAM_PATH_RE.match(path):
        raise UrlValidationError(
            "Cole o link de um Reel ou vídeo público do Instagram.",
            reason="instagram_path",
        )
    if platform == "tiktok" and not TIKTOK_PATH_RE.match(path):
        raise UrlValidationError(
            "Cole o link de um vídeo público do TikTok.",
            reason="tiktok_path",
        )
    if platform == "facebook" and not _facebook_path_ok(host, path, query):
        raise UrlValidationError(
            "Cole o link de um vídeo público do Facebook.",
            reason="facebook_path",
        )
    if platform == "youtube" and not _youtube_path_ok(host, path, query):
        raise UrlValidationError(
            "Cole o link de um vídeo do YouTube.",
            reason="youtube_path",
        )


def _facebook_path_ok(host: str, path: str, query: dict[str, str]) -> bool:
    if host == "fb.watch":
        return bool(_FB_WATCH_SHORT_RE.match(path))
    if FACEBOOK_PATH_RE.match(path):
        return True
    if path in _FB_QUERY_PATHS:
        return "v" in query or "story_fbid" in query
    return False


def _youtube_path_ok(host: str, path: str, query: dict[str, str]) -> bool:
    if host == "youtu.be":
        return bool(_YOUTU_BE_RE.match(path))
    if path in _YT_QUERY_PATHS:
        return "v" in query
    return bool(YOUTUBE_PATH_RE.match(path))
