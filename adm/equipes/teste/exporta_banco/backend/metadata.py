"""
Consultas de metadados do Firebird (RDB$...) usadas pelo inspetor.

Todas as funções recebem uma conexão já aberta (firebird.driver.Connection)
e devolvem estruturas simples (list/dict) prontas para virar JSON.
"""
from __future__ import annotations

from datetime import date, timedelta
from typing import Optional


# ---------------------------------------------------------------------------
# Utilidades
# ---------------------------------------------------------------------------

# Mapeamento RDB$FIELD_TYPE -> nome legível
_FIELD_TYPES = {
    7: "SMALLINT",
    8: "INTEGER",
    9: "QUAD",
    10: "FLOAT",
    11: "D_FLOAT",
    12: "DATE",
    13: "TIME",
    14: "CHAR",
    16: "BIGINT",
    23: "BOOLEAN",
    24: "DECFLOAT(16)",
    25: "DECFLOAT(34)",
    26: "INT128",
    27: "DOUBLE PRECISION",
    28: "TIME WITH TIME ZONE",
    29: "TIMESTAMP WITH TIME ZONE",
    35: "TIMESTAMP",
    37: "VARCHAR",
    261: "BLOB",
}

# subtipo de BLOB
_BLOB_SUBTYPES = {0: "BINARY", 1: "TEXT", 2: "BLR", 3: "ACL", 4: "RANGES", 5: "SUMMARY", 6: "FORMAT"}

# subtipo NUMERIC/DECIMAL para tipos 16 (BIGINT), 8 (INTEGER), 7 (SMALLINT), 26 (INT128)
_EXACT_NUMERIC_SUBTYPES = {1: "NUMERIC", 2: "DECIMAL"}

# RDB$TRIGGER_TYPE -> descrição (valores clássicos, pré/pós evento)
_TRIGGER_TYPES = {
    1: "BEFORE INSERT",
    2: "AFTER INSERT",
    3: "BEFORE UPDATE",
    4: "AFTER UPDATE",
    5: "BEFORE DELETE",
    6: "AFTER DELETE",
    17: "BEFORE INSERT OR UPDATE",
    18: "AFTER INSERT OR UPDATE",
    25: "BEFORE INSERT OR DELETE",
    26: "AFTER INSERT OR DELETE",
    27: "BEFORE UPDATE OR DELETE",
    28: "AFTER UPDATE OR DELETE",
    113: "BEFORE INSERT OR UPDATE OR DELETE",
    114: "AFTER INSERT OR UPDATE OR DELETE",
}

# Códigos de status dos documentos fiscais eletrônicos (padrão TGA/ERPs
# comerciais brasileiros) — usados na "Visão Geral" para o resumo de
# documentos fiscais por sistema.
STATUS_NFE = {
    0: "digitada", 1: "autorizada", 2: "cancelada",
    3: "denegada", 4: "processamento", 5: "rejeitada", 6: "inutilizada",
}
STATUS_NFSE = {"D": "digitacao", "P": "processamento", "R": "rejeitada", "E": "autorizada", "C": "cancelada"}
STATUS_CTE = {"D": "digitacao", "P": "processamento", "R": "rejeitado", "A": "autorizado", "C": "cancelado"}
STATUS_MDFE = {0: "digitacao", 1: "processamento", 2: "autorizado", 3: "encerrado", 4: "rejeitado", 5: "cancelado"}
# Status de manifestação do destinatário (TDFE.DFE) — documentos de terceiros.
STATUS_DFE = {
    "0": "nao_manifestada", "1": "confirmacao_operacao",
    "2": "ciencia_emissao", "3": "operacao_desconhecida", "4": "operacao_nao_realizada",
}


def quote_ident(name: str) -> str:
    """Coloca um identificador entre aspas duplas, escapando aspas internas."""
    return '"' + name.replace('"', '""') + '"'


def _rows_as_dicts(cur) -> list[dict]:
    cols = [d[0].lower() for d in cur.description]
    result = []
    for row in cur.fetchall():
        item = {}
        for col, val in zip(cols, row):
            if isinstance(val, str):
                val = val.rstrip()
            item[col] = val
        result.append(item)
    return result


def _query(con, sql: str, params: tuple = ()) -> list[dict]:
    cur = con.cursor()
    try:
        cur.execute(sql, params)
        return _rows_as_dicts(cur)
    finally:
        cur.close()


def describe_field_type(row: dict) -> str:
    """Monta a descrição legível de um tipo de campo a partir da linha de get_columns()."""
    ftype = row.get("field_type")
    subtype = row.get("field_sub_type") or 0
    length = row.get("field_length")
    precision = row.get("field_precision")
    scale = row.get("field_scale") or 0
    char_length = row.get("max_chars")

    if ftype == 261:  # BLOB
        sub = _BLOB_SUBTYPES.get(subtype, f"SUBTYPE {subtype}")
        return f"BLOB SUB_TYPE {subtype} ({sub})"

    if ftype in (7, 8, 16, 26) and subtype in _EXACT_NUMERIC_SUBTYPES:
        base = _EXACT_NUMERIC_SUBTYPES[subtype]
        if scale and scale < 0:
            total_digits = precision if precision else length
            return f"{base}({total_digits},{-scale})"
        return base

    base_name = _FIELD_TYPES.get(ftype, f"TIPO {ftype}")

    if ftype in (14, 37):  # CHAR / VARCHAR
        size = char_length if char_length else length
        return f"{base_name}({size})"

    return base_name


# ---------------------------------------------------------------------------
# Versão / informações gerais
# ---------------------------------------------------------------------------

def get_engine_info(con) -> dict:
    rows = _query(
        con,
        "SELECT rdb$get_context('SYSTEM', 'ENGINE_VERSION') AS version, "
        "rdb$get_context('SYSTEM', 'DB_NAME') AS db_name "
        "FROM rdb$database",
    )
    info = rows[0] if rows else {}
    counts = get_object_counts(con)
    info["counts"] = counts
    return info


def get_object_counts(con) -> dict:
    return {
        "tabelas": _scalar(con, "SELECT COUNT(*) FROM rdb$relations WHERE (rdb$system_flag = 0 OR rdb$system_flag IS NULL) AND rdb$view_blr IS NULL"),
        "views": _scalar(con, "SELECT COUNT(*) FROM rdb$relations WHERE (rdb$system_flag = 0 OR rdb$system_flag IS NULL) AND rdb$view_blr IS NOT NULL"),
        "procedures": _scalar(con, "SELECT COUNT(*) FROM rdb$procedures WHERE (rdb$system_flag = 0 OR rdb$system_flag IS NULL)"),
        "triggers": _scalar(con, "SELECT COUNT(*) FROM rdb$triggers WHERE (rdb$system_flag = 0 OR rdb$system_flag IS NULL)"),
        "generators": _scalar(con, "SELECT COUNT(*) FROM rdb$generators WHERE (rdb$system_flag = 0 OR rdb$system_flag IS NULL)"),
    }


def _scalar(con, sql: str):
    cur = con.cursor()
    try:
        cur.execute(sql)
        row = cur.fetchone()
        return row[0] if row else None
    finally:
        cur.close()


# ---------------------------------------------------------------------------
# Tabelas e views
# ---------------------------------------------------------------------------

def list_tables(con, include_views: bool = True) -> list[dict]:
    sql = """
        SELECT TRIM(r.rdb$relation_name) AS name,
               CASE WHEN r.rdb$view_blr IS NOT NULL THEN 1 ELSE 0 END AS is_view,
               r.rdb$description AS description
        FROM rdb$relations r
        WHERE (r.rdb$system_flag = 0 OR r.rdb$system_flag IS NULL)
    """
    if not include_views:
        sql += " AND r.rdb$view_blr IS NULL"
    sql += " ORDER BY r.rdb$relation_name"
    rows = _query(con, sql)
    for r in rows:
        r["is_view"] = bool(r["is_view"])
    return rows


