/* ==========================================================
   bulk-actions.js — Ações em Massa (Bulk) (ATUALIZADO)
   RESPONSÁVEL POR:
   - Popular select de filas
   - Executar alteração de fila em lote
   - Mostrar progresso
   - Integrar confirmação e toast (com fallback)
========================================================== */

console.log("%c[BULK] bulk-actions.js carregado", "color:#a855f7;font-weight:bold;");

/* ==========================================================
   HELPERS (fallback para não quebrar)
========================================================== */
function bulkToastSucesso(msg) {
  if (typeof window.toastSucesso === "function") return window.toastSucesso(msg);
  console.log("[SUCESSO]", msg);
  alert(msg);
}

function bulkToastErro(msg) {
  if (typeof window.toastErro === "function") return window.toastErro(msg);
  console.error("[ERRO]", msg);
  alert(msg);
}

function bulkConfirmar(mensagem, callback) {
  if (typeof window.confirmarAcao === "function") return window.confirmarAcao(mensagem, callback);
  if (confirm(mensagem)) callback();
}

/* ==========================================================
   CACHE LOCAL
========================================================== */
let filasCache = [];

/* ==========================================================
   GARANTIR UI DE PROGRESSO (cria se não existir)
========================================================== */
function garantirProgressUI() {
  let bar = document.getElementById("bulkProgress");
  if (bar) return bar;

  // tenta colocar dentro da barra bulk (fica bem)
  const bulkBar = document.getElementById("bulkActions");

  bar = document.createElement("div");
  bar.id = "bulkProgress";
  bar.style.display = "none";
  bar.style.marginTop = "10px";

  bar.innerHTML = `
    <div class="progress-wrap" style="
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 999px;
      overflow: hidden;
      height: 10px;
      position: relative;
    ">
      <div class="progress-fill" style="
        width: 0%;
        height: 100%;
        background: rgba(30,144,255,.75);
        transition: width .2s ease;
      "></div>
    </div>
    <div class="progress-text" style="
      margin-top: 8px;
      font-size: .82rem;
      color: rgba(255,255,255,.75);
    ">Aplicando... 0/0</div>
  `;

  if (bulkBar) {
    const content = bulkBar.querySelector(".bulk-content") || bulkBar;
    content.appendChild(bar);
  } else {
    document.body.appendChild(bar);
  }

  return bar;
}

/* ==========================================================
   POPULAR SELECT DE FILAS (BULK)
========================================================== */
window.popularFilaBulk = function (filas = []) {
  const select = document.getElementById("fila_bulk");
  if (!select) return;

  filasCache = Array.isArray(filas) ? filas : [];

  select.innerHTML = `<option value="">Selecione uma fila</option>`;

  filasCache.forEach(f => {
    const opt = document.createElement("option");
    opt.value = f.id;
    opt.textContent = f.name;
    select.appendChild(opt);
  });
};

/* ==========================================================
   APLICAR FILA — SELECIONADOS
========================================================== */
window.aplicarFilaSelecionados = function () {
  const filaSelect = document.getElementById("fila_bulk");

  if (!filaSelect || !filaSelect.value) {
    bulkToastErro("Selecione uma fila");
    return;
  }

  const selecionados = [...(window.__selecionados || new Set())];

  if (!selecionados.length) {
    bulkToastErro("Nenhum operador selecionado");
    return;
  }

  bulkConfirmar(
    `Deseja alterar a fila de ${selecionados.length} operador(es)?`,
    () => executarBulkFila(selecionados, filaSelect)
  );
};

/* ==========================================================
   EXECUÇÃO DO BULK
========================================================== */
async function executarBulkFila(ids, filaSelect) {
  const filaId = filaSelect.value;
  const filaNome = filaSelect.options[filaSelect.selectedIndex]?.text || "";

  const total = ids.length;
  let sucesso = 0;
  let falha = 0;
  let ignorados = 0;

  // garante progresso
  garantirProgressUI();
  mostrarProgresso(0, total);

  // trava botões enquanto executa (evita clique duplo)
  setBulkDisabled(true);

  for (let i = 0; i < total; i++) {
    const id = ids[i];

    const op = (window.__operadoresCache || []).find(o => Number(o.id) === Number(id));

    if (!op || !op.evolux_agent_id) {
      ignorados++;
      mostrarProgresso(i + 1, total);
      continue;
    }

    const form = new FormData();
    form.append("operador_id", op.id);
    form.append("evolux_agent_id", op.evolux_agent_id);
    form.append("queue_id", filaId);
    form.append("queue_nome", filaNome);

    try {
      const r = await fetch("backend/alterar_fila.php", { method: "POST", body: form });
      const j = await r.json();

      if (j && j.success) sucesso++;
      else falha++;

    } catch (e) {
      falha++;
    }

    mostrarProgresso(i + 1, total);
  }

  esconderProgresso();
  setBulkDisabled(false);

  // limpa seleção (fallback)
  if (typeof window.limparSelecao === "function") {
    window.limparSelecao();
  } else {
    // fallback simples
    if (window.__selecionados?.clear) window.__selecionados.clear();
    document.querySelectorAll(".operator-row input[type=checkbox]").forEach(c => (c.checked = false));
    const count = document.getElementById("bulkCount");
    if (count) count.textContent = "0";
  }

  // recarrega painel
  window.carregar?.();

  // mensagens finais
  if (sucesso) bulkToastSucesso(`Fila alterada para ${sucesso} operador(es).`);
  if (falha) bulkToastErro(`${falha} operador(es) falharam ao atualizar.`);
  if (ignorados) {
    console.warn(`[BULK] ${ignorados} ignorados (sem evolux_agent_id ou operador não encontrado).`);
  }
}

/* ==========================================================
   PROGRESSO (UI)
========================================================== */
function mostrarProgresso(atual, total) {
  const bar = garantirProgressUI();
  if (!bar) return;

  bar.style.display = "block";

  const pct = total ? Math.round((atual / total) * 100) : 0;

  const txt = bar.querySelector(".progress-text");
  const fill = bar.querySelector(".progress-fill");

  if (txt) txt.textContent = `Aplicando... ${atual}/${total} (${pct}%)`;
  if (fill) fill.style.width = `${pct}%`;
}

function esconderProgresso() {
  const bar = document.getElementById("bulkProgress");
  if (bar) bar.style.display = "none";
}

/* ==========================================================
   UX: desabilitar botões durante execução
========================================================== */
function setBulkDisabled(disabled) {
  const bulkBar = document.getElementById("bulkActions");
  if (!bulkBar) return;

  bulkBar.querySelectorAll("button, select").forEach(el => {
    el.disabled = !!disabled;
    el.style.opacity = disabled ? "0.7" : "";
    el.style.pointerEvents = disabled ? "none" : "";
  });
}
