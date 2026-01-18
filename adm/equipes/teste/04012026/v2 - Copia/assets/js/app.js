/* =========================
   CONFIG
========================= */
const API = {
  dashboard: "backend/api_dashboard.php",
  logins: "backend/api_login.php",
  conexoes: "backend/api_conexoes.php"
};

const CSRF = window.__CSRF__ || "";

/* =========================
   STATE
========================= */
const state = {
  tab: "dashboard",

  logins: { page: 1, limit: 10, q: "", status: "" },
  conexoes: { page: 1, limit: 10, q: "", tipo: "", status: "" },

  theme: localStorage.getItem("tga_theme") || "dark"
};

/* =========================
   HELPERS
========================= */
function qs(sel, root=document){ return root.querySelector(sel); }
function qsa(sel, root=document){ return [...root.querySelectorAll(sel)]; }

function setTheme(mode){
  if (mode === "light") document.documentElement.setAttribute("data-theme","light");
  else document.documentElement.removeAttribute("data-theme");

  state.theme = mode;
  localStorage.setItem("tga_theme", mode);
}

function toast(type, title, msg){
  const box = qs("#toasts");
  const el = document.createElement("div");
  el.className = `toast ${type || ""}`;
  el.innerHTML = `<strong>${escapeHtml(title || "Aviso")}</strong><span>${escapeHtml(msg || "")}</span>`;
  box.appendChild(el);
  setTimeout(() => el.remove(), 3600);
}

function escapeHtml(str){
  return String(str ?? "")
    .replaceAll("&","&amp;")
    .replaceAll("<","&lt;")
    .replaceAll(">","&gt;")
    .replaceAll('"',"&quot;")
    .replaceAll("'","&#039;");
}

function badgeStatusLogin(s){
  const v = String(s||"").toUpperCase();
  if (v === "ATIVO") return `<span class="badge ok">ATIVO</span>`;
  if (v === "INATIVO") return `<span class="badge off">INATIVO</span>`;
  if (v === "BLOQUEADO") return `<span class="badge warn">BLOQUEADO</span>`;
  return `<span class="badge">${escapeHtml(v || "—")}</span>`;
}
function badgeStatusCon(s){
  const v = String(s||"").toUpperCase();
  if (v === "ON") return `<span class="badge ok">ON</span>`;
  if (v === "OFF") return `<span class="badge off">OFF</span>`;
  if (v === "ERRO") return `<span class="badge warn">ERRO</span>`;
  return `<span class="badge">${escapeHtml(v || "—")}</span>`;
}

function debounce(fn, ms=350){
  let t = null;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...args), ms);
  };
}

async function apiPost(url, payload){
  const res = await fetch(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-Token": CSRF
    },
    body: JSON.stringify(payload || {})
  });

  const data = await res.json().catch(() => ({}));
  if (!res.ok || data?.success === false) {
    const msg = data?.message || `Erro HTTP ${res.status}`;
    throw new Error(msg);
  }
  return data;
}

/* =========================
   NAV / TABS
========================= */
function setTab(tab){
  state.tab = tab;

  qsa(".menu-item").forEach(b => b.classList.toggle("active", b.dataset.tab === tab));
  qsa(".view").forEach(v => v.classList.add("hidden"));
  qs(`#view-${tab}`)?.classList.remove("hidden");

  const title = qs("#pageTitle");
  const sub = qs("#pageSubtitle");

  if (tab === "dashboard") { title.textContent="Dashboard"; sub.textContent="Visão geral e saúde dos acessos"; }
  if (tab === "logins") { title.textContent="Logins Web"; sub.textContent="Cadastro de login/URL e versão padrão"; }
  if (tab === "conexoes") { title.textContent="Conexões / API"; sub.textContent="Controle de base/servidor/porta e status"; }
}

