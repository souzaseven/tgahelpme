// ============================================================
// troca_fila.js (v1.0)
// Regras de troca de posição na fila + "Entrar em Pausa" do 1º
// Depende de: window.controle (ControlePausaSistema)
// ============================================================

console.log("%c[troca_fila.js] módulo de trocas carregado", "color:#00cfff;font-weight:bold;");

class TrocaFilaSistema {
  constructor() {
    this.modalLista = null;
    this.modalConfirm = null;

    this.criarEstruturaModais();

    // Gancho para tratar trocas pendentes vindas do backend
    document.addEventListener("estado:atualizado", (ev) => {
      const dados = ev.detail || {};
      if (dados.trocas) {
        this.processarTrocasPendentes(dados.trocas);
      }
    });
  }

  // ----------------------------------------------------------
  // Utilitários
  // ----------------------------------------------------------
  get ctrl() {
    return window.controle;
  }

  normalizar(s) {
    return (s || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim().toLowerCase();
  }

  // Retorna a fila completa da equipe, ordenada pela posição
  obterFilaEquipe(equipe) {
    if (!this.ctrl || !Array.isArray(this.ctrl.estado)) return [];
    return this.ctrl.estado
      .filter(p => p.equipe === equipe && (p.status === "espera" || p.status === "aguardando"))
      .sort((a, b) => (a.posicao_fila || 999) - (b.posicao_fila || 999));
  }

  // Posição (1-based) do operador na fila
  obterPosicaoNaFila(nome, equipe) {
    const fila = this.obterFilaEquipe(equipe);
    const idx = fila.findIndex(p => this.normalizar(p.nome) === this.normalizar(nome));
    return idx >= 0 ? idx + 1 : null;
  }

  // Quantidade de pausas na equipe
  contarPausasEquipe(equipe) {
    if (!this.ctrl || !Array.isArray(this.ctrl.estado)) return 0;
    return this.ctrl.estado.filter(p => p.equipe === equipe && p.status === "pausa").length;
  }

  // ----------------------------------------------------------
  // ENTRAR EM PAUSA — Primeiro da fila
  // ----------------------------------------------------------
  entrarPausaPrimeiro(nome, equipe) {
    if (!this.ctrl) return;

    const fila = this.obterFilaEquipe(equipe);
    if (!fila.length) {
      this.ctrl.toast("Nenhuma fila encontrada para esta equipe.", true);
      return;
    }

    const primeiro = fila[0];
    if (this.normalizar(primeiro.nome) !== this.normalizar(nome)) {
      this.ctrl.toast("Apenas o primeiro da fila pode entrar em pausa por aqui.", true);
      return;
    }

    const pausasEquipe = this.contarPausasEquipe(equipe);
    if (pausasEquipe >= (this.ctrl.maxPausas || 2)) {
      this.ctrl.toast("Limite de pausas simultâneas já atingido para esta equipe.", true);
      return;
    }

    // Chama backend para mover primeiro para pausa
    this.ctrl.enviarAcao("fila_entrar_pausa", { nome, equipe });
  }

  // ----------------------------------------------------------
  // SOLICITAR TROCA — Botão disponível da posição 2 em diante
  // ----------------------------------------------------------
  abrirSolicitacaoTroca(nomeSolicitante, equipe) {
    if (!this.ctrl) return;

    const fila = this.obterFilaEquipe(equipe);
    if (!fila.length) {
      this.ctrl.toast("Nenhuma fila encontrada para esta equipe.", true);
      return;
    }

    const posSolic = this.obterPosicaoNaFila(nomeSolicitante, equipe);
    if (!posSolic || posSolic < 2) {
      this.ctrl.toast("Somente a partir do segundo da fila pode solicitar troca.", true);
      return;
    }

    // Monta lista de alvos elegíveis, conforme regra:
    // - Regra simplificada baseada nas posições adjacentes + 1º pode ser alvo
    //   • 2º pode solicitar troca com 1º ou 3º (se existir)
    //   • 3º pode com 2º ou 4º
    //   • 4º com 3º ou 5º...
    //   • Último com penúltimo
    const filaOrdenada = this.obterFilaEquipe(equipe);
    const idxSolic = filaOrdenada.findIndex(p => this.normalizar(p.nome) === this.normalizar(nomeSolicitante));

    if (idxSolic < 0) {
      this.ctrl.toast("Solicitante não encontrado na fila.", true);
      return;
    }

    const candidatos = new Set();

    // Sempre pode tentar troca com o anterior (se existir)
    if (idxSolic - 1 >= 0) {
      candidatos.add(filaOrdenada[idxSolic - 1]);
    }
    // E com o próximo (se existir)
    if (idxSolic + 1 < filaOrdenada.length) {
      candidatos.add(filaOrdenada[idxSolic + 1]);
    }

    // Garante que o primeiro da fila sempre pode ser alvo, se não for o próprio
    const primeiro = filaOrdenada[0];
    if (primeiro && this.normalizar(primeiro.nome) !== this.normalizar(nomeSolicitante)) {
      candidatos.add(primeiro);
    }

    const listaAlvos = Array.from(candidatos);

    if (!listaAlvos.length) {
      this.ctrl.toast("Nenhum operador elegível para trocar de posição.", true);
      return;
    }

    this.abrirModalListaTroca(nomeSolicitante, equipe, posSolic, listaAlvos);
  }

  // ----------------------------------------------------------
  // MODAIS
  // ----------------------------------------------------------
  criarEstruturaModais() {
    // Modal lista de operadores para troca
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

    // Modal de confirmação recebido pelo alvo
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

    // Eventos básicos
    this.modalLista.addEventListener("click", (e) => {
      if (e.target === this.modalLista) this.fecharModalLista();
    });
    this.modalConfirm.addEventListener("click", (e) => {
      if (e.target === this.modalConfirm) this.fecharModalConfirm();
    });

    const btnCanc = this.modalLista.querySelector("#btnTrocaCancelar");
    btnCanc.addEventListener("click", () => this.fecharModalLista());

    this._trocaAtual = null; // usado para armazenar dados da troca recebida
  }

  abrirModalListaTroca(nomeSolicitante, equipe, posSolic, listaAlvos) {
    const desc = this.modalLista.querySelector("#trocaFilaDescricao");
    const boxLista = this.modalLista.querySelector("#trocaFilaLista");

    desc.textContent = `Você está na posição ${posSolic} da fila. Escolha com quem deseja tentar trocar:`;

    boxLista.innerHTML = "";

    listaAlvos.forEach(alvo => {
      const posAlvo = this.obterPosicaoNaFila(alvo.nome, equipe) || "?";

      const btn = document.createElement("button");
      btn.className = "item-troca-op";
      btn.innerHTML = `
        <strong>${alvo.nome}</strong>
        <small>Posição atual: ${posAlvo}</small>
      `;
      btn.onclick = () => {
        this.enviarSolicitacaoTroca(nomeSolicitante, alvo.nome, equipe);
        this.fecharModalLista();
      };

      boxLista.appendChild(btn);
    });

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
      texto.textContent = `${troca.solicitante} está solicitando trocar de posição na fila com você. Deseja aceitar?`;
      btnAceitar.style.display = "inline-flex";
      btnRecusar.textContent = "Recusar";
    } else {
      // Mensagem para quem solicitou, caso precise (por exemplo, depois de aceitar/recusar)
      texto.textContent = troca.mensagem || "Situação da troca foi atualizada.";
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

  // ----------------------------------------------------------
  // Backend: solicitar / responder
  // ----------------------------------------------------------
  async enviarSolicitacaoTroca(solicitante, alvo, equipe) {
    try {
      const resp = await fetch("./php/controle_pausa_novo.php?acao=solicitar_troca", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ solicitante, alvo, equipe })
      });
      const dados = await resp.json();

      if (dados.success) {
        this.ctrl.toast(dados.msg || "Solicitação de troca enviada.");
      } else {
        this.ctrl.toast(dados.error || "Não foi possível solicitar a troca.", true);
      }
    } catch (e) {
      console.error(e);
      this.ctrl.toast("Erro ao comunicar com o servidor (troca).", true);
    }
  }

  async responderTroca(acao) {
    if (!this._trocaAtual || !this._trocaAtual.id) {
      this.fecharModalConfirm();
      return;
    }

    if (acao === "fechar") {
      this.fecharModalConfirm();
      return;
    }

    try {
      const resp = await fetch("./php/controle_pausa_novo.php?acao=responder_troca", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          id_troca: this._trocaAtual.id,
          resposta: acao // "aceitar" ou "recusar"
        })
      });
      const dados = await resp.json();

      if (dados.success) {
        this.ctrl.toast(dados.msg || "Troca processada com sucesso.");
        this.ctrl.sincronizarAtualizacoes();
      } else {
        this.ctrl.toast(dados.error || "Não foi possível processar a troca.", true);
      }
    } catch (e) {
      console.error(e);
      this.ctrl.toast("Erro ao comunicar com o servidor (resposta de troca).", true);
    } finally {
      this.fecharModalConfirm();
    }
  }

  // ----------------------------------------------------------
  // Processar trocas pendentes vindas do backend (get_estado)
  // ----------------------------------------------------------
  processarTrocasPendentes(listaTrocas) {
    if (!Array.isArray(listaTrocas) || !this.ctrl) return;

    const meuNome = this.ctrl.operador;
    if (!meuNome) return;

    const nm = (s) => this.normalizar(s);

    listaTrocas.forEach(t => {
      // t: { id, equipe, solicitante, alvo, status, mensagem }
      const souSolicitante = nm(t.solicitante) === nm(meuNome);
      const souAlvo = nm(t.alvo) === nm(meuNome);

      if (!souSolicitante && !souAlvo) return;

      if (t.status === "pendente" && souAlvo) {
        // Abre modal para o alvo decidir
        this.abrirModalConfirmacaoTroca(t, true);
      }

      if (t.status === "aceita" && souSolicitante) {
        this.ctrl.toast(t.mensagem || "Sua solicitação de troca foi aceita!");
      }

      if (t.status === "recusada" && souSolicitante) {
        this.ctrl.toast(t.mensagem || "Sua solicitação de troca foi recusada.", true);
      }
    });
  }
}

// Instância global
window.trocaFila = new TrocaFilaSistema();
