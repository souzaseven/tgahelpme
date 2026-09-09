"""
core/comparacao.py
------------------
Compara os dados entre o banco de ORIGEM e o migrado, tabela por tabela
(`SELECT COUNT(*)`), como manda o master prompt: "REGISTROS ORIGEM /
REGISTROS DESTINO / STATUS". Não depende só de COUNT — também confere que
todos os generators existem nos dois lados.

Uma tabela cujo COUNT falhou de um dos lados fica como INDETERMINADO (não
DIVERGENTE): a ferramenta não afirma que houve perda sem ter os dois números.
"""
from __future__ import annotations

import subprocess
import tempfile
from pathlib import Path

import logger
from core.firebird import InstalacaoFirebird
from core.modelos import ResultadoComparacao, TabelaComparada

_TIMEOUT = 3 * 3600
_P = "CNT"


def _quote(nome: str, dialeto: int) -> str:
    """Dialeto 3 aceita (e às vezes exige) identificador entre aspas duplas;
    no dialeto 1 aspas duplas viram string literal, então usa o nome cru."""
    nome = nome.strip()
    if dialeto == 1:
        return nome
    return '"' + nome.replace('"', '""') + '"'


def montar_script_contagem(tabelas: list[str], dialeto: int) -> str:
    linhas = ["SET HEADING OFF;", "SET LIST OFF;", "SET WIDTH 0;"]
    for t in tabelas:
        alvo = _quote(t, dialeto)
        # aspas simples escapadas para o literal com o nome da tabela
        rotulo = t.strip().replace("'", "''")
        linhas.append(
            f"SELECT '{_P};{rotulo};' || COUNT(*) FROM {alvo};"
        )
    return "\n".join(linhas) + "\n"


def parse_contagem(texto: str) -> dict[str, int]:
    out: dict[str, int] = {}
    for linha in texto.splitlines():
        s = linha.strip()
        if not s.startswith(_P + ";"):
            continue
        # CNT;<NOME_TABELA>;<n>  — o nome pode conter ';'? nomes de tabela não.
        resto = s[len(_P) + 1:]
        pos = resto.rfind(";")
        if pos <= 0:
            continue
        nome, valor = resto[:pos], resto[pos + 1:]
        try:
            out[nome.strip()] = int(valor.strip())
        except ValueError:
            continue
    return out


def _contar(
    inst: InstalacaoFirebird,
    fdb: str,
    tabelas: list[str],
    dialeto: int,
    usuario: str,
    senha: str,
) -> dict[str, int]:
    if not inst.isql_path or not tabelas:
        return {}
    tmp = Path(tempfile.mkstemp(prefix="mig_cnt_", suffix=".sql")[1])
    tmp.write_text(montar_script_contagem(tabelas, dialeto), encoding="utf-8")
    try:
        cmd = [str(inst.isql_path), "-q", "-ch", "NONE", "-i", str(tmp)]
        if usuario:
            cmd += ["-user", usuario]
        if senha:
            cmd += ["-password", senha]
        cmd.append(str(Path(fdb)))
        logger.info("Comparação: " + logger.mascarar_segredos(" ".join(cmd)))
        try:
            r = subprocess.run(cmd, capture_output=True, text=True, timeout=_TIMEOUT)
        except (OSError, subprocess.TimeoutExpired) as e:
            logger.aviso(f"Contagem falhou em {fdb}: {e}")
            return {}
        return parse_contagem(r.stdout or "")
    finally:
        try:
            tmp.unlink()
        except OSError:
            pass


def comparar(
    inst_origem: InstalacaoFirebird,
    origem_fdb: str,
    inst_destino: InstalacaoFirebird,
    destino_fdb: str,
    tabelas: list[str],
    *,
    dialeto: int,
    usuario: str,
    senha: str,
    generators_origem: int = 0,
    generators_destino: int = 0,
) -> ResultadoComparacao:
    res = ResultadoComparacao(
        generators_origem=generators_origem, generators_destino=generators_destino
    )
    if not tabelas:
        res.observacoes.append("Nenhuma tabela de usuário para comparar.")
        return res

    cont_origem = _contar(inst_origem, origem_fdb, tabelas, dialeto, usuario, senha)
    cont_destino = _contar(inst_destino, destino_fdb, tabelas, dialeto, usuario, senha)

    for t in tabelas:
        res.tabelas.append(
            TabelaComparada(
                nome=t,
                registros_origem=cont_origem.get(t),
                registros_destino=cont_destino.get(t),
            )
        )

    if res.indeterminadas:
        res.observacoes.append(
            f"{len(res.indeterminadas)} tabela(s) não puderam ser contadas em "
            "um dos lados (nome com caractere especial, permissão, ou tabela "
            "ausente). Verifique manualmente."
        )
    if generators_origem != generators_destino:
        res.observacoes.append(
            f"Quantidade de generators diferente: origem {generators_origem} x "
            f"destino {generators_destino}."
        )
    return res
