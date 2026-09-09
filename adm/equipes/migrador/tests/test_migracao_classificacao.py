"""Testes de core/migracao.classificar_resultado_gbak e dos nomes de arquivo."""
import unittest
from datetime import datetime

from core.migracao import (
    classificar_resultado_gbak,
    nome_backup,
    nome_destino,
)


class TestClassificar(unittest.TestCase):
    def test_expected_backup_description_record_e_fatal(self):
        # É o erro dos prints anexados: gbak em modo restore recebeu algo que
        # não é um .fbk válido.
        saida = (
            "gbak: gbak version WI-V5.0.0.1306 Firebird 5.0\n"
            "gbak: ERROR:expected backup description record\n"
            "gbak:Exiting before completion due to errors\n"
        )
        fatal, indice, linhas = classificar_resultado_gbak(saida, 1, destino_criado=False)
        self.assertIn("expected backup description record", fatal)
        self.assertEqual(indice, 0)
        self.assertTrue(linhas)

    def test_erro_de_fk_em_indice_nao_e_fatal(self):
        saida = (
            "gbak:activating and creating deferred index\n"
            "gbak: ERROR:violation of FOREIGN KEY constraint \"FK_ITENS_NF\"\n"
            "gbak: ERROR:violation of FOREIGN KEY constraint \"FK_ITENS_PROD\"\n"
            "gbak:finishing, closing, and going home\n"
        )
        fatal, indice, linhas = classificar_resultado_gbak(saida, 0, destino_criado=True)
        self.assertEqual(fatal, "")
        self.assertEqual(indice, 2)

    def test_restore_limpo(self):
        saida = (
            "gbak:restoring data for table CLIENTES\n"
            "gbak:    12845 records restored\n"
            "gbak:finishing, closing, and going home\n"
        )
        fatal, indice, linhas = classificar_resultado_gbak(saida, 0, destino_criado=True)
        self.assertEqual(fatal, "")
        self.assertEqual(indice, 0)
        self.assertEqual(linhas, [])

    def test_returncode_ruim_sem_destino_e_fatal_generico(self):
        saida = "gbak:opened file backup.fbk\nalgum problema estranho aqui\n"
        fatal, _, _ = classificar_resultado_gbak(saida, 1, destino_criado=False)
        self.assertNotEqual(fatal, "")

    def test_returncode_ruim_mas_destino_criado_com_indice(self):
        # gbak sai com código != 0 por causa de erros de índice, mas o banco
        # foi criado: não deve virar fatal genérico.
        saida = (
            "gbak: ERROR:violation of PRIMARY KEY constraint\n"
            "gbak:finishing, closing, and going home\n"
        )
        fatal, indice, _ = classificar_resultado_gbak(saida, 1, destino_criado=True)
        self.assertEqual(fatal, "")
        self.assertEqual(indice, 1)

    def test_disco_cheio_e_fatal(self):
        saida = "gbak: ERROR:device is full, cannot write\n"
        fatal, _, _ = classificar_resultado_gbak(saida, 1, destino_criado=True)
        self.assertIn("device is full", fatal)


class TestNomes(unittest.TestCase):
    def test_nome_backup(self):
        q = datetime(2026, 9, 6, 23, 18, 11)
        self.assertEqual(nome_backup("C:/TGA/Dados/CLIENTE.FDB", q), "CLIENTE_FB25_20260906_231811.fbk")

    def test_nome_destino(self):
        q = datetime(2026, 9, 6, 23, 18, 11)
        self.assertEqual(nome_destino("C:/TGA/Dados/CLIENTE.FDB", q), "CLIENTE_FB50_20260906_231811.FDB")


if __name__ == "__main__":
    unittest.main()
