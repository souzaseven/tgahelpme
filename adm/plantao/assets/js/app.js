/* =========================================================
   Painel de Plantão - JavaScript Enhanced (FINAL)
   Autor: Anderson de Souza
   Status: PRODUÇÃO
========================================================= */

const API = "backend/plantoes_api.php";
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || "";

/* =========================================================
   HELPERS
========================================================= */
const el = (id) => document.getElementById(id);

/* =========================================================
   ELEMENTOS
========================================================= */
const elements = {
  cardPrev: el("cardPrev"),
  cardCurrent: el("cardCurrent"),
  cardNext: el("cardNext"),

  btnRefresh: el("btnRefresh"),
  btnNovoSuporte: el("btnNovoSuporte"),
  btnCadastrarPlantao: el("btnCadastrarPlantao"),
  btnEditarAtual: el("btnEditarAtual"),
  btnEditarProximo: el("btnEditarProximo"),

  modalSuporte: el("modalSuporte"),
  modalPlantao: el("modalPlantao"),
  toast: el("toast"),

  supId: el("supId"),
  supNome: el("supNome"),
  supAtivo: el("supAtivo"),
  btnSalvarSuporte: el("btnSalvarSuporte"),
  modalSuporteTitle: el("modalSuporteTitle"),

  plId: el("plId"),
  plSabado: el("plSabado"),
  plDataCustom: el("plDataCustom"),
  plSuporte: el("plSuporte"),
  plObs: el("plObs"),
  plRange: el("plRange"),
  btnSalvarPlantao: el("btnSalvarPlantao"),

  fStart: el("fStart"),
  fEnd: el("fEnd"),
  tbodyPeriod: el("tbodyPeriod"),

  statsMonth: el("statsMonth"),
  cardStats: el("cardStats"),

  rankingRange: el("rankingRange"),
  cardRanking: el("cardRanking"),

  cardColaboradores: el("cardColaboradores")
};

/* =========================================================
   ESTADO
========================================================= */
const state = {
  supportsCache: [],
  summaryCache: null,
  plantaoSnapshot: null
};

/* =========================================================
   UI
========================================================= */
let toastTimer = null;

function showToast(msg, type = "info", ms = 3000) {
  const t = elements.toast;
  if (!t) return;

  t.textContent = msg;
  t.style.borderLeftColor =
    type === "success" ? "var(--success)" :
    type === "error"   ? "var(--danger)"  :
    type === "warning" ? "var(--warning)" : "var(--primary)";

  if (toastTimer) clearTimeout(toastTimer);
  t.classList.remove("hidden");
  toastTimer = setTimeout(() => t.classList.add("hidden"), ms);
}

function openModal(m) {
  if (m) m.classList.remove("hidden");
}

function closeModal(m) {
  if (m) m.classList.add("hidden");
}

// Executa `fn` mantendo o botão desabilitado — evita duplo-clique / requisição dupla
async function withBusy(btn, fn) {
  if (btn && btn.dataset.busy === "1") return;
  if (btn) { btn.dataset.busy = "1"; btn.disabled = true; }
  try {
    return await fn();
  } finally {
    if (btn) { btn.dataset.busy = "0"; btn.disabled = false; }
  }
}

/* =========================================================
   FORMATADORES
========================================================= */
const fmtBR = (ymd) => {
  if (!ymd) return "—";
  const [y, m, d] = ymd.split("-");
  return `${d}/${m}/${y}`;
};

const escapeHtml = (s) =>
  String(s ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");

// Usa construtor LOCAL (ano, mês, dia) para evitar bug de fuso horário
// new Date("2026-06-21") interpreta como UTC meia-noite → em UTC-3 vira dia 20
const addOneDay = (ymd) => {
  const [y, m, d] = ymd.split("-").map(Number);
  const next = new Date(y, m - 1, d + 1);
  return `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, "0")}-${String(next.getDate()).padStart(2, "0")}`;
};

// Formata um Date local como YYYY-MM-DD (sem passar por UTC / toISOString)
const ymdLocal = (d) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;

// true se a string YYYY-MM-DD cai num sábado (interpretada em horário local)
const isSaturdayStr = (ymd) => new Date(`${ymd}T00:00:00`).getDay() === 6;

/* =========================================================
   API
========================================================= */
function handleAuthExpired(r) {
  if (r.status === 401) {
    window.location.href = "login.php";
    return true;
  }
  return false;
}

async function apiGet(params) {
  const r = await fetch(`${API}?${new URLSearchParams(params)}`);
  if (handleAuthExpired(r)) return new Promise(() => {});
  const j = await r.json().catch(() => ({}));
  if (!r.ok || !j.success) throw new Error(j.error || "Erro na requisição");
  return j;
}

async function apiPost(action, body) {
  const r = await fetch(`${API}?action=${action}`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-Token": csrf
    },
    body: JSON.stringify(body || {})
  });

  if (handleAuthExpired(r)) return new Promise(() => {});
  const j = await r.json().catch(() => ({}));
  if (!r.ok || !j.success) throw new Error(j.error || "Erro ao salvar");
  return j;
}

