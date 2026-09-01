#!/usr/bin/env python3
"""
Servidor local do painel "Meu IP".

Responsabilidades:
  1. Servir a interface estatica (index.html + assets em static/).
  2. Expor /api/system com informacoes REAIS da maquina (Windows/Linux/macOS).

O que este backend NAO faz: inventar dados. Cada campo so aparece se o
sistema operacional realmente fornecer o valor.

Uso:
    pip install -r requirements.txt
    python app.py
    # abra http://127.0.0.1:5000
"""
from __future__ import annotations

import getpass
import os
import platform
import re
import socket
import struct
import subprocess
import sys
from datetime import datetime, timezone

from flask import Flask, jsonify, request, send_from_directory

try:
    import psutil  # opcional: enriquece interfaces, memoria e uptime
except ImportError:  # pragma: no cover - ambiente sem psutil
    psutil = None

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
STATIC_DIR = os.path.join(BASE_DIR, "static")

app = Flask(__name__, static_folder=None)


# --------------------------------------------------------------------------- #
# Coleta de informacoes de rede
# --------------------------------------------------------------------------- #
def local_ip() -> str | None:
    """IP de saida da maquina. Nao abre conexao real (socket UDP nao envia)."""
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        s.connect(("8.8.8.8", 80))
        return s.getsockname()[0]
    except OSError:
        return None
    finally:
        s.close()


def default_gateway() -> str | None:
    """
    Gateway padrao (rota 0.0.0.0). Le a tabela de rotas do SO em vez do
    ipconfig, porque as linhas de dados de `route print` nao sao traduzidas.
    """
    try:
        if platform.system() == "Windows":
            out = subprocess.run(
                ["route", "print", "-4", "0.0.0.0"],
                capture_output=True, text=True, timeout=5,
            ).stdout
            for line in out.splitlines():
                cols = line.split()
                if len(cols) >= 3 and cols[0] == "0.0.0.0" and cols[1] == "0.0.0.0":
                    return cols[2]
        else:
            out = subprocess.run(
                ["ip", "route", "show", "default"],
                capture_output=True, text=True, timeout=5,
            ).stdout
            m = re.search(r"default via (\S+)", out)
            if m:
                return m.group(1)
    except (OSError, subprocess.SubprocessError):
        return None
    return None


def dns_servers() -> list[str]:
    """Servidores DNS configurados (best-effort, depende do SO)."""
    servers: list[str] = []
    try:
        if platform.system() == "Windows":
            out = subprocess.run(
                ["nslookup", "127.0.0.1"],
                capture_output=True, text=True, timeout=5,
            ).stdout
            m = re.search(r"Address(?:es)?:\s*([0-9a-fA-F:.]+)", out)
            if m:
                servers.append(m.group(1).strip())
        else:
            with open("/etc/resolv.conf", encoding="utf-8") as fh:
                for line in fh:
                    if line.startswith("nameserver"):
                        servers.append(line.split()[1])
    except (OSError, subprocess.SubprocessError):
        pass
    return servers


def network_interfaces() -> list[dict]:
    """Lista de interfaces com seus enderecos. Requer psutil para detalhes."""
    if psutil is None:
        return []
    families = {
        socket.AF_INET: "IPv4",
        socket.AF_INET6: "IPv6",
    }
    if hasattr(psutil, "AF_LINK"):
        families[psutil.AF_LINK] = "MAC"

    stats = psutil.net_if_stats()
    result: list[dict] = []
    for name, addrs in psutil.net_if_addrs().items():
        entry = {
            "nome": name,
            "ativa": bool(stats.get(name) and stats[name].isup),
            "velocidade_mbps": stats[name].speed if name in stats else None,
            "enderecos": [],
        }
        for addr in addrs:
            entry["enderecos"].append({
                "tipo": families.get(addr.family, str(addr.family)),
                "endereco": addr.address,
                "mascara": addr.netmask,
            })
        result.append(entry)
    return result