def get_row_count(con, table_name: str) -> Optional[int]:
    try:
        return _scalar(con, f"SELECT COUNT(*) FROM {quote_ident(table_name)}")
    except Exception:
        return None


def get_columns(con, table_name: str) -> list[dict]:
    sql = """
        SELECT TRIM(rf.rdb$field_name) AS name,
               rf.rdb$field_position AS field_position,
               f.rdb$field_type AS field_type,
               f.rdb$field_sub_type AS field_sub_type,
               f.rdb$field_length AS field_length,
               f.rdb$field_precision AS field_precision,
               f.rdb$field_scale AS field_scale,
               f.rdb$character_length AS max_chars,
               TRIM(cs.rdb$character_set_name) AS charset,
               CASE WHEN rf.rdb$null_flag = 1 THEN 0 ELSE 1 END AS nullable,
               COALESCE(rf.rdb$default_source, f.rdb$default_source) AS default_source,
               TRIM(rf.rdb$field_source) AS domain_name,
               rf.rdb$description AS description
        FROM rdb$relation_fields rf
        JOIN rdb$fields f ON f.rdb$field_name = rf.rdb$field_source
        LEFT JOIN rdb$character_sets cs ON cs.rdb$character_set_id = f.rdb$character_set_id
        WHERE rf.rdb$relation_name = ?
        ORDER BY rf.rdb$field_position
    """
    rows = _query(con, sql, (table_name,))
    for r in rows:
        r["nullable"] = bool(r["nullable"])
        r["type_desc"] = describe_field_type(r)
        if r.get("default_source"):
            r["default_source"] = r["default_source"].replace("DEFAULT ", "", 1).strip()
    return rows


def get_primary_key(con, table_name: str) -> Optional[dict]:
    sql = """
        SELECT TRIM(rc.rdb$constraint_name) AS constraint_name,
               TRIM(sg.rdb$field_name) AS field_name,
               sg.rdb$field_position AS field_position
        FROM rdb$relation_constraints rc
        JOIN rdb$index_segments sg ON sg.rdb$index_name = rc.rdb$index_name
        WHERE rc.rdb$relation_name = ? AND rc.rdb$constraint_type = 'PRIMARY KEY'
        ORDER BY sg.rdb$field_position
    """
    rows = _query(con, sql, (table_name,))
    if not rows:
        return None
    return {
        "constraint_name": rows[0]["constraint_name"],
        "fields": [r["field_name"] for r in rows],
    }


def get_foreign_keys(con, table_name: str) -> list[dict]:
    """FKs que a própria tabela possui (chaves de saída)."""
    sql = """
        SELECT TRIM(rc.rdb$constraint_name) AS fk_name,
               TRIM(sg.rdb$field_name) AS field_name,
               sg.rdb$field_position AS field_position,
               TRIM(idx2.rdb$relation_name) AS ref_table,
               TRIM(sg2.rdb$field_name) AS ref_field,
               TRIM(refc.rdb$update_rule) AS update_rule,
               TRIM(refc.rdb$delete_rule) AS delete_rule
        FROM rdb$relation_constraints rc
        JOIN rdb$ref_constraints refc ON refc.rdb$constraint_name = rc.rdb$constraint_name
        JOIN rdb$index_segments sg ON sg.rdb$index_name = rc.rdb$index_name
        JOIN rdb$relation_constraints rc2 ON rc2.rdb$constraint_name = refc.rdb$const_name_uq
        JOIN rdb$index_segments sg2 ON sg2.rdb$index_name = rc2.rdb$index_name
             AND sg2.rdb$field_position = sg.rdb$field_position
        JOIN rdb$indices idx2 ON idx2.rdb$index_name = rc2.rdb$index_name
        WHERE rc.rdb$relation_name = ? AND rc.rdb$constraint_type = 'FOREIGN KEY'
        ORDER BY fk_name, sg.rdb$field_position
    """
    rows = _query(con, sql, (table_name,))
    return _group_fk_segments(rows)


def get_referencing_tables(con, table_name: str) -> list[dict]:
    """Quem aponta PARA esta tabela (chaves de entrada / dependentes)."""
    sql = """
        SELECT TRIM(rc.rdb$constraint_name) AS fk_name,
               TRIM(rc.rdb$relation_name) AS from_table,
               TRIM(sg.rdb$field_name) AS field_name,
               sg.rdb$field_position AS field_position,
               TRIM(sg2.rdb$field_name) AS ref_field
        FROM rdb$relation_constraints rc
        JOIN rdb$ref_constraints refc ON refc.rdb$constraint_name = rc.rdb$constraint_name
        JOIN rdb$index_segments sg ON sg.rdb$index_name = rc.rdb$index_name
        JOIN rdb$relation_constraints rc2 ON rc2.rdb$constraint_name = refc.rdb$const_name_uq
        JOIN rdb$index_segments sg2 ON sg2.rdb$index_name = rc2.rdb$index_name
             AND sg2.rdb$field_position = sg.rdb$field_position
        WHERE rc2.rdb$relation_name = ? AND rc.rdb$constraint_type = 'FOREIGN KEY'
        ORDER BY fk_name, sg.rdb$field_position
    """
    rows = _query(con, sql, (table_name,))
    grouped: dict[str, dict] = {}
    for r in rows:
        key = r["fk_name"]
        if key not in grouped:
            grouped[key] = {
                "fk_name": r["fk_name"],
                "from_table": r["from_table"],
                "fields": [],
                "ref_fields": [],
            }
        grouped[key]["fields"].append(r["field_name"])
        grouped[key]["ref_fields"].append(r["ref_field"])
    return list(grouped.values())


def _group_fk_segments(rows: list[dict]) -> list[dict]:
    grouped: dict[str, dict] = {}
    for r in rows:
        key = r["fk_name"]
        if key not in grouped:
            grouped[key] = {
                "fk_name": r["fk_name"],
                "ref_table": r["ref_table"],
                "update_rule": r["update_rule"],
                "delete_rule": r["delete_rule"],
                "fields": [],
                "ref_fields": [],
            }
        grouped[key]["fields"].append(r["field_name"])
        grouped[key]["ref_fields"].append(r["ref_field"])
    return list(grouped.values())


def get_indexes(con, table_name: str) -> list[dict]:
    sql = """
        SELECT TRIM(i.rdb$index_name) AS index_name,
               i.rdb$unique_flag AS is_unique,
               i.rdb$index_type AS index_type,
               i.rdb$index_inactive AS is_inactive,
               TRIM(sg.rdb$field_name) AS field_name,
               sg.rdb$field_position AS field_position,
               (SELECT TRIM(rc.rdb$constraint_type) FROM rdb$relation_constraints rc
                 WHERE rc.rdb$index_name = i.rdb$index_name) AS constraint_type
        FROM rdb$indices i
        JOIN rdb$index_segments sg ON sg.rdb$index_name = i.rdb$index_name
        WHERE i.rdb$relation_name = ?
        ORDER BY i.rdb$index_name, sg.rdb$field_position
    """
    rows = _query(con, sql, (table_name,))
    grouped: dict[str, dict] = {}
    for r in rows:
        key = r["index_name"]
        if key not in grouped:
            grouped[key] = {
                "index_name": r["index_name"],
                "unique": bool(r["is_unique"]),
                "order": "DESC" if r["index_type"] == 1 else "ASC",
                "inactive": bool(r["is_inactive"]),
                "constraint_type": r["constraint_type"],
                "fields": [],
            }
        grouped[key]["fields"].append(r["field_name"])
    return list(grouped.values())


