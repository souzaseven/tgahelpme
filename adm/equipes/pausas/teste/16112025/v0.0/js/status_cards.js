// ============================================================
// status_cards.js (v3.1) — Cards de Pausa e Fila (Cronômetro Fino)
// ============================================================

console.log("%c[status_cards.js] v3.1 ativo", "color:#00ff88;font-weight:bold;");

class StatusCards {
  constructor() {
    this.ctrl = null;

    // elementos DOM
    this.boxPausa = document.getElementById("pausa-lista");
    this.boxFila  = document.getElementById("lista-espera");
    this.ctPausa  = document.getElementById("contador-pausa");
    this.ctFila   = document.getElementById("contador-espera");

    this.iniciar();
  }

  iniciar() {
    if (!this.boxPausa || !this.boxFila) return;

    // aguarda sistema controle carregar
    document.addEventListener("estado:atualizado", () => this.atualizar());
    document.addEventListener("status:alterado", () => this.atualizar());
    document.addEventListener("DOMContentLoaded", () => this.atualizar());

    // cronômetro visual
    setInterval(() => this.ticarTempo(), 1000);
  }

  atualizar() {
    this.ctrl = window.controle;
    if (!this.ctrl || !this.ctrl.estado) return;

    const estado = this.ctrl.estado;

    // Filtra Minha Equipe se necessário
    let base = estado;
    if (this.ctrl.modoMinhaEquipe) {
      const minha = this.ctrl.buscarEquipePorOperador(this.ctrl.operador);
      base = minha ? estado.filter(x => x.equipe === minha) : [];
    }

    const pausas = base.filter(p => p.status === "pausa");
    const espera = base
      .filter(p => p.status === "espera" || p.status === "aguardando")
      .sort((a, b) => (a.posicao_fila || 99) - (b.posicao_fila || 99));

    // Atualiza contadores
    if (this.ctPausa) this.ctPausa.textContent = pausas.length;
    if (this.ctFila)  this.ctFila.textContent  = espera.length;

    this.renderPausas(pausas);
    this.renderFila(espera);
  }

  // ---------------------------------------
  // PAUSAS
  // ---------------------------------------
  renderPausas(lista) {
    this.boxPausa.innerHTML = "";

    if (lista.length === 0) {
      this.boxPausa.innerHTML = `
        <div class="lista-vazia">
          <i class="fas fa-user-clock"></i>
          Nenhum operador em pausa
        </div>`;
      return;
    }

    lista.forEach(p => {
      let tempo = "--:--";

      if (p.tempo_entrada) {
        const diff = (Date.now() - new Date(p.tempo_entrada).getTime()) / 1000;
        tempo = this.ctrl.formatarTempo(diff);
      }

      const div = document.createElement("div");
      div.className = "item-pausa";

      div.innerHTML = `
        <strong>${p.nome}</strong>
        <small>${p.equipe}</small>
        <span class="tempo" data-tinicio="${p.tempo_entrada || ""}">
          ${tempo}
        </span>
      `;

      this.boxPausa.appendChild(div);
    });
  }

  // ---------------------------------------
  // FILA DE ESPERA
  // ---------------------------------------
  renderFila(lista) {
    this.boxFila.innerHTML = "";

    if (lista.length === 0) {
      this.boxFila.innerHTML = `
        <div class="lista-vazia">
          <i class="fas fa-users"></i>
          Nenhuma pessoa na fila de espera
        </div>`;
      return;
    }

    lista.forEach(p => {
      let tempo = "--:--";

      if (p.tempo_entrada) {
        const diff = (Date.now() - new Date(p.tempo_entrada).getTime()) / 1000;
        tempo = this.ctrl.formatarTempo(diff);
      }

      const aguardando = p.status === "aguardando";

      const div = document.createElement("div");
      div.className = "item-fila";

      div.innerHTML = `
        <span class="pos">#${p.posicao_fila || "-"}</span>
        <strong>${p.nome}</strong>
        <small>${p.equipe}</small>

        <span class="tempo" data-tinicio="${p.tempo_entrada || ""}">
          ${tempo}
        </span>

        ${aguardando ? `<span class="aguardando-badge">⚠ Aguardando confirmação</span>` : ""}
      `;

      this.boxFila.appendChild(div);
    });
  }

  // ---------------------------------------
  // TICK DO CRONÔMETRO (1s)
  // ---------------------------------------
  ticarTempo() {
    // Se o controle ainda não foi inicializado, não mexe no texto
    if (!this.ctrl || typeof this.ctrl.formatarTempo !== "function") return;

    document
      .querySelectorAll("#pausa-lista .tempo, #lista-espera .tempo")
      .forEach((div) => {
        const t0 = div.dataset.tinicio;
        if (!t0) return;

        const base = new Date(t0).getTime();
        if (!base || Number.isNaN(base)) return;

        const diff = (Date.now() - base) / 1000;
        // Sempre escreve o tempo real, nunca "00:00" de fallback
        div.textContent = this.ctrl.formatarTempo(diff);
      });
  }
}

// instancia global
window.statusCards = new StatusCards();
