/* ==========================================================
   ui_operadores.js — Render + Ações de Interface (OFICIAL)
   - Render por líder
   - Seleção individual / por equipe
   - Abrir links (selecionados / equipe)
   - Alteração de fila individual
   - Prepara DOM com data-attributes (bulk compatível)
========================================================== */

console.log("%c[UI_OPERADORES] carregado", "color:#22c55e;font-weight:bold;");

/* ==========================================================
   ESTADO GLOBAL DA UI
========================================================== */
window.__operadoresCache = [];
window.__selecionados    = new Set();

/* ==========================================================
   HELPERS (fallback para não quebrar)
========================================================== */
const uiToastSucesso = msg =>
  typeof window.toastSucesso === "function"
    ? window.toastSucesso(msg)
    : (console.log("[SUCESSO]", msg), alert(msg));

const uiToastErro = msg =>
  typeof window.toastErro === "function"
    ? window.toastErro(msg)
    : (console.error("[ERRO]", msg), alert(msg));

const uiConfirmar = (mensagem, callback) =>
  typeof window.confirmarAcao === "function"
    ? window.confirmarAcao(mensagem, callback)
    : (confirm(mensagem) && callback());

/* ==========================================================
   SAFE ESCAPES
========================================================== */
const escapeHtml = str =>
  String(str ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");

const escapeAttr = str => escapeHtml(str).replaceAll("`", "");

/* ==========================================================
   NORMALIZAR OPERADOR
========================================================== */
const normalizarOp = o => ({
  ...o,
  id:              Number(o.id) || 0,
  evolux_agent_id: Number(o.evolux_agent_id) || 0,
  lider:           o.lider || "Sem Líder",
  nome:            o.nome  || "Operador",
  fila:            o.fila  || "Sem fila",
  link:            String(o.link || "").trim(),
});

/* ==========================================================
   RESUMO POR EQUIPE (no header)
========================================================== */
function resumoEquipe(lider, lista) {
  const daEquipe = lista.filter(op => op.lider === lider);
  const inclui   = termo => op => String(op.fila || "").toLowerCase().includes(termo);
  return {
    total:   daEquipe.length,
    suporte: daEquipe.filter(inclui("suporte matriz")).length,
    chat:    daEquipe.filter(inclui("chat/whats")).length,
  };
}

/* ==========================================================
   TEMPLATES HTML
========================================================== */
function templateFilaOptions(filasList) {
  return filasList
    .map(f => `<option value="${escapeAttr(f.id)}">${escapeHtml(f.name)}</option>`)
    .join("");
}

function templateOperadorRow(op, filasList) {
  return `
    <div class="operator-row"
         data-operador-id="${op.id}"
         data-evolux-id="${op.evolux_agent_id}"
         data-link="${escapeAttr(op.link)}">

      <input type="checkbox" onchange="toggleSelecionado(${op.id}, this.checked)">

      <div class="operator-info">
        <div class="operator-name">
          ${escapeHtml(op.nome)}
          <span class="op-ids">#${op.id} · Evolux ${op.evolux_agent_id || "-"}</span>
        </div>
        <div class="operator-fila">${escapeHtml(op.fila)}</div>
      </div>

      <div class="operator-actions">
        <button class="btn-alterar" title="Alterar fila" onclick="toggleFilaEditor(${op.id})">
          <i class="fas fa-random"></i>
        </button>
        ${op.link ? `
          <a href="${escapeAttr(op.link)}" target="_blank" class="btn-acessar" title="Editar operador">
            <i class="fas fa-edit"></i> Editar
          </a>` : ""}
      </div>
    </div>

    <div class="fila-editor hidden" id="fila-editor-${op.id}">
      <select id="fila-select-${op.id}">
        <option value="">Selecione a fila</option>
        ${templateFilaOptions(filasList)}
      </select>
      <button class="btn btn-success btn-sm" onclick="salvarFila(${op.id}, ${op.evolux_agent_id || 0})">
        <i class="fas fa-save"></i> Salvar
      </button>
    </div>`;
}

function templateTeamBlock(lider, equipe, r, filasList) {
  return `
    <div class="team-header" onclick="toggleEquipeHeader(this)">
      <div class="team-left">
        <div class="team-title">
          👑 ${escapeHtml(lider)}
        </div>
        <div class="team-metrics">
          <div class="metric-item total">
            <span class="metric-number">${r.total}</span>
            <span class="metric-label">Operadores ]</span>
          </div>
          <div class="metric-divider"></div>
          <div class="metric-item matriz">
            <span class="metric-number">[ ${r.suporte}</span>
            <span class="metric-label">Suporte Matriz ] - [</span>
          </div>
          <div class="metric-divider"></div>
          <div class="metric-item chat">
            <span class="metric-number">${r.chat}</span>
            <span class="metric-label" style="color:#25D366;">Chat/Whats</span>
          </div>
        </div>
      </div>
      <div class="team-actions" onclick="event.stopPropagation()">
        <button class="btn btn-secondary btn-sm" onclick="selecionarEquipeByHeader(this)">
          <i class="fas fa-check-double"></i> Selecionar Equipe
        </button>
        <button class="btn btn-secondary btn-sm" onclick="abrirTodosByHeader(this)">
          <i class="fas fa-external-link-alt"></i> Editar Todos
        </button>
        <button class="btn-toggle" title="Abrir/Recolher">
          <i class="fas fa-chevron-down"></i>
        </button>
      </div>
    </div>
    <div class="team-body">
      ${equipe.map(op => templateOperadorRow(op, filasList)).join("")}
    </div>`;
}

/* ==========================================================
   RENDER — AGRUPADO POR LÍDER
========================================================== */
export function renderizarOperadores(lista = [], filas = []) {
  const container = document.getElementById("listaOperadores");
  if (!container) return;

  const filasList = Array.isArray(filas) ? filas : [];

  window.__operadoresCache = (Array.isArray(lista) ? lista : []).map(normalizarOp);
  window.__selecionados.clear();
  atualizarBarraBulk();

  // agrupar por líder
  const grupos = window.__operadoresCache.reduce((acc, op) => {
    (acc[op.lider] ??= []).push(op);
    return acc;
  }, {});

  const fragment = document.createDocumentFragment();

  Object.entries(grupos).forEach(([lider, equipe]) => {
    const r     = resumoEquipe(lider, window.__operadoresCache);
    const bloco = document.createElement("div");
    bloco.className = "team-block";
    bloco.setAttribute("data-lider", lider);
    bloco.innerHTML = templateTeamBlock(lider, equipe, r, filasList);
    fragment.appendChild(bloco);
  });

  container.innerHTML = "";
  container.appendChild(fragment);

  console.log("📦 Operadores renderizados:", window.__operadoresCache.length);
}

/* ==========================================================
   ACCORDION (ABRIR / FECHAR EQUIPE)
========================================================== */
window.toggleEquipeHeader = function (header) {
  const block = header.closest(".team-block");
  if (!block) return;
  const expanded = block.classList.toggle("expanded");
  const icon = header.querySelector(".btn-toggle i");
  if (icon) icon.className = `fas fa-chevron-${expanded ? "up" : "down"}`;
};

/* ==========================================================
   SELEÇÃO
========================================================== */
window.toggleSelecionado = function (id, checked) {
  if (!id) return;
  window.__selecionados[checked ? "add" : "delete"](id);
  atualizarBarraBulk();
};

window.limparSelecao = function () {
  window.__selecionados.clear();
  document.querySelectorAll(".operator-row input[type=checkbox]")
    .forEach(c => (c.checked = false));
  atualizarBarraBulk();
};

/* ==========================================================
   SELECIONAR EQUIPE (pega o líder via DOM, sem bug de escape)
========================================================== */
window.selecionarEquipeByHeader = function (btn) {
  const block = btn.closest(".team-block");
  const lider = block?.getAttribute("data-lider") || "Sem Líder";

  const idsEquipe = (window.__operadoresCache || [])
    .filter(op => op.lider === lider)
    .map(op => op.id);

  idsEquipe.forEach(id => {
    window.__selecionados.add(id);
    const row = document.querySelector(`.operator-row[data-operador-id="${id}"]`);
    const cb  = row?.querySelector(`input[type="checkbox"]`);
    if (cb) cb.checked = true;
  });

  atualizarBarraBulk();
  uiToastSucesso(`Equipe "${lider}" selecionada (${idsEquipe.length}).`);
};

/* ==========================================================
   BARRA BULK
========================================================== */
function atualizarBarraBulk() {
  const bar   = document.getElementById("bulkActions");
  const count = document.getElementById("bulkCount");
  if (!bar || !count) return;
  const total = window.__selecionados.size;
  count.textContent = total;
  bar.style.display = total ? "block" : "none";
}

/* ==========================================================
   EDITAR LINKS (selecionados / equipe)
========================================================== */
window.editarSelecionados = function () {
  const ids = [...window.__selecionados];
  if (!ids.length) return uiToastErro("Nenhum operador selecionado.");

  const ops = (window.__operadoresCache || [])
    .filter(op => ids.includes(op.id) && op.link);

  if (!ops.length) return uiToastErro("Nenhum link disponível nos selecionados.");

  uiConfirmar(`Deseja abrir ${ops.length} links de edição em novas abas?`, () => {
    ops.forEach(op => window.open(op.link, "_blank"));
    uiToastSucesso(`Abrindo ${ops.length} links...`);
  });
};

window.abrirTodosByHeader = function (btn) {
  const block = btn.closest(".team-block");
  const lider = block?.getAttribute("data-lider") || "Sem Líder";

  const ops = (window.__operadoresCache || [])
    .filter(op => op.lider === lider && op.link);

  if (!ops.length) return uiToastErro(`Nenhum link para a equipe "${lider}".`);

  uiConfirmar(`Deseja abrir ${ops.length} operadores da equipe "${lider}"?`, () => {
    ops.forEach(op => window.open(op.link, "_blank"));
    uiToastSucesso(`Abrindo ${ops.length} links da equipe "${lider}"...`);
  });
};

/* ==========================================================
   FILA — INDIVIDUAL
========================================================== */
window.toggleFilaEditor = function (id) {
  document.getElementById(`fila-editor-${id}`)?.classList.toggle("hidden");
};

window.salvarFila = async function (id, agentId) {
  const select = document.getElementById(`fila-select-${id}`);
  if (!select?.value) return uiToastErro("Selecione uma fila.");
  if (!agentId)       return uiToastErro("Operador sem evolux_agent_id.");

  const queueId   = select.value;
  const queueNome = select.options[select.selectedIndex]?.text || "";

  uiConfirmar("Confirmar alteração de fila deste operador?", async () => {
    try {
      const form = new FormData();
      form.append("operador_id",      id);
      form.append("evolux_agent_id",  agentId);
      form.append("queue_id",         queueId);
      form.append("queue_nome",       queueNome);

      const r = await fetch("backend/alterar_fila.php", { method: "POST", body: form });
      const j = await r.json();

      if (!j.success) return uiToastErro(j.erro || "Erro ao alterar fila.");

      uiToastSucesso("Fila alterada com sucesso!");
      window.carregar?.();
    } catch (e) {
      console.error("salvarFila erro:", e);
      uiToastErro("Erro de comunicação com o servidor.");
    }
  });
};

/* ==========================================================
   EXPANDIR / RECOLHER TODAS
========================================================== */
const _setTodasEquipes = expanded => {
  document.querySelectorAll(".team-block").forEach(b => {
    b.classList.toggle("expanded", expanded);
    const icon = b.querySelector(".btn-toggle i");
    if (icon) icon.className = `fas fa-chevron-${expanded ? "up" : "down"}`;
  });
};

window.expandirTodasEquipes = () => _setTodasEquipes(true);
window.recolherTodasEquipes = () => _setTodasEquipes(false);

/* ==========================================================
   RESUMO (topo)
========================================================== */
export function atualizarResumo(lista = [], filas = []) {
  const arr  = Array.isArray(lista) ? lista : [];
  const defs = {
    totalOperadores: arr.length,
    totalEquipes:    new Set(arr.map(o => o.lider || "Sem Líder")).size,
    totalFilas:      Array.isArray(filas) ? filas.length : 0,
    totalGeral:      arr.length,
  };
  Object.entries(defs).forEach(([id, val]) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  });
}