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

    def test_erro_backup_corrompido_ou_truncado(self):
        msg = restore.traduzir_erro_gbak("gbak: ERROR: unexpected end of file on backup file")
        self.assertIn("corromp", msg.lower())


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


class TestExtrairTabelasComDadosDaSaidaMetadados(unittest.TestCase):
    def test_exclui_views_da_contagem(self):
        saida = (
            "gbak:restoring table PLG$USERS\n"
            "gbak:    restoring column PLG$UID\n"
            "gbak:restoring table PLG$VIEW_USERS\n"
            "gbak:    table PLG$VIEW_USERS is a view\n"
            "gbak:    restoring column PLG$UID\n"
            "gbak:restoring table PLG$SRP\n"
            "gbak:    restoring column PLG$COMMENT\n"
        )
        tabelas = restore.extrair_tabelas_com_dados_da_saida_metadados(saida)
        self.assertEqual(tabelas, {"PLG$USERS", "PLG$SRP"})

    def test_saida_vazia_retorna_conjunto_vazio(self):
        self.assertEqual(restore.extrair_tabelas_com_dados_da_saida_metadados(""), set())


class TestSinalCancelamento(unittest.TestCase):
    def test_motivo_padrao_e_usuario(self):
        sinal = restore.SinalCancelamento()
        self.assertFalse(sinal.is_set())
        sinal.set()
        self.assertTrue(sinal.is_set())
        self.assertIn("usuário", sinal.motivo.lower())

    def test_motivo_customizado_por_espaco_em_disco(self):
        sinal = restore.SinalCancelamento()
        sinal.set("Espaço em disco insuficiente.")
        self.assertTrue(sinal.is_set())
        self.assertEqual(sinal.motivo, "Espaço em disco insuficiente.")


class TestMonitorarEspacoDisco(unittest.TestCase):
    def setUp(self):
        self.tmp = Path(tempfile.mkdtemp(prefix="fbtest_"))

    def tearDown(self):
        shutil.rmtree(self.tmp, ignore_errors=True)

    def test_aciona_cancelamento_quando_limite_e_impossivel(self):
        import threading

        sinal = restore.SinalCancelamento()
        parar = threading.Event()
        destino_fake = self.tmp / "fake.fdb"

        thread = threading.Thread(
            target=restore._monitorar_espaco_disco,
            args=(destino_fake, sinal, parar, 999 * 1024 ** 4),  # 999 TB: impossível existir
            daemon=True,
        )
        thread.start()
        acionado = sinal.wait(timeout=5)
        parar.set()
        thread.join(timeout=2)

        self.assertTrue(acionado)
        self.assertIn("espaço", sinal.motivo.lower())

    def test_nao_aciona_com_limite_minimo(self):
        import threading

        sinal = restore.SinalCancelamento()
        parar = threading.Event()
        destino_fake = self.tmp / "fake.fdb"

        thread = threading.Thread(
            target=restore._monitorar_espaco_disco,
            args=(destino_fake, sinal, parar, 1),  # 1 byte: qualquer disco real tem isso livre
            daemon=True,
        )
        thread.start()
        acionado = sinal.wait(timeout=1.5)
        parar.set()
        thread.join(timeout=2)

        self.assertFalse(acionado)


class TestClassificarResultadoGbak(unittest.TestCase):
    """A restauração precisa distinguir um erro FATAL (nada de aproveitável
    foi criado) de um erro de índice/chave estrangeira que o gbak reporta
    sem abortar o processo (dados restaurados normalmente). Errar essa
    distinção tem dois jeitos de dar problema: tratar tudo como fatal
    esconde o resumo completo do usuário mesmo quando o banco foi
    restaurado; tratar tudo como não-fatal esconderia uma falha real de
    infraestrutura (disco, permissão, versão incompatível)."""

    def test_saida_limpa_sem_nenhum_erro(self):
        r = restore.classificar_resultado_gbak(0, "gbak: restoring table X\ngbak: finished", True)
        self.assertFalse(r.eh_falha_fatal)
        self.assertEqual(r.linhas_erro, [])

    def test_erro_de_indice_com_banco_criado_nao_e_fatal(self):
        # Caso real reportado: violação de FOREIGN KEY ao ativar um índice
        # deferred. O gbak tipicamente retorna código != 0 mesmo assim.
        saida = (
            'gbak:cannot commit index FKTMOVITENS_CODPRD\n'
            'gbak: ERROR:violation of FOREIGN KEY constraint "FKTMOVITENS_CODPRD" on table "TMOVITENS"\n'
            "gbak:     activating and creating deferred index FKTMOVITENS_CODTB1FAT\n"
        )
        r = restore.classificar_resultado_gbak(1, saida, banco_foi_criado=True)
        self.assertFalse(r.eh_falha_fatal)
        self.assertEqual(len(r.linhas_erro), 2)

    def test_codigo_retorno_diferente_de_zero_sem_banco_criado_e_fatal(self):
        r = restore.classificar_resultado_gbak(1, "gbak: alguma falha qualquer", banco_foi_criado=False)
        self.assertTrue(r.eh_falha_fatal)

    def test_erro_de_permissao_e_fatal_mesmo_com_banco_supostamente_criado(self):
        # Erro de infraestrutura conhecido nunca deve ser tratado como "só um
        # aviso de índice", mesmo que o arquivo de destino exista (parcial).
        r = restore.classificar_resultado_gbak(
            1, "gbak: ERROR: no permission for create access", banco_foi_criado=True
        )
        self.assertTrue(r.eh_falha_fatal)

    def test_versao_incompativel_e_fatal(self):
        r = restore.classificar_resultado_gbak(
            1, "wrong ODS version, expected something else", banco_foi_criado=True
        )
        self.assertTrue(r.eh_falha_fatal)

    def test_backup_corrompido_e_fatal_mesmo_com_fdb_parcial_ja_criado(self):
        # O caso mais perigoso de confundir: o gbak pode abortar NO MEIO da
        # restauração ao topar com um backup corrompido/truncado, deixando
        # um .fdb parcial (existente, tamanho > 0) para trás — sem tratar
        # isso como fatal, pareceria "sucesso com alguns erros de índice"
        # quando na verdade faltam tabelas inteiras.
        r = restore.classificar_resultado_gbak(
            1, "gbak: ERROR: unexpected end of file on backup file", banco_foi_criado=True
        )
        self.assertTrue(r.eh_falha_fatal)

    def test_codigo_zero_com_erro_pontual_ainda_reporta_a_linha(self):
        # Mesmo quando o código de retorno é 0, uma linha de erro isolada
        # (ex.: um único índice problemático) deve continuar aparecendo na
        # contagem — não deve exigir código != 0 para ser notada.
        r = restore.classificar_resultado_gbak(
            0, "gbak: ERROR: cannot commit index X\ngbak: finished", banco_foi_criado=True
        )
        self.assertFalse(r.eh_falha_fatal)
        self.assertEqual(len(r.linhas_erro), 1)


if __name__ == "__main__":
    unittest.main()
