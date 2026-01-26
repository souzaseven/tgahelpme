const API = "backend/clientes.php";
let clienteEditando = null;

/* ===============================
   ESTADO PAGINAÇÃO
================================ */
let paginaAtual = 1;
let limitePorPagina = 10;
let dadosCache = [];

/* ===============================
   ELEMENTOS — LISTA
================================ */
const lista = document.getElementById("listaClientes");
const paginacao = document.getElementById("paginacao");
const fLimit = document.getElementById("fLimit");

/* ===============================
   FILTROS
================================ */
const fCodigo   = document.getElementById("fCodigo");
const fEmpresa  = document.getElementById("fEmpresa");
const fCnpj     = document.getElementById("fCnpj");
const fVersao   = document.getElementById("fVersao");
const fFirebird = document.getElementById("fFirebird");
const fInfo     = document.getElementById("fInfo");
const fUsuarios = document.getElementById("fUsuarios");

/* ===============================
   MODAL
================================ */
const mCodigo   = document.getElementById("mCodigo");
const mEmpresa  = document.getElementById("mEmpresa");
const mCnpj     = document.getElementById("mCnpj");
const mVersao   = document.getElementById("mVersao");
const mFirebird = document.getElementById("mFirebird");
const mInfo     = document.getElementById("mInfo");
const mUsuarios = document.getElementById("mUsuarios");
const mSenha    = document.getElementById("mSenha");

/* ===============================
   CARDS
================================ */
const cardTotal = document.getElementById("cardTotal");
const cardFB25  = document.getElementById("cardFB25");
const cardFB50  = document.getElementById("cardFB50");

/* ===============================
   UTIL
================================ */
function formatarCNPJ(v){
  if (!v) return "";
  return v.replace(/\D/g,'')
          .replace(/^(\d{2})(\d)/,"$1.$2")
          .replace(/^(\d{2})\.(\d{3})(\d)/,"$1.$2.$3")
          .replace(/\.(\d{3})(\d)/,".$1/$2")
          .replace(/(\d{4})(\d)/,"$1-$2");
}

function fecharModal(){
  document.getElementById("modal").classList.remove("show");
}

/* ===============================
   MÁSCARA CNPJ (CADASTRO)
================================ */
mCnpj.addEventListener("input", () => {
  let v = mCnpj.value.replace(/\D/g, "").slice(0, 14);
  mCnpj.value = formatarCNPJ(v);
});

/* ===============================
   MÁSCARA CNPJ (FILTRO)
================================ */
fCnpj.addEventListener("input", () => {
  let v = fCnpj.value.replace(/\D/g, "").slice(0, 14);
  fCnpj.value = formatarCNPJ(v);
});

/* ===============================
   REGRA FIREBIRD
================================ */
function aplicarRegraFirebird(){
  if (mFirebird.value === "5.0") {
    mInfo.value = "MIGRADO";
    mInfo.readOnly = true;
    mInfo.style.opacity = "0.8";
  } else {
    mInfo.readOnly = false;
    mInfo.style.opacity = "1";
    if (mInfo.value === "MIGRADO") mInfo.value = "";
  }
}
mFirebird.addEventListener("change", aplicarRegraFirebird);

/* ===============================
   BUSCA BACKEND
================================ */
async function carregarClientes(){
  try {
    const params = new URLSearchParams({
      codigo:   fCodigo.value   || "",
      empresa:  fEmpresa.value  || "",
      cnpj:     fCnpj.value     || "",
      versao:   fVersao.value   || "",
      firebird: fFirebird.value || "",
      info:     fInfo.value     || "",
      usuarios: fUsuarios.value || ""
    });

    const res = await fetch(`${API}?${params.toString()}`);
    if (!res.ok) throw new Error("Erro no backend");

const json = await res.json();

/* 🔒 GARANTE ARRAY */
dadosCache = Array.isArray(json.data) ? json.data : [];

paginaAtual = json.page || 1;


atualizarCards();
renderizarTabela();
renderizarPaginacao();


  } catch (e) {
    console.error(e);
    lista.innerHTML = `
      <tr>
        <td colspan="9" style="text-align:center;color:#c00">
          Erro ao carregar dados
        </td>
      </tr>`;
  }
}

