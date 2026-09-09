"""Testes das funções puras de core/firebird.py."""
import unittest

from core.firebird import InstalacaoFirebird, _extrair_versao, escolher_por_familia
from pathlib import Path


def _inst(versao_texto: str) -> InstalacaoFirebird:
    _, tupla = _extrair_versao(versao_texto)
    p = Path("x/gbak.exe")
    return InstalacaoFirebird(
        pasta=p.parent, gbak_path=p, gfix_path=None, isql_path=None, gstat_path=None,
        versao_texto=versao_texto, versao_tupla=tupla, origem_deteccao="teste",
    )


class TestExtrairVersao(unittest.TestCase):
    def test_firebird_5(self):
        texto, tupla = _extrair_versao("gbak: gbak version WI-V5.0.3.1683 Firebird 5.0")
        self.assertEqual(tupla, (5, 0, 3, 1683))

    def test_firebird_25(self):
        texto, tupla = _extrair_versao("gbak: gbak version WI-V2.5.9.27139 Firebird 2.5")
        self.assertEqual(tupla, (2, 5, 9, 27139))

    def test_sem_V_usa_firebird(self):
        texto, tupla = _extrair_versao("something Firebird 4.0 blah")
        self.assertEqual(tupla, (4, 0))

    def test_lixo(self):
        texto, tupla = _extrair_versao("no version here")
        self.assertEqual(tupla, ())


class TestFamiliaEBuild(unittest.TestCase):
    def test_familia(self):
        self.assertEqual(_inst("WI-V2.5.9.27139 Firebird 2.5").familia, "2.5")
        self.assertEqual(_inst("WI-V5.0.3.1683 Firebird 5.0").familia, "5.0")
        self.assertEqual(_inst("no version").familia, "?")

    def test_versao_build(self):
        self.assertEqual(_inst("WI-V5.0.3.1683 Firebird 5.0").versao_build, "5.0.3.1683")


class TestEscolherPorFamilia(unittest.TestCase):
    def setUp(self):
        self.lista = [
            _inst("WI-V5.0.3.1683 Firebird 5.0"),
            _inst("WI-V4.0.5.3140 Firebird 4.0"),
            _inst("WI-V2.5.9.27139 Firebird 2.5"),
        ]

    def test_acha_25(self):
        self.assertEqual(escolher_por_familia(self.lista, "2.5").familia, "2.5")

    def test_aceita_sem_ponto(self):
        self.assertEqual(escolher_por_familia(self.lista, "5").familia, "5.0")

    def test_nao_acha(self):
        self.assertIsNone(escolher_por_familia(self.lista, "3.0"))


if __name__ == "__main__":
    unittest.main()
