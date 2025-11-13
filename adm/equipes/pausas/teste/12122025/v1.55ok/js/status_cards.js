// ============================================================
// status_cards.js (v1.2 - compatível com layout atual)
// ============================================================
// Atualiza automaticamente os blocos:
// - ☕ Pausas Ativas (#pausa-lista)
// - 🕓 Fila de Espera (#lista-espera)
// ============================================================

console.log("%c[status_cards.js] sincronizado com layout real", "color:#00ff88;");

function atualizarCardsStatus() {
  try {
    // Garantir que controle_pausa já esteja ativo
    if (!window.controle?.estado || window.controle.estado.length === 0) return;

    const estado = window.controle.estado;
    const operadorLogado = (window.controle.operador || "").toLowerCase();

    // Selecionar containers reais do HTML
    const pausaLista = document.getElementById("pausa-lista");
    const filaLista = document.getElementById("lista-espera");
    const contadorPausa = document.getElementById("contador-pausa");
    const contadorFila = document.getElementById("contador-espera");

    if (!pausaLista || !filaLista) return;

    // 🔄 Limpar conteúdo anterior
    pausaLista.innerHTML = "";
    filaLista.innerHTML = "";

    // 🔎 Filtrar operadores por status
    const pausas = estado.filter(p => p.status === "pausa");
    const fila = estado.filter(p => p.status === "espera");

    // Atualizar contadores
    if (contadorPausa) contadorPausa.textContent = pausas.length || 0;
    if (contadorFila) contadorFila.textContent = fila.length || 0;

    // ☕ === BLOCO DE PAUSAS ===
    if (pausas.length === 0) {
      pausaLista.innerHTML = `
        <div class="lista-vazia">
          <i class="fas fa-user-clock"></i>
          Nenhum operador em pausa no momento
        </div>`;
    } else {
      pausas.forEach(p => {
        const item = document.createElement("div");
        item.className = "item-pausa";
        item.innerHTML = `
          <div class="info">
            <strong>${p.nome}</strong>
            <span class="equipe">${p.equipe}</span>
          </div>
          <div class="status">☕ Em pausa</div>
        `;
        if (p.nome.toLowerCase() === operadorLogado) item.classList.add("meu-status");
        pausaLista.appendChild(item);
      });
    }

    // 🕓 === BLOCO DE FILA ===
    if (fila.length === 0) {
      filaLista.innerHTML = `
        <div class="lista-vazia">
          <i class="fas fa-users"></i>
          Nenhuma pessoa na fila de espera
        </div>`;
    } else {
      fila.forEach(p => {
        const item = document.createElement("div");
        item.className = "item-fila";
        item.innerHTML = `
          <div class="info">
            <strong>${p.nome}</strong>
            <span class="equipe">${p.equipe}</span>
          </div>
          <div class="status">🕓 Em espera</div>
        `;
        if (p.nome.toLowerCase() === operadorLogado) item.classList.add("meu-status");
        filaLista.appendChild(item);
      });
    }

  } catch (e) {
    console.warn("⚠️ Erro ao atualizar cards de status:", e);
  }
}

// ============================================================
// 🔁 Sincronização direta com o Controle de Pausa
// ============================================================

// Atualiza automaticamente a cada 2 segundos
setInterval(() => {
  if (window.controle?.estado?.length) atualizarCardsStatus();
}, 2000);

// Atualiza sempre que o painel renderizar participantes
document.addEventListener("DOMContentLoaded", () => {
  setTimeout(() => atualizarCardsStatus(), 1500);

  const antigoRender = window.controle?.renderizarParticipantes;
  if (antigoRender) {
    window.controle.renderizarParticipantes = function (...args) {
      antigoRender.apply(this, args);
      setTimeout(atualizarCardsStatus, 300);
    };
  }
});
