"""
core/relatorio.py
-----------------
Monta o relatório final da migração (texto legível + dicionário) e grava um
.txt ao lado do banco migrado. Também classifica o desfecho em
CONCLUÍDA / CONCLUÍDA COM AVISOS / FALHOU.
"""
from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path

from core.espaco import formatar_bytes
from core.modelos import (
    Aviso,
    ResultadoAnalise,
    ResultadoComparacao,
    ResultadoMigracao,
)

CONCLUIDA = "CONCLUÍDA"
CONCLUIDA_COM_AVISOS = "CONCLUÍDA COM AVISOS"
FALHOU = "FALHOU"


@dataclass
class Relatorio:
    resultado: str
    texto: str
    dados: dict = field(default_factory=dict)

    def salvar(self, pasta: str | Path, nome_base: str) -> Path:
        pasta = Path(pasta)
        pasta.mkdir(parents=True, exist_ok=True)
        destino = pasta / f"{nome_base}_relatorio_{datetime.now():%Y%m%d_%H%M%S}.txt"
        destino.write_text(self.texto, encoding="utf-8")
        return destino


def _linha(rotulo: str, valor) -> str:
    return f"  {rotulo:.<32} {valor}"


def _classificar(
    migracao: ResultadoMigracao,
    validacao,
    comparacao: ResultadoComparacao,
    avisos: list[Aviso],
) -> str:
    if not migracao.sucesso:
        return FALHOU
    if validacao is not None and not validacao.conecta_ok:
        return FALHOU
    if validacao is not None and validacao.gfix_erros:
        return CONCLUIDA_COM_AVISOS
    if migracao.erros_indice_nao_fatais:
        return CONCLUIDA_COM_AVISOS
    if comparacao.divergentes or comparacao.indeterminadas:
        return CONCLUIDA_COM_AVISOS
    if any(a.nivel == "atencao" for a in avisos):
        return CONCLUIDA_COM_AVISOS
    return CONCLUIDA