/* =========================
   DASHBOARD
========================= */
async function loadDashboard(){
  const data = await apiPost(API.dashboard, { action: "summary" });

  qs("#kpiLoginsAtivos").textContent = data.kpis.logins_ativos ?? "—";
  qs("#kpiLoginsTotal").textContent  = `Total: ${data.kpis.logins_total ?? "—"}`;

  qs("#kpiConexoesOn").textContent = data.kpis.conexoes_on ?? "—";
  qs("#kpiConexoesTotal").textContent = `Total: ${data.kpis.conexoes_total ?? "—"}`;

  qs("#kpiApis").textContent = data.kpis.apis_total ?? "—";
  qs("#kpiApisSub").textContent = `ON: ${data.kpis.apis_on ?? "—"} • OFF: ${data.kpis.apis_off ?? "—"}`;

  qs("#kpiMobile").textContent = data.kpis.mobile_total ?? "—";
  qs("#kpiMobileSub").textContent = `ON: ${data.kpis.mobile_on ?? "—"} • OFF: ${data.kpis.mobile_off ?? "—"}`;

  renderDashTables(data);
}

function renderDashTables(data){
  const tb1 = qs("#tblDashLogins tbody");
  tb1.innerHTML = (data.last_logins || []).map(r => `
    <tr>
      <td>${escapeHtml(r.codigo_cliente)}</td>
      <td>${escapeHtml(r.nome_cliente)}</td>
      <td>${escapeHtml(r.versao_padrao || "—")}</td>
      <td>${badgeStatusLogin(r.status)}</td>
      <td class="muted">${escapeHtml(r.criado_em || "—")}</td>
    </tr>
  `).join("") || `<tr><td colspan="5" class="muted">Sem dados.</td></tr>`;

  const tb2 = qs("#tblDashConexoes tbody");
  tb2.innerHTML = (data.last_conexoes || []).map(r => `
    <tr>
      <td>${escapeHtml(r.cod_cliente)}</td>
      <td>${escapeHtml(r.cliente)}</td>
      <td>${escapeHtml(r.acesso_server || "—")}</td>
      <td>${escapeHtml(r.tipo_acesso || "—")}</td>
      <td>${badgeStatusCon(r.status)}</td>
    </tr>
  `).join("") || `<tr><td colspan="5" class="muted">Sem dados.</td></tr>`;
}

/* =========================
   LISTAGEM - LOGINS
========================= */
async function loadLogins(){
  const p = state.logins;
  const data = await apiPost(API.logins, {
    action: "list",
    page: p.page,
    limit: p.limit,
    q: p.q,
    status: p.status
  });

  const tbody = qs("#tblLogins tbody");
  tbody.innerHTML = (data.rows || []).map(r => `
    <tr>
      <td class="muted">${escapeHtml(r.id)}</td>
      <td><strong>${escapeHtml(r.codigo_cliente)}</strong></td>
      <td>${escapeHtml(r.nome_cliente)}</td>
      <td class="muted">${escapeHtml(r.caminho_acesso || "—")}</td>
      <td>${escapeHtml(r.versao_padrao || "—")}</td>
      <td>${badgeStatusLogin(r.status)}</td>
      <td>
        <button class="btn ghost" data-act="edit-login" data-id="${r.id}">Editar</button>
        <button class="btn danger" data-act="del-login" data-id="${r.id}">Excluir</button>
      </td>
    </tr>
  `).join("") || `<tr><td colspan="7" class="muted">Nenhum resultado.</td></tr>`;

  qs("#loginsPage").textContent = String(data.page || p.page);
  qs("#loginsInfo").textContent = `Mostrando ${data.count_page || 0} de ${data.total || 0} registros`;
  qs("#loginsPrev").disabled = (data.page <= 1);
  qs("#loginsNext").disabled = (data.page >= data.pages);
}

async function getLoginById(id){
  const data = await apiPost(API.logins, { action: "get", id });
  return data.row;
}

