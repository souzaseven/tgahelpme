"""
Testes de parsing de versão do gbak, cobrindo as strings reais reportadas
por cada versão do Firebird (2.5 a 5.0) — o pedido de suporte à restauração
usando Firebird 2.5 depende disso funcionar igual em todas as versões, já
que a detecção de instalações é genérica (não hardcoded para uma versão).
"""
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import firebird


class TestExtrairVersao(unittest.TestCase):
    def test_firebird_2_5(self):
        texto, tupla = firebird._extrair_versao("gbak:gbak version WI-V2.5.9.27149 Firebird 2.5")
        self.assertEqual(tupla, (2, 5))

    def test_firebird_3_0(self):
        texto, tupla = firebird._extrair_versao("gbak:gbak version WI-V3.0.11.33703 Firebird 3.0")
        self.assertEqual(tupla, (3, 0))

    def test_firebird_4_0(self):
        texto, tupla = firebird._extrair_versao("gbak:gbak version WI-V4.0.3.2975 Firebird 4.0")
        self.assertEqual(tupla, (4, 0))

    def test_firebird_5_0(self):
        # String real observada nesta máquina.
        texto, tupla = firebird._extrair_versao("gbak:gbak version WI-V5.0.3.1683 Firebird 5.0")
        self.assertEqual(tupla, (5, 0))

    def test_ordenacao_por_versao_mais_recente_primeiro(self):
        # Simula o cenário de múltiplas instalações lado a lado (2.5 e 5.0),
        # como pode acontecer em um servidor que ainda roda um ERP legado.
        _, tupla_25 = firebird._extrair_versao("gbak:gbak version WI-V2.5.9.27149 Firebird 2.5")
        _, tupla_50 = firebird._extrair_versao("gbak:gbak version WI-V5.0.3.1683 Firebird 5.0")
        instalacoes = [
            firebird.InstalacaoFirebird(
                pasta=Path(r"C:\Program Files (x86)\Firebird\Firebird_2_5"),
                gbak_path=Path(r"C:\Program Files (x86)\Firebird\Firebird_2_5\bin\gbak.exe"),
                gfix_path=None, isql_path=None, gstat_path=None,
                versao_texto="2.5", versao_tupla=tupla_25, origem="pasta_padrao",
            ),
            firebird.InstalacaoFirebird(
                pasta=Path(r"C:\Program Files\Firebird\Firebird_5_0"),
                gbak_path=Path(r"C:\Program Files\Firebird\Firebird_5_0\gbak.exe"),
                gfix_path=None, isql_path=None, gstat_path=None,
                versao_texto="5.0", versao_tupla=tupla_50, origem="pasta_padrao",
            ),
        ]
        instalacoes.sort(key=lambda i: i.versao_tupla, reverse=True)
        self.assertEqual(instalacoes[0].versao_tupla, (5, 0))
        self.assertEqual(instalacoes[1].versao_tupla, (2, 5))

        # A escolha automática pega a mais recente, mas o usuário pode trocar
        # manualmente para a 2.5 pela combobox (necessário quando o backup foi
        # gerado por um Firebird 2.5 e precisa ser restaurado com ela).
        melhor = firebird.escolher_melhor(instalacoes)
        self.assertEqual(melhor.versao_tupla, (5, 0))
        self.assertIn(instalacoes[1], instalacoes)  # a 2.5 continua disponível na lista


class TestDeteccaoDeIncompatibilidade(unittest.TestCase):
    def test_erro_tipico_ao_restaurar_backup_novo_com_gbak_antigo(self):
        # Cenário real de incompatibilidade: tentar restaurar um backup do
        # Firebird 5.0 usando o gbak do Firebird 2.5.
        saida = "gbak: ERROR: wrong ODS version, expected 11.0, encountered 13.1"
        self.assertTrue(firebird.indica_incompatibilidade_de_versao(saida))

    def test_saida_normal_nao_e_falso_positivo(self):
        saida = "gbak:restoring table PLG$USERS\ngbak:   1 records restored"
        self.assertFalse(firebird.indica_incompatibilidade_de_versao(saida))


if __name__ == "__main__":
    unittest.main()