/* =========================================================
   RESUMO
========================================================= */
function renderCard(target, label, range, plantao) {
  if (!target) return;

  target.innerHTML = `
    <div class="kv">
      <div class="left">
        <div class="title">${escapeHtml(plantao?.suporte_nome || "Não definido")}</div>
        <div class="sub">${fmtBR(range.sabado)} → ${fmtBR(range.domingo)}</div>
        <div class="sub"><strong>Obs:</strong> ${escapeHtml(plantao?.observacao || "—")}</div>
      </div>
      <div class="pill">${label}</div>
    </div>
  `;
}

async function loadSummary() {
  const j = await apiGet({ action: "summary" });
  state.summaryCache = j.weekends;

  renderCard(elements.cardPrev, "Semana passada", j.weekends.prev.range, j.weekends.prev.plantao);
  renderCard(elements.cardCurrent, "Atual", j.weekends.current.range, j.weekends.current.plantao);
  renderCard(elements.cardNext, "Próximo", j.weekends.next.range, j.weekends.next.plantao);
}

/* =========================================================
   SUPORTES
========================================================= */
async function loadSupports() {
  const j = await apiGet({ action: "supports_list", only_active: "0" });
  state.supportsCache = j.data || [];

  if (elements.plSuporte) {
    elements.plSuporte.innerHTML = `
      <option value="">— Sem suporte (remover plantão) —</option>
      ${state.supportsCache
        .filter(s => Number(s.ativo) === 1)
        .map(s => `<option value="${s.id}">${escapeHtml(s.nome)}</option>`)
        .join("")}
    `;
  }

  renderColaboradores();
}

function renderColaboradores() {
  const target = elements.cardColaboradores;
  if (!target) return;

  const lista = state.supportsCache;

  if (!lista.length) {
    target.innerHTML = `<p class="empty-stats">Nenhum colaborador cadastrado.</p>`;
    return;
  }

  target.innerHTML = `
    <div class="colab-list">
      ${lista.map(s => `
        <div class="colab-item">
          <div class="colab-info">
            <span class="colab-nome">${escapeHtml(s.nome)}</span>
            <span class="badge-status ${Number(s.ativo) ? "ativo" : "inativo"}">
              ${Number(s.ativo) ? "Ativo" : "Inativo"}
            </span>
          </div>
          <div class="colab-actions">
            <button class="btn small" data-edit-id="${s.id}">Editar</button>
            <button class="btn small danger" data-del-id="${s.id}" data-del-nome="${escapeHtml(s.nome)}">Excluir</button>
          </div>
        </div>
      `).join("")}
    </div>
  `;
}

function editarSuporte(id) {
  const s = state.supportsCache.find(x => x.id == id);
  if (!s) return;

  elements.supId.value = s.id;
  elements.supNome.value = s.nome;
  elements.supAtivo.checked = Number(s.ativo) === 1;
  elements.modalSuporteTitle.textContent = "Editar colaborador";
  openModal(elements.modalSuporte);
}

async function excluirSuporte(id, nome) {
  if (!window.confirm(`Excluir o colaborador "${nome}"?\n\nEsta ação não pode ser desfeita.`)) return;
  try {
    await apiPost("support_delete", { id });
    await loadSupports();
    showToast(`${nome} excluído com sucesso`, "success");
  } catch (e) {
    showToast(e.message || "Erro ao excluir colaborador", "error", 5000);
  }
}

elements.cardColaboradores?.addEventListener("click", (e) => {
  const editBtn = e.target.closest("[data-edit-id]");
  if (editBtn) { editarSuporte(+editBtn.dataset.editId); return; }

  const delBtn = e.target.closest("[data-del-id]");
  if (delBtn) excluirSuporte(+delBtn.dataset.delId, delBtn.dataset.delNome);
});

