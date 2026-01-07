/**
 * ===================================================
 * PAINEL — PAUSAR AGENTES (EVOLUX)
 * ===================================================
 * - Status REAL via last_login / current_pause
 * - Tempo de pausa em tempo real (1s)
 * - Modal visual para motivo da pausa
 * - Toast de eventos (online / pausa)
 * ===================================================
 */

/* ===============================
   CONFIGURAÇÕES
=============================== */
const AUTO_REFRESH_MS = 10000;
const BASE_BACKEND = "backend";
const STORAGE_KEY = "painel_pausa_filtros";

/* 🔔 Expor refresh real para módulos auxiliares */
window.AUTO_REFRESH_MS = AUTO_REFRESH_MS;
window.__ultimoRefresh = Date.now();

/* ===============================
   MAPA DE PAUSAS (EVOLUX)
=============================== */
const PAUSAS = {
  almoco:       { id: 1, label: "Almoço" },
  lanche_manha: { id: 2, label: "Lanche Manhã" },
  lanche_tarde: { id: 3, label: "Lanche Tarde" }
};

/* ===============================
   ELEMENTOS
=============================== */
const painel   = document.getElementById("painelFilas");
const contador = document.getElementById("contadorStatus");

const inputBusca  = document.getElementById("buscaAgente");
const selectFila  = document.getElementById("filtroFila");
const chkOffline  = document.getElementById("chkOffline");
const chkArquiv   = document.getElementById("chkArquivados");
const chkInativos = document.getElementById("chkInativos");
const chkSomentePausa = document.getElementById("chkSomentePausa");

/* ===============================
   ESTADO
=============================== */
let agentes = [];
let snapshotEstado = new Map();

let filtros = {
  busca: "",
  fila: "all",
  mostrarInativos: false,
  mostrarOffline: false,
  mostrarArquivados: false,
  somentePausa: false
};

/* ===============================
   HELPERS — STATUS REAL
=============================== */
const isOnline = a =>
  a.last_login &&
  a.last_login.time_login &&
  !a.last_login.time_logoff;

const getStatus = a => {
  if (a.current_pause) return "paused";
  if (isOnline(a)) return "online";
  return "offline";
};

const podePausar = a =>
  isOnline(a) &&
  !a.current_pause &&
  a.enable === true &&
  a.archived === false;

const podeDespausar = a =>
  isOnline(a) &&
  a.current_pause &&
  a.enable === true &&
  a.archived === false;

/* ===============================
   FILA
=============================== */
const getFilaNome = a =>
  a.current_outbound_queue?.name ||
  a.queue_name ||
  (Array.isArray(a.queues) && a.queues[0]?.name) ||
  "Sem fila";

/* ===============================
   TEMPO EM PAUSA
=============================== */
function tempoEmPausa(a) {
  if (!a.current_pause?.time_start) return "";
  const inicio = new Date(a.current_pause.time_start);
  const diff = Math.floor((Date.now() - inicio) / 1000);
  const m = Math.floor(diff / 60);
  const s = diff % 60;
  return `${m}m ${s}s`;
}

function pausaDesde(a) {
  if (!a.current_pause?.time_start) return "";
  return new Date(a.current_pause.time_start)
    .toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" });
}

/* ===============================
   TOAST
=============================== */
function toast(msg, tipo = "info") {
  const el = document.createElement("div");
  el.className = `toast ${tipo}`;
  el.textContent = msg;
  document.body.appendChild(el);

  setTimeout(() => el.classList.add("show"), 10);
  setTimeout(() => {
    el.classList.remove("show");
    setTimeout(() => el.remove(), 300);
  }, 3000);
}

/* ===============================
   LOCAL STORAGE
=============================== */
function salvarFiltros() {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(filtros));
}

function carregarFiltros() {
  const raw = localStorage.getItem(STORAGE_KEY);
  if (raw) filtros = { ...filtros, ...JSON.parse(raw) };
}

/* ===============================
   SNAPSHOT DE STATUS
=============================== */
function criarSnapshotEstado(lista) {
  const map = new Map();
  lista.forEach(a => map.set(a.id, getStatus(a)));
  return map;
}