def get_triggers(con, table_name: str) -> list[dict]:
    sql = """
        SELECT TRIM(rdb$trigger_name) AS name,
               rdb$trigger_sequence AS sequence,
               rdb$trigger_type AS trigger_type,
               rdb$trigger_inactive AS inactive,
               rdb$trigger_source AS source
        FROM rdb$triggers
        WHERE rdb$relation_name = ? AND (rdb$system_flag = 0 OR rdb$system_flag IS NULL)
        ORDER BY rdb$trigger_sequence, rdb$trigger_name
    """
    rows = _query(con, sql, (table_name,))
    for r in rows:
        r["type_desc"] = _TRIGGER_TYPES.get(r["trigger_type"], f"TIPO {r['trigger_type']}")
        r["inactive"] = bool(r["inactive"])
    return rows


def list_triggers(con) -> list[dict]:
    """Lista TODAS as triggers do banco (não apenas de uma tabela)."""
    sql = """
        SELECT TRIM(rdb$trigger_name) AS name,
               TRIM(rdb$relation_name) AS table_name,
               rdb$trigger_type AS trigger_type,
               rdb$trigger_inactive AS inactive,
               rdb$trigger_sequence AS sequence
        FROM rdb$triggers
        WHERE (rdb$system_flag = 0 OR rdb$system_flag IS NULL)
        ORDER BY rdb$relation_name NULLS FIRST, rdb$trigger_sequence, rdb$trigger_name
    """
    try:
        rows = _query(con, sql)
    except Exception:
        # Alguns Firebird mais antigos (2.5) não aceitam NULLS FIRST nessa posição
        sql_fallback = sql.replace("rdb$relation_name NULLS FIRST", "rdb$relation_name")
        rows = _query(con, sql_fallback)
    for r in rows:
        r["type_desc"] = _TRIGGER_TYPES.get(r["trigger_type"], f"TIPO {r['trigger_type']}")
        r["inactive"] = bool(r["inactive"])
    return rows


def get_trigger_detail(con, name: str) -> Optional[dict]:
    sql = """
        SELECT TRIM(rdb$trigger_name) AS name,
               TRIM(rdb$relation_name) AS table_name,
               rdb$trigger_type AS trigger_type,
               rdb$trigger_inactive AS inactive,
               rdb$trigger_sequence AS sequence,
               rdb$trigger_source AS source
        FROM rdb$triggers
        WHERE rdb$trigger_name = ?
    """
    rows = _query(con, sql, (name,))
    if not rows:
        return None
    r = rows[0]
    r["type_desc"] = _TRIGGER_TYPES.get(r["trigger_type"], f"TIPO {r['trigger_type']}")
    r["inactive"] = bool(r["inactive"])
    return r


def get_view_source(con, view_name: str) -> Optional[str]:
    sql = "SELECT rdb$view_source AS source FROM rdb$relations WHERE rdb$relation_name = ?"
    rows = _query(con, sql, (view_name,))
    return rows[0]["source"] if rows else None


def get_table_detail(con, table_name: str) -> dict:
    tables = {t["name"]: t for t in list_tables(con)}
    meta = tables.get(table_name, {"name": table_name, "is_view": False})
    detail = {
        "name": table_name,
        "is_view": meta.get("is_view", False),
        "description": meta.get("description"),
        "columns": get_columns(con, table_name),
        "primary_key": get_primary_key(con, table_name),
        "foreign_keys": get_foreign_keys(con, table_name),
        "referenced_by": get_referencing_tables(con, table_name),
        "indexes": get_indexes(con, table_name),
        "triggers": get_triggers(con, table_name),
    }
    if detail["is_view"]:
        detail["view_source"] = get_view_source(con, table_name)
        detail["row_count"] = None
    else:
        detail["row_count"] = get_row_count(con, table_name)
    return detail


# ---------------------------------------------------------------------------
# Procedures
# ---------------------------------------------------------------------------

def list_procedures(con) -> list[dict]:
    sql = """
        SELECT TRIM(rdb$procedure_name) AS name,
               rdb$description AS description
        FROM rdb$procedures
        WHERE (rdb$system_flag = 0 OR rdb$system_flag IS NULL)
        ORDER BY rdb$procedure_name
    """
    return _query(con, sql)


def get_procedure_detail(con, name: str) -> dict:
    src_sql = """
        SELECT rdb$procedure_source AS source
        FROM rdb$procedures WHERE rdb$procedure_name = ?
    """
    params_sql = """
        SELECT TRIM(pp.rdb$parameter_name) AS name,
               pp.rdb$parameter_type AS direction,
               pp.rdb$parameter_number AS field_position,
               f.rdb$field_type AS field_type,
               f.rdb$field_sub_type AS field_sub_type,
               f.rdb$field_length AS field_length,
               f.rdb$field_precision AS field_precision,
               f.rdb$field_scale AS field_scale,
               f.rdb$character_length AS max_chars
        FROM rdb$procedure_parameters pp
        JOIN rdb$fields f ON f.rdb$field_name = pp.rdb$field_source
        WHERE pp.rdb$procedure_name = ?
        ORDER BY pp.rdb$parameter_type, pp.rdb$parameter_number
    """
    src_rows = _query(con, src_sql, (name,))
    param_rows = _query(con, params_sql, (name,))
    for p in param_rows:
        p["direction_desc"] = "ENTRADA" if p["direction"] == 0 else "SAÍDA"
        p["type_desc"] = describe_field_type(p)
    return {
        "name": name,
        "source": src_rows[0]["source"] if src_rows else None,
        "input_params": [p for p in param_rows if p["direction"] == 0],
        "output_params": [p for p in param_rows if p["direction"] == 1],
    }


# ---------------------------------------------------------------------------
# Generators / Sequences
# ---------------------------------------------------------------------------

def list_generators(con) -> list[dict]:
    sql = """
        SELECT TRIM(rdb$generator_name) AS name,
               rdb$generator_id AS id
        FROM rdb$generators
        WHERE (rdb$system_flag = 0 OR rdb$system_flag IS NULL)
        ORDER BY rdb$generator_name
    """
    rows = _query(con, sql)
    for r in rows:
        try:
            r["current_value"] = _scalar(con, f'SELECT GEN_ID({quote_ident(r["name"])}, 0) FROM rdb$database')
        except Exception:
            r["current_value"] = None
    return rows


# ---------------------------------------------------------------------------
# Busca rápida na estrutura
# ---------------------------------------------------------------------------

def search_structure(con, term: str) -> dict:
    like = f"%{term.upper()}%"

    tables = _query(
        con,
        """
        SELECT TRIM(rdb$relation_name) AS name,
               CASE WHEN rdb$view_blr IS NOT NULL THEN 1 ELSE 0 END AS is_view
        FROM rdb$relations
        WHERE (rdb$system_flag = 0 OR rdb$system_flag IS NULL)
          AND UPPER(rdb$relation_name) LIKE ?
        ORDER BY rdb$relation_name
        """,
        (like,),
    )
    for t in tables:
        t["is_view"] = bool(t["is_view"])

    columns = _query(
        con,
        """
        SELECT TRIM(rf.rdb$relation_name) AS table_name,
               TRIM(rf.rdb$field_name) AS field_name
        FROM rdb$relation_fields rf
        JOIN rdb$relations r ON r.rdb$relation_name = rf.rdb$relation_name
        WHERE (r.rdb$system_flag = 0 OR r.rdb$system_flag IS NULL)
          AND UPPER(rf.rdb$field_name) LIKE ?
        ORDER BY rf.rdb$relation_name, rf.rdb$field_name
        """,
        (like,),
    )

    procedures = _query(
        con,
        """
        SELECT TRIM(rdb$procedure_name) AS name
        FROM rdb$procedures
        WHERE (rdb$system_flag = 0 OR rdb$system_flag IS NULL)
          AND UPPER(rdb$procedure_name) LIKE ?
        ORDER BY rdb$procedure_name
        """,
        (like,),
    )

    triggers = _query(
        con,
        """
        SELECT TRIM(rdb$trigger_name) AS name, TRIM(rdb$relation_name) AS table_name
        FROM rdb$triggers
        WHERE (rdb$system_flag = 0 OR rdb$system_flag IS NULL)
          AND UPPER(rdb$trigger_name) LIKE ?
        ORDER BY rdb$trigger_name
        """,
        (like,),
    )

    generators = _query(
        con,
        """
        SELECT TRIM(rdb$generator_name) AS name
        FROM rdb$generators
        WHERE (rdb$system_flag = 0 OR rdb$system_flag IS NULL)
          AND UPPER(rdb$generator_name) LIKE ?
        ORDER BY rdb$generator_name
        """,
        (like,),
    )

    return {
        "tables": tables,
        "columns": columns,
        "procedures": procedures,
        "triggers": triggers,
        "generators": generators,
    }