/* =========================
   LISTAGEM - CONEXOES
========================= */
async function loadConexoes(){
  const p = state.conexoes;
  const data = await apiPost(API.conexoes, {
    action: "list",
    page: p.page,
    limit: p.limit,
    q: p.q,
    tipo: p.tipo,
    status: p.status
  });

  const tbody = qs("#tblConexoes tbody");
  tbody.innerHTML = (data.rows || []).map(r => `
    <tr>
      <td class="muted">${escapeHtml(r.id)}</td>
      <td><strong>${escapeHtml(r.cod_cliente)}</strong></td>
      <td>${escapeHtml(r.cliente)}</td>
      <td class="muted">${escapeHtml(r.acesso_server || "—")}</td>
      <td>${escapeHtml(r.porta ?? "—")}</td>
      <td><span class="badge">${escapeHtml(r.tipo_acesso || "—")}</span></td>
      <td>${badgeStatusCon(r.status)}</td>
      <td>
        <button class="btn ghost" data-act="edit-con" data-id="${r.id}">Editar</button>
        <button class="btn danger" data-act="del-con" data-id="${r.id}">Excluir</button>
      </td>
    </tr>
  `).join("") || `<tr><td colspan="8" class="muted">Nenhum resultado.</td></tr>`;

  qs("#conPage").textContent = String(data.page || p.page);
  qs("#conInfo").textContent = `Mostrando ${data.count_page || 0} de ${data.total || 0} registros`;
  qs("#conPrev").disabled = (data.page <= 1);
  qs("#conNext").disabled = (data.page >= data.pages);
}

async function getConexaoById(id){
  const data = await apiPost(API.conexoes, { action: "get", id });
  return data.row;
}

/* =========================
   MODAL (dynamic fields)
========================= */
const modal = {
  backdrop: qs("#modalBackdrop"),
  title: qs("#modalTitle"),
  form: qs("#modalForm"),
  fields: qs("#modalFields"),
  entity: qs("#mEntity"),
  id: qs("#mId")
};

function openModal({ entity, mode, data }){
  modal.entity.value = entity;
  modal.id.value = data?.id ?? "";

  modal.fields.innerHTML = "";

  if (entity === "login"){
    modal.title.textContent = (mode === "edit") ? `Editar Login #${data.id}` : "Novo Login Web";

    addField("codigo_cliente","Código Cliente", data?.codigo_cliente || "", true);
    addField("nome_cliente","Nome Cliente", data?.nome_cliente || "", true);
    addField("caminho_acesso","Caminho Acesso", data?.caminho_acesso || "", false, "Ex: MAT / C12345 / NOME");
    addField("versao_padrao","Versão Padrão", data?.versao_padrao || "", false, "Ex: 25.12");
    addSelect("status","Status", ["ATIVO","INATIVO","BLOQUEADO"], data?.status || "ATIVO");
  }

  if (entity === "conexao"){
    modal.title.textContent = (mode === "edit") ? `Editar Conexão #${data.id}` : "Nova Conexão / API";

    addField("cod_cliente","Código Cliente", data?.cod_cliente || "", true);
    addField("cliente","Cliente", data?.cliente || "", true);
    addField("acesso_server","Servidor/Acesso", data?.acesso_server || "", false, "Ex: SRVTGAFVAPI01");
    addField("porta","Porta", data?.porta ?? "", false, "Ex: 3050 (se aplicável)", "number");
    addSelect("tipo_acesso","Tipo de Acesso", ["API","FV_MOBILE_ON","MOBILE_OFF"], data?.tipo_acesso || "API");
    addSelect("status","Status", ["ON","OFF","ERRO"], data?.status || "OFF");
    addTextarea("observacao","Observação", data?.observacao || "");
  }

  modal.backdrop.classList.remove("hidden");
}

function closeModal(){
  modal.backdrop.classList.add("hidden");
  modal.fields.innerHTML = "";
  modal.entity.value = "";
  modal.id.value = "";
}