/* ===============================
   FETCH INICIAL
=============================== */
async function fetchAgentes() {
  try {
    const res = await fetch(`${BASE_BACKEND}/listar_agentes.php`, { cache: "no-store" });
    const json = await res.json();

    agentes = Array.isArray(json.data) ? json.data : [];
    snapshotEstado = criarSnapshotEstado(agentes);

    popularSelectFilas();
    renderPainel();

    // 🔄 refresh real
    window.__ultimoRefresh = Date.now();

  } catch (e) {
    console.error(e);
  }
}

/* ===============================
   FETCH SILENCIOSO (AUTO REFRESH)
=============================== */
async function atualizarAgentesSilencioso() {
  try {
    const res = await fetch(`${BASE_BACKEND}/listar_agentes.php`, { cache: "no-store" });
    const json = await res.json();
    if (!Array.isArray(json.data)) return;

    const novos = json.data;
    const novoSnapshot = criarSnapshotEstado(novos);

    novoSnapshot.forEach((estado, id) => {
      const anterior = snapshotEstado.get(id);
      if (!anterior || anterior === estado) return;

      const ag = novos.find(a => a.id === id);
      if (!ag) return;

      if (estado === "paused") toast(`${ag.name} entrou em pausa`, "warning");
      if (anterior === "paused" && estado === "online")
        toast(`${ag.name} saiu da pausa`, "success");
      if (estado === "online" && anterior === "offline")
        toast(`${ag.name} ficou online`, "success");
    });

    agentes = novos;
    snapshotEstado = novoSnapshot;
    renderPainel();

    // 🔄 refresh real
    window.__ultimoRefresh = Date.now();

  } catch {}
}

/* ===============================
   SELECT FILAS
=============================== */
function popularSelectFilas() {
  if (!selectFila) return;
  const filas = new Set(agentes.map(getFilaNome));

  selectFila.innerHTML = `<option value="all">Todas as filas</option>`;
  [...filas].sort().forEach(f => {
    const opt = document.createElement("option");
    opt.value = f;
    opt.textContent = f;
    selectFila.appendChild(opt);
  });

  selectFila.value = filtros.fila;
}

/* ===============================
   RENDER
=============================== */
function renderPainel() {
  if (!painel) return;
  painel.innerHTML = "";

  let online = 0, offline = 0, pausados = 0;
  const contMotivos = {};
  const filas = {};

  agentes.forEach(a => {
    const status = getStatus(a);

    if (status === "online") online++;
    if (status === "offline") offline++;
    if (status === "paused") {
      pausados++;
      const motivo = a.current_pause?.reason?.description || "Outro";
      contMotivos[motivo] = (contMotivos[motivo] || 0) + 1;
    }

    if (!filtros.mostrarOffline && status === "offline") return;
    if (!filtros.mostrarArquivados && a.archived) return;
    if (!filtros.mostrarInativos && a.enable === false) return;
    if (filtros.somentePausa && status !== "paused") return;
    if (filtros.busca && !(`${a.name}${a.login}`.toLowerCase().includes(filtros.busca))) return;
    if (filtros.fila !== "all" && getFilaNome(a) !== filtros.fila) return;

    const fila = getFilaNome(a);
    if (!filas[fila]) filas[fila] = [];
    filas[fila].push(a);
  });

  const motivosTxt = Object.entries(contMotivos)
    .map(([k, v]) => `${k}: ${v}`)
    .join(" | ");

  if (contador) {
    contador.innerHTML = `
      🟢 ${online} online • 🔴 ${offline} offline • ⏸ ${pausados} em pausa
      <br><small>${motivosTxt}</small>
    `;
  }

  Object.entries(filas).forEach(([fila, lista]) => {
    const bloco = document.createElement("div");
    bloco.className = "fila";
    bloco.innerHTML = `<h2>${fila}</h2><div class="agentes"></div>`;
    const area = bloco.querySelector(".agentes");

    lista.forEach(a => {
      const status = getStatus(a);

      const card = document.createElement("div");
      card.className = "agente";
      card.dataset.status = status;
      card.dataset.id = a.id;

      const botaoHTML =
        status === "paused" && podeDespausar(a)
          ? `<button class="btn-despausar">Despausar</button>`
          : `<button ${!podePausar(a) ? "disabled" : ""}>Pausar</button>`;

      card.innerHTML = `
        <span class="nome">${a.name}</span>
        <span class="status">${status.toUpperCase()}</span>
        ${
          status === "paused"
            ? `<span class="tempo">⏱ ${tempoEmPausa(a)}</span>
               <span class="desde">🕒 Desde ${pausaDesde(a)}</span>
               <span class="motivo">🏷 ${a.current_pause.reason.description}</span>`
            : ``
        }
        ${botaoHTML}
      `;

      const btn = card.querySelector("button");
      if (status === "paused" && podeDespausar(a)) btn.onclick = () => despausarAgente(a.id);
      else if (podePausar(a)) btn.onclick = () => abrirModalPausa(a.id);

      area.appendChild(card);
    });

    painel.appendChild(bloco);
  });

  salvarFiltros();
}