# ---------------------------------------------------------------------------
# Serialização de valores para JSON (usado pelo console de consultas)
# ---------------------------------------------------------------------------

def serializar_valor(valor):
    """Converte tipos não serializáveis em JSON (datas, BLOBs) para exibição."""
    if hasattr(valor, "isoformat"):
        return valor.isoformat()
    if isinstance(valor, (bytes, bytearray)):
        return f"<BLOB {len(valor)} bytes>"
    # BLOBs pequenos já chegam prontos como str/bytes na linha, mas acima de
    # um limiar interno do driver (alguns KB) ele devolve um BlobReader
    # "preguiçoso" em vez do conteúdo — precisamos ler explicitamente, senão
    # o FastAPI não consegue transformar esse objeto do driver em JSON.
    if hasattr(valor, "read") and hasattr(valor, "blob_type"):
        try:
            conteudo = valor.read()
        finally:
            try:
                valor.close()
            except Exception:
                pass
        return conteudo if isinstance(conteudo, str) else f"<BLOB {len(conteudo)} bytes>"
    return valor


# ---------------------------------------------------------------------------
# Resumo de negócio (específico para bancos com o padrão TPRODUTO / FCFO,
# comum em ERPs comerciais brasileiros). Se essas tabelas não existirem no
# banco conectado, retorna None e a seção simplesmente não aparece.
# ---------------------------------------------------------------------------

