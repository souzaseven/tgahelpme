"""Cache simples com expiração por tempo (TTL), seguro para uso concorrente.

Uso interno: evitar re-extrair metadados da mesma URL em poucos segundos.
Não substitui um cache distribuído — isso só entra se/quando houver volume.
"""

from __future__ import annotations

import threading
import time
from typing import Generic, TypeVar

V = TypeVar("V")


class TTLCache(Generic[V]):
    def __init__(self, ttl_seconds: float, max_entries: int = 256) -> None:
        self._ttl = ttl_seconds
        self._max = max_entries
        self._data: dict[str, tuple[float, V]] = {}
        self._lock = threading.Lock()

    @property
    def enabled(self) -> bool:
        return self._ttl > 0

    def get(self, key: str) -> V | None:
        if not self.enabled:
            return None
        now = time.monotonic()
        with self._lock:
            entry = self._data.get(key)
            if entry is None:
                return None
            expires_at, value = entry
            if expires_at < now:
                self._data.pop(key, None)
                return None
            return value

    def set(self, key: str, value: V) -> None:
        if not self.enabled:
            return
        now = time.monotonic()
        with self._lock:
            if len(self._data) >= self._max:
                # Remove os expirados; se ainda cheio, o mais antigo.
                for k in [k for k, (exp, _) in self._data.items() if exp < now]:
                    self._data.pop(k, None)
                if len(self._data) >= self._max:
                    oldest = min(self._data, key=lambda k: self._data[k][0])
                    self._data.pop(oldest, None)
            self._data[key] = (now + self._ttl, value)

    def clear(self) -> None:
        with self._lock:
            self._data.clear()