el("toggleColaboradores")?.addEventListener("click", () => {
  const body    = elements.cardColaboradores;
  const chevron = document.querySelector("#toggleColaboradores .toggle-chevron");
  const open    = body.classList.toggle("open");
  chevron?.classList.toggle("open", open);
});
/* =========================================================
   FILTRO POR PERÍODO (AUTOMÁTICO) — SEM ORDENAÇÃO
   - Aceita YYYY-MM-DD e DD/MM/YYYY
   - Dispara automaticamente ao mudar datas
========================================================= */

/* ==========================
   NORMALIZA DATA PARA YMD
========================== */
function normalizeToYMD(v) {
  v = String(v || "").trim();
  if (!v) return "";

  // Já está em YYYY-MM-DD
  if (/^\d{4}-\d{2}-\d{2}$/.test(v)) return v;

  // Está em DD/MM/YYYY
  const m = v.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
  if (m) {
    const [, dd, mm, yyyy] = m;
    return `${yyyy}-${mm}-${dd}`;
  }

  return ""; // formato desconhecido
}

/* ==========================
   CARREGAR PERÍODO
========================== */
async function loadPeriod(start, end) {
  const startYMD = normalizeToYMD(start);
  const endYMD   = normalizeToYMD(end);

  if (!startYMD || !endYMD) {
    elements.tbodyPeriod.innerHTML =
      `<tr><td colspan="4">Datas inválidas</td></tr>`;
    return;
  }

  // garante ordem correta do intervalo
  const ini = startYMD <= endYMD ? startYMD : endYMD;
  const fim = startYMD <= endYMD ? endYMD : startYMD;

  // loading
  elements.tbodyPeriod.innerHTML =
    `<tr><td colspan="4">Carregando...</td></tr>`;

  let j;
  try {
    j = await apiGet({ action: "period", start: ini, end: fim });
  } catch (e) {
    elements.tbodyPeriod.innerHTML =
      `<tr><td colspan="4">Erro ao buscar (falha de rede)</td></tr>`;
    return;
  }

  if (!j || j.success === false) {
    const msg = (j && j.error) ? j.error : "Erro ao buscar";
    elements.tbodyPeriod.innerHTML =
      `<tr><td colspan="4">${escapeHtml(msg)}</td></tr>`;
    return;
  }

  const data = Array.isArray(j.data) ? j.data : [];

  // ✅ sem ordenação, só render
  elements.tbodyPeriod.innerHTML = data.length
    ? data.map(r => `
        <tr>
          <td>${fmtBR(r.sabado)}</td>
          <td>${fmtBR(r.domingo)}</td>
          <td>${escapeHtml(r.suporte_nome || "—")}</td>
          <td>${escapeHtml(r.observacao || "—")}</td>
        </tr>
      `).join("")
    : `<tr><td colspan="4">Nenhum registro</td></tr>`;
}

/* ==========================
   FILTRO AUTOMÁTICO
========================== */
function autoFilterPeriod() {
  const start = elements.fStart?.value || "";
  const end   = elements.fEnd?.value || "";
  if (!start || !end) return;
  loadPeriod(start, end);
}

/* ==========================
   EVENTOS (SEM BOTÃO)
========================== */
elements.fStart?.addEventListener("change", autoFilterPeriod);
elements.fEnd?.addEventListener("change", autoFilterPeriod);
elements.fStart?.addEventListener("input", autoFilterPeriod);
elements.fEnd?.addEventListener("input", autoFilterPeriod);

/* ==========================
   PERÍODO PADRÃO (AUTOLOAD)
========================== */
function setDefaultPeriod() {
  const d = new Date();
  const ini = new Date(d.getFullYear(), d.getMonth(), 1);
  const fim = new Date(d.getFullYear(), d.getMonth() + 1, 0);

  elements.fStart.value = ymdLocal(ini);
  elements.fEnd.value   = ymdLocal(fim);
}


/* =========================================================
   MODAIS
========================================================= */
if (elements.btnNovoSuporte) elements.btnNovoSuporte.onclick = () => {
  elements.supId.value = 0;
  elements.supNome.value = "";
  elements.supAtivo.checked = true;
  elements.modalSuporteTitle.textContent = "Cadastrar suporte";
  openModal(elements.modalSuporte);
};

