"""Testes de segurança HTTP: cabeçalhos, limite de corpo e rate limit."""

from __future__ import annotations

import pytest
from fastapi.testclient import TestClient

from app.core.config import get_settings
from app.core.ratelimit import limiter
from app.main import app

client = TestClient(app, raise_server_exceptions=False)


# ---------- cabeçalhos ----------

def test_headers_de_seguranca_na_home() -> None:
    r = client.get("/")
    assert r.headers["x-content-type-options"] == "nosniff"
    assert r.headers["referrer-policy"] == "no-referrer"
    assert r.headers["x-frame-options"] == "DENY"
    assert "default-src 'self'" in r.headers["content-security-policy"]


def test_csp_isenta_para_docs() -> None:
    r = client.get("/docs")
    if r.status_code == 200:
        assert "content-security-policy" not in {k.lower() for k in r.headers}


# ---------- limite de corpo ----------

def test_corpo_grande_rejeitado() -> None:
    big = "x" * (get_settings().max_request_bytes + 1024)
    r = client.post("/api/analyze", json={"url": f"https://www.instagram.com/reel/{big}/"})
    assert r.status_code == 413
    assert r.json() == {"success": False, "error": "Requisição muito grande."}


# ---------- rate limit ----------

@pytest.fixture
def rate_limit_on(monkeypatch: pytest.MonkeyPatch):
    monkeypatch.setattr(get_settings(), "rate_limit_analyze", "2/minute")
    limiter.reset()
    limiter.enabled = True
    yield
    limiter.enabled = False
    limiter.reset()


def test_rate_limit_analyze(rate_limit_on) -> None:
    payload = {"url": "https://vimeo.com/1"}  # 400 rápido, sem tocar a rede
    assert client.post("/api/analyze", json=payload).status_code == 400
    assert client.post("/api/analyze", json=payload).status_code == 400
    r = client.post("/api/analyze", json=payload)
    assert r.status_code == 429
    assert r.json()["success"] is False


def test_analyze_sucesso_com_rate_limit_ligado(rate_limit_on, monkeypatch: pytest.MonkeyPatch) -> None:
    """Caminho 200 com o limiter ATIVO — pega regressões na integração slowapi."""
    import app.api.analyze as analyze_mod
    from app.services import ytdlp

    monkeypatch.setattr(
        ytdlp, "run_extract",
        lambda url, **kw: {
            "title": "ok", "duration": 5,
            "formats": [{"format_id": "a", "vcodec": "h264", "height": 720, "ext": "mp4"}],
        },
    )
    real = analyze_mod.validate_url
    monkeypatch.setattr(analyze_mod, "validate_url", lambda raw: real(raw, dns_check=False))

    r = client.post("/api/analyze", json={"url": "https://www.instagram.com/reel/Abc123def/"})
    assert r.status_code == 200
    assert r.json()["platform"] == "instagram"
