/* =========================================================
   PLANTÃO – MODO PÚBLICO (VISUAL ONLY)
   Autor: Anderson de Souza
========================================================= */

const elements = {
  cardPrev:    document.getElementById("cardPrev"),
  cardCurrent: document.getElementById("cardCurrent"),
  cardNext:    document.getElementById("cardNext"),
  tbodyPeriod: document.getElementById("tbodyPeriod"),
  fStart:      document.getElementById("fStart"),
  fEnd:        document.getElementById("fEnd")
};

let currentSabado = null;

/* =========================================================
   HELPERS
========================================================= */
function fmtBR(d) {
  if (!d) return "—";
  const [y, m, day] = d.split("-");
  return `${day}/${m}/${y}`;
}

function escapeHtml(str) {
  return String(str ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

const AVATAR_COLORS = [
  "#3b82f6", "#8b5cf6", "#10b981",
  "#f59e0b", "#06b6d4", "#ec4899", "#f97316"
];

function avatarColor(nome) {
  let hash = 0;
  for (const c of String(nome)) hash = (hash * 31 + c.charCodeAt(0)) & 0xffffffff;
  return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
}

function getInitials(nome) {
  const parts = String(nome || "").trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return "?";
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function isNowOnDuty(sabado) {
  if (!sabado) return false;
  const now = new Date();
  const hm  = now.getHours() * 60 + now.getMinutes();

  const [y, mo, d] = sabado.split("-").map(Number);
  const sat = new Date(y, mo - 1, d);       // datas locais reais — sem bug de fim de mês
  const sun = new Date(y, mo - 1, d + 1);

  const sameDay = (a, b) =>
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate();

  if (sameDay(now, sat)) return hm >= 13 * 60 && hm < 18 * 60;          // Sáb 13h–18h
  if (sameDay(now, sun)) return hm >= 7 * 60 + 30 && hm < 11 * 60 + 30; // Dom 07h30–11h30
  return false;
}

/* =========================================================
   API GET
========================================================= */
async function apiGet(params) {
  const q = new URLSearchParams(params).toString();
  const r = await fetch(`../backend/plantoes_api.php?${q}`);
  if (!r.ok) throw new Error("Erro HTTP");
  return r.json();
}

/* =========================================================
   PERÍODO FIXO – MÊS ATUAL
========================================================= */
function setFixedMonthPeriod() {
  const now   = new Date();
  const start = new Date(now.getFullYear(), now.getMonth(), 1);
  const end   = new Date(now.getFullYear(), now.getMonth() + 1, 0);
  const ymd   = d =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;

  const ini = ymd(start);
  const fim = ymd(end);

  if (elements.fStart) { elements.fStart.value = ini; elements.fStart.disabled = true; }
  if (elements.fEnd)   { elements.fEnd.value   = fim; elements.fEnd.disabled   = true; }

  return { ini, fim };
}

/* =========================================================
   RESUMO (3 CARDS)
========================================================= */
function renderCard(el, data, isCurrent = false) {
  if (!el) return;

  if (!data?.plantao?.suporte_nome) {
    el.innerHTML = `
      <div class="pub-empty">
        <span class="pub-empty-icon">📅</span>
        <span class="pub-empty-text">Sem plantão definido</span>
      </div>
    `;
    return;
  }

  const nome   = data.plantao.suporte_nome;
  const obs    = data.plantao.observacao || null;
  const onDuty = isCurrent && isNowOnDuty(data.range?.sabado);
  const color  = avatarColor(nome);
  const inits  = getInitials(nome);

  el.innerHTML = `
    <div class="pub-card">
      <div class="pub-avatar" style="background:${color}20;border-color:${color}40;color:${color}">
        ${inits}
      </div>
      <div class="pub-details">
        <div class="pub-name">${escapeHtml(nome)}</div>
        <div class="pub-dates">${fmtBR(data.range?.sabado)} • ${fmtBR(data.range?.domingo)}</div>
        ${obs ? `<div class="pub-obs">${escapeHtml(obs)}</div>` : ""}
        ${onDuty ? `
          <div class="pub-live-badge">
            <span class="pub-live-dot"></span>
            Plantão ativo agora
          </div>` : ""}
      </div>
    </div>
  `;
}

async function loadSummary() {
  const j = await apiGet({ action: "summary" });
  currentSabado = j.weekends?.current?.range?.sabado || null;

  renderCard(elements.cardPrev,    j.weekends.prev,     false);
  renderCard(elements.cardCurrent, j.weekends.current,  true);
  renderCard(elements.cardNext,    j.weekends.next,     false);
}

/* =========================================================
   HISTÓRICO – FIXO NO MÊS
========================================================= */
async function loadMonthHistory() {
  const { ini, fim } = setFixedMonthPeriod();

  elements.tbodyPeriod.innerHTML = `<tr><td colspan="4">Carregando...</td></tr>`;

  const j    = await apiGet({ action: "period", start: ini, end: fim });
  const data = Array.isArray(j.data) ? j.data : [];

  if (!data.length) {
    elements.tbodyPeriod.innerHTML = `<tr><td colspan="4">Nenhum registro neste mês</td></tr>`;
    return;
  }

  elements.tbodyPeriod.innerHTML = data.map(r => {
    const isCurr   = r.sabado === currentSabado;
    const hasNome  = !!r.suporte_nome;
    const nome     = hasNome ? r.suporte_nome : null;
    const color    = nome ? avatarColor(nome) : null;
    const inits    = nome ? getInitials(nome) : null;

    return `
      <tr class="${isCurr ? "row-current" : ""}">
        <td>${fmtBR(r.sabado)}</td>
        <td>${fmtBR(r.domingo)}</td>
        <td>
          ${nome
            ? `<div class="cell-suporte">
                <span class="cell-avatar" style="background:${color}20;border-color:${color}40;color:${color}">${inits}</span>
                ${escapeHtml(nome)}
              </div>`
            : `<span class="cell-empty">Sem plantão</span>`}
        </td>
        <td>${escapeHtml(r.observacao || "—")}</td>
      </tr>
    `;
  }).join("");
}

/* =========================================================
   BOOT
========================================================= */
async function boot() {
  try {
    await loadSummary();
    await loadMonthHistory();
  } catch (e) {
    console.error(e);
    elements.tbodyPeriod.innerHTML = `<tr><td colspan="4">Erro ao carregar dados</td></tr>`;
  }
}

boot();
