// ============================================================
// status_cards.js (v3.0) — Cards de Pausa e Fila (Totalmente Otimizado)
// ============================================================

console.log("%c[status_cards.js] v3.0 ativo", "color:#00ff88;font-weight:bold;");

class StatusCards {
  constructor() {
    this.ctrl = null;

    // elementos DOM
    this.boxPausa  = document.getElementById("pausa-lista");
    this.boxFila   = document.getElementById("lista-espera");
    this.ctPausa   = document.getElementById("contador-pausa");
    this.ctFila    = document.getElementById("contador-espera");

    this.iniciar();
  }

  iniciar() {
    if (!this.boxPausa || !this.boxFila) return;

    // aguarda sistema controle carregar
    document.addEventListener("estado:atualizado", () => this.atualizar());
    document.addEventListener("status:alterado", () => this.atualizar());
    document.addEventListener("DOMContentLoaded", () => this.atualizar());

    // cronômetro
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

    const pausas  = base.filter(p => p.status === "pausa");
    const espera  = base
      .filter(p => p.status === "espera" || p.status === "aguardando")
      .sort((a, b) => (a.posicao_fila || 99) - (b.posicao_fila || 99));

    // Atualiza contadores
    if (this.ctPausa) this.ctPausa.textContent = pausas.length;
    if (this.ctFila)  this.ctFila.textContent  = espera.length;

    this.renderPausas(pausas);
    this.renderFila(espera);
  }

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
      const tempo = this.ctrl.formatarTempo(p.tempo_espera_dinamico || 0);
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
      const tempo = this.ctrl.formatarTempo(p.tempo_espera_dinamico || 0);
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

  ticarTempo() {
    document.querySelectorAll("#pausa-lista .tempo, #lista-espera .tempo")
      .forEach(div => {
        const t0 = div.dataset.tinicio;
        if (!t0) return;

        const diff = (Date.now() - new Date(t0).getTime()) / 1000;
        div.textContent = this.ctrl ? this.ctrl.formatarTempo(diff) : "00:00";
      });
  }
}

// instancia global
window.statusCards = new StatusCards();
