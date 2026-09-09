"""
ui/trabalhadores.py
-------------------
QThreads que rodam o núcleo (core/) fora da thread da interface, para a janela
nunca travar. Só o que precisa de UI (sinais) fica aqui; toda a lógica é do
core.
"""
from __future__ import annotations

import threading
from dataclasses import dataclass
from datetime import datetime

from PySide6.QtCore import QThread, Signal

import logger
from core import comparacao, migracao, relatorio, validacao
from core.analise import analisar
from core.compatibilidade import avaliar
from core.firebird import InstalacaoFirebird
from core.migracao import OpcoesMigracao
from core.modelos import ResultadoAnalise


class TrabalhadorAnalise(QThread):
    concluido = Signal(object)   # ResultadoAnalise
    falhou = Signal(str)

    def __init__(self, inst_origem: InstalacaoFirebird, caminho: str, usuario: str, senha: str):
        super().__init__()
        self._inst = inst_origem
        self._caminho = caminho
        self._usuario = usuario
        self._senha = senha

    def run(self) -> None:
        try:
            resultado = analisar(self._inst, self._caminho, self._usuario, self._senha)
            self.concluido.emit(resultado)
        except Exception as e:  # nunca deixar a thread morrer silenciosa
            logger.erro(f"Falha inesperada na análise: {e!r}")
            self.falhou.emit(str(e))


@dataclass
class PacoteMigracao:
    inst_origem: InstalacaoFirebird
    inst_destino: InstalacaoFirebird
    origem_fdb: str
    destino_fdb: str
    fbk_path: str
    usuario: str
    senha: str
    opcoes: OpcoesMigracao
    analise: ResultadoAnalise
    validacao_completa: bool
    pasta_relatorio: str


class TrabalhadorMigracao(QThread):
    linha = Signal(str)
    fase = Signal(str)
    progresso = Signal(int)       # 0..100
    concluido = Signal(object)    # relatorio.Relatorio
    # também emite via 'linha' as mensagens de log

    _PESOS = {"backup": (5, 45), "restore": (45, 85), "validacao": (85, 94), "comparacao": (94, 100)}

    def __init__(self, pacote: PacoteMigracao):
        super().__init__()
        self._p = pacote
        self._cancelar = threading.Event()
        self._tabelas_total = max(1, len(pacote.analise.tabelas))
        self._tabelas_restauradas = 0
        self._fase_atual = "backup"

    def cancelar(self) -> None:
        self._cancelar.set()
        self.linha.emit(">>> Cancelamento solicitado…")

    # -- callbacks vindos do core (rodando nesta thread) ---------------------
    def _on_linha(self, texto: str) -> None:
        self.linha.emit(texto)
        baixo = texto.lower()
        if self._fase_atual == "restore" and "restoring data for table" in baixo:
            self._tabelas_restauradas += 1
            ini, fim = self._PESOS["restore"]
            frac = min(1.0, self._tabelas_restauradas / self._tabelas_total)
            self.progresso.emit(int(ini + (fim - ini) * frac))

    def _on_fase(self, texto: str) -> None:
        self.fase.emit(texto)
        if "Backup" in texto:
            self._fase_atual = "backup"
            self.progresso.emit(self._PESOS["backup"][0])
        elif "Restaura" in texto:
            self._fase_atual = "restore"
            self.progresso.emit(self._PESOS["restore"][0])

    def run(self) -> None:
        p = self._p
        inicio = datetime.now()
        try:
            self.fase.emit("Verificando compatibilidade")
            avisos = avaliar(p.analise)

            res_mig = migracao.executar_migracao(
                p.inst_origem,
                p.inst_destino,
                p.origem_fdb,
                p.destino_fdb,
                p.fbk_path,
                p.usuario,
                p.senha,
                p.opcoes,
                callback_linha=self._on_linha,
                callback_fase=self._on_fase,
                deve_cancelar=self._cancelar.is_set,
            )

            res_val = None
            res_cmp = comparacao_vazia()
            if res_mig.sucesso:
                self.fase.emit("Validando o banco migrado")
                self.progresso.emit(self._PESOS["validacao"][0])
                res_val = validacao.validar(
                    p.inst_destino, p.destino_fdb, p.usuario, p.senha,
                    completa=p.validacao_completa,
                )
                self.fase.emit("Comparando contagens de registros")
                self.progresso.emit(self._PESOS["comparacao"][0])
                res_cmp = comparacao.comparar(
                    p.inst_origem, p.origem_fdb,
                    p.inst_destino, p.destino_fdb,
                    p.analise.tabelas,
                    dialeto=p.analise.sql_dialect or 3,
                    usuario=p.usuario, senha=p.senha,
                    generators_origem=p.analise.qtd_generators,
                    generators_destino=res_val.qtd_generators if res_val else 0,
                )

            fim = datetime.now()
            rel = relatorio.montar(
                analise=p.analise,
                avisos=avisos,
                migracao=res_mig,
                validacao=res_val,
                comparacao=res_cmp,
                origem_build=p.inst_origem.rotulo,
                destino_build=p.inst_destino.rotulo,
                inicio=inicio,
                fim=fim,
            )
            self.progresso.emit(100)
            self.concluido.emit(rel)
        except Exception as e:
            logger.erro(f"Falha inesperada na migração: {e!r}")
            fim = datetime.now()
            from core.modelos import ResultadoMigracao

            res_mig = ResultadoMigracao(caminho_origem=p.origem_fdb)
            res_mig.erro_fatal = f"Erro inesperado: {e}"
            rel = relatorio.montar(
                analise=p.analise,
                avisos=avaliar(p.analise),
                migracao=res_mig,
                validacao=None,
                comparacao=comparacao_vazia(),
                origem_build=p.inst_origem.rotulo,
                destino_build=p.inst_destino.rotulo,
                inicio=inicio,
                fim=fim,
            )
            self.concluido.emit(rel)


def comparacao_vazia():
    from core.modelos import ResultadoComparacao

    return ResultadoComparacao()
