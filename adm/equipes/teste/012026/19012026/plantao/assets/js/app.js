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
  btnFiltrar: el("btnFiltrar"),
  tbodyPeriod: el("tbodyPeriod")
};

/* =========================================================
   ESTADO
========================================================= */
const state = {
  supportsCache: [],
  summaryCache: null,
  isLoading: false
};

/* =========================================================
   UI
========================================================= */
function showToast(msg, type = "info", ms = 3000) {
  const t = elements.toast;
  if (!t) return;

  t.textContent = msg;
  t.style.borderLeftColor =
    type === "success" ? "var(--success)" :
    type === "error"   ? "var(--danger)"  :
    type === "warning" ? "var(--warning)" : "var(--primary)";

  t.classList.remove("hidden");
  setTimeout(() => t.classList.add("hidden"), ms);
}

function openModal(m) {
  if (m) m.classList.remove("hidden");
}

function closeModal(m) {
  if (m) m.classList.add("hidden");
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

/* =========================================================
   API
========================================================= */
async function apiGet(params) {
  const r = await fetch(`${API}?${new URLSearchParams(params)}`);
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
}
/* =========================================================
   FILTRO POR PERÍODO (AUTOMÁTICO)
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
    const dd = m[1], mm = m[2], yyyy = m[3];
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

  // validação básica
  if (!startYMD || !endYMD) {
    elements.tbodyPeriod.innerHTML =
      `<tr><td colspan="4">Datas inválidas</td></tr>`;
    return;
  }

  // garante ordem correta
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

  // erro da API
  if (!j || j.success === false) {
    const msg = (j && j.error) ? j.error : "Erro ao buscar";
    elements.tbodyPeriod.innerHTML =
      `<tr><td colspan="4">${escapeHtml(msg)}</td></tr>`;
    return;
  }

  const data = Array.isArray(j.data) ? j.data : [];

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
  const start = elements.fStart.value;
  const end   = elements.fEnd.value;

  if (!start || !end) return;

  loadPeriod(start, end);
}

/* ==========================
   EVENTOS (SEM BOTÃO)
========================== */
elements.fStart.addEventListener("change", autoFilterPeriod);
elements.fEnd.addEventListener("change", autoFilterPeriod);

elements.fStart.addEventListener("input", autoFilterPeriod);
elements.fEnd.addEventListener("input", autoFilterPeriod);

/* ==========================
   PERÍODO PADRÃO (AUTOLOAD)
========================== */
function setDefaultPeriod() {
  const d = new Date();
  const ini = new Date(d.getFullYear(), d.getMonth(), 1);
  const fim = new Date(d.getFullYear(), d.getMonth() + 1, 0);

  const ymd = x =>
    `${x.getFullYear()}-${String(x.getMonth() + 1).padStart(2, "0")}-${String(x.getDate()).padStart(2, "0")}`;

  elements.fStart.value = ymd(ini);
  elements.fEnd.value   = ymd(fim);

  // 🔥 carrega automaticamente ao abrir
  loadPeriod(elements.fStart.value, elements.fEnd.value);
}


/* =========================================================
   MODAIS
========================================================= */
elements.btnNovoSuporte.onclick = () => {
  elements.supId.value = 0;
  elements.supNome.value = "";
  elements.supAtivo.checked = true;
  elements.modalSuporteTitle.textContent = "Cadastrar suporte";
  openModal(elements.modalSuporte);
};

elements.btnSalvarSuporte.onclick = async () => {
  if (!elements.supNome.value.trim())
    return showToast("Informe o nome", "warning");

  await apiPost("support_save", {
    id: +elements.supId.value,
    nome: elements.supNome.value.trim(),
    ativo: elements.supAtivo.checked ? 1 : 0
  });

  closeModal(elements.modalSuporte);
  await loadSupports();
  showToast("Suporte salvo", "success");
};

function openPlantaoModal(sabado) {
  const d = new Date(sabado);
  const dom = new Date(d);
  dom.setDate(d.getDate() + 1);

  elements.plId.value = 0;
  elements.plSabado.value = sabado;
  elements.plDataCustom.value = sabado;
  elements.plRange.textContent = `${fmtBR(sabado)} → ${fmtBR(dom.toISOString().slice(0,10))}`;
  elements.plObs.value = "";
  elements.plSuporte.value = "";

  openModal(elements.modalPlantao);
}

elements.plDataCustom?.addEventListener("change", () => {
  const sab = elements.plDataCustom.value;
  if (!sab) return;

  const d = new Date(sab);
  d.setDate(d.getDate() + 1);

  elements.plSabado.value = sab;
  elements.plRange.textContent = `${fmtBR(sab)} → ${fmtBR(d.toISOString().slice(0,10))}`;
});

elements.btnEditarAtual.onclick = () =>
  openPlantaoModal(state.summaryCache?.current?.range?.sabado);

elements.btnEditarProximo.onclick = () =>
  openPlantaoModal(state.summaryCache?.next?.range?.sabado);

elements.btnCadastrarPlantao.onclick = () => {
  const d = new Date();
  const diff = (6 - d.getDay() + 7) % 7;
  d.setDate(d.getDate() + diff);
  openPlantaoModal(d.toISOString().slice(0,10));
};

elements.btnSalvarPlantao.onclick = async () => {
  const suporte = elements.plSuporte.value;
  const sabado  = elements.plSabado.value;

  // 🔒 VALIDAÇÃO LOCAL (antes de ir pro backend)
  const hoje = new Date();
  hoje.setHours(0,0,0,0);

  const sab = new Date(sabado);
  sab.setHours(0,0,0,0);

  if (sab < hoje) {
    return showToast(
      "❌ Não é permitido criar ou alterar plantão em data passada.",
      "warning",
      5000
    );
  }

  try {

    // Remover plantão (ninguém no plantão)
    if (!suporte) {
      await apiPost("plantao_delete", { sabado });
      closeModal(elements.modalPlantao);
      await boot(true);
      return showToast("Plantão removido", "success");
    }

    await apiPost("plantao_save", {
      sabado,
      suporte_id: +suporte,
      observacao: elements.plObs.value.trim()
    });

    closeModal(elements.modalPlantao);
    await boot(true);
    showToast("Plantão salvo com sucesso", "success");

  } catch (e) {
    // fallback (caso backend rejeite por outro motivo)
    showToast(e.message || "Erro ao salvar plantão", "error", 5000);
  }
};



/* =========================================================
   FECHAMENTO DE MODAIS
========================================================= */
document.addEventListener("click", (e) => {
  const btn = e.target.closest("[data-close]");
  if (btn) closeModal(document.getElementById(btn.dataset.close));
  if (e.target.classList.contains("modal-backdrop")) closeModal(e.target);
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    document.querySelectorAll(".modal-backdrop:not(.hidden)")
      .forEach(closeModal);
  }
});

/* =========================================================
   BOOT
========================================================= */
async function boot(skipToast = false) {
  try {
    await loadSupports();
    await loadSummary();
    setDefaultPeriod();
    await loadPeriod(elements.fStart.value, elements.fEnd.value);
    if (!skipToast) showToast("Painel carregado", "success");
  } catch (e) {
    showToast(e.message, "error");
  }
}

document.addEventListener("DOMContentLoaded", boot);
