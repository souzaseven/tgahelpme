/* ==========================================================
   ui_operadores.js — Render + Ações de Interface
========================================================== */

import { carregar, operadores } from "./app.js";

/* ==========================================================
   RENDER — AGRUPADO POR LÍDER
========================================================== */
export function renderizarOperadores(lista, filas) {
  const container = document.getElementById("listaOperadores");
  container.innerHTML = "";

  const grupos = {};

  lista.forEach(op => {
    if (!grupos[op.lider]) grupos[op.lider] = [];
    grupos[op.lider].push(op);
  });

  Object.entries(grupos).forEach(([lider, ops]) => {
    const bloco = document.createElement("div");
    bloco.className = "team-block"; // ❌ NÃO começa expanded

    bloco.innerHTML = `
      <div class="team-header" onclick="toggleEquipeHeader(this)">
        <span class="team-title">${lider}</span>

        <div class="team-actions" onclick="event.stopPropagation()">
          <button class="btn btn-secondary btn-sm"
            onclick="abrirTodos('${lider}')">
            <i class="fas fa-external-link-alt"></i>
            Abrir Todos
          </button>

          <button class="btn-toggle">
            <i class="fas fa-chevron-down"></i>
          </button>
        </div>
      </div>

      <div class="team-body">
        ${ops.map(op => `
          <div class="operator-row">
            <input type="checkbox">

            <div class="operator-info">
              <div class="operator-name">
                ${op.nome}
                <span class="op-ids">
                  #${op.id} · Evolux ${op.evolux_agent_id || "-"}
                </span>
              </div>
              <div class="operator-fila">
                ${op.fila || "Sem fila"}
              </div>
            </div>

            <div class="operator-actions">
              <button class="btn-alterar"
                onclick="toggleFilaEditor(${op.id})"
                title="Alterar fila">
                <i class="fas fa-random"></i>
              </button>

              ${op.link ? `
                <a href="${op.link}" target="_blank" class="btn-acessar">
                  <i class="fas fa-edit"></i> Editar
                </a>
              ` : ``}
            </div>
          </div>

          <div class="fila-editor hidden" id="fila-editor-${op.id}">
            <select id="fila-select-${op.id}">
              <option value="">Selecione a fila</option>
              ${filas.map(f => `
                <option value="${f.id}">${f.name}</option>
              `).join("")}
            </select>

            <button class="btn btn-success btn-sm"
              onclick="salvarFila(${op.id}, ${op.evolux_agent_id})">
              <i class="fas fa-save"></i> Salvar
            </button>
          </div>
        `).join("")}
      </div>
    `;

    container.appendChild(bloco);
  });

  console.log("📦 Operadores renderizados (equipes fechadas)");
}

/* ==========================================================
   ACCORDION — HEADER CONTROLA .expanded
========================================================== */
window.toggleEquipeHeader = function (header) {
  const block = header.closest(".team-block");
  if (!block) return;

  const expanded = block.classList.toggle("expanded");

  const icon = header.querySelector(".btn-toggle i");
  if (icon) {
    icon.className = expanded
      ? "fas fa-chevron-up"
      : "fas fa-chevron-down";
  }

  console.log(
    "🔁 Toggle equipe:",
    header.querySelector(".team-title")?.textContent,
    "expanded:",
    expanded
  );
};

/* ==========================================================
   ABRIR TODOS OS LINKS DE EDIÇÃO DO LÍDER
========================================================== */
window.abrirTodos = function (lider) {
  operadores
    .filter(op => op.lider === lider && op.link)
    .forEach(op => window.open(op.link, "_blank"));
};

/* ==========================================================
   TOGGLE DO EDITOR DE FILA
========================================================== */
window.toggleFilaEditor = function (id) {
  const editor = document.getElementById(`fila-editor-${id}`);
  if (!editor) return;

  editor.classList.toggle("hidden");
};

/* ==========================================================
   SALVAR FILA — EVOLUX + BANCO LOCAL
========================================================== */
window.salvarFila = async function (id, agentId) {
  const select = document.getElementById(`fila-select-${id}`);
  const filaId = select.value;
  const filaNome = select.options[select.selectedIndex]?.text;

  if (!filaId) {
    alert("Selecione uma fila");
    return;
  }

  const form = new FormData();
  form.append("id", id);
  form.append("evolux_agent_id", agentId);
  form.append("evolux_queue_id", filaId);
  form.append("fila", filaNome);

  const r = await fetch("backend/alterar_fila.php", {
    method: "POST",
    body: form
  });

  const j = await r.json();

  if (j.success) {
    carregar();
  } else {
    alert(j.erro || "Erro ao alterar fila");
  }
};

/* ==========================================================
   EXPANDIR / RECOLHER TODAS
========================================================== */
window.expandirTodasEquipes = function () {
  document.querySelectorAll(".team-block").forEach(block => {
    block.classList.add("expanded");

    const icon = block.querySelector(".btn-toggle i");
    if (icon) icon.className = "fas fa-chevron-up";
  });

  console.log("🔓 Todas as equipes EXPANDIDAS");
};

window.recolherTodasEquipes = function () {
  document.querySelectorAll(".team-block").forEach(block => {
    block.classList.remove("expanded");

    const icon = block.querySelector(".btn-toggle i");
    if (icon) icon.className = "fas fa-chevron-down";
  });

  console.log("🔒 Todas as equipes RECOLHIDAS");
};