if (elements.btnSalvarSuporte) elements.btnSalvarSuporte.onclick = () =>
  withBusy(elements.btnSalvarSuporte, async () => {
    if (!elements.supNome.value.trim())
      return showToast("Informe o nome", "warning");

    try {
      await apiPost("support_save", {
        id: +elements.supId.value,
        nome: elements.supNome.value.trim(),
        ativo: elements.supAtivo.checked ? 1 : 0
      });

      closeModal(elements.modalSuporte);
      elements.modalSuporteTitle.textContent = "Cadastrar suporte";
      await loadSupports();
      showToast("Suporte salvo", "success");
    } catch (e) {
      showToast(e.message || "Erro ao salvar suporte", "error", 5000);
    }
  });

function openPlantaoModal(sabado) {
  const domingo = addOneDay(sabado);

  elements.plId.value = 0;
  elements.plSabado.value = sabado;
  elements.plDataCustom.value = sabado;
  elements.plRange.textContent = `${fmtBR(sabado)} → ${fmtBR(domingo)}`;
  elements.plObs.value = "";
  elements.plSuporte.value = "";

  // Captura estado inicial para detectar alterações não salvas
  state.plantaoSnapshot = {
    suporte: elements.plSuporte.value,
    obs: elements.plObs.value,
    data: elements.plDataCustom.value
  };

  openModal(elements.modalPlantao);
}

function isPlantaoModalDirty() {
  const s = state.plantaoSnapshot;
  if (!s) return false;
  return (
    elements.plSuporte.value    !== s.suporte ||
    elements.plObs.value.trim() !== s.obs     ||
    elements.plDataCustom.value !== s.data
  );
}

function closePlantaoModal() {
  if (isPlantaoModalDirty()) {
    if (!window.confirm("Fechar sem salvar?\nAs alterações preenchidas serão perdidas.")) return;
  }
  state.plantaoSnapshot = null;
  closeModal(elements.modalPlantao);
}

elements.plDataCustom?.addEventListener("change", () => {
  const sab = elements.plDataCustom.value;
  if (!sab) return;

  elements.plSabado.value = sab;
  elements.plRange.textContent = `${fmtBR(sab)} → ${fmtBR(addOneDay(sab))}`;

  if (!isSaturdayStr(sab)) {
    showToast("Atenção: a data selecionada não é um sábado.", "warning", 4000);
  }
});

if (elements.btnEditarAtual) elements.btnEditarAtual.onclick = () =>
  openPlantaoModal(state.summaryCache?.current?.range?.sabado);

if (elements.btnEditarProximo) elements.btnEditarProximo.onclick = () =>
  openPlantaoModal(state.summaryCache?.next?.range?.sabado);

if (elements.btnCadastrarPlantao) elements.btnCadastrarPlantao.onclick = () => {
  const d = new Date();
  const diff = (6 - d.getDay() + 7) % 7;
  d.setDate(d.getDate() + diff);
  openPlantaoModal(ymdLocal(d));
};

if (elements.btnSalvarPlantao) elements.btnSalvarPlantao.onclick = () =>
  withBusy(elements.btnSalvarPlantao, async () => {
    const suporte = elements.plSuporte.value;
    const sabado  = elements.plSabado.value;

    // 🔒 VALIDAÇÃO LOCAL (antes de ir pro backend)
    const hoje = new Date();
    hoje.setHours(0, 0, 0, 0);

    const sab = new Date(`${sabado}T00:00:00`);
    sab.setHours(0, 0, 0, 0);

    if (sab < hoje) {
      return showToast(
        "❌ Não é permitido criar ou alterar plantão em data passada.",
        "warning",
        5000
      );
    }

    if (!isSaturdayStr(sabado)) {
      return showToast("A data do plantão precisa ser um sábado.", "warning", 5000);
    }

    try {

      // Remover plantão (ninguém no plantão)
      if (!suporte) {
        const confirmado = window.confirm(
          `Tem certeza que deseja remover o plantão do fim de semana ${fmtBR(sabado)}?\n\nEssa ação não pode ser desfeita.`
        );
        if (!confirmado) return;

        await apiPost("plantao_delete", { sabado });
        state.plantaoSnapshot = null;
        closeModal(elements.modalPlantao);
        await boot(true);
        return showToast("Plantão removido", "success");
      }

      await apiPost("plantao_save", {
        sabado,
        suporte_id: +suporte,
        observacao: elements.plObs.value.trim()
      });

      state.plantaoSnapshot = null;
      closeModal(elements.modalPlantao);
      await boot(true);
      showToast("Plantão salvo com sucesso", "success");

    } catch (e) {
      // fallback (caso backend rejeite por outro motivo)
      showToast(e.message || "Erro ao salvar plantão", "error", 5000);
    }
  });



