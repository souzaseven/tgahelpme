/* ===================================================
   UTIL
=================================================== */
const $ = sel => document.querySelector(sel);

function apiFetch(url, options = {}) {
  return fetch(url, {
    credentials: "same-origin",
    headers: { "Content-Type": "application/json" },
    ...options
  });
}

function formatDate(dt) {
  if (!dt) return "-";
  try {
    return new Date(dt.replace(" ", "T")).toLocaleString("pt-BR");
  } catch {
    return dt;
  }
}

/* ===================================================
   TOAST
=================================================== */
let toastId = 1;
function pushToast(msg, type = "ok") {
  const toast = $("#toast");
  const div = document.createElement("div");

  div.className = `toastItem ${type === "err" ? "err" : "ok"}`;
  div.innerHTML = `
    <div style="display:flex;justify-content:space-between;gap:10">
      <div>${msg}</div>
      <button class="btn ghost">✕</button>
    </div>
  `;

  div.querySelector("button").onclick = () => div.remove();
  toast.prepend(div);
  setTimeout(() => div.remove(), 4200);
}

/* ===================================================
   ESTADO GLOBAL
=================================================== */
const state = {
  q: "",
  versao: "",
  versaoLivre: "",
  firebird: "",
  firebirdLivre: "",
  page: 1,
  perPage: 20,
  total: 0,
  items: [],
  edit: null
};

/* ===================================================
   HELPERS DE FILTRO (ANTI BUG)
=================================================== */
function getFirebirdFinal() {
  return state.firebird === "outro"
    ? state.firebirdLivre.trim()
    : state.firebird;
}

function getVersaoFinal() {
  return state.versao === "outro"
    ? state.versaoLivre.trim()
    : state.versao;
}

/* ===================================================
   FETCH CLIENTES
=================================================== */
async function fetchClientes() {
  const params = new URLSearchParams({
    q: state.q,
    versao: getVersaoFinal(),
    firebird: getFirebirdFinal(),
    page: state.page,
    perPage: state.perPage
  });

  try {
    $("#tbody").innerHTML = `<tr><td colspan="9">Carregando...</td></tr>`;
    const r = await apiFetch(`backend/clientes.php?${params}`);
    const j = await r.json();

    if (!j.success) {
      pushToast(j.error || "Erro ao listar.", "err");
      return;
    }

    state.items = j.items;
    state.total = j.total;

    renderCards();
    renderTable();
    renderPagination();

  } catch {
    pushToast("Erro ao consultar.", "err");
  }
}

/* ===================================================
   CARDS
=================================================== */
function renderCards() {
  let fb25 = 0, fb50 = 0, migrados = 0;

  state.items.forEach(c => {
    if (String(c.firebird).startsWith("2")) fb25++;
    if (String(c.firebird).startsWith("5")) fb50++;
    if (String(c.info_adicional).toUpperCase() === "MIGRADO") migrados++;
  });

  $("#cardTotal").innerText = state.total;
  $("#cardFb25").innerText = fb25;
  $("#cardFb50").innerText = fb50;
  $("#cardMigrados").innerText = migrados;
}

