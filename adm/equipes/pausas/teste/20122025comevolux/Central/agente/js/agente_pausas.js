// ===================================================
// agente_pausas.js — Supervisor de Pausas (NOC MODE)
// ===================================================

// 🔹 CONFIGURAÇÕES
const LIMITE_ALERTA_MIN = 15;       // alerta visual após X minutos
const AUTO_REFRESH_MS  = 60000;     // refresh silencioso

// 🔹 MAPA MANUAL DE PAUSAS (ID → DESCRIÇÃO)
const MAPA_PAUSAS = {
  1: 'Almoço',
  2: 'Lanche da manhã',
  3: 'Lanche da tarde'
};

let pausasAtivas = [];
let filtroAtual  = '';
let ranking      = {};

// ===================================================
// INIT
// ===================================================
document.addEventListener("DOMContentLoaded", () => {
  criarTopoSupervisor();
  carregarPausados();

  // ⏱️ tempo em pausa em tempo real
  setInterval(atualizarTempos, 1000);

  // 🔄 auto-refresh silencioso
  setInterval(carregarPausados, AUTO_REFRESH_MS);
});

// ===================================================
// TOPO SUPERVISOR (FILTRO + CONTADORES + RANKING)
// ===================================================
function criarTopoSupervisor() {
  const topo = document.createElement('div');
  topo.className = 'supervisor-topo';

  topo.innerHTML = `
    <div class="linha-filtros">
      <select id="filtro-pausa">
        <option value="">Todas as pausas</option>
        <option value="1">Almoço</option>
        <option value="2">Lanche da manhã</option>
        <option value="3">Lanche da tarde</option>
      </select>

      <span id="contador-pausas" class="contador"></span>
    </div>

    <div id="ranking-pausas" class="ranking"></div>
  `;

  document.querySelector('.container').prepend(topo);

  document.getElementById('filtro-pausa').addEventListener('change', e => {
    filtroAtual = e.target.value;
    renderizarLista();
  });
}

// ===================================================
// CARREGAR AGENTES PAUSADOS
// ===================================================
function carregarPausados() {
  fetch("backend/listar_agentes.php", { cache: 'no-store' })
    .then(r => r.json())
    .then(json => {
      const agentes = json.data || [];

      const pausados = agentes.filter(a =>
        (a.status || '').toLowerCase().includes('paus')
      );

      pausasAtivas = [];
      ranking = {};

      if (!pausados.length) {
        renderizarLista();
        renderizarRanking();
        return;
      }

      pausados.forEach(agent => carregarPausaAgente(agent));
    })
    .catch(() => {
      document.getElementById("lista-pausas").innerHTML =
        '<p class="muted">Erro ao carregar pausas</p>';
    });
}

function carregarPausaAgente(agent) {
  fetch(`backend/listar_pausas.php?agent_id=${agent.id}`, { cache: 'no-store' })
    .then(r => r.json())
    .then(json => {
      if (!json.data) return;

      const ativa = json.data.find(p => p.active);
      if (!ativa) return;

      const inicio = new Date(ativa.started_at);
      const agora  = new Date();
      const tempo  = Math.floor((agora - inicio) / 1000);

      ranking[agent.nome] = (ranking[agent.nome] || 0) + tempo;

      pausasAtivas.push({
        agent,
        pause: ativa,
        startedAt: ativa.started_at
      });

      renderizarLista();
      renderizarRanking();
    });
}

// ===================================================
// RENDERIZAÇÃO DA LISTA
// ===================================================
function renderizarLista() {
  const box = document.getElementById("lista-pausas");
  box.innerHTML = '';

  if (!pausasAtivas.length) {
    box.innerHTML = '<p class="muted">Nenhum agente em pausa</p>';
  }

  const contador = { 1: 0, 2: 0, 3: 0 };

  pausasAtivas.forEach(item => {
    const pauseId = item.pause.pause.id;
    contador[pauseId]++;

    if (filtroAtual && String(pauseId) !== filtroAtual) return;

    box.appendChild(criarCard(item));
  });

  const contadorBox = document.getElementById('contador-pausas');
  contadorBox.innerHTML = `
    Almoço: <strong>${contador[1]}</strong> |
    Manhã: <strong>${contador[2]}</strong> |
    Tarde: <strong>${contador[3]}</strong>
  `;
}

function criarCard(item) {
  const { agent, pause } = item;
  const pauseId = pause.pause.id;

  const div = document.createElement('div');
  div.className = 'pausa-card ativa';
  div.dataset.startedAt = pause.started_at;

  div.innerHTML = `
    <strong>${agent.nome}</strong>
    <span class="motivo">${MAPA_PAUSAS[pauseId] || 'Pausa'}</span>
    <small class="tempo">--</small>
    <button onclick="despausar('${agent.id}')">Despausar</button>
  `;

  return div;
}

// ===================================================
// TEMPO EM PAUSA + ALERTA
// ===================================================
function atualizarTempos() {
  document.querySelectorAll('.pausa-card').forEach(card => {
    const start = card.dataset.startedAt;
    if (!start) return;

    const tempo = calcularSegundos(start);
    card.querySelector('.tempo').innerText = formatarTempo(tempo);

    if (tempo >= LIMITE_ALERTA_MIN * 60) {
      card.classList.add('alerta');
    }
  });
}

function calcularSegundos(start) {
  return Math.floor((new Date() - new Date(start)) / 1000);
}

function formatarTempo(seg) {
  const h = Math.floor(seg / 3600);
  const m = Math.floor((seg % 3600) / 60);
  const s = seg % 60;

  if (h > 0) return `${h}h ${m}m`;
  if (m > 0) return `${m}m ${s}s`;
  return `${s}s`;
}

// ===================================================
// RANKING DE PAUSAS
// ===================================================
function renderizarRanking() {
  const box = document.getElementById('ranking-pausas');
  if (!box) return;

  const top = Object.entries(ranking)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 5);

  if (!top.length) {
    box.innerHTML = '';
    return;
  }

  box.innerHTML = `
    <strong>🏆 Ranking de Pausas</strong><br>
    ${top.map(([nome, tempo], i) =>
      `${i + 1}. ${nome} — ${formatarTempo(tempo)}`
    ).join('<br>')}
  `;
}

// ===================================================
// DESPAUSAR (BACKEND CORRETO)
// ===================================================
function despausar(agentId) {
  if (!confirm('Despausar agente?')) return;

  fetch("backend/despausar.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ ids: [agentId] })
  })
  .then(() => carregarPausados());
}
