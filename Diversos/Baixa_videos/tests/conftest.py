"""Fixtures compartilhadas dos testes."""

from __future__ import annotations

import pytest

from app.core.ratelimit import limiter


@pytest.fixture(autouse=True)
def _rate_limiter_off():
    """Desliga o rate limit por padrão; testes específicos religam explicitamente."""
    limiter.reset()
    previous = limiter.enabled
    limiter.enabled = False
    try:
        yield
    finally:
        limiter.enabled = previous
        limiter.reset()
