import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import restore
import stats


class TestAnalisarSaidaGbak(unittest.TestCase):
    def test_conta_registros_por_tabela_e_total(self):
        saida = (
            "gbak:restoring data for table PLG$SRP\n"
            "gbak:   1 records restored\n"
            "gbak:restoring data for table PLG$USERS\n"
            "gbak:   1 records restored\n"
        )
        analise = restore.analisar_saida_gbak(saida)
        self.assertEqual(analise.total_registros_restaurados, 2)
        self.assertEqual(analise.quantidade_tabelas_com_dados, 2)
        self.assertEqual(analise.registros_por_tabela["PLG$SRP"], 1)
        self.assertEqual(analise.registros_por_tabela["PLG$USERS"], 1)

    def test_tabela_sem_dados_nao_conta(self):
        # "restoring table X" (metadados) sem "records restored" depois não
        # deve aparecer na contagem de tabelas COM DADOS.
        saida = "gbak:restoring table PLG$VIEW_USERS\ngbak:    table PLG$VIEW_USERS is a view\n"
        analise = restore.analisar_saida_gbak(saida)
        self.assertEqual(analise.total_registros_restaurados, 0)
        self.assertEqual(analise.quantidade_tabelas_com_dados, 0)

    def test_saida_vazia(self):
        analise = restore.analisar_saida_gbak("")
        self.assertEqual(analise.total_registros_restaurados, 0)


class TestExtrairTabelasComProblema(unittest.TestCase):
    def test_identifica_tabela_com_registro_orfao(self):
        saida = (
            "Validation started\n"
            "Relation 128 (EMPLOYEE)\n"
            "    process pointer page 0 of 1\n"
            "Relation 130 (SOME_TABLE)\n"
            "    process pointer page 0 of 1\n"
            "    record 340, page 0, line 21 is an orphan\n"
            "Validation finished: 1 errors, 0 warnings, 0 fixed\n"
        )
        tabelas = stats.extrair_tabelas_com_problema(saida)
        self.assertEqual(tabelas, ["SOME_TABLE"])

    def test_banco_limpo_nao_reporta_nenhuma_tabela(self):
        saida = (
            "Relation 128 (EMPLOYEE)\n"
            "    process pointer page 0 of 1\n"
            "Validation finished: 0 errors, 0 warnings, 0 fixed\n"
        )
        self.assertEqual(stats.extrair_tabelas_com_problema(saida), [])

    def test_resultado_validacao_inclui_tabelas_na_mensagem(self):
        saida = (
            "Relation 130 (CLIENTES)\n"
            "    process pointer page 0 of 1\n"
            "    checksum error\n"
            "Validation finished: 1 errors, 0 warnings, 0 fixed\n"
        )
        # Simula o parsing direto (sem rodar o gfix de verdade)
        tabelas = stats.extrair_tabelas_com_problema(saida)
        m = stats._PADRAO_RESUMO_VALIDACAO.search(saida)
        self.assertIsNotNone(m)
        self.assertEqual(tabelas, ["CLIENTES"])


if __name__ == "__main__":
    unittest.main()
