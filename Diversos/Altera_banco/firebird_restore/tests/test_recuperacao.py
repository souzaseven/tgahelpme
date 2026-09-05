"""
Testes de recuperacao.py: as validações que não dependem de Firebird
instalado (nome sugerido, parsing da saída do gbak -b, checagens de entrada)
ficam aqui com mensagens sintéticas. O fluxo completo de ponta a ponta (gfix
-mend + gbak -ignore de verdade) é testado em test_integration_gbak.py,
pulado automaticamente se esta máquina não tiver o Firebird instalado.
"""
import shutil
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import MagicMock

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import recuperacao


class TestSugerirNomeBackupRecuperacao(unittest.TestCase):
    def test_usa_mesmo_nome_com_sufixo_na_mesma_pasta(self):
        sugestao = recuperacao.sugerir_nome_backup_recuperacao(r"C:\dados\TGA.FDB")
        self.assertEqual(sugestao, r"C:\dados\TGA_RECUPERADO.fbk")


class TestAnalisarSaidaBackup(unittest.TestCase):
    def test_conta_registros_escritos_por_tabela(self):
        saida = (
            "gbak:    writing data for table T\n"
            "gbak:2 records written\n"
            "gbak:    writing data for table OUTRA\n"
            "gbak:5 records written\n"
        )
        analise = recuperacao._analisar_saida_backup(saida)
        self.assertEqual(analise.total_registros, 7)
        self.assertEqual(analise.quantidade_tabelas_com_dados, 2)

    def test_saida_vazia_retorna_zero(self):
        analise = recuperacao._analisar_saida_backup("")
        self.assertEqual(analise.total_registros, 0)
        self.assertEqual(analise.quantidade_tabelas_com_dados, 0)

    def test_nao_confunde_com_formato_de_restore(self):
        # "records restored" (formato do RESTORE) não deve ser contado aqui —
        # só "records written" (formato do BACKUP).
        saida = "gbak:restoring data for table T\ngbak:3 records restored\n"
        analise = recuperacao._analisar_saida_backup(saida)
        self.assertEqual(analise.total_registros, 0)


class TestValidacoesDeEntrada(unittest.TestCase):
    """A REGRA CRÍTICA aqui é dupla: nunca mexer no arquivo original, e
    nunca sobrescrever um backup de saída já existente."""

    def setUp(self):
        self.tmp = Path(tempfile.mkdtemp(prefix="fbtest_recup_"))
        self.instalacao_fake = MagicMock()

    def tearDown(self):
        shutil.rmtree(self.tmp, ignore_errors=True)

    def test_origem_inexistente_e_rejeitada(self):
        r = recuperacao.recuperar_banco_corrompido(
            str(self.tmp / "nao_existe.fdb"), str(self.tmp / "saida.fbk"),
            self.instalacao_fake, "SYSDBA", "x", on_status=lambda _m: None,
        )
        self.assertFalse(r.sucesso)

    def test_destino_ja_existente_e_rejeitado_sem_sobrescrever(self):
        origem = self.tmp / "origem.fdb"
        origem.write_bytes(b"conteudo original")
        destino = self.tmp / "ja_existe.fbk"
        destino.write_bytes(b"nao pode ser sobrescrito")

        r = recuperacao.recuperar_banco_corrompido(
            str(origem), str(destino), self.instalacao_fake, "SYSDBA", "x", on_status=lambda _m: None,
        )

        self.assertFalse(r.sucesso)
        self.assertEqual(destino.read_bytes(), b"nao pode ser sobrescrito")

    def test_sem_gfix_e_rejeitado(self):
        origem = self.tmp / "origem.fdb"
        origem.write_bytes(b"conteudo original")
        self.instalacao_fake.gfix_path = None

        r = recuperacao.recuperar_banco_corrompido(
            str(origem), str(self.tmp / "saida.fbk"), self.instalacao_fake, "SYSDBA", "x",
            on_status=lambda _m: None,
        )

        self.assertFalse(r.sucesso)
        self.assertIn("gfix", r.mensagem_usuario.lower())


if __name__ == "__main__":
    unittest.main()
