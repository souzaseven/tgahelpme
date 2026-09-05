"""
Testes de ui/janela_fila_restauracao.py: só a lógica pura de sugestão de
destino (_sugerir_destino é @staticmethod, chamável sem instanciar a janela
nem precisar de um mainloop do Tkinter). O restante (Treeview, threading,
cancelamento) foi validado manualmente com Firebird real durante o
desenvolvimento — testar UI Tkinter de ponta a ponta em unittest tem baixo
retorno frente à complexidade de simular corretamente o mainloop.
"""
import shutil
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from ui.janela_fila_restauracao import JanelaFilaRestauracao


class TestSugerirDestino(unittest.TestCase):
    def setUp(self):
        self.tmp = Path(tempfile.mkdtemp(prefix="fbtest_fila_"))

    def tearDown(self):
        shutil.rmtree(self.tmp, ignore_errors=True)

    def test_usa_nome_do_backup_quando_destino_nao_existe(self):
        backup = self.tmp / "backup_20260904_105746.fbk"
        backup.touch()
        destino = JanelaFilaRestauracao._sugerir_destino(str(backup), {"comprimido": False})
        self.assertEqual(destino, str(self.tmp / "backup.FDB"))

    def test_aplica_sufixo_quando_destino_ja_existe(self):
        # Duas restaurações da mesma origem na mesma fila (ou uma pasta que já
        # tem um .FDB com esse nome) não podem colidir sem decisão manual —
        # como não há diálogo interativo durante o processamento em lote, o
        # sufixo com timestamp precisa ser aplicado automaticamente.
        backup = self.tmp / "backup_20260904_105746.fbk"
        backup.touch()
        (self.tmp / "backup.FDB").touch()  # já existe

        destino = JanelaFilaRestauracao._sugerir_destino(str(backup), {"comprimido": False})

        self.assertNotEqual(destino, str(self.tmp / "backup.FDB"))
        self.assertIn("_RESTAURADO_", destino)
        self.assertTrue(destino.endswith(".FDB"))


if __name__ == "__main__":
    unittest.main()