function addField(name, label, value, required=false, placeholder="", type="text"){
  const div = document.createElement("div");
  div.className = "field";
  div.innerHTML = `
    <label>${escapeHtml(label)} ${required ? '<span class="muted">*</span>' : ''}</label>
    <input name="${escapeHtml(name)}" type="${escapeHtml(type)}" value="${escapeHtml(value)}"
           placeholder="${escapeHtml(placeholder)}" ${required ? "required":""}/>
  `;
  modal.fields.appendChild(div);
}

function addSelect(name, label, options, selected){
  const div = document.createElement("div");
  div.className = "field";
  div.innerHTML = `
    <label>${escapeHtml(label)}</label>
    <select name="${escapeHtml(name)}">
      ${options.map(o => `<option value="${escapeHtml(o)}" ${String(o)===String(selected)?"selected":""}>${escapeHtml(o)}</option>`).join("")}
    </select>
  `;
  modal.fields.appendChild(div);
}

function addTextarea(name, label, value){
  const div = document.createElement("div");
  div.className = "field";
  div.innerHTML = `
    <label>${escapeHtml(label)}</label>
    <textarea name="${escapeHtml(name)}">${escapeHtml(value)}</textarea>
  `;
  modal.fields.appendChild(div);
}

/* =========================
   ACTIONS
========================= */
async function refreshCurrent(){
  try{
    if (state.tab === "dashboard") await loadDashboard();
    if (state.tab === "logins") await loadLogins();
    if (state.tab === "conexoes") await loadConexoes();
    toast("ok","Atualizado","Dados recarregados com sucesso.");
  }catch(e){
    toast("err","Erro", e.message || "Falha ao atualizar.");
  }
}

async function handleTableClick(e){
  const btn = e.target.closest("button[data-act]");
  if (!btn) return;

  const act = btn.dataset.act;
  const id = Number(btn.dataset.id);

  try{
    if (act === "edit-login"){
      const row = await getLoginById(id);
      openModal({ entity: "login", mode: "edit", data: row });
    }
    if (act === "del-login"){
      if (!confirm(`Excluir Login #${id}?`)) return;
      await apiPost(API.logins, { action: "delete", id });
      toast("ok","Excluído","Login removido.");
      await loadLogins();
      await loadDashboard().catch(()=>{});
    }

    if (act === "edit-con"){
      const row = await getConexaoById(id);
      openModal({ entity: "conexao", mode: "edit", data: row });
    }
    if (act === "del-con"){
      if (!confirm(`Excluir Conexão #${id}?`)) return;
      await apiPost(API.conexoes, { action: "delete", id });
      toast("ok","Excluído","Conexão removida.");
      await loadConexoes();
      await loadDashboard().catch(()=>{});
    }
  }catch(err){
    toast("err","Erro", err.message || "Falha na ação.");
  }
}

async function handleModalSubmit(e){
  e.preventDefault();

  const entity = modal.entity.value;
  const id = modal.id.value ? Number(modal.id.value) : null;
  const formData = new FormData(modal.form);
  const payload = Object.fromEntries(formData.entries());

  // Normaliza porta vazia -> null
  if (entity === "conexao"){
    payload.porta = payload.porta === "" ? null : Number(payload.porta);
  }

  try{
    if (entity === "login"){
      if (id){
        await apiPost(API.logins, { action: "update", id, data: payload });
        toast("ok","Salvo","Login atualizado.");
      }else{
        await apiPost(API.logins, { action: "create", data: payload });
        toast("ok","Criado","Login cadastrado.");
      }
      closeModal();
      await loadLogins();
      await loadDashboard().catch(()=>{});
    }

    if (entity === "conexao"){
      if (id){
        await apiPost(API.conexoes, { action: "update", id, data: payload });
        toast("ok","Salvo","Conexão atualizada.");
      }else{
        await apiPost(API.conexoes, { action: "create", data: payload });
        toast("ok","Criado","Conexão cadastrada.");
      }
      closeModal();
      await loadConexoes();
      await loadDashboard().catch(()=>{});
    }

  }catch(err){
    toast("err","Erro", err.message || "Falha ao salvar.");
  }
}

