"""Testes das regras puras de core/compatibilidade.py."""
import unittest

from core.compatibilidade import (
    avaliar,
    avaliar_charset,
    avaliar_dialeto,
    avaliar_ods,
    avaliar_page_size,
    avaliar_palavras_reservadas,
    avaliar_tabelas_externas,
    avaliar_udfs,
    existe_bloqueio,
)
from core.modelos import ResultadoAnalise


class TestUdf(unittest.TestCase):
    def test_sem_udf(self):
        self.assertIsNone(avaliar_udfs([]))

    def test_com_udf(self):
        a = avaliar_udfs(["RTRIM", "F_LPAD", "  ", "RTRIM"])
        self.assertIsNotNone(a)
        self.assertEqual(a.nivel, "atencao")
        self.assertEqual(a.itens, ["F_LPAD", "RTRIM"])


class TestCharset(unittest.TestCase):
    def test_none_gera_aviso(self):
        self.assertIsNotNone(avaliar_charset("NONE"))
        self.assertIsNotNone(avaliar_charset(" ascii "))

    def test_utf8_ok(self):
        self.assertIsNone(avaliar_charset("UTF8"))
        self.assertIsNone(avaliar_charset("WIN1252"))


class TestPalavrasReservadas(unittest.TestCase):
    def test_detecta_boolean_e_window(self):
        a = avaliar_palavras_reservadas(["CLIENTES", "BOOLEAN", "window", "Pos"])
        self.assertIsNotNone(a)
        self.assertEqual(len(a.itens), 2)
        self.assertTrue(any("Firebird 3.0" in i for i in a.itens))

    def test_nada_reservado(self):
        self.assertIsNone(avaliar_palavras_reservadas(["CLIENTES", "PRODUTOS", "NF_ITENS"]))

    def test_rdb_record_version(self):
        a = avaliar_palavras_reservadas(["RDB$RECORD_VERSION"])
        self.assertIsNotNone(a)


class TestOutros(unittest.TestCase):
    def test_tabelas_externas(self):
        self.assertIsNone(avaliar_tabelas_externas([]))
        self.assertIsNotNone(avaliar_tabelas_externas(["EXT_LOG"]))

    def test_dialeto_1_info(self):
        a = avaliar_dialeto(1)
        self.assertEqual(a.nivel, "info")
        self.assertIsNone(avaliar_dialeto(3))

    def test_page_size_pequeno(self):
        self.assertIsNotNone(avaliar_page_size(2048))
        self.assertIsNone(avaliar_page_size(8192))
        self.assertIsNone(avaliar_page_size(0))

    def test_ods_bloqueio(self):
        a = avaliar_ods(ResultadoAnalise(caminho="x", ods="13.0"))
        self.assertEqual(a.nivel, "bloqueio")
        self.assertIsNone(avaliar_ods(ResultadoAnalise(caminho="x", ods="11.2")))
        self.assertIsNone(avaliar_ods(ResultadoAnalise(caminho="x", ods="?")))


class TestAvaliarOrquestrador(unittest.TestCase):
    def test_ordem_e_bloqueio(self):
        analise = ResultadoAnalise(
            caminho="x",
            ods="13.0",  # dispara bloqueio
            sql_dialect=1,  # dispara info
            charset_padrao="NONE",  # dispara atencao
            page_size=1024,
            udfs=["RTRIM"],
            identificadores=["BOOLEAN"],
        )
        avisos = avaliar(analise)
        self.assertEqual(avisos[0].nivel, "bloqueio")
        niveis = [a.nivel for a in avisos]
        self.assertEqual(niveis, sorted(niveis, key={"bloqueio": 0, "atencao": 1, "info": 2}.get))
        self.assertTrue(existe_bloqueio(avisos))

    def test_25_sem_bloqueio(self):
        analise = ResultadoAnalise(caminho="x", ods="11.2", sql_dialect=3, charset_padrao="UTF8")
        self.assertFalse(existe_bloqueio(avaliar(analise)))


if __name__ == "__main__":
    unittest.main()
