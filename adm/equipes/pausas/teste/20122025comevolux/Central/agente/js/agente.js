// ===================================================
// CONTROLE DO AGENTE — FRONTEND
// ---------------------------------------------------
// RESPONSABILIDADES:
// - Listar agentes
// - Permitir busca/filtro
// - Selecionar agente
// - Consultar pausas
// - Pausar agente
// - Deslogar agente
// - Monitorar status em tempo real
//
// COMUNICAÇÃO:
// - Frontend fala SOMENTE com backend PHP
// - Backend atua como proxy da API Evolux
// ===================================================


// ===================================================
// ESTADO GLOBAL
// ===================================================
let agentes = [];              // Lista completa de agentes
let agenteSelecionado = null;  // Agente atualmente selecionado
let statusInterval = null;     // Intervalo do polling de status


// ===================================================
// CARREGAR AGENTES (LISTA PRINCIPAL)
// ===================================================
function carregarAgentes() {
  fetch("backend/listar_agentes.php")
    .then(res => res.json())
    .then(json => {
      agentes = json.data || [];
      renderizarAgentes(agentes);
    })
    .catch(err => {
      console.error(err);
      document.getElementById("lista-agentes").innerHTML =
        "<p>❌ Erro ao carregar agentes.</p>";
    });
}


// ===================================================
// RENDERIZAR LISTA DE AGENTES
// ===================================================
function renderizarAgentes(lista) {
  const container = document.getElementById("lista-agentes");
  container.innerHTML = "";

  if (lista.length === 0) {
    container.innerHTML = "<p>Nenhum agente encontrado.</p>";
    return;
  }

  lista.forEach(ag => {
    const item = document.createElement("div");
    item.className = "agente-item";

    // 🔹 Destaque se for o agente selecionado
    if (agenteSelecionado && agenteSelecionado.id === ag.id) {
      item.classList.add("selecionado");
    }

    item.onclick = () => selecionarAgente(ag);

    item.innerHTML = `
      <strong>${ag.id}</strong> | ${ag.nome}
      <span>${ag.fila} • Ramal ${ag.ramal}</span>
      <small>${ag.login}</small>
    `;

    container.appendChild(item);
  });
}


// ===================================================
// FILTRO / BUSCA DE AGENTES
// ===================================================
function filtrarAgentes() {
  const termo = document
    .getElementById("buscaAgente")
    .value
    .toLowerCase();

  const filtrados = agentes.filter(ag =>
    `${ag.id} ${ag.nome} ${ag.login} ${ag.ramal} ${ag.fila}`
      .toLowerCase()
      .includes(termo)
  );

  renderizarAgentes(filtrados);
}


// ===================================================
// SELECIONAR AGENTE
// ===================================================
function selecionarAgente(ag) {
  agenteSelecionado = ag;

  const box = document.getElementById("agente-selecionado");

  // 🔹 Reseta visual
  box.className = "info-agente";
  box.innerHTML = `
    <strong>${ag.id}</strong> - ${ag.nome}<br>
    Fila: ${ag.fila} | Ramal: ${ag.ramal} | Login: ${ag.login}
    <div id="badge-status"></div>
  `;

  document.getElementById("lista-pausas").innerHTML = "";

  // 🔁 Inicia monitoramento de status em tempo real
  if (statusInterval) clearInterval(statusInterval);
  atualizarStatusAgente();
  statusInterval = setInterval(atualizarStatusAgente, 5000);

  renderizarAgentes(agentes);
}


// ===================================================
// BUSCAR PAUSAS DISPONÍVEIS DO AGENTE
// ===================================================
function carregarPausas() {
  if (!agenteSelecionado) {
    alert("Selecione um agente primeiro.");
    return;
  }

  const lista = document.getElementById("lista-pausas");
  lista.innerHTML = "<p>🔄 Buscando pausas do agente...</p>";

  fetch(`backend/listar_pausas.php?agent_id=${agenteSelecionado.id}`)
    .then(res => res.json())
    .then(json => {
      lista.innerHTML = "";

      if (!json.data || json.data.length === 0) {
        lista.innerHTML = "<p>Nenhuma pausa disponível.</p>";
        return;
      }

      json.data.forEach(item => {
        const btn = document.createElement("button");
        btn.textContent = item.pause.description;
        btn.onclick = () => pausarAgente(item.pause.id);
        lista.appendChild(btn);
      });
    })
    .catch(err => {
      console.error(err);
      lista.innerHTML = "<p>❌ Erro ao buscar pausas.</p>";
    });
}


// ===================================================
// PAUSAR AGENTE
// ===================================================
function pausarAgente(pauseId) {
  if (!agenteSelecionado) return;

  if (!confirm("Deseja realmente colocar o agente em pausa?")) return;

  fetch("backend/pausar.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      agent_id: agenteSelecionado.id,
      pause_id: pauseId
    })
  })
    .then(res => res.json())
    .then(() => alert("✅ Agente pausado com sucesso"))
    .catch(err => {
      console.error(err);
      alert("❌ Erro ao pausar agente");
    });
}


// ===================================================
// DESLOGAR AGENTE
// ===================================================
function logoffAgente() {
  if (!agenteSelecionado) {
    alert("Selecione um agente primeiro.");
    return;
  }

  if (!confirm("Deseja realmente deslogar o agente?")) return;

  fetch("backend/logoff.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      agent_id: agenteSelecionado.id
    })
  })
    .then(res => res.json())
    .then(() => {
      alert("✅ Agente deslogado");

      // 🔹 Para o polling
      if (statusInterval) {
        clearInterval(statusInterval);
        statusInterval = null;
      }
    })
    .catch(err => {
      console.error(err);
      alert("❌ Erro ao deslogar agente");
    });
}


// ===================================================
// STATUS DO AGENTE — TEMPO REAL
// ===================================================
function atualizarStatusAgente() {
  if (!agenteSelecionado) return;

  fetch(`backend/status_agente.php?agent_id=${agenteSelecionado.id}`)
    .then(res => res.json())
    .then(json => {
      if (!json.success) return;

      const box = document.getElementById("agente-selecionado");
      const badge = document.getElementById("badge-status");

      // Limpa estado visual
      box.classList.remove("status-ativo", "status-pausa", "status-offline");
      badge.innerHTML = "";

      if (json.status === "ativo") {
        box.classList.add("status-ativo");
        badge.innerHTML = `<div class="badge ativo">Ativo</div>`;
      }

      if (json.status === "pausa") {
        box.classList.add("status-pausa");
        badge.innerHTML =
          `<div class="badge pausa">Em pausa (${json.pausa})</div>`;
      }

      if (json.status === "offline") {
        box.classList.add("status-offline");
        badge.innerHTML = `<div class="badge offline">Offline</div>`;
      }

      // Guarda status para uso futuro (lista / alertas)
      agenteSelecionado.status = json.status;
    })
    .catch(err => console.error(err));
}


// ===================================================
// INIT
// ===================================================
carregarAgentes();
