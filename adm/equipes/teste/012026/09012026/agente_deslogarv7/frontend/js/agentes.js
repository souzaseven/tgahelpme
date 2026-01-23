/**
 * ===================================================
 * AGENTE.JS — PAINEL DE AGENTES EVOLUX
 * ===================================================
 * Backend:
 * - GET  ../backend/listar_agentes.php
 * - POST ../backend/deslogar_agente.php
 * ===================================================
 */

document.addEventListener("DOMContentLoaded", () => {
  carregarAgentes();

  // filtros
  document.getElementById("filtroStatus")?.addEventListener("change", aplicarFiltros);
  document.getElementById("buscaNome")?.addEventListener("input", aplicarFiltros);
  document.getElementById("agruparFila")?.addEventListener("change", aplicarFiltros);
});

let agentes = [];

/* ===================================================
   BUSCAR AGENTES
=================================================== */
async function carregarAgentes() {
  const lista = document.getElementById("listaAgentes");
  lista.innerHTML = `<div class="loading">Carregando agentes...</div>`;

  try {
    const res = await fetch("../backend/listar_agentes.php", { cache: "no-store" });
    const json = await res.json();

    if (!json.data) {
      console.error(json);
      lista.innerHTML = `<div class="erro">Erro ao carregar agentes</div>`;
      return;
    }

    agentes = json.data;
    renderizarAgentes(agentes);

  } catch (err) {
    console.error(err);
    lista.innerHTML = `<div class="erro">Erro inesperado</div>`;
  }
}

/* ===================================================
   RENDERIZAÇÃO
=================================================== */
function renderizarAgentes(listaAgentes) {
  const lista = document.getElementById("listaAgentes");
  lista.innerHTML = "";

  if (!listaAgentes.length) {
    lista.innerHTML = `<div class="vazio">Nenhum agente encontrado</div>`;
    return;
  }

  listaAgentes.forEach(agent => {
    const online = agent.last_login && agent.last_login.time_logoff === null;
    const fila   = agent.current_outbound_queue?.name || "Sem fila";

    const card = document.createElement("div");
    card.className = "agente-card";

    card.innerHTML = `
      <div class="agente-topo">
        <strong>${agent.name}</strong>
        <span class="status ${online ? "online" : "offline"}">
          ${online ? "Online" : "Offline"}
        </span>
      </div>

      <div class="agente-info">
        <div><b>ID:</b> ${agent.id}</div>
        <div><b>Login:</b> ${agent.login}</div>
        <div><b>Fila:</b> ${fila}</div>
      </div>

      <div class="agente-acoes">
        ${
          online
            ? `<button class="btn btn-danger" onclick="deslogarAgente(${agent.id})">
                Deslogar
              </button>`
            : `<button class="btn btn-disabled" disabled>Offline</button>`
        }
      </div>
    `;

    lista.appendChild(card);
  });
}

/* ===================================================
   FILTROS
=================================================== */
function aplicarFiltros() {
  const status = document.getElementById("filtroStatus").value;
  const busca  = document.getElementById("buscaNome").value.toLowerCase();

  let filtrados = agentes.filter(agent => {
    const online = agent.last_login && agent.last_login.time_logoff === null;

    if (status === "active" && !online) return false;
    if (status === "inactive" && online) return false;

    if (busca) {
      const nome  = agent.name.toLowerCase();
      const login = agent.login.toLowerCase();
      if (!nome.includes(busca) && !login.includes(busca)) return false;
    }

    return true;
  });

  renderizarAgentes(filtrados);
}

/* ===================================================
   DESLOGAR AGENTE
=================================================== */
async function deslogarAgente(agentId) {
  if (!confirm("Deseja realmente deslogar este agente?")) return;

  try {
    const fd = new FormData();
    fd.append("agent_id", agentId);

    const res = await fetch("../backend/deslogar_agente.php", {
      method: "POST",
      body: fd
    });

    const json = await res.json();

    if (json.success) {
      alert("Agente deslogado com sucesso");
      carregarAgentes();
    } else {
      alert("Erro ao deslogar agente");
      console.error(json);
    }

  } catch (err) {
    console.error(err);
    alert("Erro inesperado ao deslogar");
  }
}
