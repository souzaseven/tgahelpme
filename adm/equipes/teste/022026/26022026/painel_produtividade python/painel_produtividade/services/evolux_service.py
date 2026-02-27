# ════════════════════════════════════════════════════════
# services/evolux_service.py — Camada de comunicação com API Evolux
# ════════════════════════════════════════════════════════

import requests
from config import BASE_URL, TOKEN, TIMEOUT


# ──────────────────────────────────────────────────────
# HEADERS PADRÃO
# ──────────────────────────────────────────────────────
def _headers():
    """Retorna cabeçalhos de autenticação."""
    return {"token": TOKEN}


# ──────────────────────────────────────────────────────
# FUNÇÃO: LISTAR TODAS AS FILAS
# Retorna lista de filas disponíveis para o filtro.
# ──────────────────────────────────────────────────────
def get_all_queues():
    """
    Consulta todas as filas disponíveis (incluindo arquivadas).
    Endpoint: GET /api/v1/queues
    """
    url = f"{BASE_URL}/api/v1/queues"
    params = {"include_archived": "true"}

    try:
        resp = requests.get(url, params=params, headers=_headers(), timeout=TIMEOUT)
        if resp.status_code == 200:
            body = resp.json()
            # A API pode retornar lista direta ou dentro de 'data'
            raw = body.get("data", body) if isinstance(body, dict) else body
            if isinstance(raw, list):
                return raw
        return []
    except Exception as e:
        print(f"[ERRO] get_all_queues: {e}")
        return []


# ──────────────────────────────────────────────────────
# FUNÇÃO: CONSULTAR UMA FILA ESPECÍFICA
# ──────────────────────────────────────────────────────
def get_queue(queue_id):
    """
    Consulta dados de uma fila pelo ID.
    Endpoint: GET /api/v1/queues/{queue_id}
    """
    url = f"{BASE_URL}/api/v1/queues/{queue_id}"
    params = {"include_archived": "true"}

    try:
        resp = requests.get(url, params=params, headers=_headers(), timeout=TIMEOUT)
        if resp.status_code == 200:
            return resp.json().get("data", {})
        return {}
    except Exception as e:
        print(f"[ERRO] get_queue({queue_id}): {e}")
        return {}


# ──────────────────────────────────────────────────────
# FUNÇÃO: PRODUTIVIDADE DE OPERADORES
# Suporte a múltiplos queue_ids via parâmetro repetido.
# ──────────────────────────────────────────────────────
def get_agents_performance(start_date, end_date, queue_ids="all", agent_ids="all"):
    """
    Consulta relatório de produtividade de operadores.
    Endpoint: GET /api/v1/report/agents_performance

    Parâmetros:
        start_date  — ISO 8601, ex: 2024-01-01T03:00:00.000Z
        end_date    — ISO 8601, ex: 2024-01-02T02:59:59.999Z
        queue_ids   — "all" ou lista de IDs separados por vírgula
        agent_ids   — "all" ou ID específico
    """
    url = f"{BASE_URL}/api/v1/report/agents_performance"

    # Monta params — se queue_ids for lista, repete o parâmetro (API exige)
    params = []
    params.append(("start_date", start_date))
    params.append(("end_date",   end_date))
    params.append(("agent_ids",  agent_ids))

    if queue_ids and queue_ids.strip().lower() != "all":
        ids = [q.strip() for q in queue_ids.split(",") if q.strip()]
        for qid in ids:
            params.append(("queue_id", qid))
        params.append(("entity", "queues"))
        params.append(("queue_or_group", "queues"))
    else:
        params.append(("queue_ids", "all"))

    try:
        resp = requests.get(url, params=params, headers=_headers(), timeout=TIMEOUT)
        if resp.status_code == 200:
            return resp.json()
        else:
            return {"error": f"HTTP {resp.status_code}: {resp.text}"}
    except Exception as e:
        return {"error": str(e)}