/* =========================
   INIT
========================= */
function init(){
  setTheme(state.theme);

  // Tabs
  qsa(".menu-item").forEach(btn => {
    btn.addEventListener("click", async () => {
      setTab(btn.dataset.tab);
      if (state.tab === "dashboard") await loadDashboard().catch(()=>{});
      if (state.tab === "logins") await loadLogins().catch(()=>{});
      if (state.tab === "conexoes") await loadConexoes().catch(()=>{});
    });
  });

  // Sidebar toggle (mobile)
  qs("#btnToggleSidebar").addEventListener("click", () => {
    qs("#sidebar").classList.toggle("open");
  });

  // Theme
  qs("#btnToggleTheme").addEventListener("click", () => {
    const next = (state.theme === "dark") ? "light" : "dark";
    setTheme(next);
  });

  // Refresh
  qs("#btnRefresh").addEventListener("click", refreshCurrent);

  // Filters - Logins
  qs("#qLogins").addEventListener("input", debounce(async (e)=>{
    state.logins.q = e.target.value.trim();
    state.logins.page = 1;
    await loadLogins().catch(()=>{});
  }, 300));

  qs("#fLoginStatus").addEventListener("change", async (e)=>{
    state.logins.status = e.target.value;
    state.logins.page = 1;
    await loadLogins().catch(()=>{});
  });

  qs("#fLoginLimit").addEventListener("change", async (e)=>{
    state.logins.limit = Number(e.target.value || 10);
    state.logins.page = 1;
    await loadLogins().catch(()=>{});
  });

  qs("#loginsPrev").addEventListener("click", async ()=>{
    state.logins.page = Math.max(1, state.logins.page - 1);
    await loadLogins().catch(()=>{});
  });
  qs("#loginsNext").addEventListener("click", async ()=>{
    state.logins.page += 1;
    await loadLogins().catch(()=>{});
  });

  qs("#btnNovoLogin").addEventListener("click", ()=>{
    openModal({ entity: "login", mode: "create", data: {} });
  });

  // Filters - Conexoes
  qs("#qConexoes").addEventListener("input", debounce(async (e)=>{
    state.conexoes.q = e.target.value.trim();
    state.conexoes.page = 1;
    await loadConexoes().catch(()=>{});
  }, 300));

  qs("#fConTipo").addEventListener("change", async (e)=>{
    state.conexoes.tipo = e.target.value;
    state.conexoes.page = 1;
    await loadConexoes().catch(()=>{});
  });

  qs("#fConStatus").addEventListener("change", async (e)=>{
    state.conexoes.status = e.target.value;
    state.conexoes.page = 1;
    await loadConexoes().catch(()=>{});
  });

  qs("#fConLimit").addEventListener("change", async (e)=>{
    state.conexoes.limit = Number(e.target.value || 10);
    state.conexoes.page = 1;
    await loadConexoes().catch(()=>{});
  });

  qs("#conPrev").addEventListener("click", async ()=>{
    state.conexoes.page = Math.max(1, state.conexoes.page - 1);
    await loadConexoes().catch(()=>{});
  });
  qs("#conNext").addEventListener("click", async ()=>{
    state.conexoes.page += 1;
    await loadConexoes().catch(()=>{});
  });

  qs("#btnNovaConexao").addEventListener("click", ()=>{
    openModal({ entity: "conexao", mode: "create", data: {} });
  });

  // Tables actions
  qs("#tblLogins").addEventListener("click", handleTableClick);
  qs("#tblConexoes").addEventListener("click", handleTableClick);

  // Modal
  qs("#btnCloseModal").addEventListener("click", closeModal);
  qs("#btnCancelModal").addEventListener("click", closeModal);
  qs("#modalBackdrop").addEventListener("click", (e)=>{
    if (e.target.id === "modalBackdrop") closeModal();
  });
  modal.form.addEventListener("submit", handleModalSubmit);

  // Load initial
  setTab("dashboard");
  loadDashboard().catch((e)=>toast("err","Erro", e.message || "Falha ao carregar dashboard."));
}

init();
