/* =====================================================
   CONFIG / ELEMENTOS
===================================================== */
const API = "api/clientes.php";

const listaOn  = document.getElementById("listaOn");
const listaApi = document.getElementById("listaApi");

const modal = document.getElementById("modal");
const busca = document.getElementById("busca");

const countOn  = document.getElementById("countOn");
const countApi = document.getElementById("countApi");

/* cache global */
let cache = [];

/* controle de ordenação */
let ordem = {
  campo: null,
  asc: true
};

/* debounce busca */
let searchTimeout = null;

/* =====================================================
   UTILIDADES
===================================================== */
function detectarTipo(server = "") {
  server = server.toLowerCase();
  if (server.includes("api")) return "API";
  return "SMART_CLIENTE";
}

/* =====================================================
   LOAD INICIAL
===================================================== */
function carregar() {
  fetch(API, {
    headers: { "X-CSRF-TOKEN": CSRF_TOKEN }
  })
    .then(r => r.json())
    .then(resp => {
      cache = Array.isArray(resp.data) ? resp.data : [];
      render();
    })
    .catch(err => console.error("Erro ao carregar:", err));
}

/* =====================================================
   RENDER
===================================================== */
function render() {
  const termo = busca.value.toLowerCase();

  listaOn.innerHTML  = "";
  listaApi.innerHTML = "";

  let totalOn  = 0;
  let totalApi = 0;

  cache
    .filter(c =>
      (c.cliente || "").toLowerCase().includes(termo) ||
      (c.cod_cliente || "").toLowerCase().includes(termo) ||
      (c.acesso_server || "").toLowerCase().includes(termo)
    )
    .forEach(c => {
      const linha = `
        <tr>
          <td>${c.cod_cliente}</td>
          <td>${c.cliente}</td>
          <td>${c.acesso_server}</td>
          <td>${c.porta ?? ""}</td>
          <td>
            <button class="btn-edit" onclick='editar(${JSON.stringify(c)})' title="Editar">✏️</button>
            <button class="btn-delete" onclick='excluir(${c.id})' title="Excluir">🗑</button>
          </td>
        </tr>
      `;

      if (c.tipo_acesso === "API") {
        listaApi.insertAdjacentHTML("beforeend", linha);
        totalApi++;
      } else {
        listaOn.insertAdjacentHTML("beforeend", linha);
        totalOn++;
      }
    });

  countOn.textContent  = totalOn;
  countApi.textContent = totalApi;
}

/* =====================================================
   BUSCA (com debounce)
===================================================== */
busca.addEventListener("input", () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(render, 200);
});

/* =====================================================
   ORDENAÇÃO
===================================================== */
function ordenar(campo) {
  ordem.asc = ordem.campo === campo ? !ordem.asc : true;
  ordem.campo = campo;

  cache.sort((a, b) => {
    const v1 = String(a[campo] ?? "");
    const v2 = String(b[campo] ?? "");

    return ordem.asc
      ? v1.localeCompare(v2, "pt-BR", { numeric: true })
      : v2.localeCompare(v1, "pt-BR", { numeric: true });
  });

  render();
}

/* =====================================================
   EXPANDIR / RECOLHER
===================================================== */
function toggleGrupo(tipo) {
  const body   = document.getElementById(tipo === "on" ? "grupoOn" : "grupoApi");
  const toggle = body?.previousElementSibling?.querySelector(".toggle");

  if (!body) return;

  const fechado = body.style.display === "none";
  body.style.display = fechado ? "block" : "none";

  if (toggle) toggle.textContent = fechado ? "▾" : "▸";
}

/* =====================================================
   MODAL
===================================================== */
function abrirModal() {
  modal.style.display = "block";
}

function fecharModal() {
  modal.style.display = "none";
  document.querySelectorAll("#modal input, #modal textarea")
    .forEach(el => el.value = "");
}

/* =====================================================
   SALVAR
===================================================== */
function salvar() {
  const server = acesso_server.value.trim();
  const tipo   = detectarTipo(server);

  const data = {
    id: id.value || null,
    cod_cliente: cod_cliente.value.trim(),
    cliente: cliente.value.trim(),
    acesso_server: server,
    porta: porta.value || null,
    tipo_acesso: tipo,
    status: tipo === "API" ? "OFF" : "ON",
    observacao: observacao.value.trim()
  };

  fetch(API, {
    method: data.id ? "PUT" : "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": CSRF_TOKEN
    },
    body: JSON.stringify(data)
  })
    .then(() => {
      fecharModal();
      carregar();
    })
    .catch(err => console.error("Erro ao salvar:", err));
}

/* =====================================================
   EDITAR
===================================================== */
function editar(c) {
  abrirModal();
  Object.keys(c).forEach(k => {
    const el = document.getElementById(k);
    if (el) el.value = c[k];
  });
}

/* =====================================================
   LOGIN CLIENTES (NAVEGAÇÃO INTERNA)
===================================================== */
function abrirLoginClientes(e) {
  e.preventDefault();
  document.getElementById("infra").style.display = "none";
  document.getElementById("clientes").style.display = "none";
  document.getElementById("modulo-login-clientes").style.display = "block";
}

function voltarPainel() {
  document.getElementById("modulo-login-clientes").style.display = "none";
  document.getElementById("infra").style.display = "block";
  document.getElementById("clientes").style.display = "block";
}

/* =====================================================
   EXCLUIR
===================================================== */
function excluir(id) {
  if (!confirm("Deseja realmente excluir este cliente?")) return;

  fetch(`${API}?id=${id}`, {
    method: "DELETE",
    headers: { "X-CSRF-TOKEN": CSRF_TOKEN }
  })
    .then(() => carregar())
    .catch(err => console.error("Erro ao excluir:", err));
}

/* =====================================================
   INIT
===================================================== */
carregar();
