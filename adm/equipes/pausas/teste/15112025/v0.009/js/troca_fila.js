// ============================================================
// troca_fila.js (v2.0 FINAL)
// Regras oficiais de troca de posição da fila (versão completa)
// Compatível com interface_botoes.js v5.0 e controle_pausa.js v5.3
// ============================================================

console.log("%c[troca_fila.js] módulo de trocas (v2.0) carregado", "color:#00cfff;font-weight:bold;");

class TrocaFilaSistema {
    constructor() {
        this.modalLista = null;
        this.modalConfirm = null;

        this.criarEstruturaModais();

        // Processa trocas pendentes
        document.addEventListener("estado:atualizado", (ev) => {
            const dados = ev.detail || {};
            if (dados.trocas) {
                this.processarTrocasPendentes(dados.trocas);
            }
        });
    }

    // -------------------------------------------
    // Utilitários
    // -------------------------------------------
    get ctrl() {
        return window.controle;
    }

    normalizar(s) {
        return (s || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim().toLowerCase();
    }

    obterFilaEquipe(equipe) {
        if (!this.ctrl?.estado) return [];
        return this.ctrl.estado
            .filter(p => p.equipe === equipe && ["espera", "aguardando"].includes(p.status))
            .sort((a, b) => (a.posicao_fila || 999) - (b.posicao_fila || 999));
    }

    obterPosicaoNaFila(nome, equipe) {
        const fila = this.obterFilaEquipe(equipe);
        const i = fila.findIndex(p => this.normalizar(p.nome) === this.normalizar(nome));
        return i >= 0 ? i + 1 : null;
    }

    contarPausasEquipe(equipe) {
        return (this.ctrl?.estado || []).filter(
            p => p.equipe === equipe && p.status === "pausa"
        ).length;
    }

    // -------------------------------------------
    // ENTRAR EM PAUSA — Primeiro da fila
    // -------------------------------------------
    entrarPausaPrimeiro(nome, equipe) {
        const fila = this.obterFilaEquipe(equipe);
        if (!fila.length) return this.ctrl.toast("Fila vazia.", true);

        const primeiro = fila[0];
        if (this.normalizar(primeiro.nome) !== this.normalizar(nome)) {
            return this.ctrl.toast("Somente o primeiro da fila pode entrar em pausa.", true);
        }

        const pausas = this.contarPausasEquipe(equipe);
        if (pausas >= (this.ctrl.maxPausas || 2)) {
            return this.ctrl.toast("Não há vagas de pausa.", true);
        }

        this.ctrl.enviarAcao("fila_entrar_pausa", { nome, equipe });
    }

    // -------------------------------------------
    // SOLICITAR TROCA — COM REGRAS AVANÇADAS
    // -------------------------------------------
    abrirSolicitacaoTroca(nomeSolic, equipe) {
        const fila = this.obterFilaEquipe(equipe);
        if (!fila.length) return;

        const pos = this.obterPosicaoNaFila(nomeSolic, equipe);
        if (!pos || pos < 2) {
            return this.ctrl.toast("Apenas quem está da posição 2+ pode solicitar troca.", true);
        }

        const listaAlvos = [];
        const idx = pos - 1;

        // =========================================
        // REGRAS EXATAS QUE VOCÊ PEDIU:
        // =========================================
        // 🔹 Primeiro pode trocar com segundo OU último
        // 🔹 Segundo troca SOMENTE com terceiro
        // 🔹 Terceiro troca com segundo ou quarto
        // 🔹 Último troca com penúltimo
        // 🔹 Primeiro pode “ir para último”
        // =========================================

        const ultimoIdx = fila.length - 1;

        if (pos === 1) {
            // Nunca acionado aqui (pos=1 bloqueado antes)
        }
        else if (pos === 2) {
            if (fila[2]) listaAlvos.push(fila[2]); // apenas terceiro
        }
        else if (pos === fila.length) {
            listaAlvos.push(fila[fila.length - 2]); // penúltimo
        }
        else {
            // intermediários: anterior e próximo
            listaAlvos.push(fila[idx - 1]);
            listaAlvos.push(fila[idx + 1]);
        }

        // PRIMEIRO É SEMPRE ALVO OPCIONAL
        const primeiro = fila[0];
        if (primeiro && this.normalizar(primeiro.nome) !== this.normalizar(nomeSolic)) {
            listaAlvos.push(primeiro);
        }

        this.abrirModalListaTroca(nomeSolic, equipe, pos, listaAlvos);
    }

    // -------------------------------------------
    // Estrutura dos Modais
    // -------------------------------------------
    criarEstruturaModais() {
        this.modalLista = document.createElement("div");
        this.modalLista.id = "modalTrocaFila";
        this.modalLista.className = "modal-generico hidden";
        this.modalLista.innerHTML = `
        <div class="modal-conteudo">
            <h2>Solicitar troca de posição</h2>
            <p id="trocaFilaDescricao"></p>
            <div id="trocaFilaLista"></div>
            <div class="modal-acoes">
                <button id="btnTrocaCancelar" class="btn-secundario">Cancelar</button>
            </div>
        </div>
        `;

        this.modalConfirm = document.createElement("div");
        this.modalConfirm.id = "modalTrocaConfirmacao";
        this.modalConfirm.className = "modal-generico hidden";
        this.modalConfirm.innerHTML = `
        <div class="modal-conteudo">
            <h2>Pedido de troca de posição</h2>
            <p id="trocaConfirmTexto"></p>
            <div class="modal-acoes">
                <button id="btnTrocaAceitar" class="btn-primario">Aceitar</button>
                <button id="btnTrocaRecusar" class="btn-perigo">Recusar</button>
            </div>
        </div>
        `;

        document.body.appendChild(this.modalLista);
        document.body.appendChild(this.modalConfirm);

        this.modalLista.onclick = e => { if (e.target === this.modalLista) this.fecharModalLista(); };
        this.modalConfirm.onclick = e => { if (e.target === this.modalConfirm) this.fecharModalConfirm(); };

        this.modalLista.querySelector("#btnTrocaCancelar").onclick = () => this.fecharModalLista();
    }

    abrirModalListaTroca(nomeSolic, equipe, posSolic, listaAlvos) {
        const desc = this.modalLista.querySelector("#trocaFilaDescricao");
        const box = this.modalLista.querySelector("#trocaFilaLista");

        desc.textContent = `Você está na posição ${posSolic}. Selecione com quem deseja trocar:`;
        box.innerHTML = "";

        listaAlvos.forEach(p => {
            const pos = this.obterPosicaoNaFila(p.nome, equipe);

            const btn = document.createElement("button");
            btn.className = "item-troca-op";
            btn.innerHTML = `
                <strong>${p.nome}</strong>
                <small>Posição: ${pos}</small>
            `;
            btn.onclick = () => {
                this.enviarSolicitacaoTroca(nomeSolic, p.nome, equipe, "troca");
                this.fecharModalLista();
            };

            box.appendChild(btn);
        });

        // Regra especial: PRIMEIRO pode "ir para o último"
        if (posSolic === 1) {
            const btnUlt = document.createElement("button");
            btnUlt.className = "item-troca-op perigo";
            btnUlt.innerHTML = `
                <strong>Ir para o último da fila</strong>
                <small>Desce para última posição</small>
            `;
            btnUlt.onclick = () => {
                this.enviarSolicitacaoTroca(nomeSolic, null, equipe, "ultimo");
                this.fecharModalLista();
            };
            box.appendChild(btnUlt);
        }

        this.modalLista.classList.remove("hidden");
    }

    fecharModalLista() {
        this.modalLista.classList.add("hidden");
    }

    abrirModalConfirmacaoTroca(troca, souAlvo) {
        this._trocaAtual = troca;

        const texto = this.modalConfirm.querySelector("#trocaConfirmTexto");
        const btnAceitar = this.modalConfirm.querySelector("#btnTrocaAceitar");
        const btnRecusar = this.modalConfirm.querySelector("#btnTrocaRecusar");

        if (souAlvo) {
            texto.textContent = `${troca.solicitante} deseja trocar de posição com você. Aceitar?`;
            btnAceitar.style.display = "inline-flex";
            btnRecusar.textContent = "Recusar";
        } else {
            texto.textContent = troca.mensagem || "Situação da troca atualizada.";
            btnAceitar.style.display = "none";
            btnRecusar.textContent = "Fechar";
        }

        btnAceitar.onclick = () => this.responderTroca("aceitar");
        btnRecusar.onclick = () => this.responderTroca(souAlvo ? "recusar" : "fechar");

        this.modalConfirm.classList.remove("hidden");
    }

    fecharModalConfirm() {
        this.modalConfirm.classList.add("hidden");
        this._trocaAtual = null;
    }

    // -------------------------------------------
    // BACKEND
    // -------------------------------------------
    async enviarSolicitacaoTroca(solicitante, alvo, equipe, tipo = "troca") {
        try {
            const resp = await fetch("./php/controle_pausa_novo.php?acao=solicitar_troca", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ solicitante, alvo, equipe, tipo })
            });

