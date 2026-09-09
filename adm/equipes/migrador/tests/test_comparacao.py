"""Testes das funções puras de core/comparacao.py e dos modelos de comparação."""
import unittest

from core.comparacao import montar_script_contagem, parse_contagem
from core.modelos import ResultadoComparacao, TabelaComparada


class TestScriptContagem(unittest.TestCase):
    def test_dialeto_3_usa_aspas(self):
        sql = montar_script_contagem(["CLIENTES", "NF ITENS"], dialeto=3)
        self.assertIn('FROM "CLIENTES"', sql)
        self.assertIn('FROM "NF ITENS"', sql)

    def test_dialeto_1_sem_aspas(self):
        sql = montar_script_contagem(["CLIENTES"], dialeto=1)
        self.assertIn("FROM CLIENTES", sql)
        self.assertNotIn('FROM "CLIENTES"', sql)

    def test_rotulo_no_literal(self):
        sql = montar_script_contagem(["CLIENTES"], dialeto=3)
        self.assertIn("'CNT;CLIENTES;'", sql)


class TestParseContagem(unittest.TestCase):
    def test_basico(self):
        texto = "CNT;CLIENTES;12845\nCNT;PRODUTOS;5310\nlixo\nCNT;VAZIA;0\n"
        d = parse_contagem(texto)
        self.assertEqual(d, {"CLIENTES": 12845, "PRODUTOS": 5310, "VAZIA": 0})

    def test_linha_incompleta_ignorada(self):
        self.assertEqual(parse_contagem("CNT;SONOME\nCNT;X;naoNumero\n"), {})


class TestModelosComparacao(unittest.TestCase):
    def test_status_tabela(self):
        self.assertEqual(TabelaComparada("A", 10, 10).status, "OK")
        self.assertEqual(TabelaComparada("A", 10, 9).status, "DIVERGENTE")
        self.assertEqual(TabelaComparada("A", None, 9).status, "INDETERMINADO")

    def test_agregados(self):
        r = ResultadoComparacao(tabelas=[
            TabelaComparada("A", 10, 10),
            TabelaComparada("B", 5, 4),
            TabelaComparada("C", None, None),
        ])
        self.assertEqual(r.total_origem, 15)
        self.assertEqual(r.total_destino, 14)
        self.assertEqual([t.nome for t in r.divergentes], ["B"])
        self.assertEqual([t.nome for t in r.indeterminadas], ["C"])
        self.assertFalse(r.tudo_confere)

    def test_tudo_confere(self):
        r = ResultadoComparacao(tabelas=[TabelaComparada("A", 10, 10)])
        self.assertTrue(r.tudo_confere)


if __name__ == "__main__":
    unittest.main()
