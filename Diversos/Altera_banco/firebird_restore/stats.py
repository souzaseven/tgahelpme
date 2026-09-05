"""
stats.py
--------
Coleta de informações sobre o banco restaurado, usadas no RESUMO exibido ao
final da operação: Page Size, versão do ODS (estrutura em disco), versão do
sistema TGA (quando aplicável) e uma validação de integridade opcional
(contagem de erros/avisos via gfix).

Usa os utilitários oficiais do Firebird (gstat, gfix, isql) — nunca lê o
arquivo .fdb diretamente.
"""
from __future__ import annotations

import re
import subprocess
from dataclasses import dataclass, field
from pathlib import Path
from typing import Optional

_CREATION_FLAGS = subprocess.CREATE_NO_WINDOW if hasattr(subprocess, "CREATE_NO_WINDOW") else 0


@dataclass
class InfoHeaderBanco:
    ok: bool
    page_size: Optional[int] = None
    ods_version: Optional[str] = None
    generation: Optional[int] = None
    dialeto: Optional[int] = None
    data_criacao: Optional[str] = None
    texto_bruto: str = ""
    erro: str = ""


_PADROES_HEADER = {
    "page_size": re.compile(r"Page size\s+(\d+)", re.IGNORECASE),
    "ods_version": re.compile(r"ODS version\s+([\d.]+)", re.IGNORECASE),
    "generation": re.compile(r"Generation\s+(\d+)", re.IGNORECASE),
    "dialeto": re.compile(r"Database dialect\s+(\d+)", re.IGNORECASE),
    "data_criacao": re.compile(r"Creation date\s+(.+)", re.IGNORECASE),
}


def obter_info_header(
    gstat_path: Optional[Path], caminho_fdb: str, usuario: str, senha: str, timeout: int = 30
) -> InfoHeaderBanco:
    if gstat_path is None:
        return InfoHeaderBanco(False, erro="gstat.exe não encontrado nesta instalação do Firebird.")

    comando = [str(gstat_path), "-h"]
    if usuario:
        comando += ["-user", usuario]
    if senha:
        comando += ["-password", senha]
    comando += [str(caminho_fdb)]

    try:
        resultado = subprocess.run(
            comando, capture_output=True, text=True, timeout=timeout, creationflags=_CREATION_FLAGS
        )
    except (OSError, subprocess.TimeoutExpired) as exc:
        return InfoHeaderBanco(False, erro=str(exc))

    saida = (resultado.stdout or "") + (resultado.stderr or "")
    if resultado.returncode != 0:
        return InfoHeaderBanco(False, texto_bruto=saida, erro="gstat retornou erro ao ler o cabeçalho do banco.")

    valores: dict[str, str] = {}
    for chave, padrao in _PADROES_HEADER.items():
        m = padrao.search(saida)
        if m:
            valores[chave] = m.group(1).strip()

    return InfoHeaderBanco(
        True,
        page_size=int(valores["page_size"]) if "page_size" in valores else None,
        ods_version=valores.get("ods_version"),
        generation=int(valores["generation"]) if "generation" in valores else None,
        dialeto=int(valores["dialeto"]) if "dialeto" in valores else None,
        data_criacao=valores.get("data_criacao"),
        texto_bruto=saida,
    )


@dataclass
class ResultadoValidacaoIntegridade:
    ok: bool
    erros: int = 0
    avisos: int = 0
    corrigidos: int = 0
    texto_bruto: str = ""
    mensagem: str = ""
    tabelas_com_problema: list = field(default_factory=list)


_PADRAO_RESUMO_VALIDACAO = re.compile(
    r"Validation finished:\s*(\d+)\s*errors?,\s*(\d+)\s*warnings?,\s*(\d+)\s*fixed",
    re.IGNORECASE,
)

# Formato estável do gfix -v -full: cada tabela ("relation") verificada
# aparece como um bloco "Relation N (NOME)" seguido das linhas de detalhe
# daquela tabela, até a próxima ocorrência de "Relation". Problemas reais
# (registro órfão, checksum de página, comprimento inválido, etc.) aparecem
# como linhas de texto livre dentro desse bloco.
_PADRAO_RELATION = re.compile(r"^\s*Relation\s+\d+\s+\(([^)]+)\)", re.IGNORECASE)
_PADRAO_PALAVRAS_PROBLEMA = re.compile(
    r"orphan|corrupt|checksum|wrong \w+ length|damaged|missing|lost|bad \w+|inconsistent|garbage",
    re.IGNORECASE,
)


