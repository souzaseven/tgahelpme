/* =====================================================
   CONFIG / ELEMENTOS — LOGIN CLIENTES
===================================================== */
const API_LOGIN = "api/clientes_login.php";

const listaLogin = document.getElementById("listaLogin");
const buscaLogin = document.getElementById("buscaLogin");
const modalLogin = document.getElementById("modalLogin");
const countLogin = document.getElementById("countLogin");

/* cache */
let cacheLogin = [];

/* filtro de status (padrão ATIVO) */
let filtroStatus = "ATIVO";

/* =====================================================
   LOAD
===================================================== */
function carregarLoginClientes() {
  fetch(API_LOGIN, {
    headers: { "X-CSRF-TOKEN": CSRF_TOKEN }
  })
    .then(r => r.json())
    .then(resp => {
      cacheLogin = Array.isArray(resp.data) ? resp.data : [];
      renderLogin();
    })
    .catch(err => console.error("Erro ao carregar login clientes:", err));
}

/* =====================================================
   RENDER
===================================================== */
function renderLogin() {
  if (!listaLogin || !buscaLogin) return;

  const termo = buscaLogin.value.toLowerCase();
  listaLogin.innerHTML = "";

  let total = 0;

  cacheLogin
    .filter(c => {
      const matchTexto =
        (c.codigo_cliente || "").toLowerCase().includes(termo) ||
        (c.nome_cliente || "").toLowerCase().includes(termo) ||
        (c.caminho_acesso || "").toLowerCase().includes(termo);

      const matchStatus =
        filtroStatus === "TODOS" || c.status === filtroStatus;

      return matchTexto && matchStatus;
    })
    .forEach(c => {
      total++;

      listaLogin.insertAdjacentHTML("beforeend", `
        <tr>
          <td>${c.codigo_cliente}</td>
          <td>${c.nome_cliente}</td>
          <td>${c.caminho_acesso}</td>
          <td>${c.versao_padrao}</td>
          <td>
            <span class="badge ${c.status === "ATIVO" ? "badge-on" : "badge-off"}">
              ${c.status}
            </span>
          </td>
          <td>
            <button class="btn-edit" onclick='editarLogin(${JSON.stringify(c)})'>✏️</button>
            <button class="btn-delete" onclick='excluirLogin(${c.id})'>🗑</button>
          </td>
        </tr>
      `);
    });

  if (countLogin) {
    countLogin.textContent = total;
  }
}

/* =====================================================
   BUSCA
===================================================== */
if (buscaLogin) {
  buscaLogin.addEventListener("input", renderLogin);
}

/* =====================================================
   FILTRO ATIVO / INATIVO / TODOS
===================================================== */
document.querySelectorAll("input[name='filtroStatus']").forEach(radio => {
  radio.addEventListener("change", () => {
    filtroStatus = radio.value;
    renderLogin();
  });
});

/* =====================================================
   MODAL
===================================================== */
function abrirModalLogin() {
  if (modalLogin) modalLogin.style.display = "block";
}

function fecharModalLogin() {
  if (!modalLogin) return;

  modalLogin.style.display = "none";
  document.querySelectorAll("#modalLogin input, #modalLogin select")
    .forEach(el => el.value = "");
}

/* =====================================================
   SALVAR (INSERT / UPDATE)
===================================================== */
function salvarLogin() {
  const data = {
    id: document.getElementById("id_login").value || null,
    codigo_cliente: document.getElementById("codigo_cliente").value.trim(),
    nome_cliente: document.getElementById("nome_cliente").value.trim(),
    caminho_acesso: document.getElementById("caminho_acesso").value.trim(),
    versao_padrao: document.getElementById("versao_padrao").value.trim(),
    status: document.getElementById("status").value || "ATIVO"
  };

  fetch(API_LOGIN, {
    method: data.id ? "PUT" : "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": CSRF_TOKEN
    },
    body: JSON.stringify(data)
  })
    .then(() => {
      fecharModalLogin();
      carregarLoginClientes();
    })
    .catch(err => console.error("Erro ao salvar login:", err));
}

/* =====================================================
   EDITAR
===================================================== */
function editarLogin(c) {
  if (!c) return;
  abrirModalLogin();

  document.getElementById("id_login").value        = c.id;
  document.getElementById("codigo_cliente").value = c.codigo_cliente;
  document.getElementById("nome_cliente").value   = c.nome_cliente;
  document.getElementById("caminho_acesso").value = c.caminho_acesso;
  document.getElementById("versao_padrao").value  = c.versao_padrao;
  document.getElementById("status").value         = c.status || "ATIVO";
}

/* =====================================================
   EXCLUIR
===================================================== */
function excluirLogin(id) {
  if (!id) return;
  if (!confirm("Deseja excluir este login de cliente?")) return;

  fetch(`${API_LOGIN}?id=${id}`, {
    method: "DELETE",
    headers: { "X-CSRF-TOKEN": CSRF_TOKEN }
  })
    .then(() => carregarLoginClientes())
    .catch(err => console.error("Erro ao excluir login:", err));
}

/* =====================================================
   INIT
===================================================== */
if (listaLogin) {
  carregarLoginClientes();
}