def get_business_summary(con) -> Optional[dict]:
    tabelas_existentes = {t["name"] for t in list_tables(con, include_views=False)}
    resultado: dict = {}

    if "TPRODUTO" in tabelas_existentes:
        try:
            sql = """
                SELECT
                    SUM(CASE WHEN TIPO = 'P' THEN 1 ELSE 0 END) AS p_total,
                    SUM(CASE WHEN TIPO = 'P' AND INATIVO = 'F' THEN 1 ELSE 0 END) AS p_ativos,
                    SUM(CASE WHEN TIPO = 'P' AND INATIVO = 'T' THEN 1 ELSE 0 END) AS p_inativos,
                    SUM(CASE WHEN TIPO = 'P' AND COALESCE(SALDOGERALFISICO, 0) <> 0 THEN 1 ELSE 0 END) AS p_com_saldo,
                    SUM(CASE WHEN TIPO = 'P' AND COALESCE(SALDOGERALFISICO, 0) = 0 THEN 1 ELSE 0 END) AS p_sem_saldo,
                    SUM(CASE WHEN TIPO = 'S' THEN 1 ELSE 0 END) AS s_total,
                    SUM(CASE WHEN TIPO = 'S' AND INATIVO = 'F' THEN 1 ELSE 0 END) AS s_ativos,
                    SUM(CASE WHEN TIPO = 'S' AND INATIVO = 'T' THEN 1 ELSE 0 END) AS s_inativos,
                    SUM(CASE WHEN TIPO = 'S' AND COALESCE(SALDOGERALFISICO, 0) <> 0 THEN 1 ELSE 0 END) AS s_com_saldo,
                    SUM(CASE WHEN TIPO = 'S' AND COALESCE(SALDOGERALFISICO, 0) = 0 THEN 1 ELSE 0 END) AS s_sem_saldo
                FROM TPRODUTO
            """
            rows = _query(con, sql)
            if rows:
                r = rows[0]
                resultado["produtos"] = {
                    "produtos": {
                        "total": r["p_total"],
                        "ativos": r["p_ativos"],
                        "inativos": r["p_inativos"],
                        "com_saldo": r["p_com_saldo"],
                        "sem_saldo": r["p_sem_saldo"],
                    },
                    "servicos": {
                        "total": r["s_total"],
                        "ativos": r["s_ativos"],
                        "inativos": r["s_inativos"],
                        "com_saldo": r["s_com_saldo"],
                        "sem_saldo": r["s_sem_saldo"],
                    },
                }
        except Exception:
            pass

    if "FCFO" in tabelas_existentes:
        try:
            sql = """
                SELECT
                    SUM(CASE WHEN TIPO = 'C' THEN 1 ELSE 0 END) AS c_total,
                    SUM(CASE WHEN TIPO = 'C' AND ATIVO = 'T' THEN 1 ELSE 0 END) AS c_ativos,
                    SUM(CASE WHEN TIPO = 'C' AND ATIVO = 'F' THEN 1 ELSE 0 END) AS c_inativos,
                    SUM(CASE WHEN TIPO = 'F' THEN 1 ELSE 0 END) AS f_total,
                    SUM(CASE WHEN TIPO = 'F' AND ATIVO = 'T' THEN 1 ELSE 0 END) AS f_ativos,
                    SUM(CASE WHEN TIPO = 'F' AND ATIVO = 'F' THEN 1 ELSE 0 END) AS f_inativos,
                    SUM(CASE WHEN TIPO = 'A' THEN 1 ELSE 0 END) AS a_total,
                    SUM(CASE WHEN TIPO = 'A' AND ATIVO = 'T' THEN 1 ELSE 0 END) AS a_ativos,
                    SUM(CASE WHEN TIPO = 'A' AND ATIVO = 'F' THEN 1 ELSE 0 END) AS a_inativos,
                    COUNT(*) AS total_geral
                FROM FCFO
            """
            rows = _query(con, sql)
            if rows:
                r = rows[0]
                resultado["parceiros"] = {
                    "clientes": {"total": r["c_total"], "ativos": r["c_ativos"], "inativos": r["c_inativos"]},
                    "fornecedores": {"total": r["f_total"], "ativos": r["f_ativos"], "inativos": r["f_inativos"]},
                    "ambos": {"total": r["a_total"], "ativos": r["a_ativos"], "inativos": r["a_inativos"]},
                    "total_geral": r["total_geral"],
                }
        except Exception:
            pass

        # Clientes por situação financeira (cruza FCFO com FLAN por CODCFO) —
        # mesma classificação usada no TGA: um cliente cai em exatamente uma
        # categoria, da mais urgente pra menos ("vencidos" ganha de "a
        # vencer", que ganha de "sem lançamento aberto"); "inativo" só conta
        # quem não tem cadastro ativo, independente de ter lançamento ou não.
        if "FLAN" in tabelas_existentes:
            try:
                sql = """
                    SELECT
                        SUM(CASE WHEN ATIVO = 'F' THEN 1 ELSE 0 END) AS inativos,
                        SUM(CASE WHEN ATIVO = 'T' AND EXISTS (
                            SELECT 1 FROM FLAN L WHERE L.CODCFO = FCFO.CODCFO AND L.PAGREC = 'R'
                            AND L.STATUSLAN = 'A' AND L.DATAVENCIMENTO < CURRENT_DATE
                        ) THEN 1 ELSE 0 END) AS vencidos,
                        SUM(CASE WHEN ATIVO = 'T' AND NOT EXISTS (
                            SELECT 1 FROM FLAN L WHERE L.CODCFO = FCFO.CODCFO AND L.PAGREC = 'R'
                            AND L.STATUSLAN = 'A' AND L.DATAVENCIMENTO < CURRENT_DATE
                        ) AND EXISTS (
                            SELECT 1 FROM FLAN L WHERE L.CODCFO = FCFO.CODCFO AND L.PAGREC = 'R'
                            AND L.STATUSLAN = 'A' AND L.DATAVENCIMENTO = CURRENT_DATE
                        ) THEN 1 ELSE 0 END) AS vencidos_no_dia,
                        SUM(CASE WHEN ATIVO = 'T' AND NOT EXISTS (
                            SELECT 1 FROM FLAN L WHERE L.CODCFO = FCFO.CODCFO AND L.PAGREC = 'R'
                            AND L.STATUSLAN = 'A' AND L.DATAVENCIMENTO <= CURRENT_DATE
                        ) AND EXISTS (
                            SELECT 1 FROM FLAN L WHERE L.CODCFO = FCFO.CODCFO AND L.PAGREC = 'R'
                            AND L.STATUSLAN = 'A' AND L.DATAVENCIMENTO > CURRENT_DATE
                        ) THEN 1 ELSE 0 END) AS a_vencer,
                        SUM(CASE WHEN ATIVO = 'T' AND NOT EXISTS (
                            SELECT 1 FROM FLAN L WHERE L.CODCFO = FCFO.CODCFO AND L.PAGREC = 'R' AND L.STATUSLAN = 'A'
                        ) THEN 1 ELSE 0 END) AS sem_aberto,
                        COUNT(*) AS total
                    FROM FCFO
                    WHERE TIPO = 'C'
                """
                r = _query(con, sql)[0]
                resultado["clientes_situacao"] = {
                    "vencidos": r["vencidos"] or 0,
                    "vencidos_no_dia": r["vencidos_no_dia"] or 0,
                    "a_vencer": r["a_vencer"] or 0,
                    "sem_aberto": r["sem_aberto"] or 0,
                    "inativos": r["inativos"] or 0,
                    "total": r["total"] or 0,
                }
            except Exception:
                pass

    # ---- Sistema Estoque: TMOV (movimentos e ordens de serviço) ----
    if "TMOV" in tabelas_existentes:
        try:
            sql = """
                SELECT
                    SUM(CASE WHEN STATUS = 'N' THEN 1 ELSE 0 END) AS normal,
                    SUM(CASE WHEN STATUS = 'F' THEN 1 ELSE 0 END) AS faturado,
                    SUM(CASE WHEN STATUS = 'P' THEN 1 ELSE 0 END) AS parc_quitado,
                    SUM(CASE WHEN STATUS = 'Q' THEN 1 ELSE 0 END) AS quitado,
                    SUM(CASE WHEN STATUS = 'A' THEN 1 ELSE 0 END) AS a_faturar,
                    SUM(CASE WHEN STATUS = 'C' THEN 1 ELSE 0 END) AS cancelado,
                    COUNT(*) AS total
                FROM TMOV
            """
            rows = _query(con, sql)
            if rows:
                resultado["movimentos"] = rows[0]
        except Exception:
            pass

        # Movimentos por tipo de documento (Orçamento, Pedido de Venda, PDV,
        # Nota Fiscal, Compra, Ajuste de Estoque, Ordem de Serviço, etc.) —
        # o nome de cada tipo vem do próprio cadastro do TGA (TTIPOMOV), não
        # é um valor adivinhado por nós.
        if "TTIPOMOV" in tabelas_existentes:
            try:
                sql = """
                    SELECT FIRST 15 M.CODTMV AS codigo, TRIM(T.NOME) AS nome, COUNT(*) AS qtd
                    FROM TMOV M
                    LEFT JOIN TTIPOMOV T ON T.CODTIPOMOV = M.CODTMV
                    GROUP BY M.CODTMV, T.NOME
                    ORDER BY 3 DESC
                """
                resultado["movimentos_por_tipo"] = [
                    {
                        "codigo": l["codigo"],
                        "nome": l["nome"] or f"Tipo {l['codigo']} (sem cadastro em TTIPOMOV)",
                        "qtd": l["qtd"] or 0,
                    }
                    for l in _query(con, sql)
                ]
            except Exception:
                pass

        try:
            sql = """
                SELECT
                    SUM(CASE WHEN STATUS2 = 'E' THEN 1 ELSE 0 END) AS encerrado,
                    SUM(CASE WHEN STATUS2 = 'S' THEN 1 ELSE 0 END) AS em_servico,
                    SUM(CASE WHEN STATUS2 = 'A' THEN 1 ELSE 0 END) AS em_aberto,
                    COUNT(*) AS total
                FROM TMOV
                WHERE STATUS2 IS NOT NULL
            """
            rows = _query(con, sql)
            if rows and rows[0]["total"]:
                resultado["ordens_servico"] = rows[0]
        except Exception:
            pass

    # ---- Sistema Estoque: Documentos Fiscais Eletrônicos (status por tipo) ----
    # NF-e e NFC-e moram juntas em TNFE; separamos pelo MODELODOCUMENTO de
    # TMOV (55=NF-e, 65=NFC-e). Boa parte dos registros mais antigos não tem
    # esse campo preenchido — em vez de errar a classificação, eles entram
    # no balde "nfe_indefinido", sem quebra por status.
    doc_fiscal: dict = {}

    if "TNFE" in tabelas_existentes and "TMOV" in tabelas_existentes:
        try:
            sql = """
                SELECT M.MODELODOCUMENTO AS modelo, N.STATUSNFE AS status, COUNT(*) AS qtd
                FROM TNFE N
                JOIN TMOV M ON M.IDMOV = N.IDMOV
                GROUP BY M.MODELODOCUMENTO, N.STATUSNFE
            """
            nfe = {"total": 0, **{v: 0 for v in STATUS_NFE.values()}}
            nfce = {"total": 0, **{v: 0 for v in STATUS_NFE.values()}}
            indefinido = {"total": 0}
            for linha in _query(con, sql):
                qtd = linha["qtd"] or 0
                nome_status = STATUS_NFE.get(linha["status"], "outro")
                alvo = nfe if linha["modelo"] == "55" else nfce if linha["modelo"] == "65" else None
                if alvo is None:
                    indefinido["total"] += qtd
                    continue
                alvo["total"] += qtd
                alvo[nome_status] = alvo.get(nome_status, 0) + qtd
            doc_fiscal["nfe"] = nfe
            doc_fiscal["nfce"] = nfce
            doc_fiscal["nfe_indefinido"] = indefinido
        except Exception:
            pass

    if "TNFEMUNICIPAL" in tabelas_existentes:
        try:
            nfse = {"total": 0, **{v: 0 for v in STATUS_NFSE.values()}}
            for linha in _query(con, "SELECT STATUS, COUNT(*) AS qtd FROM TNFEMUNICIPAL GROUP BY STATUS"):
                qtd = linha["qtd"] or 0
                nome = STATUS_NFSE.get(linha["status"], "outro")
                nfse["total"] += qtd
                nfse[nome] = nfse.get(nome, 0) + qtd
            doc_fiscal["nfse"] = nfse
        except Exception:
            pass

    if "TCTE" in tabelas_existentes:
        try:
            cte = {"total": 0, **{v: 0 for v in STATUS_CTE.values()}}
            for linha in _query(con, "SELECT STATUS, COUNT(*) AS qtd FROM TCTE GROUP BY STATUS"):
                qtd = linha["qtd"] or 0
                nome = STATUS_CTE.get(linha["status"], "outro")
                cte["total"] += qtd
                cte[nome] = cte.get(nome, 0) + qtd
            doc_fiscal["cte"] = cte
        except Exception:
            pass

    if "TMDFE" in tabelas_existentes:
        try:
            mdfe = {"total": 0, **{v: 0 for v in STATUS_MDFE.values()}}
            for linha in _query(con, "SELECT STATUS, COUNT(*) AS qtd FROM TMDFE GROUP BY STATUS"):
                qtd = linha["qtd"] or 0
                nome = STATUS_MDFE.get(linha["status"], "outro")
                mdfe["total"] += qtd
                mdfe[nome] = mdfe.get(nome, 0) + qtd
            doc_fiscal["mdfe"] = mdfe
        except Exception:
            pass

    if "TDFE" in tabelas_existentes:
        try:
            # TDFE = documentos de terceiros (NF-e onde somos destinatário).
            # O campo DFE guarda o status de manifestação do destinatário —
            # confirmado cruzando com os eventos SEFAZ em TDFEEVENTOS
            # (tpEvento 210200/210210/210220 = Confirmação/Ciência/Desconhecimento):
            # a contagem por DFE bateu exatamente com a contagem por evento mais
            # recente de cada documento. NFE ('T'/'F') indica se o XML já foi
            # baixado — bate 1:1 com XMLNFE estar preenchido ou não.
            mde = {"total": 0, **{v: 0 for v in STATUS_DFE.values()}}
            for linha in _query(con, "SELECT DFE, COUNT(*) AS qtd FROM TDFE GROUP BY DFE"):
                qtd = linha["qtd"] or 0
                nome = STATUS_DFE.get(str(linha["dfe"]).strip() if linha["dfe"] is not None else None, "outro")
                mde["total"] += qtd
                mde[nome] = mde.get(nome, 0) + qtd

            for linha in _query(con, "SELECT NFE, COUNT(*) AS qtd FROM TDFE GROUP BY NFE"):
                qtd = linha["qtd"] or 0
                if str(linha["nfe"]).strip().upper() == "T":
                    mde["download_realizado"] = mde.get("download_realizado", 0) + qtd
                else:
                    mde["download_pendente"] = mde.get("download_pendente", 0) + qtd

            doc_fiscal["mde"] = mde
        except Exception:
            pass

    if doc_fiscal:
        resultado["documentos_fiscais"] = doc_fiscal

    # ---- Sistema Financeiro: FLAN (lançamentos) ----
    if "FLAN" in tabelas_existentes:
        try:
            sql = """
                SELECT
                    SUM(CASE WHEN STATUSLAN = 'A' THEN 1 ELSE 0 END) AS aberto,
                    SUM(CASE WHEN STATUSLAN = 'B' THEN 1 ELSE 0 END) AS baixado,
                    SUM(CASE WHEN STATUSLAN = 'C' THEN 1 ELSE 0 END) AS cancelado,
                    SUM(CASE WHEN STATUSLAN = 'F' THEN 1 ELSE 0 END) AS faturado,
                    COUNT(*) AS total
                FROM FLAN
            """
            rows = _query(con, sql)
            if rows:
                resultado["financeiro"] = rows[0]
        except Exception:
            pass

    return resultado or None


