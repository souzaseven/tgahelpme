"""
ui/aba_origem.py
----------------
Aba 1: escolher o banco de origem (.fdb Firebird 2.5), as credenciais e quais
instalações do Firebird usar para backup (origem) e restore (destino).
"""
from __future__ import annotations

from pathlib import Path

from PySide6.QtCore import Qt, Signal
from PySide6.QtWidgets import (
    QFileDialog,
    QGroupBox,
    QHBoxLayout,
    QLabel,
    QLineEdit,
    QPushButton,
    QVBoxLayout,
    QWidget,
)

from core.firebird import escolher_por_familia, instalacao_manual, localizar_instalacoes
from ui.estado import Estado
from ui.widgets import SeletorArquivo, combo_instalacoes, faixa_central, form_padrao

_form = form_padrao


class AbaOrigem(QWidget):
    pedir_analise = Signal()

    def __init__(self, estado: Estado) -> None:
        super().__init__()
        self.estado = estado

        raiz = QVBoxLayout(self)
        raiz.setContentsMargins(0, 0, 0, 0)

        conteudo = QWidget()
        raiz.addLayout(faixa_central(conteudo))

        lay = QVBoxLayout(conteudo)
        lay.setContentsMargins(28, 24, 28, 20)
        lay.setSpacing(16)

        titulo = QLabel("Banco de origem")
        titulo.setObjectName("titulo")
        lay.addWidget(titulo)
        sub = QLabel(
            "Selecione o banco Firebird 2.5 e as instalações que serão usadas "
            "para o backup (origem) e o restore (destino)."
        )
        sub.setObjectName("subtitulo")
        sub.setWordWrap(True)
        lay.addWidget(sub)

        # ---- banco -------------------------------------------------------
        gb_arq = QGroupBox("Banco de dados")
        f_arq = _form()
        gb_arq.setLayout(f_arq)
        self.sel_origem = SeletorArquivo(
            "Caminho do arquivo .fdb do Firebird 2.5…",
            filtro="Banco Firebird (*.fdb *.FDB *.gdb);;Todos os arquivos (*.*)",
            titulo_dialogo="Selecionar banco de origem",
        )
        self.sel_origem.mudou.connect(self._on_origem_mudou)
        f_arq.addRow("Arquivo .fdb", self.sel_origem)
        lay.addWidget(gb_arq)

        # ---- credenciais ----------------------------------------------
        gb_cred = QGroupBox("Credenciais de conexão")
        f_cred = _form()
        gb_cred.setLayout(f_cred)
        self.ed_usuario = QLineEdit(self.estado.usuario or "SYSDBA")
        self.ed_senha = QLineEdit(self.estado.senha or "masterkey")
        self.ed_usuario.setClearButtonEnabled(True)
        self.ed_senha.setClearButtonEnabled(True)
        self.ed_usuario.textChanged.connect(lambda t: setattr(self.estado, "usuario", t.strip()))
        self.ed_senha.textChanged.connect(lambda t: setattr(self.estado, "senha", t))
        # garante o estado sincronizado com o valor pré-preenchido
        self.estado.usuario = self.ed_usuario.text().strip()
        self.estado.senha = self.ed_senha.text()
        f_cred.addRow("Usuário", self.ed_usuario)
        f_cred.addRow("Senha", self.ed_senha)
        dica_cred = QLabel("Padrão do Firebird: SYSDBA / masterkey. Edite se o seu servidor usa outra senha.")
        dica_cred.setObjectName("dica")
        dica_cred.setWordWrap(True)
        f_cred.addRow("", dica_cred)
        lay.addWidget(gb_cred)

        # ---- instalações --------------------------------------------
        gb_fb = QGroupBox("Instalações do Firebird")
        v_fb = QVBoxLayout(gb_fb)
        v_fb.setSpacing(12)
        f_fb = _form()
        self.cmb_origem = combo_instalacoes()
        self.cmb_destino = combo_instalacoes()
        self.cmb_origem.currentIndexChanged.connect(self._on_combo_mudou)
        self.cmb_destino.currentIndexChanged.connect(self._on_combo_mudou)
        f_fb.addRow("Backup — Firebird 2.5", self.cmb_origem)
        f_fb.addRow("Restore — Firebird 5.0", self.cmb_destino)
        v_fb.addLayout(f_fb)

        linha_btn = QHBoxLayout()
        linha_btn.setSpacing(8)
        btn_redetectar = QPushButton("Redetectar")
        btn_redetectar.clicked.connect(self.detectar_instalacoes)
        btn_manual = QPushButton("Apontar gbak.exe do Firebird 2.5…")
        btn_manual.clicked.connect(self._apontar_gbak_manual)
        linha_btn.addWidget(btn_redetectar)
        linha_btn.addWidget(btn_manual)
        linha_btn.addStretch(1)
        v_fb.addLayout(linha_btn)

        self.lbl_fb_aviso = QLabel()
        self.lbl_fb_aviso.setObjectName("avisoTexto")
        self.lbl_fb_aviso.setWordWrap(True)
        self.lbl_fb_aviso.hide()
        v_fb.addWidget(self.lbl_fb_aviso)
        lay.addWidget(gb_fb)

        lay.addStretch(1)

        rodape = QHBoxLayout()
        rodape.addStretch(1)
        self.btn_analisar = QPushButton("Analisar banco  →")
        self.btn_analisar.setObjectName("primario")
        self.btn_analisar.clicked.connect(self.pedir_analise.emit)
        rodape.addWidget(self.btn_analisar)
        lay.addLayout(rodape)

        self.detectar_instalacoes()
        self._atualizar_habilitacao()

    # ------------------------------------------------------------------ #
    def detectar_instalacoes(self) -> None:
        self.estado.instalacoes = localizar_instalacoes()
        self._preencher_combos()

    def _preencher_combos(self) -> None:
        for cmb in (self.cmb_origem, self.cmb_destino):
            cmb.blockSignals(True)
            cmb.clear()
            if not self.estado.instalacoes:
                cmb.addItem("(nenhuma instalação detectada)", None)
            for inst in self.estado.instalacoes:
                cmb.addItem(inst.rotulo_curto, inst)
                cmb.setItemData(cmb.count() - 1, str(inst.pasta), Qt.ToolTipRole)
            cmb.blockSignals(False)

        self._selecionar_por_instalacao(self.cmb_origem, escolher_por_familia(self.estado.instalacoes, "2.5"))
        sug_destino = escolher_por_familia(self.estado.instalacoes, "5.0")
        if sug_destino:
            self._selecionar_por_instalacao(self.cmb_destino, sug_destino)
        elif self.estado.instalacoes:
            self.cmb_destino.setCurrentIndex(0)
        self._on_combo_mudou()

    @staticmethod
    def _selecionar_por_instalacao(cmb, inst) -> None:
        if inst is None:
            return
        idx = cmb.findData(inst)
        if idx >= 0:
            cmb.setCurrentIndex(idx)

    def _apontar_gbak_manual(self) -> None:
        caminho, _ = QFileDialog.getOpenFileName(
            self, "Selecionar gbak.exe do Firebird 2.5", "", "gbak (gbak.exe)"
        )
        if not caminho:
            return
        inst = instalacao_manual(caminho)
        if not inst:
            self._mostrar_aviso(f"Não consegui ler a versão de {caminho}.")
            return
        self.estado.instalacoes = [
            i for i in self.estado.instalacoes if i.gbak_path != inst.gbak_path
        ] + [inst]
        self.estado.instalacoes.sort(key=lambda i: i.versao_tupla, reverse=True)
        self._preencher_combos()
        self._selecionar_por_instalacao(self.cmb_origem, inst)

    def _on_combo_mudou(self, *_) -> None:
        self.estado.inst_origem = self.cmb_origem.currentData()
        self.estado.inst_destino = self.cmb_destino.currentData()
        avisos = []
        o, d = self.estado.inst_origem, self.estado.inst_destino
        if o and o.familia not in ("2.5", "?"):
            avisos.append(
                f"A instalação de origem escolhida é Firebird {o.familia}. Este "
                "MVP espera Firebird 2.5 na origem — use com cautela."
            )
        if d and d.familia != "5.0":
            avisos.append(
                f"A instalação de destino escolhida é Firebird {d.familia}, não 5.0."
            )
        if o and not o.isql_path:
            avisos.append("A instalação de origem não tem isql.exe — a análise fica limitada.")
        self._mostrar_aviso("  •  ".join(avisos))
        self._atualizar_habilitacao()

    def _mostrar_aviso(self, texto: str) -> None:
        self.lbl_fb_aviso.setText(texto)
        self.lbl_fb_aviso.setVisible(bool(texto))

    def _on_origem_mudou(self, texto: str) -> None:
        self.estado.caminho_origem = texto.strip()
        if texto.strip() and not self.estado.pasta_destino:
            self.estado.pasta_destino = str(Path(texto).parent)
        self._atualizar_habilitacao()

    def _atualizar_habilitacao(self) -> None:
        origem_ok = bool(self.estado.caminho_origem) and Path(self.estado.caminho_origem).is_file()
        self.btn_analisar.setEnabled(
            origem_ok and self.estado.inst_origem is not None and self.estado.inst_destino is not None
        )