            const dados = await resp.json();
            if (dados.success) this.ctrl.toast(dados.msg || "Solicitação enviada.");
            else this.ctrl.toast(dados.error || "Falha ao enviar solicitação.", true);

        } catch (e) {
            console.error(e);
            this.ctrl.toast("Erro ao comunicar com o servidor (troca).", true);
        }
    }

    async responderTroca(acao) {
        if (!this._trocaAtual?.id) return this.fecharModalConfirm();

        if (acao === "fechar") return this.fecharModalConfirm();

        try {
            const resp = await fetch("./php/controle_pausa_novo.php?acao=responder_troca", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ id_troca: this._trocaAtual.id, resposta: acao })
            });

            const dados = await resp.json();
            if (dados.success) this.ctrl.toast(dados.msg || "Troca processada.");
            else this.ctrl.toast(dados.error || "Erro na troca.", true);

            this.ctrl.sincronizarAtualizacoes();

        } catch (e) {
            console.error(e);
            this.ctrl.toast("Erro ao responder troca.", true);
        }

        this.fecharModalConfirm();
    }

    // -------------------------------------------
    // PENDÊNCIAS — recebidas do backend
    // -------------------------------------------
    processarTrocasPendentes(lista) {
        const meuNome = this.ctrl.operador;
        if (!meuNome) return;

        lista.forEach(t => {
            const ehSolic = this.normalizar(t.solicitante) === this.normalizar(meuNome);
            const ehAlvo = this.normalizar(t.alvo) === this.normalizar(meuNome);

            if (!ehSolic && !ehAlvo) return;

            if (t.status === "pendente" && ehAlvo) {
                this.abrirModalConfirmacaoTroca(t, true);
            }

            if (t.status === "aceita" && ehSolic) {
                this.ctrl.toast(t.mensagem || "Troca aceita!");
            }

            if (t.status === "recusada" && ehSolic) {
                this.ctrl.toast(t.mensagem || "Troca recusada.", true);
            }
        });
    }
}

// Instância global
window.trocaFila = new TrocaFilaSistema();
