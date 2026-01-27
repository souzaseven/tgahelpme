/* =========================================================
   Logins Web - Módulo
   Painel Unificado Clientes Web (v3 FINAL)
   Autor: Anderson de Souza
   Correção FINAL: eventos + paginação funcional
========================================================= */

/* ==========================
   STATE
========================== */
let loginState = {
  page: 1,
  pages: 1,
  limit: 10,
  q: '',
  status: '',
  versao: ''
};

/* ==========================
   LOAD
========================== */
async function loadLogins() {
  try {
    const view = document.getElementById('view-logins');
    if (!view) return;

    bindLoginEventsOnce();
    loadVersoes();

    const selLimit = document.getElementById('loginLimit');
    if (selLimit) {
      const v = Number(selLimit.value);
      if (!Number.isNaN(v) && v > 0) loginState.limit = v;
    }

    const res = await apiFetch('backend/api_login.php', {
      method: 'POST',
      body: {
        action: 'list',
        page: loginState.page,
        limit: loginState.limit,
        q: loginState.q,
        status: loginState.status,
        versao: loginState.versao
      }
    });

    if (!res.success) {
      throw new Error(res.message || 'Erro ao carregar logins');
    }

    renderLogins(res.rows || []);
    updateLoginPager(res);

  } catch (err) {
    console.error(err);
    showToast('Erro ao carregar Logins Web', 'danger');
  }
}

/* ==========================
   EVENTS (bind 1x)
========================== */
function bindLoginEventsOnce() {
  if (document.body.dataset.loginsBound === '1') return;
  document.body.dataset.loginsBound = '1';

  document.getElementById('btnNovoLogin').onclick = novoLogin;

  document.getElementById('loginSearch').oninput = e => {
    loginState.q = e.target.value;
    loginState.page = 1;
    loadLogins();
  };

  document.getElementById('loginStatus').onchange = e => {
    loginState.status = e.target.value;
    loginState.page = 1;
    loadLogins();
  };

  document.getElementById('loginVersao').onchange = e => {
    loginState.versao = e.target.value;
    loginState.page = 1;
    loadLogins();
  };

  document.getElementById('loginLimit').onchange = e => {
    loginState.limit = Number(e.target.value) || 10;
    loginState.page = 1;
    loadLogins();
  };

  document.getElementById('loginPrev').onclick = () => {
    if (loginState.page > 1) {
      loginState.page--;
      loadLogins();
    }
  };

  document.getElementById('loginNext').onclick = () => {
    if (loginState.page < loginState.pages) {
      loginState.page++;
      loadLogins();
    }
  };
}

/* ==========================
   LOAD VERSÕES
========================== */
async function loadVersoes() {
  const select = document.getElementById('loginVersao');
  if (!select || select.dataset.loaded) return;

  const res = await apiFetch('backend/api_login.php', {
    method: 'POST',
    body: { action: 'versions' }
  });

  if (!res.success) return;

  res.versions.forEach(v => {
    const opt = document.createElement('option');
    opt.value = v;
    opt.textContent = v;
    select.appendChild(opt);
  });

  select.dataset.loaded = '1';
}

/* ==========================
   RENDER
========================== */
function renderLogins(rows) {
  const tbody = document.querySelector('#tblLogins tbody');
  if (!tbody) return;

  tbody.innerHTML = '';

  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="6">Nenhum registro encontrado</td></tr>`;
    return;
  }

  rows.forEach(r => {
    const ativo = r.status === 'ATIVO';

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${r.codigo_cliente}</td>
      <td>${r.nome_cliente}</td>
      <td>${r.caminho_acesso || '-'}</td>
      <td>${r.versao_padrao || '-'}</td>
      <td>
        <span class="badge ${ativo ? 'success' : 'danger'}">
          ${r.status}
        </span>
      </td>
      <td>
        <button class="btn ghost" onclick="editarLogin(${r.id})">✏️</button>
        <button class="btn ghost" onclick="excluirLogin(${r.id})">🗑</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

/* ==========================
   PAGINAÇÃO
========================== */
function updateLoginPager(res) {
  const total = Number(res.total) || 0;

  loginState.pages = Math.max(
    1,
    Math.ceil(total / loginState.limit)
  );

  const start = total === 0
    ? 0
    : (loginState.page - 1) * loginState.limit + 1;

  const end = Math.min(
    loginState.page * loginState.limit,
    total
  );

  const elPage = document.getElementById('loginPage');
  const elInfo = document.getElementById('loginInfo');

  if (elPage) elPage.textContent = loginState.page;
  if (elInfo) elInfo.textContent = `Exibindo ${start} até ${end} de ${total}`;

  const btnPrev = document.getElementById('loginPrev');
  const btnNext = document.getElementById('loginNext');

  if (btnPrev) btnPrev.disabled = loginState.page <= 1;
  if (btnNext) btnNext.disabled = loginState.page >= loginState.pages;
}

/* ==========================
   NOVO
========================== */
function novoLogin() {
  openModal({
    title: 'Novo Login Web',
    entity: 'login',
    fields: [
      { label: 'Código do Cliente', name: 'codigo_cliente', required: true },
      { label: 'Nome do Cliente', name: 'nome_cliente', required: true },
      { label: 'Caminho de Acesso', name: 'caminho_acesso' },
      { label: 'Versão Padrão', name: 'versao_padrao' },
      { label: 'Status', name: 'status', value: 'ATIVO' }
    ]
  });

  bindLoginSubmit();
}

/* ==========================
   EDITAR
========================== */
async function editarLogin(id) {
  const res = await apiFetch('backend/api_login.php', {
    method: 'POST',
    body: { action: 'get', id }
  });

  if (!res.success) {
    showToast('Erro ao abrir registro', 'danger');
    return;
  }

  const r = res.row;

  openModal({
    title: 'Editar Login Web',
    entity: 'login',
    id,
    fields: [
      { label: 'Código do Cliente', name: 'codigo_cliente', value: r.codigo_cliente, required: true },
      { label: 'Nome do Cliente', name: 'nome_cliente', value: r.nome_cliente, required: true },
      { label: 'Caminho de Acesso', name: 'caminho_acesso', value: r.caminho_acesso },
      { label: 'Versão Padrão', name: 'versao_padrao', value: r.versao_padrao },
      { label: 'Status', name: 'status', value: r.status }
    ]
  });

  bindLoginSubmit(id);
}

/* ==========================
   SUBMIT
========================== */
function bindLoginSubmit(id = '') {
  modalForm.onsubmit = async e => {
    e.preventDefault();

    const data = {};
    modalForm.querySelectorAll('input[name]').forEach(i => {
      data[i.name] = i.value;
    });

    try {
      const res = await apiFetch('backend/api_login.php', {
        method: 'POST',
        body: {
          action: id ? 'update' : 'create',
          id,
          data
        }
      });

      if (!res.success) {
        throw new Error(res.message || 'Erro ao salvar');
      }

      showToast('Login salvo com sucesso', 'success');
      closeModal();
      loadLogins();

    } catch (err) {
      console.error(err);
      showToast(err.message || 'Erro ao salvar login', 'danger');
    }
  };
}

/* ==========================
   EXCLUIR
========================== */
async function excluirLogin(id) {
  if (!confirm('Excluir este login?')) return;

  const res = await apiFetch('backend/api_login.php', {
    method: 'POST',
    body: { action: 'delete', id }
  });

  if (!res.success) {
    showToast('Erro ao excluir login', 'danger');
    return;
  }

  showToast('Login removido', 'success');
  loadLogins();
}