/* =========================================================
   FECHAMENTO DE MODAIS
========================================================= */
document.addEventListener("click", (e) => {
  const btn = e.target.closest("[data-close]");
  if (btn) {
    const target = document.getElementById(btn.dataset.close);
    target === elements.modalPlantao ? closePlantaoModal() : closeModal(target);
    return;
  }
  if (e.target.classList.contains("modal-backdrop")) {
    e.target === elements.modalPlantao ? closePlantaoModal() : closeModal(e.target);
  }
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    document.querySelectorAll(".modal-backdrop:not(.hidden)").forEach(m => {
      m === elements.modalPlantao ? closePlantaoModal() : closeModal(m);
    });
  }
});


/* =========================================================
   ESTATÍSTICAS POR COLABORADOR
========================================================= */
const MEDALS = ["🥇", "🥈", "🥉"];

async function loadStats() {
  const ym = elements.statsMonth?.value;
  if (!ym || !elements.cardStats) return;

  try {
    const j = await apiGet({ action: "stats", ym });
    const data = j.data || [];
    const max = data.length ? Math.max(...data.map(r => +r.total)) : 1;

    elements.cardStats.innerHTML = data.length
      ? `<div class="stats-list">
          ${data.map((r, i) => `
            <div class="stats-item">
              <span class="stats-rank">${MEDALS[i] ?? i + 1}</span>
              <span class="stats-name">${escapeHtml(r.suporte_nome)}</span>
              <div class="stats-bar-wrap">
                <div class="stats-bar" style="width:${Math.round((+r.total / max) * 100)}%"></div>
              </div>
              <span class="stats-count">${r.total} ${plural(r.total)}</span>
            </div>
          `).join("")}
        </div>`
      : `<p class="empty-stats">Nenhum plantão registrado neste mês.</p>`;
  } catch {
    elements.cardStats.innerHTML = `<p class="empty-stats">Erro ao carregar estatísticas.</p>`;
  }
}

function setDefaultStatsMonth() {
  if (!elements.statsMonth) return;
  const d = new Date();
  elements.statsMonth.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
}

elements.statsMonth?.addEventListener("change", loadStats);

/* =========================================================
   RANKING DE PLANTÕES (dashboard — quem mais ficou de plantão)
========================================================= */
const RANK_COLORS = ["#3b82f6", "#8b5cf6", "#10b981", "#f59e0b", "#06b6d4", "#ec4899", "#f97316", "#14b8a6"];

function rankColor(nome) {
  let h = 0;
  for (const c of String(nome)) h = (h * 31 + c.charCodeAt(0)) & 0xffffffff;
  return RANK_COLORS[Math.abs(h) % RANK_COLORS.length];
}

const plural = (n) => (+n === 1 ? "plantão" : "plantões");

// Gera uma escala de eixo "redonda" com 4–6 divisões inteiras
function buildTicks(maxVal) {
  const axisMax = Math.max(1, maxVal);
  const step = [1, 2, 5, 10, 20, 25, 50, 100].find(s => axisMax / s <= 6) || Math.ceil(axisMax / 5);
  const top = Math.ceil(axisMax / step) * step;
  const ticks = [];
  for (let t = 0; t <= top; t += step) ticks.push(t);
  return { top, ticks };
}