def montar(
    *,
    analise: ResultadoAnalise,
    avisos: list[Aviso],
    migracao: ResultadoMigracao,
    validacao,
    comparacao: ResultadoComparacao,
    origem_build: str,
    destino_build: str,
    inicio: datetime,
    fim: datetime,
) -> Relatorio:
    resultado = _classificar(migracao, validacao, comparacao, avisos)
    dur = (fim - inicio).total_seconds()

    L: list[str] = []
    L.append("=" * 68)
    L.append("  RELATÓRIO DE MIGRAÇÃO FIREBIRD")
    L.append("=" * 68)
    L.append("")
    L.append(_linha("Início", f"{inicio:%d/%m/%Y %H:%M:%S}"))
    L.append(_linha("Fim", f"{fim:%d/%m/%Y %H:%M:%S}"))
    L.append(_linha("Duração total", f"{dur:.0f} s"))
    L.append(_linha("  backup", f"{migracao.duracao_backup_seg:.0f} s"))
    L.append(_linha("  restore", f"{migracao.duracao_restore_seg:.0f} s"))
    L.append("")
    L.append("-" * 68)
    L.append("  ORIGEM  ->  DESTINO")
    L.append("-" * 68)
    L.append(_linha("Banco origem", migracao.caminho_origem))
    L.append(_linha("Tamanho origem", formatar_bytes(analise.tamanho_bytes)))
    L.append(_linha("Backup gerado", migracao.caminho_fbk))
    L.append(_linha("Banco migrado", migracao.caminho_destino))
    L.append("")
    L.append(_linha("Firebird origem", origem_build))
    L.append(_linha("Firebird destino", destino_build))
    ods_destino = getattr(validacao, "ods", "?")
    L.append(_linha("ODS", f"{analise.ods}  ->  {ods_destino}"))
    ps_destino = getattr(validacao, "page_size", 0) or "?"
    L.append(_linha("Page size", f"{analise.page_size}  ->  {ps_destino}"))
    L.append(_linha("SQL Dialect", analise.sql_dialect or "?"))
    L.append(_linha("Charset padrão", analise.charset_padrao))
    L.append("")
    L.append("-" * 68)
    L.append("  OBJETOS (origem / destino)")
    L.append("-" * 68)
    v = validacao
    L.append(_linha("Tabelas", f"{analise.qtd_tabelas} / {getattr(v, 'qtd_tabelas', '?')}"))
    L.append(_linha("Views", f"{analise.qtd_views} / {getattr(v, 'qtd_views', '?')}"))
    L.append(_linha("Procedures", f"{analise.qtd_procedures} / {getattr(v, 'qtd_procedures', '?')}"))
    L.append(_linha("Triggers", f"{analise.qtd_triggers} / {getattr(v, 'qtd_triggers', '?')}"))
    L.append(_linha("Generators", f"{analise.qtd_generators} / {getattr(v, 'qtd_generators', '?')}"))
    L.append(_linha("Domains", analise.qtd_domains))
    L.append(_linha("Índices", analise.qtd_indices))
    L.append(_linha("Foreign keys", analise.qtd_foreign_keys))
    L.append("")
    L.append("-" * 68)
    L.append("  DADOS")
    L.append("-" * 68)
    L.append(_linha("Registros (gbak reportou)", migracao.registros_reportados_gbak))
    L.append(_linha("Soma COUNT(*) origem", comparacao.total_origem))
    L.append(_linha("Soma COUNT(*) destino", comparacao.total_destino))
    L.append(_linha("Tabelas comparadas", len(comparacao.tabelas)))
    L.append(_linha("  conferem", len(comparacao.tabelas) - len(comparacao.divergentes) - len(comparacao.indeterminadas)))
    L.append(_linha("  divergentes", len(comparacao.divergentes)))
    L.append(_linha("  indeterminadas", len(comparacao.indeterminadas)))
    if comparacao.divergentes:
        L.append("")
        L.append("  Tabelas com contagem divergente:")
        for t in comparacao.divergentes:
            L.append(f"    - {t.nome}: origem {t.registros_origem} x destino {t.registros_destino}")
    L.append("")
    L.append("-" * 68)
    L.append("  INTEGRIDADE / ERROS")
    L.append("-" * 68)
    L.append(_linha("Erro fatal do gbak", migracao.erro_fatal or "(nenhum)"))
    L.append(_linha("Erros de índice não fatais", migracao.erros_indice_nao_fatais))
    if v is not None:
        L.append(_linha("Conexão ao banco migrado", "OK" if v.conecta_ok else "FALHOU"))
        if v.gfix_executado:
            L.append(_linha("gfix -v -full: erros", v.gfix_erros))
            L.append(_linha("gfix -v -full: avisos", v.gfix_avisos))
    if migracao.linhas_erro_gbak:
        L.append("")
        L.append("  Linhas de erro do gbak:")
        for linha in migracao.linhas_erro_gbak[:40]:
            L.append(f"    {linha}")
    L.append("")
    L.append("-" * 68)
    L.append("  AVISOS DE COMPATIBILIDADE")
    L.append("-" * 68)
    if not avisos:
        L.append("  (nenhum)")
    for a in avisos:
        L.append(f"  [{a.nivel.upper()}] {a.categoria}: {a.titulo}")
        L.append(f"    {a.detalhe}")
        for item in a.itens[:30]:
            L.append(f"      - {item}")
    for obs in analise.observacoes:
        L.append(f"  [nota] {obs}")
    for obs in comparacao.observacoes:
        L.append(f"  [nota] {obs}")
    if v is not None:
        for m in v.mensagens:
            L.append(f"  [nota] {m}")
    L.append("")
    L.append("=" * 68)
    L.append(f"  RESULTADO: {resultado}")
    L.append("=" * 68)

    texto = "\n".join(L)
    dados = {
        "resultado": resultado,
        "inicio": inicio.isoformat(),
        "fim": fim.isoformat(),
        "duracao_seg": dur,
        "origem": migracao.caminho_origem,
        "fbk": migracao.caminho_fbk,
        "destino": migracao.caminho_destino,
        "erro_fatal": migracao.erro_fatal,
        "erros_indice_nao_fatais": migracao.erros_indice_nao_fatais,
        "tabelas_divergentes": [t.nome for t in comparacao.divergentes],
        "tabelas_indeterminadas": [t.nome for t in comparacao.indeterminadas],
        "avisos": [
            {"nivel": a.nivel, "categoria": a.categoria, "titulo": a.titulo}
            for a in avisos
        ],
    }
    return Relatorio(resultado=resultado, texto=texto, dados=dados)
