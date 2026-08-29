"""
Guarda de segurança para o console de consultas: garante que apenas
comandos de LEITURA (SELECT / WITH ... SELECT) sejam executados.

Isso é uma barreira de defesa em profundidade na camada da aplicação.
Para proteção real em produção, conecte com um usuário/role do Firebird
que só tenha permissão de SELECT nas tabelas.
"""
from __future__ import annotations

import re

_PROIBIDAS = re.compile(
    r"\b(INSERT|UPDATE|DELETE|MERGE|DROP|CREATE|ALTER|EXECUTE|GRANT|REVOKE|"
    r"TRUNCATE|SET\s+TRANSACTION|COMMIT|ROLLBACK)\b",
    re.IGNORECASE,
)

_INICIO_PERMITIDO = re.compile(r"^\s*(SELECT|WITH)\b", re.IGNORECASE)


class ConsultaNaoPermitida(Exception):
    pass


def validar_somente_leitura(sql: str) -> str:
    """Valida que o SQL é uma consulta somente leitura. Retorna o SQL "limpo"."""
    sql_limpo = sql.strip().rstrip(";")

    if not sql_limpo:
        raise ConsultaNaoPermitida("Informe uma consulta SQL.")

    # bloqueia múltiplos comandos separados por ';'
    if ";" in sql_limpo:
        raise ConsultaNaoPermitida("Apenas um único comando SELECT por vez é permitido.")

    if not _INICIO_PERMITIDO.match(sql_limpo):
        raise ConsultaNaoPermitida(
            "Somente comandos SELECT (ou WITH ... SELECT) são permitidos nesta ferramenta."
        )

    if _PROIBIDAS.search(sql_limpo):
        raise ConsultaNaoPermitida(
            "A consulta contém uma palavra-chave não permitida para uma ferramenta somente leitura."
        )

    return sql_limpo
