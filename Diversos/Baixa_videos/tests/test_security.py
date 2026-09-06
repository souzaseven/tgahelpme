"""Testes das primitivas de segurança (SSRF + sanitização)."""

from __future__ import annotations

from pathlib import Path

import pytest

from app.core.security import (
    UnsafeHostError,
    assert_public_host,
    is_ip_literal,
    is_public_ip,
    safe_join,
    sanitize_filename,
)


@pytest.mark.parametrize(
    "ip",
    [
        "127.0.0.1",
        "10.0.0.5",
        "192.168.0.1",
        "172.16.0.1",
        "169.254.169.254",   # metadata cloud
        "::1",
        "fe80::1",
        "0.0.0.0",
        "100.64.0.1",        # CGNAT
        "::ffff:127.0.0.1",  # IPv4 mapeado em IPv6
        "not-an-ip",
    ],
)
def test_is_public_ip_rejeita_internos(ip: str) -> None:
    assert is_public_ip(ip) is False


@pytest.mark.parametrize("ip", ["8.8.8.8", "1.1.1.1", "157.240.0.1"])
def test_is_public_ip_aceita_externos(ip: str) -> None:
    assert is_public_ip(ip) is True


@pytest.mark.parametrize(
    "host,expected",
    [
        ("127.0.0.1", True),
        ("[::1]", True),
        ("::1", True),
        ("instagram.com", False),
        ("localhost", False),
    ],
)
def test_is_ip_literal(host: str, expected: bool) -> None:
    assert is_ip_literal(host) is expected


def test_assert_public_host_bloqueia_ip_literal() -> None:
    with pytest.raises(UnsafeHostError):
        assert_public_host("127.0.0.1")


def test_assert_public_host_bloqueia_localhost() -> None:
    # "localhost" resolve para 127.0.0.1 / ::1 em qualquer sistema.
    with pytest.raises(UnsafeHostError):
        assert_public_host("localhost")


@pytest.mark.parametrize(
    "raw,expected",
    [
        ("Meu Vídeo Incrível!.mp4", "Meu-Video-Incrivel.mp4"),
        ("../../etc/passwd", "etc-passwd"),
        ("..\\..\\win.ini", "win.ini"),
        ("   ", "arquivo"),
        ("çãõ 日本語", "cao"),
        (".hidden", "hidden"),
    ],
)
def test_sanitize_filename(raw: str, expected: str) -> None:
    assert sanitize_filename(raw) == expected


def test_safe_join_permite_dentro(tmp_path: Path) -> None:
    assert safe_join(tmp_path, "abc", "source.mp4") == (tmp_path / "abc" / "source.mp4").resolve()


@pytest.mark.parametrize("evil", ["../fora.txt", "abc/../../fora.txt", "/etc/passwd"])
def test_safe_join_bloqueia_traversal(tmp_path: Path, evil: str) -> None:
    with pytest.raises(ValueError):
        safe_join(tmp_path, evil)