def extrair_tabelas_com_problema(saida: str) -> list:
    """Varre a saída do gfix -v -full associando cada linha de problema à
    tabela ("Relation") que estava sendo verificada naquele ponto."""
    tabela_atual = None
    tabelas: list = []
    for linha in saida.splitlines():
        m = _PADRAO_RELATION.match(linha)
        if m:
            tabela_atual = m.group(1).strip()
            continue
        if tabela_atual and _PADRAO_PALAVRAS_PROBLEMA.search(linha) and tabela_atual not in tabelas:
            tabelas.append(tabela_atual)
    return tabelas


def rodar_validacao_completa(
    gfix_path: Optional[Path],
    caminho_fdb: str,
    usuario: str,
    senha: str,
    timeout: int = 1800,
) -> ResultadoValidacaoIntegridade:
    """Roda `gfix -v -full`, uma varredura completa do banco. Pode demorar
    bastante em bancos grandes — por isso é sempre opcional na interface,
    nunca disparada automaticamente sem o usuário pedir."""
    if gfix_path is None:
        return ResultadoValidacaoIntegridade(False, mensagem="gfix.exe não encontrado nesta instalação do Firebird.")

    comando = [str(gfix_path), "-v", "-full"]
    if usuario:
        comando += ["-user", usuario]
    if senha:
        comando += ["-password", senha]
    comando += [str(caminho_fdb)]

    try:
        resultado = subprocess.run(
            comando, capture_output=True, text=True, timeout=timeout, creationflags=_CREATION_FLAGS
        )
    except subprocess.TimeoutExpired:
        return ResultadoValidacaoIntegridade(False, mensagem="A validação completa excedeu o tempo limite.")
    except OSError as exc:
        return ResultadoValidacaoIntegridade(False, mensagem=str(exc))

    saida = (resultado.stdout or "") + (resultado.stderr or "")

    # O gfix não imprime nada quando o banco está íntegro e não há avisos.
    if not saida.strip() and resultado.returncode == 0:
        return ResultadoValidacaoIntegridade(True, erros=0, avisos=0, corrigidos=0, texto_bruto=saida,
                                              mensagem="Nenhum erro encontrado.")

    tabelas_com_problema = extrair_tabelas_com_problema(saida)

    m = _PADRAO_RESUMO_VALIDACAO.search(saida)
    if m:
        erros, avisos, corrigidos = (int(m.group(i)) for i in (1, 2, 3))
        mensagem = f"{erros} erro(s), {avisos} aviso(s), {corrigidos} corrigido(s)."
        if tabelas_com_problema:
            mensagem += f" Tabelas afetadas: {', '.join(tabelas_com_problema)}."
        return ResultadoValidacaoIntegridade(
            resultado.returncode == 0, erros=erros, avisos=avisos, corrigidos=corrigidos, texto_bruto=saida,
            mensagem=mensagem, tabelas_com_problema=tabelas_com_problema,
        )

    # Sem o resumo padrão, mas com saída: contamos ocorrências como estimativa.
    erros_estimados = len(re.findall(r"\berror\b", saida, re.IGNORECASE))
    avisos_estimados = len(re.findall(r"\bwarning\b", saida, re.IGNORECASE))
    return ResultadoValidacaoIntegridade(
        resultado.returncode == 0,
        erros=erros_estimados,
        avisos=avisos_estimados,
        texto_bruto=saida,
        tabelas_com_problema=tabelas_com_problema,
        mensagem=f"{erros_estimados} erro(s) e {avisos_estimados} aviso(s) identificados (estimativa)."
        if (erros_estimados or avisos_estimados) else "Nenhum erro encontrado.",
    )


@dataclass
class InfoSistemaTGA:
    """Versão do sistema TGA gravada no próprio banco (tabela GDIVERSOS) —
    diferente da ODS version do Firebird: aqui é a versão do aplicativo/base
    de dados do ERP, não da estrutura em disco. Só existe em bancos do TGA;
    em qualquer outro banco restaurado, `encontrado` vem False silenciosamente
    (não é tratado como erro)."""
    encontrado: bool
    versao_base: Optional[str] = None
    data_atualizacao: Optional[str] = None
    versao_mobile: Optional[str] = None
    tga_start: Optional[str] = None
    erro: str = ""


