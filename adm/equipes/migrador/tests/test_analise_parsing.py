"""Testes das funções de parsing de core/analise.py (sem Firebird instalado)."""
import unittest

from core.analise import montar_script_isql, parse_gstat_header, parse_isql_saida

GSTAT_FB25 = """
Database "C:\\TGA\\DADOS\\TGA.FDB"

Database header page information:
        Flags                   0
        Checksum                12345
        Generation              987654
        Page size               16384
        ODS version             11.2
        Oldest transaction      123
        Oldest active           124
        Oldest snapshot         124
        Next transaction        125
        Implementation ID       16
        Shadow count            0
        Page buffers            0
        Next header page        0
        Database dialect        3
        Creation date           Sep 5, 2026 10:00:00
        Attributes              force write

    Variable header data:
        Sweep interval:         20000
        *END*
"""

ISQL_SAIDA = """
MIG;CHARSET;WIN1252
MIG;DIALECT;3
MIG;ODS;11.2
MIG;PAGESIZE;16384
MIG;COUNT;TABLES;128
MIG;COUNT;VIEWS;35
MIG;COUNT;PROCEDURES;42
MIG;COUNT;TRIGGERS;78
MIG;COUNT;GENERATORS;19
MIG;COUNT;DOMAINS;54
MIG;COUNT;INDICES;210
MIG;COUNT;FK;61
MIG;TABLE;CLIENTES
MIG;TABLE;PRODUTOS
MIG;TABLE;NF_ITENS
MIG;UDF;RTRIM
MIG;UDF;F_LPAD
MIG;EXTTABLE;EXT_LOG
MIG;RESVD;BOOLEAN
MIG;RESVD;WINDOW
"""


class TestParseGstatHeader(unittest.TestCase):
    def test_campos(self):
        d = parse_gstat_header(GSTAT_FB25)
        self.assertEqual(d["ods"], "11.2")
        self.assertEqual(d["page_size"], 16384)
        self.assertEqual(d["sql_dialect"], 3)
        self.assertEqual(d["sweep_interval"], 20000)
        self.assertEqual(d["forced_writes"], "sim")
        self.assertIn("force write", d["atributos"])

    def test_texto_vazio(self):
        d = parse_gstat_header("")
        self.assertEqual(d["atributos"], [])
        self.assertNotIn("ods", d)


class TestParseIsql(unittest.TestCase):
    def test_valores(self):
        d = parse_isql_saida(ISQL_SAIDA)
        self.assertEqual(d["charset_padrao"], "WIN1252")
        self.assertEqual(d["sql_dialect"], 3)
        self.assertEqual(d["page_size"], 16384)
        self.assertEqual(d["contagens"]["TABLES"], 128)
        self.assertEqual(d["contagens"]["FK"], 61)
        self.assertEqual(d["tabelas"], ["CLIENTES", "PRODUTOS", "NF_ITENS"])
        self.assertEqual(d["udfs"], ["RTRIM", "F_LPAD"])
        self.assertEqual(d["tabelas_externas"], ["EXT_LOG"])
        self.assertEqual(sorted(d["identificadores"]), ["BOOLEAN", "WINDOW"])

    def test_ignora_ruido(self):
        d = parse_isql_saida("Database:  TGA.FDB\nSQL> \nMIG;CHARSET;NONE\n")
        self.assertEqual(d["charset_padrao"], "NONE")
        self.assertEqual(d["tabelas"], [])


class TestScriptIsql(unittest.TestCase):
    def test_menciona_tabelas_chave(self):
        sql = montar_script_isql()
        for termo in ("MON$DATABASE", "RDB$RELATION_TYPE", "RDB$FUNCTIONS", "'BOOLEAN'"):
            self.assertIn(termo, sql)


if __name__ == "__main__":
    unittest.main()
