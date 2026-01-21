/**
 * ===================================================
 * RESUMO DE PAUSAS — EVOLUX
 * + DESTAQUE DE AGENTE ESTOURADO
 * ===================================================
 */

document.addEventListener("DOMContentLoaded", () => {
  aguardarPainel();
});

/* ===============================
   LIMITES (segundos)
=============================== */
const LIMITES_PAUSA = {
  almoco: 2 * 60 * 60, // 2h
  lanche: 20 * 60     // 20min
};

/* ===============================
   AGUARDAR PAINEL
=============================== */
function aguardarPainel() {
  if (
    typeof window.agentes === "undefined" ||
    typeof window.getStatus !== "function"
  ) {
    setTimeout(aguardarPainel, 300);
    return;
  }

  iniciarResumoPausas();
}

/* ===============================
   INIT
=============================== */
function iniciarResumoPausas() {
  renderResumoPausas();
  setInterval(renderResumoPausas, 5000);
}

/* ===============================
   RENDER
=============================== */
function renderResumoPausas() {
  const container = document.getElementById("resumoPausas");
  if (!container) return;

  const contagem = {};
  const maiorTempo = {};
  const agentesEstourados = new Set();

  window.agentes.forEach(a => {
    if (window.getStatus(a) !== "paused") return;

    const motivo = a.current_pause?.reason?.description || "Outro";
    const inicio = new Date(a.current_pause.time_start);
    const tempo = Math.floor((Date.now() - inicio) / 1000);

    contagem[motivo] = (contagem[motivo] || 0) + 1;
    maiorTempo[motivo] = Math.max(maiorTempo[motivo] || 0, tempo);

    if (estourouLimite(motivo, tempo)) {
      agentesEstourados.add(a.id);
    }
  });

  aplicarDestaqueAgentes(agentesEstourados);

  container.innerHTML = "";
  const entradas = Object.entries(contagem);

  if (entradas.length === 0) {
    container.innerHTML =
      `<div class="pausa-vazia">Nenhum agente em pausa no momento</div>`;
    return;
  }

  entradas.forEach(([motivo, qtd]) => {
    const alerta = estourouLimite(motivo, maiorTempo[motivo]);

    const item = document.createElement("div");
    item.className = `
      pausa-item
      ${classePorMotivo(motivo)}
      ${alerta ? "pausa-alerta" : ""}
    `;

    item.innerHTML = `
      <span class="icon">${alerta ? "⚠️" : "⏸"}</span>
      <span>${motivo}:</span>
      <strong>${qtd}</strong>
      ${alerta ? `<span class="alerta-txt">Estourado</span>` : ""}
    `;

    container.appendChild(item);
  });
}

/* ===============================
   APLICAR DESTAQUE NOS AGENTES
=============================== */
function aplicarDestaqueAgentes(ids) {
  document.querySelectorAll(".agente").forEach(card => {
    const id = Number(card.dataset.id);

    if (ids.has(id)) {
      card.classList.add("pausa-estourada");
    } else {
      card.classList.remove("pausa-estourada");
    }
  });
}

/* ===============================
   LIMITE
=============================== */
function estourouLimite(motivo, tempo) {
  const m = motivo.toLowerCase();

  if (m.includes("almoço")) return tempo > LIMITES_PAUSA.almoco;
  if (m.includes("lanche")) return tempo > LIMITES_PAUSA.lanche;

  return false;
}

/* ===============================
   CLASSES
=============================== */
function classePorMotivo(motivo) {
  const m = motivo.toLowerCase();

  if (m.includes("almoço")) return "pausa-almoco";
  if (m.includes("lanche")) return "pausa-lanche";

  return "pausa-outros";
}
