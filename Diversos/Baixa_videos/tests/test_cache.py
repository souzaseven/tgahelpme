"""Testes do TTLCache."""

from __future__ import annotations

import time

from app.utils.cache import TTLCache


def test_get_set() -> None:
    c: TTLCache[int] = TTLCache(ttl_seconds=10)
    assert c.get("a") is None
    c.set("a", 1)
    assert c.get("a") == 1


def test_expira() -> None:
    c: TTLCache[int] = TTLCache(ttl_seconds=0.05)
    c.set("a", 1)
    assert c.get("a") == 1
    time.sleep(0.08)
    assert c.get("a") is None


def test_desativado_com_ttl_zero() -> None:
    c: TTLCache[int] = TTLCache(ttl_seconds=0)
    assert c.enabled is False
    c.set("a", 1)
    assert c.get("a") is None


def test_limite_de_entradas() -> None:
    c: TTLCache[int] = TTLCache(ttl_seconds=100, max_entries=3)
    for i in range(5):
        c.set(f"k{i}", i)
    # Nunca passa do teto.
    assert len(c._data) <= 3


def test_clear() -> None:
    c: TTLCache[int] = TTLCache(ttl_seconds=100)
    c.set("a", 1)
    c.clear()
    assert c.get("a") is None