/* ===================================================
   TABELA
=================================================== */
function renderTable() {
  const tbody = $("#tbody");
  tbody.innerHTML = "";

  if (!state.items.length) {
    tbody.innerHTML = `<tr><td colspan="9">Nenhum registro encontrado.</td></tr>`;
    return;
  }

  state.items.forEach(row => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${row.codigotga}</td>
      <td>${row.nome_empresa}</td>
      <td>${row.cnpj}</td>
      <td>${row.versao || "-"}</td>
      <td>${row.firebird || "-"}</td>
      <td>${row.info_adicional || "-"}</td>
      <td>${row.qntusuarios}</td>
      <td>${formatDate(row.updated_at)}</td>
      <td>
        <button class="btn">Editar</button>
        <button class="btn danger">Excluir</button>
      </td>
    `;
    tr.querySelector(".btn").onclick = () => openEdit(row);
    tr.querySelector(".danger").onclick = () => removeRow(row);
    tbody.appendChild(tr);
  });
}

/* ===================================================
   PAGINAÇÃO
=================================================== */
function renderPagination() {
  $("#pageInfo").innerText =
    `Página ${state.page} de ${Math.max(1, Math.ceil(state.total / state.perPage))}`;
}

/* ===================================================
   MODAL / CRUD
=================================================== */
function openNew() {
  state.edit = {
    id: 0,
    codigotga: "",
    nome_empresa: "",
    cnpj: "",
    versao: "",
    firebird: "",
    info_adicional: "",
    qntusuarios: 0,
    senhapadrao: "tga@1234"
  };
  openModal();
}

function openEdit(row) {
  state.edit = { ...row };
  openModal();
}

function openModal() {
  const e = state.edit;
  const m = $("#modal");

  m.innerHTML = `
    <div class="modalBack">
      <div class="modal">
        <div class="modalHeader">
          <h2>${e.id ? "Editar" : "Novo"} cliente</h2>
          <button class="btn" id="closeModal">Fechar</button>
        </div>

        <div class="modalBody grid">
          <input class="input field" id="m_cod" placeholder="Código TGA" value="${e.codigotga}">
          <input class="input field" id="m_emp" placeholder="Empresa" value="${e.nome_empresa}">
          <input class="input field" id="m_cnpj" placeholder="CNPJ" value="${e.cnpj}">
          <input class="input field" id="m_ver" placeholder="Versão" value="${e.versao || ""}">
          <input class="input field" id="m_fb" placeholder="Firebird" value="${e.firebird || ""}">
          <input class="input field" id="m_usr" type="number" placeholder="Usuários" value="${e.qntusuarios}">

          <select class="select" id="infoSel">
            <option value="outro">Info livre</option>
            <option value="MIGRADO">MIGRADO</option>
          </select>

          <input class="input field" id="infoTxt" placeholder="Info adicional">
        </div>

        <div class="actions">
          <button class="btn" id="cancel">Cancelar</button>
          <button class="btn primary" id="save">Salvar</button>
        </div>
      </div>
    </div>
  `;

  const firebirdInput = $("#m_fb");
  const infoSel = $("#infoSel");
  const infoTxt = $("#infoTxt");

  infoSel.value = e.info_adicional === "MIGRADO" ? "MIGRADO" : "outro";
  infoTxt.value = e.info_adicional !== "MIGRADO" ? e.info_adicional : "";

  function validarMigrado() {
    if (firebirdInput.value.trim() !== "5.0") {
      infoSel.value = "outro";
      infoSel.disabled = true;
      infoTxt.style.display = "block";
    } else {
      infoSel.disabled = false;
    }
  }

  validarMigrado();
  firebirdInput.oninput = validarMigrado;

  infoSel.onchange = () => {
    infoTxt.style.display = infoSel.value === "MIGRADO" ? "none" : "block";
  };

  $("#closeModal").onclick = closeModal;
  $("#cancel").onclick = closeModal;
  $("#save").onclick = saveForm;
}

function closeModal() {
  $("#modal").innerHTML = "";
}

async function saveForm() {
  const payload = {
    ...state.edit,
    codigotga: $("#m_cod").value.trim(),
    nome_empresa: $("#m_emp").value.trim(),
    cnpj: $("#m_cnpj").value.trim(),
    versao: $("#m_ver").value.trim(),
    firebird: $("#m_fb").value.trim(),
    qntusuarios: Number($("#m_usr").value),
    info_adicional:
      $("#infoSel").value === "MIGRADO" ? "MIGRADO" : $("#infoTxt").value.trim()
  };

  if (!payload.codigotga || !payload.nome_empresa || !payload.cnpj) {
    return pushToast("Código, Empresa e CNPJ são obrigatórios.", "err");
  }

  if (payload.info_adicional === "MIGRADO" && payload.firebird !== "5.0") {
    return pushToast("Migrado só é permitido com Firebird 5.0.", "err");
  }

  try {
    const r = await apiFetch("backend/clientes.php", {
      method: payload.id ? "PUT" : "POST",
      body: JSON.stringify(payload)
    });
    const j = await r.json();

    if (!j.success) {
      pushToast(j.error || "Erro ao salvar.", "err");
      return;
    }

    pushToast("Salvo com sucesso.");
    closeModal();
    fetchClientes();

  } catch {
    pushToast("Erro ao salvar.", "err");
  }
}

async function removeRow(row) {
  if (!confirm(`Excluir "${row.nome_empresa}"?`)) return;

  try {
    const r = await apiFetch(`backend/clientes.php?id=${row.id}`, { method: "DELETE" });
    const j = await r.json();

    if (!j.success) {
      pushToast(j.error || "Erro ao excluir.", "err");
      return;
    }

    pushToast("Excluído.");
    fetchClientes();

  } catch {
    pushToast("Erro ao excluir.", "err");
  }
}

/* ===================================================
   INIT + DEBOUNCE SEGURO
=================================================== */
let debounceTimer = null;
let lastQuery = "";

function debounceFetch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    const key = `${state.q}|${state.versao}|${state.firebird}|${state.firebirdLivre}`;
    if (key === lastQuery) return;
    lastQuery = key;
    state.page = 1;
    fetchClientes();
  }, 350);
}

document.addEventListener("DOMContentLoaded", () => {

  $("#q").oninput = e => { state.q = e.target.value; debounceFetch(); };

  $("#versao").onchange = e => {
    state.versao = e.target.value;
    $("#versaoLivre")?.style.display =
      e.target.value === "outro" ? "block" : "none";
    debounceFetch();
  };

  $("#firebird").onchange = e => {
    state.firebird = e.target.value;
    $("#firebirdLivre").style.display =
      e.target.value === "outro" ? "block" : "none";
    debounceFetch();
  };

  $("#firebirdLivre").oninput = e => {
    state.firebirdLivre = e.target.value;
    debounceFetch();
  };

  $("#perPage").onchange = e => {
    state.perPage = Number(e.target.value);
    state.page = 1;
    fetchClientes();
  };

  $("#btnNew").onclick = openNew;
  $("#prev").onclick = () => { if (state.page > 1) { state.page--; fetchClientes(); } };
  $("#next").onclick = () => { state.page++; fetchClientes(); };

  fetchClientes();
});
