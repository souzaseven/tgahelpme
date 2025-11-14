// ============================================================
// status_cards.js (v2.0) — cards de Pausas e Fila
// ============================================================

console.log("%c[status_cards.js] ativo", "color:#00ff88;");

function atualizarCardsStatus() {
  const ctrl = window.controle;
  if (!ctrl?.estado) return;

  const pausaLista = document.getElementById("pausa-lista");
  const filaLista  = document.getElementById("lista-espera");
  const contPausa  = document.getElementById("contador-pausa");
  const contEspera = document.getElementById("contador-espera");
  if (!pausaLista || !filaLista || !contPausa || !contEspera) return;

  const isMinhaEquipe = ctrl.modoMinhaEquipe;
  let base = ctrl.estado;

  if (isMinhaEquipe) {
    const minhaEquipe = ctrl.buscarEquipePorOperador(ctrl.operador);
    base = minhaEquipe ? base.filter(p => p.equipe === minhaEquipe) : [];
  }

  const emPausa  = base.filter(p => p.status === "pausa");
  const emEspera = base.filter(p => p.status === "espera").sort((a, b) => (a.posicao_fila||99) - (b.posicao_fila||99));

  contPausa.textContent  = emPausa.length;
  contEspera.textContent = emEspera.length;

  // Pausas
  pausaLista.innerHTML = "";
  if (emPausa.length === 0) {
    pausaLista.innerHTML = `
      <div class="lista-vazia">
        <i class="fas fa-user-clock"></i>
        Nenhum operador em pausa no momento
      </div>`;
  } else {
    emPausa.forEach(p => {
      const li = document.createElement("div");
      li.className = "item-pausa";
      li.innerHTML = `
        <strong>${p.nome}</strong>
        <small>${p.equipe}</small>
        <span class="tempo" data-tinicio="${p.tempo_entrada||""}">${ctrl.formatarTempo(p.tempo_espera_dinamico||0)}</span>
      `;
      pausaLista.appendChild(li);
    });
  }

  // Fila
  filaLista.innerHTML = "";
  if (emEspera.length === 0) {
    filaLista.innerHTML = `
      <div class="lista-vazia">
        <i class="fas fa-users"></i>
        Nenhuma pessoa na fila de espera
      </div>`;
  } else {
    emEspera.forEach(p => {
      const li = document.createElement("div");
      li.className = "item-fila";
      li.innerHTML = `
        <span class="pos">#${p.posicao_fila || "-"}</span>
        <strong>${p.nome}</strong>
        <small>${p.equipe}</small>
        <span class="tempo" data-tinicio="${p.tempo_entrada||""}">${ctrl.formatarTempo(p.tempo_espera_dinamico||0)}</span>
      `;
      filaLista.appendChild(li);
    });
  }
}

document.addEventListener("estado:atualizado", atualizarCardsStatus);
document.addEventListener("status:alterado", atualizarCardsStatus);
document.addEventListener("DOMContentLoaded", atualizarCardsStatus);

setInterval(() => {
  document.querySelectorAll(".pausas-container .tempo, .fila-container .tempo").forEach(div => {
    const t0 = div.dataset.tinicio;
    if (!t0) return;
    const diff = (Date.now() - new Date(t0).getTime()) / 1000;
    div.textContent = window.controle?.formatarTempo(diff) || "00:00";
  });
}, 1000);
