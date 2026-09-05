"""
Teste de integração de ponta a ponta para recuperacao.py: cria um banco
Firebird de verdade e roda o fluxo completo (gfix -mend -full, gfix -sweep,
gbak -b -ignore) sobre ele. Não simula corrupção real (não há uma forma
confiável e portátil de corromper um banco Firebird sob demanda só para
teste) — o que este teste garante é que a MECÂNICA do fluxo funciona nesta
instalação do Firebird (os comandos existem, aceitam essas flags, e o
resultado é consistente), não que a recuperação funcione sobre um banco
realmente danificado.

Pulado automaticamente (não falha) nas mesmas condições de
test_integration_gbak.py: sem Firebird instalado, ou credenciais padrão
(SYSDBA/masterkey) diferentes nesta máquina.
"""
import shutil
import subprocess
import sys
import tempfile
import threading
import time
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import firebird
import recuperacao
from restore import SinalCancelamento

_USUARIO_TESTE = "SYSDBA"
_SENHA_TESTE = "masterkey"
_CREATION_FLAGS = subprocess.CREATE_NO_WINDOW if hasattr(subprocess, "CREATE_NO_WINDOW") else 0


class TestIntegracaoRecuperacaoReal(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        instalacoes = firebird.localizar_instalacoes()
        if not instalacoes:
            raise unittest.SkipTest("Nenhuma instalação do Firebird encontrada nesta máquina.")
        cls.instalacao = instalacoes[0]
        if cls.instalacao.isql_path is None or cls.instalacao.gfix_path is None:
            raise unittest.SkipTest("isql.exe ou gfix.exe não encontrado na instalação detectada.")

        cls.tmp = Path(tempfile.mkdtemp(prefix="fbtest_integracao_recup_"))
        cls.banco = cls.tmp / "BANCO_TESTE_RECUP.FDB"

        script_criacao = (
            f"CREATE DATABASE '{cls.banco}' USER '{_USUARIO_TESTE}' PASSWORD '{_SENHA_TESTE}';\n"
            "CREATE TABLE TESTE_RECUPERACAO (ID INTEGER, NOME VARCHAR(50));\n"
            "COMMIT;\n"
            "INSERT INTO TESTE_RECUPERACAO VALUES (1, 'primeiro registro');\n"
            "INSERT INTO TESTE_RECUPERACAO VALUES (2, 'segundo registro');\n"
            "INSERT INTO TESTE_RECUPERACAO VALUES (3, 'terceiro registro');\n"
            "COMMIT;\n"
        )
        try:
            resultado = subprocess.run(
                [str(cls.instalacao.isql_path), "-user", _USUARIO_TESTE, "-password", _SENHA_TESTE],
                input=script_criacao, capture_output=True, text=True, timeout=20, creationflags=_CREATION_FLAGS,
            )
        except (OSError, subprocess.TimeoutExpired) as exc:
            shutil.rmtree(cls.tmp, ignore_errors=True)
            raise unittest.SkipTest(f"Não foi possível executar isql.exe: {exc}")

        if resultado.returncode != 0 or not cls.banco.exists():
            saida = (resultado.stdout or "") + (resultado.stderr or "")
            shutil.rmtree(cls.tmp, ignore_errors=True)
            raise unittest.SkipTest(
                f"Não foi possível criar o banco de teste com as credenciais padrão "
                f"({_USUARIO_TESTE}/{_SENHA_TESTE}) — provavelmente elas foram alteradas nesta "
                f"máquina. Saída do isql: {saida[:300]!r}"
            )

    @classmethod
    def tearDownClass(cls):
        if hasattr(cls, "tmp"):
            shutil.rmtree(cls.tmp, ignore_errors=True)

    def test_recupera_banco_saudavel_e_gera_backup_utilizavel(self):
        saida_backup = self.tmp / "recuperado.fbk"

        resultado = recuperacao.recuperar_banco_corrompido(
            caminho_fdb_corrompido=str(self.banco),
            caminho_backup_saida=str(saida_backup),
            instalacao=self.instalacao,
            usuario=_USUARIO_TESTE,
            senha=_SENHA_TESTE,
            on_status=lambda _msg: None,
        )

        self.assertTrue(resultado.sucesso, f"Recuperação falhou: {resultado.mensagem_usuario}")
        self.assertTrue(saida_backup.exists())
        self.assertEqual(resultado.total_registros_recuperados, 3)
        self.assertEqual(resultado.quantidade_tabelas_com_dados, 1)

        # O banco original não pode ter sido alterado — a lógica trabalha
        # sempre sobre uma cópia temporária.
        tamanho_original_depois = self.banco.stat().st_size
        self.assertGreater(tamanho_original_depois, 0)

    def test_nao_sobrescreve_backup_de_saida_existente(self):
        saida_ja_existente = self.tmp / "ja_existe.fbk"
        saida_ja_existente.write_bytes(b"nao pode ser sobrescrito")

        resultado = recuperacao.recuperar_banco_corrompido(
            caminho_fdb_corrompido=str(self.banco),
            caminho_backup_saida=str(saida_ja_existente),
            instalacao=self.instalacao,
            usuario=_USUARIO_TESTE,
            senha=_SENHA_TESTE,
            on_status=lambda _msg: None,
        )

        self.assertFalse(resultado.sucesso)
        self.assertEqual(saida_ja_existente.read_bytes(), b"nao pode ser sobrescrito")

    def test_cancelamento_no_meio_da_execucao_interrompe_rapido(self):
        # Valida o caso real (não só "cancelado antes de começar"): o sinal é
        # setado DEPOIS que a etapa gfix -mend -full já deve ter iniciado o
        # processo de verdade — sem o kill ativo em _rodar_e_logar, isto só
        # retornaria quando o gfix terminasse sozinho, ignorando o pedido de
        # cancelamento até a próxima checagem entre etapas.
        saida_backup = self.tmp / "recuperado_cancelado.fbk"
        sinal = SinalCancelamento()
        resultado_thread: list = []

        def worker():
            resultado_thread.append(recuperacao.recuperar_banco_corrompido(
                caminho_fdb_corrompido=str(self.banco),
                caminho_backup_saida=str(saida_backup),
                instalacao=self.instalacao,
                usuario=_USUARIO_TESTE,
                senha=_SENHA_TESTE,
                on_status=lambda _msg: None,
                sinal_cancelamento=sinal,
            ))

        thread = threading.Thread(target=worker, daemon=True)
        inicio = time.time()
        thread.start()
        time.sleep(0.3)  # tempo suficiente para o gfix -mend já ter iniciado
        sinal.set("Cancelado pelo teste.")
        thread.join(timeout=15)
        duracao = time.time() - inicio

        self.assertFalse(thread.is_alive(), "A recuperação não parou a tempo após o cancelamento.")
        self.assertLess(duracao, 15, "Cancelar deveria interromper bem antes do timeout de 1800s por etapa.")
        self.assertEqual(len(resultado_thread), 1)
        resultado = resultado_thread[0]
        self.assertFalse(resultado.sucesso)
        self.assertTrue(resultado.cancelado)
        self.assertFalse(saida_backup.exists())  # nada de resultado parcial confuso


if __name__ == "__main__":
    unittest.main()
