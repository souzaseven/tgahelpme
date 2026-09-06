"""Testes dos exception handlers centrais (app.main).

Garante: status HTTP certo por tipo de erro, corpo JSON padronizado
({"success": false, "error": "..."}) e ZERO vazamento de stack trace.
"""

from __future__ import annotations

import pytest
from fastapi.testclient import TestClient

import app.api.analyze as analyze_mod
import app.api.download as download_mod
from app.main import app
from app.services.errors import (
    ContentAuthRequiredError,
    ContentUnavailableError,
    FeatureUnavailableError,
)

client = TestClient(app, raise_server_exceptions=False)
REEL = "https://www.instagram.com/reel/Dc6Q0IuAVCa/"


@pytest.fixture
def no_dns(monkeypatch: pytest.MonkeyPatch):
    for mod in (analyze_mod, download_mod):
        real = mod.validate_url
        monkeypatch.setattr(mod, "validate_url",
                            lambda raw, _r=real: _r(raw, dns_check=False))


class FakeService:
    def __init__(self, exc: Exception) -> None:
        self._exc = exc

    async def get_metadata(self, url):
        raise self._exc

    async def download(self, url, kind, quality):
        raise self._exc

    async def start_download(self, url, kind, quality):
        raise self._exc


def _no_trace(body: str) -> bool:
    lowered = body.lower()
    return "traceback" not in lowered and "file \"" not in lowered


# ---------- validação de URL ----------

def test_analyze_url_invalida() -> None:
    r = client.post("/api/analyze", json={"url": "http://www.instagram.com/reel/x/"})
    assert r.status_code == 400
    assert r.json() == {"success": False, "error": "Link inválido."}


def test_analyze_plataforma_nao_suportada() -> None:
    r = client.post("/api/analyze", json={"url": "https://vimeo.com/123456"})
    assert r.status_code == 400
    assert "Vimeo" in r.json()["error"]


def test_requisicao_malformada_vira_422() -> None:
    r = client.post("/api/analyze", json={})  # falta "url"
    assert r.status_code == 422
    assert r.json() == {"success": False, "error": "Requisição inválida."}


# ---------- erros de serviço ----------

def test_analyze_auth_required_vira_502(monkeypatch: pytest.MonkeyPatch, no_dns) -> None:
    monkeypatch.setattr(
        analyze_mod, "get_service",
        lambda platform: FakeService(ContentAuthRequiredError("exige login", reason="x")),
    )
    r = client.post("/api/analyze", json={"url": REEL})
    assert r.status_code == 502
    assert r.json() == {"success": False, "error": "exige login"}


def test_analyze_indisponivel_vira_502(monkeypatch: pytest.MonkeyPatch, no_dns) -> None:
    monkeypatch.setattr(
        analyze_mod, "get_service",
        lambda platform: FakeService(ContentUnavailableError("sumiu", reason="x")),
    )
    r = client.post("/api/analyze", json={"url": REEL})
    assert r.status_code == 502


def test_analyze_erro_inesperado_vira_500_sem_trace(
    monkeypatch: pytest.MonkeyPatch, no_dns
) -> None:
    monkeypatch.setattr(
        analyze_mod, "get_service",
        lambda platform: FakeService(RuntimeError("segredo interno boom")),
    )
    r = client.post("/api/analyze", json={"url": REEL})
    assert r.status_code == 500
    assert r.json() == {"success": False, "error": "Não foi possível processar o link."}
    assert "boom" not in r.text and _no_trace(r.text)


def test_download_precheck_feature_unavailable_vira_501(
    monkeypatch: pytest.MonkeyPatch, no_dns
) -> None:
    # Erro no start_download (precheck) sai como status HTTP no POST.
    monkeypatch.setattr(
        download_mod, "get_service",
        lambda platform: FakeService(FeatureUnavailableError("sem ffmpeg", reason="x")),
    )
    r = client.post("/api/download", json={"url": REEL, "format": "audio"})
    assert r.status_code == 501


def test_download_precheck_erro_generico_vira_500(
    monkeypatch: pytest.MonkeyPatch, no_dns
) -> None:
    monkeypatch.setattr(
        download_mod, "get_service",
        lambda platform: FakeService(RuntimeError("boom interno")),
    )
    r = client.post("/api/download", json={"url": REEL, "format": "video"})
    assert r.status_code == 500
    assert "boom" not in r.text and _no_trace(r.text)
