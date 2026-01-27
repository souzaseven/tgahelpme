/* =========================================================
   Logins Web - Módulo
   Painel Unificado Clientes Web (v3 FINAL)
   Autor: Anderson de Souza
========================================================= */

/* ==========================
   STATE
========================== */
let loginState = {
  page: 1,
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

  } catch (e) {
    console.error(e);
    showToast('Erro ao carregar Logins Web', 'danger');
  }
}

/* ==========================
   EVENTS
========================== */
function bindLoginEvents() {

  const search = document.getElementById('loginsSearch');
  const limit  = document.getElementById('loginsLimit');
  const prev   = document.getElementById('loginsPrev');
  const next   = document.getElementById('loginsNext');
  const btnNew = document.getElementById('btnNovoLogin');

  if (btnNew) btnNew.onclick = novoLogin;

  if (search) {
    search.oninput = e => {
      loginState.q = e.target.value;
      loginState.page = 1;
      loadLogins();
    };
  }

  if (limit) {
    limit.onchange = e => {
      loginState.limit = parseInt(e.target.value, 10) || 10;
      loginState.page = 1;
      loadLogins();
    };
  }

  if (prev) {
    prev.onclick = () => {
      if (loginState.page > 1) {
        loginState.page--;
        loadLogins();
      }
    };
  }

  if (next) {
    next.onclick = () => {
      loginState.page++;
      loadLogins();
    };
  }
}

/* ==========================
   RENDER
========================== */
function renderLogins(rows) {
  const tbody = document.querySelector('#tblLogins tbody');
  if (!tbody) return;

  tbody.innerHTML = '';

  if (!rows.length) {
    tbody.innerHTML =
      `<tr><td colspan="6">Nenhum registro encontrado</td></tr>`;
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
  const page = document.getElementById('loginsPage');
  const info = document.getElementById('loginsInfo');

  if (page) page.textContent = res.page ?? 1;
  if (info) {
    info.textContent =
      `Exibindo ${res.count_page ?? 0} de ${res.total ?? 0}`;
  }
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

  await apiFetch('backend/api_login.php', {
    method: 'POST',
    body: { action: 'delete', id }
  });

  showToast('Login removido', 'success');
  loadLogins();
}

/* ==========================
   INIT
========================== */
document.addEventListener('DOMContentLoaded', () => {
  bindLoginEvents();
});