def get_financeiro_valores(con, inicio: str, fim: str) -> Optional[dict]:
    """
    Valores financeiros de FLAN: total em aberto (a pagar/a receber) e total
    baixado (pago/recebido) dentro do período informado (baseado em
    DATABAIXA). Requer os campos PAGREC, STATUSLAN, VALORORIGINAL,
    VALORBAIXADO e DATABAIXA — se não existirem, retorna None.
    """
    tabelas_existentes = {t["name"] for t in list_tables(con, include_views=False)}
    if "FLAN" not in tabelas_existentes:
        return None

    try:
        sql = """
            SELECT
                SUM(CASE WHEN PAGREC = 'P' AND STATUSLAN = 'A' THEN VALORORIGINAL ELSE 0 END) AS a_pagar,
                SUM(CASE WHEN PAGREC = 'R' AND STATUSLAN = 'A' THEN VALORORIGINAL ELSE 0 END) AS a_receber,
                SUM(CASE WHEN PAGREC = 'R' AND STATUSLAN = 'B' AND DATABAIXA BETWEEN ? AND ? THEN VALORBAIXADO ELSE 0 END) AS recebido_periodo,
                SUM(CASE WHEN PAGREC = 'P' AND STATUSLAN = 'B' AND DATABAIXA BETWEEN ? AND ? THEN VALORBAIXADO ELSE 0 END) AS pago_periodo
            FROM FLAN
        """
        rows = _query(con, sql, (inicio, fim, inicio, fim))
        if not rows:
            return None
        r = rows[0]
        return {
            "a_pagar": float(r["a_pagar"] or 0),
            "a_receber": float(r["a_receber"] or 0),
            "recebido_periodo": float(r["recebido_periodo"] or 0),
            "pago_periodo": float(r["pago_periodo"] or 0),
            "inicio": inicio,
            "fim": fim,
        }
    except Exception:
        return None


# ---------------------------------------------------------------------------
# Snapshot da estrutura (para detectar mudanças após uma atualização do ERP)
# ---------------------------------------------------------------------------

def obter_snapshot_schema(con) -> dict:
    """
    Captura a estrutura inteira do banco (tabelas+campos, procedures,
    triggers, generators) numa única passada, para salvar como referência e
    comparar depois. Usa uma única consulta para todos os campos de todas as
    tabelas (mais rápido do que consultar tabela por tabela).
    """
    tabelas = {
        t["name"]: {"is_view": t["is_view"], "campos": {}}
        for t in list_tables(con)
    }

    sql = """
        SELECT TRIM(rf.rdb$relation_name) AS tabela,
               TRIM(rf.rdb$field_name) AS campo,
               f.rdb$field_type AS field_type,
               f.rdb$field_sub_type AS field_sub_type,
               f.rdb$field_length AS field_length,
               f.rdb$field_precision AS field_precision,
               f.rdb$field_scale AS field_scale,
               f.rdb$character_length AS max_chars,
               CASE WHEN rf.rdb$null_flag = 1 THEN 0 ELSE 1 END AS nullable
        FROM rdb$relation_fields rf
        JOIN rdb$fields f ON f.rdb$field_name = rf.rdb$field_source
        JOIN rdb$relations r ON r.rdb$relation_name = rf.rdb$relation_name
        WHERE (r.rdb$system_flag = 0 OR r.rdb$system_flag IS NULL)
        ORDER BY rf.rdb$relation_name, rf.rdb$field_position
    """
    for row in _query(con, sql):
        tabela = row["tabela"]
        if tabela not in tabelas:
            continue
        row["nullable"] = bool(row["nullable"])
        tabelas[tabela]["campos"][row["campo"]] = {
            "tipo": describe_field_type(row),
            "nullable": row["nullable"],
        }

    return {
        "tabelas": tabelas,
        "procedures": sorted(p["name"] for p in list_procedures(con)),
        "triggers": sorted(t["name"] for t in list_triggers(con)),
        "generators": sorted(g["name"] for g in list_generators(con)),
    }


def _diff_lista(antigo: dict, novo: dict, chave: str) -> dict:
    a = set(antigo.get(chave, []))
    n = set(novo.get(chave, []))
    return {"adicionados": sorted(n - a), "removidos": sorted(a - n)}