# --------------------------------------------------------------------------- #
# Coleta de informacoes da maquina
# --------------------------------------------------------------------------- #
def system_payload() -> dict:
    uname = platform.uname()
    is_windows = platform.system() == "Windows"

    data: dict = {
        "coletado_em": datetime.now(timezone.utc).astimezone().isoformat(),
        "fonte": "backend-local",
        "identificacao": {
            "hostname": socket.gethostname(),
            "fqdn": socket.getfqdn(),
            "nome_computador": uname.node,
            "usuario": getpass.getuser(),
        },
        "sistema_operacional": {
            "sistema": uname.system,
            "versao": uname.version,
            "release": uname.release,
            "descricao": platform.platform(),
            "python": platform.python_version(),
        },
        "hardware": {
            "arquitetura": uname.machine,
            "bits_processo": struct.calcsize("P") * 8,
            "bits_python": platform.architecture()[0],
            "processador": uname.processor or platform.processor() or None,
            "nucleos_logicos": os.cpu_count(),
        },
        "rede": {
            "ip_local": local_ip(),
            "gateway": default_gateway(),
            "dns": dns_servers(),
            "interfaces": network_interfaces(),
        },
    }

    if is_windows:
        release, version, csd, ptype = platform.win32_ver()
        data["sistema_operacional"]["windows"] = {
            "edicao": getattr(platform, "win32_edition", lambda: None)(),
            "release": release,
            "build": version,
            "tipo": ptype,
        }

    if psutil is not None:
        vm = psutil.virtual_memory()
        boot = datetime.fromtimestamp(psutil.boot_time(), tz=timezone.utc).astimezone()
        data["hardware"]["nucleos_fisicos"] = psutil.cpu_count(logical=False)
        data["hardware"]["memoria_total_gb"] = round(vm.total / 1024**3, 2)
        data["hardware"]["memoria_disponivel_gb"] = round(vm.available / 1024**3, 2)
        data["hardware"]["memoria_uso_percent"] = vm.percent
        data["sistema_operacional"]["ligado_desde"] = boot.isoformat()
        data["sistema_operacional"]["uptime_segundos"] = int(
            datetime.now(timezone.utc).timestamp() - psutil.boot_time()
        )
    else:
        data["aviso_psutil"] = (
            "psutil nao instalado: interfaces, memoria e uptime indisponiveis. "
            "Rode `pip install psutil`."
        )

    return data


# --------------------------------------------------------------------------- #
# Rotas
# --------------------------------------------------------------------------- #
@app.after_request
def allow_cross_origin(response):
    """
    Libera CORS apenas para /api/*, para que a pagina funcione tambem quando
    aberta como arquivo (file://) ou de outra porta durante o desenvolvimento.
    """
    if request.path.startswith("/api/"):
        response.headers["Access-Control-Allow-Origin"] = "*"
        response.headers["Access-Control-Allow-Methods"] = "GET, OPTIONS"
    return response


@app.get("/")
def index():
    return send_from_directory(BASE_DIR, "index.html")


@app.get("/static/<path:filename>")
def static_files(filename):
    return send_from_directory(STATIC_DIR, filename)


@app.get("/api/ping")
def ping():
    """Endpoint leve so para o frontend detectar que o backend esta no ar."""
    return jsonify(ok=True, servico="meu-ip", versao=1)


@app.get("/api/system")
def api_system():
    try:
        return jsonify(system_payload())
    except Exception as exc:  # nao derruba o servidor por um campo problematico
        app.logger.exception("Falha ao montar payload do sistema")
        return jsonify(erro=str(exc), fonte="backend-local"), 500


if __name__ == "__main__":
    host = os.environ.get("MEU_IP_HOST", "127.0.0.1")
    port = int(os.environ.get("MEU_IP_PORT", "5000"))
    print(f"  Painel Meu IP  ->  http://{host}:{port}")
    print(f"  API do sistema ->  http://{host}:{port}/api/system")
    print(f"  Python {platform.python_version()} | psutil: {'ok' if psutil else 'ausente'}")
    app.run(host=host, port=port, debug=bool(os.environ.get("MEU_IP_DEBUG")))
