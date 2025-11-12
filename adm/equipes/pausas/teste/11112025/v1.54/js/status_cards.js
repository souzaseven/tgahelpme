// ============================================================
// status_cards.js (v1.0)
// ============================================================
// Sincroniza os blocos de status (Pausas e Fila de Espera)
// com o estado mais recente do Controle de Pausas.
// ============================================================

console.log("%c[status_cards.js] carregado com sucesso!", "color:#00ff88;");

function atualizarCardsStatus() {
  try {
    if (!window.controle?.estado || !Array.isArray(window.controle.estado)) return;

    const estado = window.controle.estado;
    const operadorLogado = (window.controle.operador || "").toLowerCase();

    // Contêineres principais
    const pausaBox = document.getElementById("pausas-container");
    const filaBox = document.getElementById("fila-container");

    if (!pausaBox || !filaBox) return;

    // Limpa os blocos antes de recriar
    pausaBox.innerHTML = "";
    filaBox.innerHTML = "";

    // Filtra pausas e fila
    const pausas = estado.filter(p => p.status === "pausa");
    const fila = estado.filter(p => p.status === "espera");

    // Renderiza bloco de pausas
    if (pausas.length === 0) {
      pausaBox.innerHTML = `<div class="vazio">☕ Nenhum operador em pausa</div>`;
    } else {
      pausas.forEach(p => {
        const card = document.createElement("div");
        card.className = "card-status pausa";
        card.innerHTML = `
          <strong>${p.nome}</strong>
          <small>${p.equipe}</small>
          <span class="status-text">☕ Em pausa</span>
        `;
        pausaBox.appendChild(card);
      });
    }

    // Renderiza bloco de fila
    if (fila.length === 0) {
      filaBox.innerHTML = `<div class="vazio">🕓 Nenhum operador em fila de espera</div>`;
    } else {
      fila.forEach(p => {
        const card = document.createElement("div");
        card.className = "card-status fila";
        card.innerHTML = `
          <strong>${p.nome}</strong>
          <small>${p.equipe}</small>
          <span class="status-text">🕓 Em espera</span>
        `;
        filaBox.appendChild(card);
      });
    }

    // Destacar operador logado
    document.querySelectorAll(".card-status").forEach(card => {
      const nome = (card.querySelector("strong")?.textContent || "").toLowerCase();
      if (nome === operadorLogado) {
        card.classList.add("meu-status");
      }
    });
  } catch (e) {
    console.warn("⚠️ Erro ao atualizar cards de status:", e);
  }
}

// ============================================================
// Atualização automática e integração com controle_pausa.js
// ============================================================

// Atualiza a cada 2s (ou sincronizado com controle_pausa)
setInterval(atualizarCardsStatus, 2000);

// Atualiza imediatamente quando o controle renderizar participantes
document.addEventListener("DOMContentLoaded", atualizarCardsStatus);