def comparar_snapshots(antigo: dict, novo: dict) -> dict:
    """Compara dois snapshots (ver `obter_snapshot_schema`) e retorna as diferenças."""
    tabelas_antigas = antigo.get("tabelas", {})
    tabelas_novas = novo.get("tabelas", {})
    nomes_antigos = set(tabelas_antigas)
    nomes_novos = set(tabelas_novas)

    campos_adicionados: dict = {}
    campos_removidos: dict = {}
    campos_alterados: dict = {}

    for tabela in sorted(nomes_antigos & nomes_novos):
        campos_a = tabelas_antigas[tabela]["campos"]
        campos_n = tabelas_novas[tabela]["campos"]
        novos = sorted(set(campos_n) - set(campos_a))
        removidos = sorted(set(campos_a) - set(campos_n))
        alterados = []
        for campo in sorted(set(campos_a) & set(campos_n)):
            if campos_a[campo] != campos_n[campo]:
                alterados.append({"campo": campo, "de": campos_a[campo], "para": campos_n[campo]})
        if novos:
            campos_adicionados[tabela] = novos
        if removidos:
            campos_removidos[tabela] = removidos
        if alterados:
            campos_alterados[tabela] = alterados

    return {
        "tabelas_adicionadas": sorted(nomes_novos - nomes_antigos),
        "tabelas_removidas": sorted(nomes_antigos - nomes_novos),
        "campos_adicionados": campos_adicionados,
        "campos_removidos": campos_removidos,
        "campos_alterados": campos_alterados,
        "procedures": _diff_lista(antigo, novo, "procedures"),
        "triggers": _diff_lista(antigo, novo, "triggers"),
        "generators": _diff_lista(antigo, novo, "generators"),
    }


def diff_tem_mudancas(diff: dict) -> bool:
    return bool(
        diff["tabelas_adicionadas"] or diff["tabelas_removidas"]
        or diff["campos_adicionados"] or diff["campos_removidos"] or diff["campos_alterados"]
        or diff["procedures"]["adicionados"] or diff["procedures"]["removidos"]
        or diff["triggers"]["adicionados"] or diff["triggers"]["removidos"]
        or diff["generators"]["adicionados"] or diff["generators"]["removidos"]
    )


# ---------------------------------------------------------------------------
# Análise Financeira: saldo de caixas, hoje/em atraso e "aging list"
# ---------------------------------------------------------------------------

def get_analise_financeira(con, data_referencia: Optional[date] = None) -> Optional[dict]:
    """
    Tela inspirada nos dashboards financeiros de ERP: saldo por caixa/conta
    (FCAIXA), total a pagar/receber "hoje", total em atraso no mês, e a
    "posição dos lançamentos" (aging list) por faixa de dias, vencidos e a
    vencer — tudo calculado a partir de `data_referencia` (padrão: hoje de
    verdade), permitindo ver a posição financeira em qualquer data. Requer
    FLAN; o saldo de caixas é opcional (FCAIXA).
    """
    tabelas_existentes = {t["name"] for t in list_tables(con, include_views=False)}
    if "FLAN" not in tabelas_existentes:
        return None

    hoje = data_referencia or date.today()
    primeiro_dia_mes = hoje.replace(day=1)

    try:
        linhas = _query(
            con,
            "SELECT PAGREC, DATAVENCIMENTO, VALORORIGINAL FROM FLAN WHERE STATUSLAN = 'A'",
        )
    except Exception:
        return None

    hoje_r = hoje_p = atraso_mes_r = atraso_mes_p = 0.0
    faixas = ["hoje", "d01_07", "d08_15", "d16_30", "d31_60", "d61_90", "mais_90"]
    aging = {
        f: {"receber_vencidos": 0.0, "pagar_vencidos": 0.0, "receber_vencer": 0.0, "pagar_vencer": 0.0}
        for f in faixas
    }

    for linha in linhas:
        venc = linha["datavencimento"]
        if venc is None:
            continue
        valor = float(linha["valororiginal"] or 0)
        pagrec = linha["pagrec"]
        dias = (hoje - venc).days  # > 0 = vencido há X dias; < 0 = vence em X dias

        if dias == 0:
            chave = "hoje"
        else:
            d = abs(dias)
            if d <= 7:
                chave = "d01_07"
            elif d <= 15:
                chave = "d08_15"
            elif d <= 30:
                chave = "d16_30"
            elif d <= 60:
                chave = "d31_60"
            elif d <= 90:
                chave = "d61_90"
            else:
                chave = "mais_90"

        prefixo = "receber" if pagrec == "R" else "pagar"
        sufixo = "vencidos" if dias >= 0 else "vencer"
        aging[chave][f"{prefixo}_{sufixo}"] += valor

        if dias == 0:
            if pagrec == "R":
                hoje_r += valor
            else:
                hoje_p += valor
        elif dias > 0 and venc >= primeiro_dia_mes:
            if pagrec == "R":
                atraso_mes_r += valor
            else:
                atraso_mes_p += valor

    resultado: dict = {
        "hoje": {"receber": hoje_r, "pagar": hoje_p},
        "atraso_mes": {"receber": atraso_mes_r, "pagar": atraso_mes_p},
        "aging": aging,
        "hoje_iso": hoje.isoformat(),
        "primeiro_dia_mes_iso": primeiro_dia_mes.isoformat(),
    }

    if "FCAIXA" in tabelas_existentes:
        try:
            caixas = _query(
                con,
                """
                SELECT TRIM(CODCAIXA) AS codigo, TRIM(DESCRICAO) AS descricao,
                       SALDOINICIAL AS saldo_inicial, SALDOINSTANTANEO AS saldo_atual,
                       INATIVO AS inativo
                FROM FCAIXA
                ORDER BY CODCAIXA
                """,
            )
            for c in caixas:
                c["saldo_inicial"] = float(c["saldo_inicial"] or 0)
                c["saldo_atual"] = float(c["saldo_atual"] or 0)
                c["inativo"] = c["inativo"] == "T"
            resultado["caixas"] = caixas
            resultado["saldo_total"] = sum(c["saldo_atual"] for c in caixas)
        except Exception:
            pass

    # Ranking dos 10 clientes/fornecedores com maior valor em atraso (soma do
    # VALORORIGINAL dos lançamentos em aberto, vencidos até `hoje`) — mesma
    # regra de "vencido" usada no aging list acima (dias >= 0). PAGREC='R' =
    # contas a receber (clientes); PAGREC='P' = contas a pagar (fornecedores).
    if "FCFO" in tabelas_existentes:
        def _ranking_atraso(pagrec: str) -> list[dict]:
            sql = """
                SELECT FIRST 10 F.CODCFO AS codigo, TRIM(C.NOMEFANTASIA) AS nome,
                       SUM(F.VALORORIGINAL) AS valor_atraso, COUNT(*) AS qtd,
                       MIN(F.DATAVENCIMENTO) AS vencimento_mais_antigo
                FROM FLAN F
                JOIN FCFO C ON C.CODCFO = F.CODCFO
                WHERE F.PAGREC = ? AND F.STATUSLAN = 'A' AND F.DATAVENCIMENTO <= ?
                GROUP BY F.CODCFO, C.NOMEFANTASIA
                ORDER BY 3 DESC
            """
            ranking = []
            for l in _query(con, sql, (pagrec, hoje)):
                venc_antigo = l["vencimento_mais_antigo"]
                dias = (hoje - venc_antigo).days if venc_antigo else None
                ranking.append({
                    "codigo": l["codigo"],
                    "nome": l["nome"],
                    "valor_atraso": float(l["valor_atraso"] or 0),
                    "qtd_lancamentos": l["qtd"] or 0,
                    "dias_atraso_mais_antigo": dias,
                })
            return ranking

        try:
            resultado["ranking_clientes_atraso"] = _ranking_atraso("R")
        except Exception:
            pass
        try:
            resultado["ranking_fornecedores_atraso"] = _ranking_atraso("P")
        except Exception:
            pass

    return resultado


# ---------------------------------------------------------------------------
# Análise de Estoque: saldo, mínimo/máximo, custo/valor, top grupos/fabricantes
# ---------------------------------------------------------------------------

