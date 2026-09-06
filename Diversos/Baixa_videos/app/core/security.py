"""Primitivas de segurança reutilizáveis.

- Verificação de IP público (defesa contra SSRF).
- Sanitização de nome de arquivo e junção segura de caminho (path traversal).

Estas funções são genéricas e não conhecem plataformas específicas.
"""

from __future__ import annotations

import ipaddress
import re
import socket
import unicodedata
from pathlib import Path


class HostResolutionError(Exception):
    """Não foi possível resolver o host para verificar se é público."""


class UnsafeHostError(Exception):
    """O host resolve para um endereço não roteável / interno."""


# ----------------------------------------------------------------------
# SSRF
# ----------------------------------------------------------------------

def is_public_ip(value: str) -> bool:
    """True somente se `value` for um IP globalmente roteável.

    Bloqueia loopback, privado (RFC 1918), link-local (169.254/16 — inclui o
    endpoint de metadata 169.254.169.254), CGNAT, reservado, multicast e
    não especificado. Também trata IPv6 mapeado em IPv4.
    """
    try:
        ip = ipaddress.ip_address(value)
    except ValueError:
        return False

    if isinstance(ip, ipaddress.IPv6Address) and ip.ipv4_mapped is not None:
        ip = ip.ipv4_mapped

    if any(
        (
            ip.is_private,
            ip.is_loopback,
            ip.is_link_local,
            ip.is_multicast,
            ip.is_reserved,
            ip.is_unspecified,
        )
    ):
        return False

    # CGNAT 100.64.0.0/10 não é marcado como privado pelo módulo.
    if isinstance(ip, ipaddress.IPv4Address) and ip in ipaddress.ip_network("100.64.0.0/10"):
        return False

    return ip.is_global


def is_ip_literal(host: str) -> bool:
    """True se `host` for um literal de IP (v4 ou v6), não um nome de domínio."""
    try:
        ipaddress.ip_address(host.strip("[]"))
        return True
    except ValueError:
        return False


def resolve_host(host: str, timeout: float = 5.0) -> list[str]:
    """Resolve `host` para a lista de IPs (strings). Levanta HostResolutionError."""
    old = socket.getdefaulttimeout()
    socket.setdefaulttimeout(timeout)
    try:
        infos = socket.getaddrinfo(host, None, proto=socket.IPPROTO_TCP)
    except (socket.gaierror, socket.herror, OSError, UnicodeError) as exc:
        raise HostResolutionError(str(exc)) from exc
    finally:
        socket.setdefaulttimeout(old)

    ips = {info[4][0] for info in infos}
    if not ips:
        raise HostResolutionError("nenhum endereço retornado")
    return sorted(ips)


def assert_public_host(host: str, timeout: float = 5.0) -> None:
    """Garante que `host` resolve exclusivamente para IPs públicos.

    Levanta UnsafeHostError se qualquer IP for interno, ou HostResolutionError
    se o DNS não puder ser consultado.
    """
    if is_ip_literal(host):
        # URLs das plataformas nunca usam IP literal; e um literal só poderia
        # servir para apontar a rede interna.
        raise UnsafeHostError("host é um literal de IP")

    for ip in resolve_host(host, timeout=timeout):
        if not is_public_ip(ip):
            raise UnsafeHostError(f"host resolve para IP não público ({ip})")


# ----------------------------------------------------------------------
# Nome de arquivo / caminho
# ----------------------------------------------------------------------

_FILENAME_SAFE = re.compile(r"[^A-Za-z0-9._-]+")
_SEP_RUN = re.compile(r"[-_.]{2,}")


def _collapse_separators(match: re.Match[str]) -> str:
    # Um ponto na sequência costuma separar a extensão — preserve-o.
    return "." if "." in match.group(0) else "-"


def sanitize_filename(name: str, *, fallback: str = "arquivo", max_len: int = 80) -> str:
    """Reduz `name` a um nome de arquivo seguro (sem diretório, sem controle)."""
    normalized = unicodedata.normalize("NFKD", name or "")
    ascii_only = normalized.encode("ascii", "ignore").decode("ascii")
    ascii_only = ascii_only.replace(" ", "-")
    cleaned = _FILENAME_SAFE.sub("-", ascii_only)
    cleaned = _SEP_RUN.sub(_collapse_separators, cleaned).strip("-._")
    cleaned = cleaned[:max_len].strip("-._")
    return cleaned or fallback


def safe_join(base: Path, *parts: str) -> Path:
    """Junta `parts` a `base` garantindo que o resultado fique dentro de `base`."""
    base_resolved = base.resolve()
    candidate = base_resolved.joinpath(*parts).resolve()
    if base_resolved != candidate and base_resolved not in candidate.parents:
        raise ValueError("caminho fora do diretório base")
    return candidate