async function loadRanking() {
  const target = elements.cardRanking;
  if (!target) return;

  const range  = elements.rankingRange?.value || "all";
  const params = { action: "ranking" };
  if (range !== "all") {
    params.start = `${range}-01-01`;
    params.end   = `${range}-12-31`;
  }

  target.innerHTML = `<p class="empty-stats">Carregando...</p>`;

  let j;
  try {
    j = await apiGet(params);
  } catch {
    target.innerHTML = `<p class="empty-stats">Erro ao carregar o ranking.</p>`;
    return;
  }

  // preenche o filtro de anos uma única vez
  if (elements.rankingRange && !elements.rankingRange.dataset.filled && Array.isArray(j.years)) {
    elements.rankingRange.insertAdjacentHTML(
      "beforeend",
      j.years.map(y => `<option value="${y}">${y}</option>`).join("")
    );
    elements.rankingRange.dataset.filled = "1";
  }

  const data = j.data || [];
  if (!data.length) {
    target.innerHTML = `<p class="empty-stats">Nenhum plantão registrado no período.</p>`;
    return;
  }

  const totalPlantoes = j.total_plantoes || data.reduce((s, r) => s + (+r.total), 0);
  const maxTotal      = Math.max(...data.map(r => +r.total));
  const lider         = data[0];
  const media         = totalPlantoes / data.length;

  const share   = (n) => (totalPlantoes ? Math.round((n / totalPlantoes) * 100) : 0);
  const { top, ticks } = buildTicks(maxTotal);
  const barPct  = (n) => (n / top) * 100;

  /* ---- KPIs ---- */
  const kpis = `
    <div class="rank-kpis">
      <div class="rank-kpi">
        <span class="rank-kpi-label">Plantões no período</span>
        <span class="rank-kpi-value">${totalPlantoes}</span>
      </div>
      <div class="rank-kpi">
        <span class="rank-kpi-label">Colaboradores na escala</span>
        <span class="rank-kpi-value">${data.length}</span>
      </div>
      <div class="rank-kpi">
        <span class="rank-kpi-label">Média por colaborador</span>
        <span class="rank-kpi-value">${media.toFixed(1)}</span>
      </div>
      <div class="rank-kpi is-leader">
        <span class="rank-kpi-label">Quem mais fez</span>
        <span class="rank-kpi-value" title="${escapeHtml(lider.suporte_nome)}">${escapeHtml(lider.suporte_nome)}</span>
        <span class="rank-kpi-sub">${lider.total} ${plural(lider.total)} · ${share(+lider.total)}%</span>
      </div>
    </div>
  `;

  /* ---- Barra de distribuição 100% ---- */
  const distSeg = data.map(r => {
    const c = rankColor(r.suporte_nome);
    return `<div class="rank-dist-seg" style="width:${(+r.total / totalPlantoes) * 100}%;background:${c}"
      title="${escapeHtml(r.suporte_nome)}: ${r.total} (${share(+r.total)}%)"></div>`;
  }).join("");

  const distLegend = data.map(r => {
    const c = rankColor(r.suporte_nome);
    return `<span class="rank-legend-item">
      <span class="rank-legend-dot" style="background:${c}"></span>
      ${escapeHtml(r.suporte_nome)} <strong>${r.total}</strong>
    </span>`;
  }).join("");

  /* ---- Gráfico de barras horizontais ---- */
  const gridHtml = ticks.map(t =>
    `<div class="rank-gridline" style="left:${(t / top) * 100}%"></div>`
  ).join("");

  const axisHtml = ticks.map((t, i) => {
    const tx = i === 0 ? "0" : i === ticks.length - 1 ? "-100%" : "-50%";
    return `<span style="left:${(t / top) * 100}%;transform:translateX(${tx})">${t}</span>`;
  }).join("");

  const rowsHtml = data.map((r, i) => {
    const rankClass = i < 3 ? ` is-${i + 1}` : "";
    return `
      <div class="rank-chart-row${rankClass}">
        <div class="rank-chart-label">
          <span class="rank-badge">${i + 1}</span>
          <span class="rank-chart-name" title="${escapeHtml(r.suporte_nome)}">${escapeHtml(r.suporte_nome)}</span>
        </div>
        <div class="rank-chart-track">
          <div class="rank-chart-bar" style="width:${barPct(+r.total)}%"></div>
          <span class="rank-chart-val" style="left:${barPct(+r.total)}%">${r.total}<small>${share(+r.total)}%</small></span>
        </div>
      </div>
    `;
  }).join("");

  target.innerHTML = `
    ${kpis}

    <div class="rank-block">
      <div class="rank-block-title">Distribuição dos plantões</div>
      <div class="rank-dist-bar">${distSeg}</div>
      <div class="rank-dist-legend">${distLegend}</div>
    </div>

    <div class="rank-block">
      <div class="rank-block-title">Plantões por colaborador</div>
      <div class="rank-chart">
        <div class="rank-chart-plot">
          <div class="rank-grid">${gridHtml}</div>
          <div class="rank-chart-rows">${rowsHtml}</div>
        </div>
        <div class="rank-axis">
          <div></div>
          <div class="rank-axis-inner">${axisHtml}</div>
        </div>
      </div>
    </div>
  `;
}

elements.rankingRange?.addEventListener("change", loadRanking);

/* =========================================================
   BOOT
========================================================= */
async function boot(skipToast = false) {
  try {
    await loadSupports();
    await loadSummary();
    await loadStats();
    await loadRanking();
    setDefaultPeriod();
    await loadPeriod(elements.fStart.value, elements.fEnd.value);
    if (!skipToast) showToast("Painel carregado", "success");
  } catch (e) {
    showToast(e.message, "error");
  }
}

document.addEventListener("DOMContentLoaded", () => {
  setDefaultStatsMonth();
  boot();
});

