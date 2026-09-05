"""
Testes automatizados (unittest da biblioteca padrão, sem dependências
externas) cobrindo os cenários de risco listados no escopo do projeto:

  - FBK inexistente
  - arquivo vazio
  - arquivo que não é backup válido (texto, PNG, ZIP...)
  - caminho com espaços e com acentos
  - destino já existente (nunca deve autorizar sobrescrita)
  - nome de restauração paralela sugerido
  - espaço em disco insuficiente (estimativa)
  - descompactação de .fbk.gz corrompido
"""
import gzip
import shutil
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import validator


class TestValidarArquivoBackup(unittest.TestCase):
    def setUp(self):
        self.tmp = Path(tempfile.mkdtemp(prefix="fbtest_"))

    def tearDown(self):
        shutil.rmtree(self.tmp, ignore_errors=True)

    def test_arquivo_inexistente(self):
        resultado = validator.validar_arquivo_backup(str(self.tmp / "nao_existe.fbk"))
        self.assertFalse(resultado.ok)

    def test_arquivo_vazio(self):
        caminho = self.tmp / "vazio.fbk"
        caminho.touch()
        resultado = validator.validar_arquivo_backup(str(caminho))
        self.assertFalse(resultado.ok)
        self.assertIn("vazio", resultado.mensagem_usuario.lower())

    def test_extensao_invalida(self):
        caminho = self.tmp / "arquivo.txt"
        caminho.write_text("qualquer coisa")
        resultado = validator.validar_arquivo_backup(str(caminho))
        self.assertFalse(resultado.ok)

    def test_caminho_com_espacos_e_acentos(self):
        pasta = self.tmp / "pasta com espaço e acentuação"
        pasta.mkdir()
        caminho = pasta / "CLIENTE_ção.fbk"
        caminho.write_bytes(b"\x00\x02conteudo_falso_de_backup_1234567890")
        resultado = validator.validar_arquivo_backup(str(caminho))
        self.assertTrue(resultado.ok, resultado.mensagem_usuario)
        self.assertEqual(resultado.metadados["nome"], "CLIENTE_ção.fbk")

    def test_backup_gz_valido_e_extensao_dupla(self):
        caminho = self.tmp / "backup_20260904_105746.fbk.gz"
        with gzip.open(caminho, "wb") as f:
            f.write(b"\x00\x02conteudo_falso_de_backup_1234567890")
        resultado = validator.validar_arquivo_backup(str(caminho))
        self.assertTrue(resultado.ok)
        self.assertTrue(resultado.metadados["comprimido"])


class TestChecarIndiciosDeBackupValido(unittest.TestCase):
    def setUp(self):
        self.tmp = Path(tempfile.mkdtemp(prefix="fbtest_"))

    def tearDown(self):
        shutil.rmtree(self.tmp, ignore_errors=True)

    def test_arquivo_texto_puro_e_rejeitado(self):
        caminho = self.tmp / "nao_e_backup.fbk"
        caminho.write_text("isso e apenas um arquivo de texto comum, nao e binario")
        resultado = validator.checar_indicios_de_backup_valido(str(caminho))
        self.assertFalse(resultado.ok)

    def test_arquivo_png_e_rejeitado(self):
        caminho = self.tmp / "imagem_disfarcada.fbk"
        caminho.write_bytes(b"\x89PNG\r\n\x1a\n" + b"\x00" * 20)
        resultado = validator.checar_indicios_de_backup_valido(str(caminho))
        self.assertFalse(resultado.ok)

    def test_binario_plausivel_passa(self):
        caminho = self.tmp / "parece_backup.fbk"
        caminho.write_bytes(bytes(range(0, 32)) * 4)
        resultado = validator.checar_indicios_de_backup_valido(str(caminho))
        self.assertTrue(resultado.ok)


class TestPrepararBackupParaRestauracao(unittest.TestCase):
    def setUp(self):
        self.tmp = Path(tempfile.mkdtemp(prefix="fbtest_"))

    def tearDown(self):
        shutil.rmtree(self.tmp, ignore_errors=True)

    def test_gz_corrompido_gera_erro_claro(self):
        caminho = self.tmp / "corrompido.fbk.gz"
        caminho.write_bytes(b"isso nao e um gzip valido")
        resultado = validator.preparar_backup_para_restauracao(str(caminho), self.tmp / "extraido")
        self.assertFalse(resultado.ok)

    def test_gz_valido_e_descompactado(self):
        caminho = self.tmp / "ok.fbk.gz"
        conteudo = b"conteudo binario de teste" * 10
        with gzip.open(caminho, "wb") as f:
            f.write(conteudo)
        resultado = validator.preparar_backup_para_restauracao(str(caminho), self.tmp / "extraido")
        self.assertTrue(resultado.ok)
        caminho_efetivo = Path(resultado.metadados["caminho_efetivo"])
        self.assertTrue(caminho_efetivo.exists())
        self.assertEqual(caminho_efetivo.read_bytes(), conteudo)

    def test_fbk_puro_nao_e_alterado(self):
        caminho = self.tmp / "puro.fbk"
        caminho.write_bytes(b"conteudo")
        resultado = validator.preparar_backup_para_restauracao(str(caminho), self.tmp / "extraido")
        self.assertTrue(resultado.ok)
        self.assertEqual(resultado.metadados["caminho_efetivo"], str(caminho))
        self.assertFalse(resultado.metadados["temporario"])