_CAMPOS_GDIVERSOS = ("VERSAO_BASE", "DATA_ATUALIZACAO", "VERSAO_MOBILE", "TGA_START")


def obter_versao_sistema_tga(
    isql_path: Optional[Path], caminho_fdb: str, usuario: str, senha: str, timeout: int = 15
) -> InfoSistemaTGA:
    if isql_path is None:
        return InfoSistemaTGA(False, erro="isql.exe não encontrado nesta instalação do Firebird.")

    comando = [str(isql_path)]
    if usuario:
        comando += ["-user", usuario]
    if senha:
        comando += ["-password", senha]
    comando += [str(caminho_fdb)]

    # GDIVERSOS pode acumular mais de um registro (histórico de migrações de
    # versão); TGA_START IS NULL identifica o registro vigente (não é um
    # registro "em migração"/histórico). Se nenhuma linha atender a esse
    # filtro — por exemplo em bancos mais antigos, sem essa coluna preenchida
    # — cai para o registro mais simples (FIRST 1, sem filtro) como fallback.
    script = (
        "SET LIST ON;\n"
        f"SELECT FIRST 1 {', '.join(_CAMPOS_GDIVERSOS)} FROM GDIVERSOS WHERE TGA_START IS NULL;\n"
    )

    try:
        resultado = subprocess.run(
            comando, input=script, capture_output=True, text=True, timeout=timeout, creationflags=_CREATION_FLAGS
        )
    except (OSError, subprocess.TimeoutExpired) as exc:
        return InfoSistemaTGA(False, erro=str(exc))

    saida = (resultado.stdout or "") + (resultado.stderr or "")

    if "Table unknown" in saida or "GDIVERSOS" in saida and "not found" in saida.lower():
        # Não é um banco do sistema TGA — não é um erro, apenas não aplicável.
        return InfoSistemaTGA(False, erro="Este banco não possui a tabela GDIVERSOS (não é um banco TGA).")

    valores = _extrair_campos_gdiversos(saida)

    # Coluna TGA_START pode não existir em bancos de versões mais antigas do
    # TGA — nesse caso o isql acusa erro na própria coluna do WHERE/SELECT;
    # refaz a consulta sem filtrar por ela, e sem pedi-la no SELECT.
    if not valores and ("Column unknown" in saida or "TGA_START" in saida.upper()):
        campos_sem_tga_start = tuple(c for c in _CAMPOS_GDIVERSOS if c != "TGA_START")
        script_fallback = (
            "SET LIST ON;\n"
            f"SELECT FIRST 1 {', '.join(campos_sem_tga_start)} FROM GDIVERSOS;\n"
        )
        try:
            resultado = subprocess.run(
                comando, input=script_fallback, capture_output=True, text=True,
                timeout=timeout, creationflags=_CREATION_FLAGS,
            )
        except (OSError, subprocess.TimeoutExpired) as exc:
            return InfoSistemaTGA(False, erro=str(exc))
        saida = (resultado.stdout or "") + (resultado.stderr or "")
        valores = _extrair_campos_gdiversos(saida)

    if resultado.returncode != 0 and not valores:
        return InfoSistemaTGA(False, erro="Não foi possível consultar a versão do sistema TGA.")

    if not valores:
        return InfoSistemaTGA(False, erro="Não foi possível ler os dados da tabela GDIVERSOS.")

    def normalizar(v: Optional[str]) -> Optional[str]:
        return None if v in (None, "<null>") else v

    return InfoSistemaTGA(
        True,
        versao_base=normalizar(valores.get("VERSAO_BASE")),
        data_atualizacao=normalizar(valores.get("DATA_ATUALIZACAO")),
        versao_mobile=normalizar(valores.get("VERSAO_MOBILE")),
        tga_start=normalizar(valores.get("TGA_START")),
    )


def _extrair_campos_gdiversos(saida: str) -> dict:
    valores: dict = {}
    for linha in saida.splitlines():
        m = re.match(r"^(" + "|".join(_CAMPOS_GDIVERSOS) + r")\s+(.*)$", linha.strip())
        if m:
            valores[m.group(1)] = m.group(2).strip()
    return valores
