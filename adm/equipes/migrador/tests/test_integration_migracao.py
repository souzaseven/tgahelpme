"""
Teste de integração ponta-a-ponta: cria um banco Firebird 2.5 real, migra para
o Firebird 5.0 com core.migracao.executar_migracao() e confere as contagens.

É PULADO automaticamente (não falha) se a máquina não tiver ao mesmo tempo uma
instalação Firebird 2.5 e uma 5.0, ou se as credenciais padrão não funcionarem.
"""
import subprocess
import tempfile
import unittest
from pathlib import Path

from core import comparacao, validacao
from core.analise import analisar
from core.firebird import escolher_por_familia, localizar_instalacoes
from core.migracao import OpcoesMigracao, executar_migracao

USUARIO = "SYSDBA"
SENHA = "masterkey"

_INSTALACOES = localizar_instalacoes()
_FB25 = escolher_por_familia(_INSTALACOES, "2.5")
_FB50 = escolher_por_familia(_INSTALACOES, "5.0")


@unittest.skipUnless(
    _FB25 and _FB50 and _FB25.isql_path and _FB50.isql_path,
    "Requer Firebird 2.5 e 5.0 instalados (com isql).",
)
class TestMigracaoReal(unittest.TestCase):
    def setUp(self):
        self.tmp = Path(tempfile.mkdtemp(prefix="mig_it_"))
        self.origem = self.tmp / "ORIGEM25.FDB"
        self.fbk = self.tmp / "ORIGEM25.fbk"
        self.destino = self.tmp / "DESTINO50.FDB"
        self._criar_banco_25()

    def tearDown(self):
        for p in self.tmp.glob("*"):
            try:
                p.unlink()
            except OSError:
                pass
        try:
            self.tmp.rmdir()
        except OSError:
            pass

    def _isql25(self, script: str, banco: str = ""):
        sql = self.tmp / "s.sql"
        sql.write_text(script, encoding="utf-8")
        cmd = [str(_FB25.isql_path), "-q", "-user", USUARIO, "-password", SENHA, "-i", str(sql)]
        if banco:
            cmd.append(banco)
        r = subprocess.run(cmd, capture_output=True, text=True, timeout=120)
        self.assertEqual(r.returncode, 0, r.stdout + r.stderr)
        return r

    def _criar_banco_25(self):
        self._isql25(
            f"CREATE DATABASE '{self.origem}' USER '{USUARIO}' PASSWORD '{SENHA}' "
            f"PAGE_SIZE 8192 DEFAULT CHARACTER SET WIN1252;"
        )
        self._isql25(
            "CREATE TABLE CLIENTES (ID INTEGER NOT NULL PRIMARY KEY, NOME VARCHAR(60));\n"
            "CREATE GENERATOR GEN_CLIENTES;\n"
            "SET GENERATOR GEN_CLIENTES TO 10582;\n"
            "COMMIT;\n"
            "INSERT INTO CLIENTES VALUES (1, 'Fulano');\n"
            "INSERT INTO CLIENTES VALUES (2, 'Ciclano');\n"
            "INSERT INTO CLIENTES VALUES (3, 'Beltrano');\n"
            "COMMIT;\n",
            banco=str(self.origem),
        )

    def test_migracao_completa(self):
        analise = analisar(_FB25, str(self.origem), USUARIO, SENHA)
        self.assertTrue(analise.ok, analise.erros)
        self.assertTrue(analise.ods.startswith("11"))
        self.assertIn("CLIENTES", analise.tabelas)

        res = executar_migracao(
            _FB25, _FB50,
            str(self.origem), str(self.destino), str(self.fbk),
            USUARIO, SENHA,
            OpcoesMigracao(),
        )
        self.assertEqual(res.erro_fatal, "", res.erro_fatal)
        self.assertTrue(res.sucesso)
        self.assertTrue(self.destino.is_file())

        val = validacao.validar(_FB50, str(self.destino), USUARIO, SENHA, completa=False)
        self.assertTrue(val.conecta_ok)
        self.assertGreaterEqual(val.qtd_tabelas, 1)

        cmp = comparacao.comparar(
            _FB25, str(self.origem), _FB50, str(self.destino),
            analise.tabelas, dialeto=analise.sql_dialect or 3,
            usuario=USUARIO, senha=SENHA,
        )
        self.assertTrue(cmp.tudo_confere, [t.__dict__ for t in cmp.tabelas])


if __name__ == "__main__":
    unittest.main()