/* ===============================
   RENDER TABELA
================================ */
function renderizarTabela(){
  lista.innerHTML = "";

  if (!dadosCache.length){
    lista.innerHTML = `
      <tr>
        <td colspan="9" style="text-align:center;opacity:.7">
          Nenhum registro encontrado
        </td>
      </tr>`;
    return;
  }

  let dados = [...dadosCache];

  if (limitePorPagina !== "all") {
    const ini = (paginaAtual - 1) * limitePorPagina;
    const fim = ini + limitePorPagina;
    dados = dados.slice(ini, fim);
  }

  dados.forEach(c => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${c.codigotga}</td>
      <td>${c.nome_empresa}</td>
      <td>${formatarCNPJ(c.cnpj)}</td>
      <td>${c.versao}</td>
      <td>${c.firebird}</td>
      <td>${c.info_adicional || ""}</td>
      <td>${c.qntusuarios ?? ""}</td>
      <td>${c.senhapadrao}</td>
      <td>
        <button class="btn-edit" onclick='editar(${JSON.stringify(c)})'>
           Editar
        </button>
      </td>
    `;
    lista.appendChild(tr);
  });
}

/* ===============================
   PAGINAÇÃO
================================ */
function renderizarPaginacao(){
  paginacao.innerHTML = "";

  if (limitePorPagina === "all") return;

  const totalPaginas = Math.ceil(dadosCache.length / limitePorPagina);
  if (totalPaginas <= 1) return;

  for (let i = 1; i <= totalPaginas; i++){
    const btn = document.createElement("button");
    btn.textContent = i;
    btn.className = (i === paginaAtual) ? "active" : "";
    btn.onclick = () => {
      paginaAtual = i;
      renderizarTabela();
      renderizarPaginacao();
    };
    paginacao.appendChild(btn);
  }
}
/* ===============================
   CARDS
================================ */
function atualizarCards(){
  if (!Array.isArray(dadosCache)) return;

  let fb25 = 0;
  let fb50 = 0;

  dadosCache.forEach(c => {
    if (c.firebird === "2.5") fb25++;
    if (c.firebird === "5.0") fb50++;
  });

  if (cardTotal) cardTotal.textContent = dadosCache.length;
  if (cardFB25)  cardFB25.textContent  = fb25;
  if (cardFB50)  cardFB50.textContent  = fb50;
}

/* ===============================
   EDITAR / NOVO
================================ */
function editar(c){
  clienteEditando = c;

  mCodigo.value   = c.codigotga;
  mEmpresa.value  = c.nome_empresa;
  mCnpj.value     = formatarCNPJ(c.cnpj);
  mVersao.value   = c.versao;
  mFirebird.value = c.firebird;
  mUsuarios.value = c.qntusuarios;
  mSenha.value    = c.senhapadrao;
  mInfo.value     = c.info_adicional || "";

  aplicarRegraFirebird();
  document.getElementById("btnExcluir").style.display = "inline-block";
  document.getElementById("modal").classList.add("show");
}

document.getElementById("btnNovo").onclick = () => {
  clienteEditando = null;

  mCodigo.value = mEmpresa.value = mCnpj.value = "";
  mVersao.value = mFirebird.value = "";
  mUsuarios.value = "";
  mSenha.value = "tga@1234";
  mInfo.value = "";

  document.getElementById("btnExcluir").style.display = "none";
  document.getElementById("modal").classList.add("show");
};

/* ===============================
   SALVAR / EXCLUIR
================================ */
document.getElementById("btnSalvar").onclick = async () => {
  await fetch(API,{
    method:"POST",
    headers:{ "Content-Type":"application/json" },
    body: JSON.stringify({
      id: clienteEditando?.id || null,
      codigotga: mCodigo.value,
      nome_empresa: mEmpresa.value,
      cnpj: mCnpj.value,
      versao: mVersao.value,
      firebird: mFirebird.value,
      info_adicional: mInfo.value,
      qntusuarios: mUsuarios.value,
      senhapadrao: mSenha.value
    })
  });

  fecharModal();
  carregarClientes();
};

document.getElementById("btnExcluir").onclick = async () => {
  if (!clienteEditando || !confirm("Deseja excluir este cliente?")) return;

  await fetch(API,{
    method:"POST",
    headers:{ "Content-Type":"application/json" },
    body: JSON.stringify({
      id: clienteEditando.id,
      excluir: true
    })
  });

  fecharModal();
  carregarClientes();
};

/* ===============================
   EVENTOS
================================ */
document.getElementById("btnFiltrar").onclick = carregarClientes;
document.getElementById("btnExportCSV").onclick =
  () => window.open("backend/exportar_clientes.php", "_blank");

fLimit.onchange = () => {
  limitePorPagina = fLimit.value === "all" ? "all" : parseInt(fLimit.value);
  paginaAtual = 1;
  renderizarTabela();
  renderizarPaginacao();
};

/* ===============================
   BUSCA AUTOMÁTICA
================================ */
let debounceTimer;
["fCodigo","fEmpresa","fCnpj"].forEach(id => {
  const el = document.getElementById(id);
  el.addEventListener("input", () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(carregarClientes, 400);
  });
});

/* INIT */
carregarClientes();
