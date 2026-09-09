"""Testes da matemática de espaço em disco (core/espaco.py)."""
import unittest

from core.espaco import estimar, formatar_bytes


class TestEstimar(unittest.TestCase):
    def test_suficiente(self):
        e = estimar(
            tamanho_origem=1_000_000_000,
            disponivel_no_destino=10_000_000_000,
            fator_fbk=0.4, fator_fdb=2.0, margem=1.3,
        )
        # necessário = (0.4e9 + 2.0e9) * 1.3 = 3.12e9
        self.assertEqual(e.necessario_com_margem, 3_120_000_000)
        self.assertTrue(e.suficiente)
        self.assertEqual(e.faltando, 0)

    def test_insuficiente(self):
        e = estimar(
            tamanho_origem=1_000_000_000,
            disponivel_no_destino=2_000_000_000,
            fator_fbk=0.4, fator_fdb=2.0, margem=1.3,
        )
        self.assertFalse(e.suficiente)
        self.assertEqual(e.faltando, 1_120_000_000)


class TestFormatarBytes(unittest.TestCase):
    def test_varias_unidades(self):
        self.assertEqual(formatar_bytes(512), "512 B")
        self.assertEqual(formatar_bytes(1536), "1.5 KB")
        self.assertEqual(formatar_bytes(5 * 1024 * 1024), "5.0 MB")
        self.assertTrue(formatar_bytes(3 * 1024**3).endswith("GB"))


if __name__ == "__main__":
    unittest.main()
