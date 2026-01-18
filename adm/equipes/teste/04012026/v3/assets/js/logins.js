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

    if (!document.getElementById('tblLogins')) {
      view.innerHTML = `
        <div class="card">

          <div class="toolbar">
            <div class="search">
              <input id="loginSearch"
                     placeholder="Buscar por código, nome, caminho, versão">
            </div>

            <div class="filters">
              <select id="loginStatus">
                <option value="">Status: Todos</option>
                <option value="ATIVO">ATIVO</option>
                <option value="INATIVO">INATIVO</option>
              </select>

              <select id="loginVersao">
                <option value="">Versão: Todas</option>
              </select>

              <button class="btn primary" id="btnNovoLogin">
                ➕ Novo Login
              </button>
            </div>
          </div>

          <div class="table-wrap">
            <table class="table" id="tblLogins">
              <thead>
                <tr>
                  <th>Código</th>
                  <th>Cliente</th>
                  <th>Caminho</th>
                  <th>Versão</th>
                  <th>Status</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>

          <div class="pager">
            <span id="loginInfo" class="muted"></span>
            <div>
              <button class="btn ghost" id="loginPrev">←</button>
              <span class="badge" id="loginPage">1</span>
              <button class="btn ghost" id="loginNext">→</button>
            </div>
          </div>

        </div>
      `;

      bindLoginEvents();
      loadVersoes();
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

  } catch (e) {
    console.error(e);
    showToast('Erro ao carregar Logins Web', 'danger');
  }
}

/* ==========================
   LOAD VERSÕES
========================== */
async function loadVersoes() {
  const select = document.getElementById('loginVersao');
  if (!select || select.dataset.loaded) return;

  try {
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

  } catch (e) {
    console.warn('Erro ao carregar versões', e);
  }
}

/* ==========================
   EVENTS
========================== */
function bindLoginEvents() {
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

  document.getElementById('loginPrev').onclick = () => {
    if (loginState.page > 1) {
      loginState.page--;
      loadLogins();
    }
  };

  document.getElementById('loginNext').onclick = () => {
    loginState.page++;
    loadLogins();
  };
}

/* ==========================
   RENDER
========================== */
function renderLogins(rows) {
  const tbody = document.querySelector('#tblLogins tbody');
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
  document.getElementById('loginPage').textContent = res.page;
  document.getElementById('loginInfo').textContent =
    `Exibindo ${res.count_page} de ${res.total}`;
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
  modalForm.onsubmit = null;

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
