"""Rate limiting por IP (slowapi).

O `limiter` é criado uma vez aqui e reutilizado pelos endpoints e por app.main.
Os limites são lidos das settings em tempo de requisição (funções), para
poderem ser ajustados por ambiente / testes.
"""

from __future__ import annotations

from slowapi import Limiter
from slowapi.util import get_remote_address

from app.core.config import get_settings

# headers_enabled fica desligado: a injeção de X-RateLimit-* pela slowapi exige
# um parâmetro `response: Response` em cada endpoint e quebra quando a rota
# devolve um modelo/FileResponse. O rate limit (429 no excesso) funciona sem isso.
limiter = Limiter(
    key_func=get_remote_address,
    enabled=get_settings().rate_limit_enabled,
)


def analyze_limit() -> str:
    return get_settings().rate_limit_analyze


def download_limit() -> str:
    return get_settings().rate_limit_download
