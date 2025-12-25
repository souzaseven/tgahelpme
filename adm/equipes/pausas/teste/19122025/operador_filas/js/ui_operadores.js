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
window.__selecionados = new Set();

/* ==========================================================
   HELPERS (fallback para não quebrar)
========================================================== */
function uiToastSucesso(msg) {
  if (typeof window.toastSucesso === "function") return window.toastSucesso(msg);
  console.log("[SUCESSO]", msg);
  alert(msg);
}
function uiToastErro(msg) {
  if (typeof window.toastErro === "function") return window.toastErro(msg);
  console.error("[ERRO]", msg);
  alert(msg);
}
function uiConfirmar(mensagem, callback) {
  if (typeof window.confirmarAcao === "function") return window.confirmarAcao(mensagem, callback);
  if (confirm(mensagem)) callback();
}

/* ==========================================================
   RESUMO POR EQUIPE (no header)
========================================================== */
function resumoEquipe(lider, lista) {
  const daEquipe = lista.filter(op => (op.lider || "Sem Líder") === lider);
  const total = daEquipe.length;

  const suporte = daEquipe.filter(op =>
    String(op.fila || "").toLowerCase().includes("suporte matriz")
  ).length;

  const chat = daEquipe.filter(op =>
    String(op.fila || "").toLowerCase().includes("chat/whats")
  ).length;

  return { total, suporte, chat };
}

/* ==========================================================
   RENDER — AGRUPADO POR LÍDER
========================================================== */
export function renderizarOperadores(lista = [], filas = []) {
  const container = document.getElementById("listaOperadores");
  if (!container) return;

  container.innerHTML = "";

  const ops = Array.isArray(lista) ? lista : [];
  const filasList = Array.isArray(filas) ? filas : [];

  // normaliza link e ids
  window.__operadoresCache = ops.map(o => ({
    ...o,
    id: Number(o.id) || 0,
    evolux_agent_id: Number(o.evolux_agent_id) || 0,
    lider: o.lider || "Sem Líder",
    nome: o.nome || "Operador",
    fila: o.fila || "Sem fila",
    link: String(o.link || "").trim()
  }));

  window.__selecionados.clear();
  atualizarBarraBulk();

  // agrupar por líder
  const grupos = {};
  window.__operadoresCache.forEach(op => {
    if (!grupos[op.lider]) grupos[op.lider] = [];
    grupos[op.lider].push(op);
  });

  Object.entries(grupos).forEach(([lider, equipe]) => {
    const bloco = document.createElement("div");
    bloco.className = "team-block";
    bloco.setAttribute("data-lider", lider);

    const r = resumoEquipe(lider, window.__operadoresCache);

    bloco.innerHTML = `
      <div class="team-header" onclick="toggleEquipeHeader(this)">
        <div class="team-left">
          <span class="team-title">${escapeHtml(lider)}</span>
          <span class="team-meta">
            <span class="badge">Total: <b>${r.total}</b></span>
            <span class="badge">Matriz: <b>${r.suporte}</b></span>
            <span class="badge">Chat/Whats: <b>${r.chat}</b></span>
          </span>
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
        ${equipe.map(op => `
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
                </a>
              ` : ``}
            </div>
          </div>

          <div class="fila-editor hidden" id="fila-editor-${op.id}">
            <select id="fila-select-${op.id}">
              <option value="">Selecione a fila</option>
              ${filasList.map(f => `
                <option value="${escapeAttr(f.id)}">${escapeHtml(f.name)}</option>
              `).join("")}
            </select>

            <button class="btn btn-success btn-sm" onclick="salvarFila(${op.id}, ${op.evolux_agent_id || 0})">
              <i class="fas fa-save"></i> Salvar
            </button>
          </div>
        `).join("")}
      </div>
    `;

    container.appendChild(bloco);
  });

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
  if (icon) icon.className = expanded ? "fas fa-chevron-up" : "fas fa-chevron-down";
};

/* ==========================================================
   SELEÇÃO
========================================================== */
window.toggleSelecionado = function (id, checked) {
  if (!id) return;
  checked ? window.__selecionados.add(id) : window.__selecionados.delete(id);
  atualizarBarraBulk();
};

window.limparSelecao = function () {
  window.__selecionados.clear();
  document.querySelectorAll(".operator-row input[type=checkbox]").forEach(c => (c.checked = false));
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

  idsEquipe.forEach(id => window.__selecionados.add(id));

  idsEquipe.forEach(id => {
    const row = document.querySelector(`.operator-row[data-operador-id="${id}"]`);
    const cb = row?.querySelector(`input[type="checkbox"]`);
    if (cb) cb.checked = true;
  });

  atualizarBarraBulk();
  uiToastSucesso(`Equipe "${lider}" selecionada (${idsEquipe.length}).`);
};

/* ==========================================================
   BARRA BULK
========================================================== */
function atualizarBarraBulk() {
  const bar = document.getElementById("bulkActions");
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
  if (!select || !select.value) return uiToastErro("Selecione uma fila.");
  if (!agentId) return uiToastErro("Operador sem evolux_agent_id.");

  const queueId = select.value;
  const queueNome = select.options[select.selectedIndex]?.text || "";

  uiConfirmar("Confirmar alteração de fila deste operador?", async () => {
    try {
      const form = new FormData();
      form.append("operador_id", id);
      form.append("evolux_agent_id", agentId);
      form.append("queue_id", queueId);
      form.append("queue_nome", queueNome);

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
window.expandirTodasEquipes = function () {
  document.querySelectorAll(".team-block").forEach(b => {
    b.classList.add("expanded");
    const i = b.querySelector(".btn-toggle i");
    if (i) i.className = "fas fa-chevron-up";
  });
};

window.recolherTodasEquipes = function () {
  document.querySelectorAll(".team-block").forEach(b => {
    b.classList.remove("expanded");
    const i = b.querySelector(".btn-toggle i");
    if (i) i.className = "fas fa-chevron-down";
  });
};

/* ==========================================================
   RESUMO (topo)
========================================================== */
export function atualizarResumo(lista = [], filas = []) {
  const elOps = document.getElementById("totalOperadores");
  const elEq = document.getElementById("totalEquipes");
  const elFilas = document.getElementById("totalFilas");
  const elTotal = document.getElementById("totalGeral");

  if (elOps) elOps.textContent = (Array.isArray(lista) ? lista.length : 0);
  if (elEq) elEq.textContent = new Set((Array.isArray(lista) ? lista : []).map(o => o.lider || "Sem Líder")).size;
  if (elFilas) elFilas.textContent = (Array.isArray(filas) ? filas.length : 0);
  if (elTotal) elTotal.textContent = (Array.isArray(lista) ? lista.length : 0);
}

/* ==========================================================
   SAFE ESCAPES
========================================================== */
function escapeHtml(str) {
  return String(str ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}
function escapeAttr(str) {
  return escapeHtml(str).replaceAll("`", "");
}
