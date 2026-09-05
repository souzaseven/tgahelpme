"""
Testes de tradução de erros e de proteção contra sobrescrita em restore.py.
Não dependem de uma instalação real do Firebird (usam mensagens sintéticas);
o teste de integração de ponta a ponta com gbak real fica em
test_integration_gbak.py (pulado automaticamente se o Firebird não estiver
instalado na máquina que rodar os testes).
"""
import shutil
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import MagicMock

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import restore


class TestTraduzirErroGbak(unittest.TestCase):
    def test_erro_permissao(self):
        msg = restore.traduzir_erro_gbak("gbak: ERROR: no permission for create access")
        self.assertIn("permissão", msg.lower())

    def test_erro_login(self):
        msg = restore.traduzir_erro_gbak("your user name and password are not defined")
        self.assertIn("senha", msg.lower())

    def test_erro_incompatibilidade_versao(self):
        msg = restore.traduzir_erro_gbak("wrong ODS version, expected something else")
        self.assertIn("incompat", msg.lower())

    def test_erro_ja_existe(self):
        msg = restore.traduzir_erro_gbak('database "X.FDB" already exists')
        self.assertIn("já existe", msg.lower())

    def test_erro_desconhecido_tem_fallback_generico(self):
        msg = restore.traduzir_erro_gbak("uma mensagem nunca vista antes 12345")
        self.assertTrue(len(msg) > 0)


class TestNuncaSobrescreveDestinoExistente(unittest.TestCase):
    """Garante a REGRA CRÍTICA do projeto: executar_restauracao() precisa
    recusar-se a prosseguir se o destino já existir, mesmo que todo o
    resto (backup válido, Firebird disponível) esteja correto."""

    def setUp(self):
        self.tmp = Path(tempfile.mkdtemp(prefix="fbtest_"))
        self.backup = self.tmp / "backup.fbk"
        self.backup.write_bytes(b"\x00\x02conteudo binario de teste" * 5)
        self.destino = self.tmp / "JA_EXISTE.FDB"
        self.destino.write_bytes(b"banco original que nao pode ser perdido")

    def tearDown(self):
        shutil.rmtree(self.tmp, ignore_errors=True)

    def test_bloqueia_quando_destino_existe(self):
        instalacao_fake = MagicMock()
        conteudo_original = self.destino.read_bytes()

        resultado = restore.executar_restauracao(
            caminho_backup=str(self.backup),
            caminho_destino=str(self.destino),
            instalacao=instalacao_fake,
            usuario="SYSDBA",
            senha="x",
            fator_estimativa=4.0,
            margem_seguranca=1.3,
            on_status=lambda _msg: None,
        )

        self.assertFalse(resultado.sucesso)
        self.assertIn("já existe", resultado.mensagem_usuario.lower())
        # O arquivo original não pode ter sido tocado.
        self.assertEqual(self.destino.read_bytes(), conteudo_original)


if __name__ == "__main__":
    unittest.main()