class TestValidarDestino(unittest.TestCase):
    def setUp(self):
        self.tmp = Path(tempfile.mkdtemp(prefix="fbtest_"))

    def tearDown(self):
        shutil.rmtree(self.tmp, ignore_errors=True)

    def test_extensao_diferente_de_fdb_e_rejeitada(self):
        resultado = validator.validar_destino(str(self.tmp / "saida.txt"))
        self.assertFalse(resultado.ok)

    def test_destino_inexistente_e_valido(self):
        resultado = validator.validar_destino(str(self.tmp / "NOVO.FDB"))
        self.assertTrue(resultado.ok)
        self.assertFalse(resultado.metadados["ja_existe"])

    def test_destino_ja_existente_e_sinalizado_sem_autorizar_sobrescrita(self):
        caminho = self.tmp / "EXISTENTE.FDB"
        caminho.write_bytes(b"banco fake")
        resultado = validator.validar_destino(str(caminho))
        self.assertTrue(resultado.ok)  # o caminho em si é válido...
        self.assertTrue(resultado.metadados["ja_existe"])  # ...mas o chamador NUNCA deve sobrescrever

    def test_pasta_de_destino_e_criada_quando_nao_existe(self):
        caminho = self.tmp / "subpasta_nova" / "NOVO.FDB"
        resultado = validator.validar_destino(str(caminho))
        self.assertTrue(resultado.ok)
        self.assertTrue(caminho.parent.is_dir())


class TestExtrairNomeBancoOriginal(unittest.TestCase):
    """A rotina de backup atual gera nomes genéricos como
    'backup_20260904_105746.fbk.gz' — sem o nome do cliente/banco. O gbak,
    porém, grava o caminho completo do banco original em texto plano no
    cabeçalho do arquivo, então extraímos dali em vez de usar o nome do
    arquivo de backup."""

    def setUp(self):
        self.tmp = Path(tempfile.mkdtemp(prefix="fbtest_"))

    def tearDown(self):
        shutil.rmtree(self.tmp, ignore_errors=True)

    @staticmethod
    def _cabecalho_falso(caminho_banco: str) -> bytes:
        # Reproduz o formato observado em um backup real do gbak: alguns
        # bytes binários de controle seguidos do caminho do banco em texto.
        return b"\x00\x02\x04\x0b\x00\x00\x00\x04" + caminho_banco.encode("latin-1") + b"\x01\x18resto"

    def test_extrai_nome_de_backup_puro(self):
        caminho = self.tmp / "backup_20260904_105746.fbk"
        caminho.write_bytes(self._cabecalho_falso(r"S:\tga\C08365\dados\C08365\TGA.FDB"))
        nome = validator.extrair_nome_banco_original(str(caminho), comprimido=False)
        self.assertEqual(nome, "TGA.FDB")

    def test_extrai_nome_de_backup_comprimido(self):
        caminho = self.tmp / "backup_20260904_105746.fbk.gz"
        with gzip.open(caminho, "wb") as f:
            f.write(self._cabecalho_falso(r"C:\TGA\DADOS\CLIENTE.FDB"))
        nome = validator.extrair_nome_banco_original(str(caminho), comprimido=True)
        self.assertEqual(nome, "CLIENTE.FDB")

    def test_retorna_none_quando_nao_ha_caminho_reconhecivel(self):
        caminho = self.tmp / "backup_sem_pista.fbk"
        caminho.write_bytes(b"\x00\x02conteudo binario qualquer sem caminho nenhum" * 3)
        nome = validator.extrair_nome_banco_original(str(caminho), comprimido=False)
        self.assertIsNone(nome)


class TestSugestaoDeNomeParalelo(unittest.TestCase):
    def test_sugestao_contem_marcador_restaurado_e_timestamp(self):
        sugestao = validator.sugerir_nome_restauracao_paralela(r"C:\TGA\DADOS\CLIENTE.FDB")
        self.assertIn("CLIENTE_RESTAURADO_", sugestao)
        self.assertTrue(sugestao.endswith(".FDB"))
        self.assertNotEqual(sugestao, r"C:\TGA\DADOS\CLIENTE.FDB")


class TestVerificarEspacoDisco(unittest.TestCase):
    def setUp(self):
        self.tmp = Path(tempfile.mkdtemp(prefix="fbtest_"))

    def tearDown(self):
        shutil.rmtree(self.tmp, ignore_errors=True)

    def test_estimativa_absurda_reporta_espaco_insuficiente(self):
        resultado = validator.verificar_espaco_disco(
            str(self.tmp), tamanho_backup_bytes=10**18, fator_estimativa=1.0, margem_seguranca=1.0
        )
        self.assertFalse(resultado.ok)

    def test_backup_pequeno_reporta_espaco_suficiente(self):
        resultado = validator.verificar_espaco_disco(
            str(self.tmp), tamanho_backup_bytes=1024, fator_estimativa=4.0, margem_seguranca=1.3
        )
        self.assertTrue(resultado.ok)


if __name__ == "__main__":
    unittest.main()
