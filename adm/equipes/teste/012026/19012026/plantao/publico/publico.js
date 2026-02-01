/* =========================================================
   PLANTÃO – MODO PÚBLICO (VISUAL ONLY)
   Autor: Anderson de Souza
   Função: exibir dados SEM edição
========================================================= */

/* ==========================
   ELEMENTOS
========================== */
const elements = {
  cardPrev:      document.getElementById("cardPrev"),
  cardCurrent:   document.getElementById("cardCurrent"),
  cardNext:      document.getElementById("cardNext"),
  tbodyPeriod:   document.getElementById("tbodyPeriod"),
  fStart:        document.getElementById("fStart"),
  fEnd:          document.getElementById("fEnd")
};

/* ==========================
   HELPERS
========================== */
function fmtBR(d) {
  if (!d) return "—";
  const [y, m, day] = d.split("-");
  return `${day}/${m}/${y}`;
}

function escapeHtml(str) {
  return String(str || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}

/* ==========================
   API GET
========================== */
async function apiGet(params) {
  const q = new URLSearchParams(params).toString();
  const r = await fetch(`../backend/plantoes_api.php?${q}`);
  if (!r.ok) throw new Error("Erro HTTP");
  return r.json();
}

/* ==========================
   PERÍODO FIXO – MÊS ATUAL
========================== */
function setFixedMonthPeriod() {
  const now = new Date();

  const start = new Date(now.getFullYear(), now.getMonth(), 1);
  const end   = new Date(now.getFullYear(), now.getMonth() + 1, 0);

  const ymd = d =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;

  const ini = ymd(start);
  const fim = ymd(end);

  // Preenche os campos APENAS PARA VISUAL
  if (elements.fStart) {
    elements.fStart.value = ini;
    elements.fStart.disabled = true;
  }

  if (elements.fEnd) {
    elements.fEnd.value = fim;
    elements.fEnd.disabled = true;
  }

  return { ini, fim };
}

/* ==========================
   RESUMO (3 CARDS)
========================== */
async function loadSummary() {
  const j = await apiGet({ action: "summary" });

  function renderCard(el, data) {
    if (!el) return;

    if (!data || !data.plantao) {
      el.innerHTML = `<div class="empty">Sem plantão</div>`;
      return;
    }

    el.innerHTML = `
      <div class="plantao-info">
        <strong>${escapeHtml(data.plantao.suporte_nome)}</strong>
        <div class="dates">
          ${fmtBR(data.range.sabado)} • ${fmtBR(data.range.domingo)}
        </div>
        <div class="obs">
          ${escapeHtml(data.plantao.observacao || "—")}
        </div>
      </div>
    `;
  }

  renderCard(elements.cardPrev,    j.weekends.prev);
  renderCard(elements.cardCurrent, j.weekends.current);
  renderCard(elements.cardNext,    j.weekends.next);
}

/* ==========================
   HISTÓRICO – FIXO NO MÊS
========================== */
async function loadMonthHistory() {
  const { ini, fim } = setFixedMonthPeriod();

  elements.tbodyPeriod.innerHTML =
    `<tr><td colspan="4">Carregando...</td></tr>`;

  const j = await apiGet({
    action: "period",
    start: ini,
    end: fim
  });

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
    : `<tr><td colspan="4">Nenhum registro neste mês</td></tr>`;
}

/* ==========================
   BOOT
========================== */
async function boot() {
  try {
    await loadSummary();
    await loadMonthHistory();
  } catch (e) {
    console.error(e);
    elements.tbodyPeriod.innerHTML =
      `<tr><td colspan="4">Erro ao carregar dados</td></tr>`;
  }
}

boot();
