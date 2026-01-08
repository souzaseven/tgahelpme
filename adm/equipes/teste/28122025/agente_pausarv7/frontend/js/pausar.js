/**
 * ===================================================
 * PAINEL — PAUSAR AGENTES (EVOLUX)
 * ===================================================
 * - Status REAL via last_login / current_pause
 * - Tempo de pausa real
 * - Modal visual para motivo da pausa
 * ===================================================
 */

const AUTO_REFRESH_MS = 10000;
const BASE_BACKEND = "backend";
const STORAGE_KEY = "painel_pausa_filtros";

/* ===============================
   MAPA DE PAUSAS (EVOLUX)
=============================== */
const PAUSAS = {
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

/* ===============================
   ESTADO
=============================== */
let agentes = [];
let filtros = {
  busca: "",
  fila: "all",
  mostrarInativos: false,
  mostrarOffline: false,
  mostrarArquivados: false
};

/* ===============================
   HELPERS (STATUS REAL)
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

/* ===============================
   FILA
=============================== */
const getFilaNome = a =>
  a.current_outbound_queue?.name ||
  a.queue_name ||
  (Array.isArray(a.queues) && a.queues[0]) ||
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
  const d = new Date(a.current_pause.time_start);
  return d.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" });
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
   FETCH
=============================== */
async function fetchAgentes() {
  if (painel) painel.innerHTML = `<div class="loading">Carregando agentes...</div>`;
  try {
    const res = await fetch(`${BASE_BACKEND}/listar_agentes.php`, { cache: "no-store" });
    const json = await res.json();
    agentes = Array.isArray(json.data) ? json.data : [];
    popularSelectFilas();
    renderPainel();
  } catch (e) {
    console.error(e);
  }
}

async function atualizarAgentesSilencioso() {
  try {
    const res = await fetch(`${BASE_BACKEND}/listar_agentes.php`, { cache: "no-store" });
    const json = await res.json();
    if (Array.isArray(json.data)) {
      agentes = json.data;
      renderPainel();
    }
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
}

/* ===============================
   RENDER
=============================== */
function renderPainel() {
  if (!painel) return;
  painel.innerHTML = "";

  let online = 0, offline = 0;
  const filas = {};

  agentes.forEach(a => {
    const status = getStatus(a);
    if (status === "online") online++;
    else offline++;

    if (!filtros.mostrarOffline && status === "offline") return;
    if (!filtros.mostrarArquivados && a.archived) return;
    if (!filtros.mostrarInativos && a.enable === false) return;
    if (filtros.busca &&
        !(`${a.name}${a.login}`.toLowerCase().includes(filtros.busca))) return;
    if (filtros.fila !== "all" && getFilaNome(a) !== filtros.fila) return;

    const fila = getFilaNome(a);
    if (!filas[fila]) filas[fila] = [];
    filas[fila].push(a);
  });

  if (contador) {
    contador.innerHTML = `🟢 ${online} online • 🔴 ${offline} offline`;
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

      card.innerHTML = `
        <span class="nome">${a.name}</span>
        <span class="status">${status.toUpperCase()}</span>
        ${
          status === "paused"
            ? `<span class="tempo">⏱ ${tempoEmPausa(a)}</span>
               <span class="desde">🕒 Desde ${pausaDesde(a)}</span>`
            : ``
        }
        <button ${!podePausar(a) ? "disabled" : ""}>Pausar</button>
      `;

      if (podePausar(a)) {
        card.querySelector("button").onclick = () => abrirModalPausa(a.id);
      }

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
  modal.className = "modal-pausa";
  modal.innerHTML = `
    <div class="modal-box">
      <h3>Motivo da pausa</h3>
      ${Object.entries(PAUSAS).map(([k,v]) => `
        <label>
          <input type="radio" name="motivo" value="${v.id}">
          ${v.label}
        </label>
      `).join("")}
      <div class="acoes">
        <button class="cancelar">Cancelar</button>
        <button class="confirmar">Confirmar</button>
      </div>
    </div>
  `;

  document.body.appendChild(modal);

  modal.querySelector(".cancelar").onclick = () => modal.remove();
  modal.querySelector(".confirmar").onclick = () => {
    const sel = modal.querySelector("input[name='motivo']:checked");
    if (!sel) return alert("Selecione o motivo");
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
  if (inputBusca)
    inputBusca.addEventListener("input", e => {
      filtros.busca = e.target.value.toLowerCase();
      renderPainel();
    });

  if (selectFila)
    selectFila.addEventListener("change", e => {
      filtros.fila = e.target.value;
      renderPainel();
    });

  if (chkOffline)
    chkOffline.addEventListener("change", e => {
      filtros.mostrarOffline = e.target.checked;
      renderPainel();
    });

  if (chkArquiv)
    chkArquiv.addEventListener("change", e => {
      filtros.mostrarArquivados = e.target.checked;
      renderPainel();
    });

  if (chkInativos)
    chkInativos.addEventListener("change", e => {
      filtros.mostrarInativos = e.target.checked;
      renderPainel();
    });
}

/* ===============================
   INIT
=============================== */
carregarFiltros();
bindFiltros();
fetchAgentes();
setInterval(atualizarAgentesSilencioso, AUTO_REFRESH_MS);
