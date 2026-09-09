"""
core/compatibilidade.py
-----------------------
Regras puras de compatibilidade para a direção 2.5 -> 5.0.

Nessa direção o caminho backup/restore é o oficialmente suportado, então quase
tudo aqui é "atenção" (algo a ajustar na configuração do Firebird 5.0 ou na
aplicação depois), não "bloqueio". Um "bloqueio" só é emitido diante de risco
real de perda de dados.

A verificação definitiva continua sendo a própria tentativa do gbak — esta
análise é best-effort e serve para avisar ANTES.
"""
from __future__ import annotations

from collections.abc import Iterable

from core.modelos import Aviso, ResultadoAnalise

# Palavras que passaram a ser RESERVADAS depois do Firebird 2.5 (valor = versão
# em que a reserva entrou). Usadas como nome de tabela/coluna/procedure/
# parâmetro sem aspas, quebram o restore dos metadados no destino.
PALAVRAS_RESERVADAS_NOVAS: dict[str, str] = {
    # Firebird 3.0
    "BOOLEAN": "3.0",
    "CORR": "3.0",
    "COVAR_POP": "3.0",
    "COVAR_SAMP": "3.0",
    "DETERMINISTIC": "3.0",
    "LOCALTIME": "3.0",
    "LOCALTIMESTAMP": "3.0",
    "OVER": "3.0",
    "RDB$RECORD_VERSION": "3.0",
    "REGR_AVGX": "3.0",
    "REGR_AVGY": "3.0",
    "REGR_COUNT": "3.0",
    "REGR_INTERCEPT": "3.0",
    "REGR_R2": "3.0",
    "REGR_SLOPE": "3.0",
    "REGR_SXX": "3.0",
    "REGR_SXY": "3.0",
    "REGR_SYY": "3.0",
    "RETURN": "3.0",
    "SCROLL": "3.0",
    "SQLSTATE": "3.0",
    "STDDEV_POP": "3.0",
    "STDDEV_SAMP": "3.0",
    "VAR_POP": "3.0",
    "VAR_SAMP": "3.0",
    "WINDOW": "3.0",
    # Firebird 4.0
    "BINARY": "4.0",
    "DECFLOAT": "4.0",
    "INT128": "4.0",
    "LATERAL": "4.0",
    "LOCAL": "4.0",
    "PUBLICATION": "4.0",
    "RESETTING": "4.0",
    "TIMEZONE_HOUR": "4.0",
    "TIMEZONE_MINUTE": "4.0",
    "UNBOUNDED": "4.0",
    "VARBINARY": "4.0",
    "WITHOUT": "4.0",
}

_CHARSETS_ARRISCADOS = {"NONE", "ASCII"}
_PAGE_SIZE_MINIMO_FB5 = 4096


def avaliar_udfs(udfs: Iterable[str]) -> Aviso | None:
    nomes = sorted({u.strip() for u in udfs if u and u.strip()})
    if not nomes:
        return None
    return Aviso(
        nivel="atencao",
        categoria="UDF",
        titulo=f"{len(nomes)} função(ões) externa(s) (UDF) no banco de origem",
        detalhe=(
            "O Firebird 5.0 vem com UdfAccess = None no firebird.conf: UDFs "
            "legadas (fbudf, ib_udf) ficam desativadas por padrão. Depois de "
            "migrar, ou libere o acesso a UDF no firebird.conf e copie a "
            "biblioteca para a pasta UDF, ou migre essas rotinas para UDR. "
            "Enquanto isso, views/procedures/triggers que chamam essas funções "
            "vão falhar ao serem usadas."
        ),
        itens=nomes,
    )


def avaliar_charset(charset: str) -> Aviso | None:
    cs = (charset or "").strip().upper()
    if cs not in _CHARSETS_ARRISCADOS:
        return None
    return Aviso(
        nivel="atencao",
        categoria="Charset",
        titulo=f"Charset padrão do banco é {cs}",
        detalhe=(
            "Bases com charset NONE/ASCII frequentemente guardam texto acentuado "
            "gravado em uma página de código do Windows sem declarar. No restore "
            "com o Firebird 5.0, use as opções avançadas -fix_fss_data e "
            "-fix_fss_metadata (informando o charset real, ex.: WIN1252) para "
            "evitar erros de 'cannot transliterate' e caracteres corrompidos."
        ),
        itens=[cs],
    )