def get_analise_estoque(con, limite: int = 10, dias_parado: int = 90) -> Optional[dict]:
    """
    Tela inspirada nos dashboards de estoque de ERP: saldo físico total,
    quantidade de produtos abaixo do mínimo e acima do máximo, custo e valor
    de venda do estoque parado, produtos sem giro (sem movimentação recente)
    e os `limite` grupos/fabricantes/produtos com maior custo de estoque.
    Requer TPRODUTO.

    `limite` controla quantas linhas aparecem nos rankings/tabelas (Top
    Produtos, Grupo, Fabricante, Produtos Parados). `dias_parado` é o
    número de dias sem nenhuma movimentação em TMOVITENS a partir do qual um
    produto com saldo em estoque é considerado "sem giro".

    Não inclui "Compras x Vendas", "Ranking de Fornecedores" nem "Pedidos de
    Compra pendentes" — não encontrei no banco uma tabela confiável que
    classifique os movimentos como compra/venda (só um código `CODTMV` sem
    tabela de descrição) nem uma tabela de pedidos de compra; prefiro deixar
    de fora a mostrar números adivinhados.
    """
    tabelas_existentes = {t["name"] for t in list_tables(con, include_views=False)}
    if "TPRODUTO" not in tabelas_existentes:
        return None

    limite = max(1, min(int(limite or 10), 200))
    dias_parado = max(1, min(int(dias_parado or 90), 3650))

    resultado: dict = {}

    try:
        sql = """
            SELECT
                SUM(SALDOGERALFISICO) AS saldo_total,
                SUM(CASE WHEN SALDOGERALFISICO < ESTOQUEMINIMO THEN 1 ELSE 0 END) AS abaixo_minimo,
                SUM(CASE WHEN ESTOQUEMAXIMO > 0 AND SALDOGERALFISICO > ESTOQUEMAXIMO THEN 1 ELSE 0 END) AS acima_maximo,
                SUM(SALDOGERALFISICO * CUSTOMEDIO) AS custo_estoque,
                SUM(SALDOGERALFISICO * PRECO1) AS valor_estoque
            FROM TPRODUTO
            WHERE TIPO = 'P'
        """
        r = _query(con, sql)[0]
        resultado["resumo"] = {
            "saldo_total": float(r["saldo_total"] or 0),
            "abaixo_minimo": r["abaixo_minimo"] or 0,
            "acima_maximo": r["acima_maximo"] or 0,
            "custo_estoque": float(r["custo_estoque"] or 0),
            "valor_estoque": float(r["valor_estoque"] or 0),
        }
    except Exception:
        pass

    if "TGRUPO" in tabelas_existentes:
        try:
            sql = f"""
                SELECT FIRST {limite} TRIM(COALESCE(G.DESCRICAO, P.CODGRUPO, 'Sem grupo')) AS grupo,
                       SUM(P.SALDOGERALFISICO * P.CUSTOMEDIO) AS custo
                FROM TPRODUTO P
                LEFT JOIN TGRUPO G ON G.CODGRUPO = P.CODGRUPO AND G.CODEMPRESA = P.CODEMPRESA
                WHERE P.TIPO = 'P'
                GROUP BY 1
                ORDER BY 2 DESC
            """
            resultado["por_grupo"] = [
                {"nome": l["grupo"], "custo": float(l["custo"] or 0)} for l in _query(con, sql)
            ]
        except Exception:
            pass

    try:
        sql = f"""
            SELECT FIRST {limite} COALESCE(CODFAB, 'Sem fabricante') AS fabricante,
                   SUM(SALDOGERALFISICO * CUSTOMEDIO) AS custo
            FROM TPRODUTO
            WHERE TIPO = 'P'
            GROUP BY 1
            ORDER BY 2 DESC
        """
        resultado["por_fabricante"] = [
            {"nome": l["fabricante"], "custo": float(l["custo"] or 0)} for l in _query(con, sql)
        ]
    except Exception:
        pass

    try:
        sql = """
            SELECT
                SUM(CASE WHEN SALDOGERALFISICO < 0 THEN 1 ELSE 0 END) AS negativo,
                SUM(CASE WHEN SALDOGERALFISICO = 0 THEN 1 ELSE 0 END) AS zero,
                SUM(CASE WHEN SALDOGERALFISICO > 0 THEN 1 ELSE 0 END) AS positivo
            FROM TPRODUTO
            WHERE TIPO = 'P'
        """
        r = _query(con, sql)[0]
        resultado["saldo_status"] = {
            "negativo": r["negativo"] or 0,
            "zero": r["zero"] or 0,
            "positivo": r["positivo"] or 0,
        }
    except Exception:
        pass

    try:
        sql = f"""
            SELECT FIRST {limite} TRIM(CODPRD) AS codigo, TRIM(NOMEFANTASIA) AS nome,
                   SALDOGERALFISICO AS saldo, CUSTOMEDIO AS custo_unitario,
                   SALDOGERALFISICO * CUSTOMEDIO AS custo_total
            FROM TPRODUTO
            WHERE TIPO = 'P'
            ORDER BY 5 DESC
        """
        resultado["top_produtos"] = [
            {
                "codigo": l["codigo"],
                "nome": l["nome"],
                "saldo": float(l["saldo"] or 0),
                "custo_unitario": float(l["custo_unitario"] or 0),
                "custo_total": float(l["custo_total"] or 0),
            }
            for l in _query(con, sql)
        ]
    except Exception:
        pass

    # Produtos parados (sem giro): maior data de movimentação (TMOVITENS) por
    # produto, ordenados do mais parado para o mais recente. O contador usa
    # `dias_parado` como corte; a lista mostra sempre os `limite` mais parados,
    # independente do corte (útil pra ver "quem está no fim da fila").
    if "TMOVITENS" in tabelas_existentes:
        try:
            cutoff = date.today() - timedelta(days=dias_parado)
            hoje = date.today()

            sql = f"""
                SELECT FIRST {limite}
                    TRIM(P.CODPRD) AS codigo, TRIM(P.NOMEFANTASIA) AS nome,
                    P.SALDOGERALFISICO AS saldo, P.CUSTOMEDIO AS custo_unitario,
                    (SELECT MAX(M.DATAEMISSAO) FROM TMOVITENS M WHERE M.CODPRD = P.CODPRD) AS ultima_mov
                FROM TPRODUTO P
                WHERE P.TIPO = 'P' AND P.SALDOGERALFISICO > 0
                ORDER BY ultima_mov ASC NULLS FIRST
            """
            lista = []
            for l in _query(con, sql):
                ultima = l["ultima_mov"]
                ultima_data = ultima.date() if hasattr(ultima, "date") else ultima
                dias = (hoje - ultima_data).days if ultima_data else None
                lista.append({
                    "codigo": l["codigo"],
                    "nome": l["nome"],
                    "saldo": float(l["saldo"] or 0),
                    "custo_unitario": float(l["custo_unitario"] or 0),
                    "ultima_movimentacao": ultima_data.isoformat() if ultima_data else None,
                    "dias_parado": dias,
                })

            sql_qtd = """
                SELECT COUNT(*) AS qtd FROM TPRODUTO P
                WHERE P.TIPO = 'P' AND P.SALDOGERALFISICO > 0
                AND (
                    (SELECT MAX(M.DATAEMISSAO) FROM TMOVITENS M WHERE M.CODPRD = P.CODPRD) IS NULL
                    OR (SELECT MAX(M.DATAEMISSAO) FROM TMOVITENS M WHERE M.CODPRD = P.CODPRD) < ?
                )
            """
            qtd = _query(con, sql_qtd, (cutoff,))[0]["qtd"] or 0

            resultado["produtos_parados"] = {
                "dias_limite": dias_parado,
                "qtd_sem_giro": qtd,
                "lista": lista,
            }
        except Exception:
            pass

    resultado["limite_atual"] = limite
    return resultado or None
