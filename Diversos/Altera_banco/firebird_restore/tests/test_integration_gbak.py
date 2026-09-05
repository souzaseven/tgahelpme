"""
Teste de integração de ponta a ponta: cria um banco Firebird de verdade,
faz backup dele com gbak de verdade, e restaura esse backup usando
restore.executar_restauracao() — a mesma função que a interface chama.

Os demais testes (test_restore.py, test_validator.py, test_stats.py,
test_firebird.py) usam saídas sintéticas de gbak/gfix/isql e não pegariam um
problema real de integração (ex.: um argumento de linha de comando que o
gbak da máquina não aceita). Este teste pega isso, ao custo de precisar de
uma instalação real do Firebird — por isso é pulado automaticamente (não
falha) quando:
    - nenhuma instalação do Firebird é encontrada na máquina; ou
    - as credenciais padrão (SYSDBA / masterkey) não funcionam nela.
Em ambos os casos o teste não pode concluir nada, então SkipTest é o
resultado correto — um FAIL aqui não distinguiria "o código quebrou" de
"esta máquina não está configurada para este teste".
"""
import shutil
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import firebird
import restore

_USUARIO_TESTE = "SYSDBA"
_SENHA_TESTE = "masterkey"
_CREATION_FLAGS = subprocess.CREATE_NO_WINDOW if hasattr(subprocess, "CREATE_NO_WINDOW") else 0


def _rodar_isql(isql_path: Path, script: str, timeout: int = 20) -> subprocess.CompletedProcess:
    return subprocess.run(
        [str(isql_path), "-user", _USUARIO_TESTE, "-password", _SENHA_TESTE],
        input=script, capture_output=True, text=True, timeout=timeout, creationflags=_CREATION_FLAGS,
    )


class TestIntegracaoRestauracaoReal(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        instalacoes = firebird.localizar_instalacoes()
        if not instalacoes:
            raise unittest.SkipTest("Nenhuma instalação do Firebird encontrada nesta máquina.")
        cls.instalacao = instalacoes[0]
        if cls.instalacao.isql_path is None:
            raise unittest.SkipTest("isql.exe não encontrado na instalação detectada.")

        cls.tmp = Path(tempfile.mkdtemp(prefix="fbtest_integracao_"))
        cls.banco_original = cls.tmp / "ORIGEM_TESTE.FDB"
        cls.backup = cls.tmp / "origem_teste.fbk"
        cls.destino_restaurado = cls.tmp / "RESTAURADO_TESTE.FDB"

        script_criacao = (
            f"CREATE DATABASE '{cls.banco_original}' USER '{_USUARIO_TESTE}' PASSWORD '{_SENHA_TESTE}';\n"
            "CREATE TABLE TESTE_INTEGRACAO (ID INTEGER, NOME VARCHAR(50));\n"
            "COMMIT;\n"
            "INSERT INTO TESTE_INTEGRACAO VALUES (1, 'primeiro registro');\n"
            "INSERT INTO TESTE_INTEGRACAO VALUES (2, 'segundo registro');\n"
            "COMMIT;\n"
        )
        try:
            resultado = _rodar_isql(cls.instalacao.isql_path, script_criacao)
        except (OSError, subprocess.TimeoutExpired) as exc:
            shutil.rmtree(cls.tmp, ignore_errors=True)
            raise unittest.SkipTest(f"Não foi possível executar isql.exe: {exc}")

        saida = (resultado.stdout or "") + (resultado.stderr or "")
        if resultado.returncode != 0 or not cls.banco_original.exists():
            shutil.rmtree(cls.tmp, ignore_errors=True)
            raise unittest.SkipTest(
                f"Não foi possível criar o banco de teste com as credenciais padrão "
                f"({_USUARIO_TESTE}/{_SENHA_TESTE}) — provavelmente elas foram alteradas nesta "
                f"máquina. Saída do isql: {saida[:300]!r}"
            )

        comando_backup = [str(cls.instalacao.gbak_path), "-b", "-user", _USUARIO_TESTE,
                           "-password", _SENHA_TESTE, str(cls.banco_original), str(cls.backup)]
        try:
            resultado_backup = subprocess.run(
                comando_backup, capture_output=True, text=True, timeout=30, creationflags=_CREATION_FLAGS
            )
        except (OSError, subprocess.TimeoutExpired) as exc:
            shutil.rmtree(cls.tmp, ignore_errors=True)
            raise unittest.SkipTest(f"Não foi possível rodar gbak -b: {exc}")

        if resultado_backup.returncode != 0 or not cls.backup.exists():
            shutil.rmtree(cls.tmp, ignore_errors=True)
            raise unittest.SkipTest(
                f"gbak -b falhou ao gerar o backup de teste: "
                f"{((resultado_backup.stdout or '') + (resultado_backup.stderr or ''))[:300]!r}"
            )

    @classmethod
    def tearDownClass(cls):
        if hasattr(cls, "tmp"):
            shutil.rmtree(cls.tmp, ignore_errors=True)

    def test_restaura_backup_real_com_sucesso(self):
        resultado = restore.executar_restauracao(
            caminho_backup=str(self.backup),
            caminho_destino=str(self.destino_restaurado),
            instalacao=self.instalacao,
            usuario=_USUARIO_TESTE,
            senha=_SENHA_TESTE,
            fator_estimativa=4.0,
            margem_seguranca=1.3,
            on_status=lambda _msg: None,
        )

        self.assertTrue(resultado.sucesso, f"Restauração falhou: {resultado.mensagem_usuario}")
        self.assertTrue(self.destino_restaurado.exists())
        self.assertIsNotNone(resultado.resumo)
        self.assertEqual(resultado.resumo.gbak_erros_count, 0)
        # As duas linhas inseridas na criação devem ter sido restauradas.
        self.assertEqual(resultado.resumo.total_registros_restaurados, 2)

    def test_recusa_sobrescrever_destino_ja_existente(self):
        # Reafirma a regra crítica do projeto também no caminho real (não só
        # com mensagens sintéticas, como em test_restore.py).
        destino_ja_existente = self.tmp / "JA_EXISTE_INTEGRACAO.FDB"
        destino_ja_existente.write_bytes(b"nao pode ser sobrescrito")

        resultado = restore.executar_restauracao(
            caminho_backup=str(self.backup),
            caminho_destino=str(destino_ja_existente),
            instalacao=self.instalacao,
            usuario=_USUARIO_TESTE,
            senha=_SENHA_TESTE,
            fator_estimativa=4.0,
            margem_seguranca=1.3,
            on_status=lambda _msg: None,
        )

        self.assertFalse(resultado.sucesso)
        self.assertEqual(destino_ja_existente.read_bytes(), b"nao pode ser sobrescrito")


if __name__ == "__main__":
    unittest.main()