def avaliar_palavras_reservadas(identificadores: Iterable[str]) -> Aviso | None:
    achados: list[str] = []
    for ident in identificadores:
        nome = (ident or "").strip().upper()
        versao = PALAVRAS_RESERVADAS_NOVAS.get(nome)
        if versao:
            achados.append(f"{ident.strip()}  (reservada a partir do Firebird {versao})")
    achados = sorted(set(achados))
    if not achados:
        return None
    return Aviso(
        nivel="atencao",
        categoria="Palavras reservadas",
        titulo=f"{len(achados)} objeto(s) com nome que virou palavra reservada",
        detalhe=(
            "Esses nomes eram válidos no Firebird 2.5, mas passaram a ser "
            "reservados em versões mais novas. Se o backup guardar o DDL sem "
            "aspas, o restore dos metadados pode falhar. Solução: renomear o "
            "objeto na origem antes de migrar, ou passar a referenciá-lo sempre "
            'entre aspas duplas ("NOME"). A confirmação real vem do próprio gbak '
            "durante o restore."
        ),
        itens=achados,
    )


def avaliar_tabelas_externas(nomes: Iterable[str]) -> Aviso | None:
    lista = sorted({n.strip() for n in nomes if n and n.strip()})
    if not lista:
        return None
    return Aviso(
        nivel="atencao",
        categoria="Tabelas externas",
        titulo=f"{len(lista)} tabela(s) externa(s) (EXTERNAL FILE)",
        detalhe=(
            "Tabelas ligadas a arquivo externo só funcionam no Firebird 5.0 se "
            "ExternalFileAccess no firebird.conf permitir o diretório onde os "
            "arquivos ficam. Ajuste essa configuração no destino e confira se os "
            "arquivos externos foram copiados junto."
        ),
        itens=lista,
    )


def avaliar_dialeto(sql_dialect: int) -> Aviso | None:
    if sql_dialect == 1:
        return Aviso(
            nivel="info",
            categoria="SQL Dialect",
            titulo="Banco em SQL Dialect 1",
            detalhe=(
                "O restore preserva o Dialect 1 — o banco migrado continua em "
                "Dialect 1 e a aplicação segue funcionando. Migrar para Dialect 3 "
                "é um projeto à parte (muda DATE/TIME, aspas, precisão numérica) "
                "e não é feito automaticamente por esta ferramenta."
            ),
            itens=[],
        )
    return None


def avaliar_page_size(page_size: int) -> Aviso | None:
    if 0 < page_size < _PAGE_SIZE_MINIMO_FB5:
        return Aviso(
            nivel="info",
            categoria="Page size",
            titulo=f"Page size de origem é {page_size} bytes",
            detalhe=(
                f"O Firebird 5.0 usa no mínimo {_PAGE_SIZE_MINIMO_FB5} bytes por "
                "página. O restore vai aumentar o page size automaticamente; o "
                "banco migrado fica um pouco maior, sem perda de dados."
            ),
            itens=[],
        )
    return None


def avaliar_ods(analise: ResultadoAnalise) -> Aviso | None:
    """A origem precisa mesmo ser Firebird 2.5 (ODS 11.x) para este MVP."""
    if analise.ods == "?" or analise.ods.startswith("11"):
        return None
    return Aviso(
        nivel="bloqueio",
        categoria="Versão de origem",
        titulo=f"ODS {analise.ods} não é Firebird 2.5",
        detalhe=(
            "Esta versão do Migrador só executa o caminho Firebird 2.5 -> 5.0 "
            f"(ODS 11.x na origem). O banco selecionado está em ODS {analise.ods} "
            f"(família provável {analise.familia_provavel}). Use a ferramenta "
            "apropriada para a versão de origem correta."
        ),
        itens=[],
    )


def avaliar(analise: ResultadoAnalise) -> list[Aviso]:
    """Executa todas as regras e devolve a lista de avisos (na ordem: bloqueios,
    depois atenções, depois informativos)."""
    candidatos = [
        avaliar_ods(analise),
        avaliar_udfs(analise.udfs),
        avaliar_charset(analise.charset_padrao),
        avaliar_palavras_reservadas(analise.identificadores),
        avaliar_tabelas_externas(analise.tabelas_externas),
        avaliar_page_size(analise.page_size),
        avaliar_dialeto(analise.sql_dialect),
    ]
    avisos = [a for a in candidatos if a is not None]
    ordem = {"bloqueio": 0, "atencao": 1, "info": 2}
    avisos.sort(key=lambda a: ordem.get(a.nivel, 3))
    return avisos


def existe_bloqueio(avisos: Iterable[Aviso]) -> bool:
    return any(a.nivel == "bloqueio" for a in avisos)
