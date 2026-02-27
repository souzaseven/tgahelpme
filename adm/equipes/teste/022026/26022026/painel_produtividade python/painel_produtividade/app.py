# ════════════════════════════════════════════════════════
# app.py — Servidor Flask do Painel de Produtividade Evolux
# ════════════════════════════════════════════════════════

from flask import Flask, render_template, request, jsonify
from services.evolux_service import get_agents_performance, get_all_queues
from datetime import datetime, timedelta
import json

app = Flask(__name__)


# ──────────────────────────────────────────────────────
# UTILITÁRIO: Formata data do formulário para ISO 8601
# O input datetime-local retorna "2024-01-01T08:00"
# A API exige    "2024-01-01T11:00:00.000Z" (UTC)
# ──────────────────────────────────────────────────────
def to_iso(dt_local: str, end=False) -> str:
    """
    Converte datetime-local (sem fuso) para ISO 8601 UTC.
    Assume horário de Brasília (UTC-3).
    """
    try:
        dt = datetime.fromisoformat(dt_local)
        dt_utc = dt + timedelta(hours=3)   # BRT → UTC
        ms = ".999Z" if end else ".000Z"
        return dt_utc.strftime(f"%Y-%m-%dT%H:%M:%S") + ms
    except Exception:
        return dt_local


# ──────────────────────────────────────────────────────
# ROTA PRINCIPAL — Exibe painel e processa filtros
# ──────────────────────────────────────────────────────
@app.route("/", methods=["GET", "POST"])
def index():
    """Renderiza o painel principal com tabela de produtividade."""

    # Carrega filas para o seletor
    queues = get_all_queues()

    data      = None
    error     = None
    params_used = {}

    if request.method == "POST":
        start_local = request.form.get("start_date", "")
        end_local   = request.form.get("end_date",   "")
        queue_ids   = request.form.get("queue_ids",  "all")
        agent_ids   = request.form.get("agent_ids",  "all")

        start_iso = to_iso(start_local, end=False)
        end_iso   = to_iso(end_local,   end=True)

        params_used = {
            "start_date": start_local,
            "end_date":   end_local,
            "queue_ids":  queue_ids,
            "agent_ids":  agent_ids,
        }

        response = get_agents_performance(
            start_date = start_iso,
            end_date   = end_iso,
            queue_ids  = queue_ids,
            agent_ids  = agent_ids,
        )

        if "error" in response:
            error = response["error"]
        else:
            data = response.get("data", [])

    return render_template(
        "index.html",
        queues      = queues,
        data        = data,
        error       = error,
        params_used = params_used,
    )


# ──────────────────────────────────────────────────────
# ROTA API — Retorna JSON puro (útil para AJAX / refresh)
# ──────────────────────────────────────────────────────
@app.route("/api/performance")
def api_performance():
    """Endpoint JSON para consulta via AJAX."""
    start_date = request.args.get("start_date")
    end_date   = request.args.get("end_date")
    queue_ids  = request.args.get("queue_ids", "all")
    agent_ids  = request.args.get("agent_ids", "all")

    result = get_agents_performance(start_date, end_date, queue_ids, agent_ids)
    return jsonify(result)


# ──────────────────────────────────────────────────────
# EXECUÇÃO
# ──────────────────────────────────────────────────────
if __name__ == "__main__":
    app.run(debug=True, host="0.0.0.0", port=5000)