/* ===============================
   MODAL PAUSA
=============================== */
function abrirModalPausa(agentId) {
  const modal = document.createElement("div");
  modal.className = "modal-backdrop";
  modal.innerHTML = `
    <div class="modal">
      <h3>Motivo da pausa</h3>
      ${Object.values(PAUSAS).map(v => `
        <label class="radio">
          <input type="radio" name="motivo" value="${v.id}">
          <span>${v.label}</span>
        </label>
      `).join("")}
      <div class="acoes">
        <button class="btn cancelar">Cancelar</button>
        <button class="btn confirmar">Confirmar</button>
      </div>
    </div>
  `;

  document.body.appendChild(modal);

  modal.onclick = e => { if (e.target === modal) modal.remove(); };
  modal.querySelector(".cancelar").onclick = () => modal.remove();
  modal.querySelector(".confirmar").onclick = () => {
    const sel = modal.querySelector("input[name='motivo']:checked");
    if (!sel) return toast("Selecione o motivo", "warning");
    pausarAgente(agentId, Number(sel.value));
    modal.remove();
  };
}

/* ===============================
   PAUSAR
=============================== */
async function pausarAgente(agentId, pauseId) {
  await fetch(`${BASE_BACKEND}/pausar_agente.php`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ agent_id: agentId, pause_id: pauseId })
  });
}

/* ===============================
   FILTROS
=============================== */
function bindFiltros() {
  inputBusca?.addEventListener("input", e => {
    filtros.busca = e.target.value.toLowerCase();
    renderPainel();
  });

  selectFila?.addEventListener("change", e => {
    filtros.fila = e.target.value;
    renderPainel();
  });

  chkOffline?.addEventListener("change", e => {
    filtros.mostrarOffline = e.target.checked;
    renderPainel();
  });

  chkArquiv?.addEventListener("change", e => {
    filtros.mostrarArquivados = e.target.checked;
    renderPainel();
  });

  chkInativos?.addEventListener("change", e => {
    filtros.mostrarInativos = e.target.checked;
    renderPainel();
  });

  chkSomentePausa?.addEventListener("change", e => {
    filtros.somentePausa = e.target.checked;
    renderPainel();
  });
}

/* ===============================
   ATUALIZAR TEMPO DE PAUSA (1s)
=============================== */
setInterval(() => {
  document.querySelectorAll(".agente[data-status='paused']").forEach(card => {
    const id = Number(card.dataset.id);
    const agente = agentes.find(a => a.id === id);
    if (!agente) return;

    const tempoEl = card.querySelector(".tempo");
    if (tempoEl) tempoEl.textContent = `⏱ ${tempoEmPausa(agente)}`;
  });
}, 1000);

/* ===============================
   INIT
=============================== */
carregarFiltros();
bindFiltros();
fetchAgentes();
setInterval(atualizarAgentesSilencioso, AUTO_REFRESH_MS);
