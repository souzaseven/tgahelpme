/* =========================================================
   Usuários Web - Módulo
   Painel Unificado Clientes Web (v4 PAGINAÇÃO + ORDENAÇÃO)
   Autor: Anderson de Souza
========================================================= */

/* ==========================
   STATE
========================== */
let usuariosWebState = {
  q: '',
  minUsers: '',
  status: 'ATIVO',

  orderBy: 'qtd_usuarios',
  orderDir: 'DESC',

  page: 1,
  limit: 10,

  rows: []
};

/* ==========================
   LOAD
========================== */
async function loadUsuariosWeb() {
  const view = document.getElementById('view-usuarios-web');
  if (!view) return;

  bindUsuariosWebEventsOnce();
  await fetchUsuariosWeb();
  loadUltimosUsuariosWeb();
}

/* ==========================
   FETCH
========================== */
async function fetchUsuariosWeb() {
  try {
    const res = await apiFetch('backend/api_usuarios_web.php', {
      method: 'POST',
      body: {
        action: 'list',
        q: usuariosWebState.q,
        min_users: usuariosWebState.minUsers,
        status: usuariosWebState.status,
        order_by: usuariosWebState.orderBy,
        order_dir: usuariosWebState.orderDir
      }
    });

    if (!res.success) {
      throw new Error(res.message || 'Erro ao carregar Usuários Web');
    }

    usuariosWebState.rows = res.rows || [];
    usuariosWebState.page = 1;

    updateUsuariosWebResumo(res.stats || {});
    renderUsuariosWeb();

  } catch (err) {
    console.error(err);
    showToast(err.message || 'Erro ao carregar Usuários Web', 'danger');
  }
}

/* ==========================
   RESUMO
========================== */
function updateUsuariosWebResumo(stats) {
  const map = {
    uwTotal: stats.total,
    uwAtivos: stats.ativos,
    uwInativos: stats.inativos,
    uwUsuarios: stats.total_usuarios
  };

  Object.entries(map).forEach(([id, val]) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val ?? 0;
  });
}

/* ==========================
   TABELA + PAGINAÇÃO
========================== */
function renderUsuariosWeb() {
  const tbody = document.querySelector('#tblUsuariosWeb tbody');
  if (!tbody) return;

  tbody.innerHTML = '';

  let rows = [...usuariosWebState.rows];

  /* Limite */
  if (usuariosWebState.limit !== 'all') {
    const start = (usuariosWebState.page - 1) * usuariosWebState.limit;
    const end   = start + usuariosWebState.limit;
    rows = rows.slice(start, end);
  }

  if (!rows.length) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" style="text-align:center;opacity:.6">
          Nenhum registro encontrado
        </td>
      </tr>
    `;
    renderPagination(0);
    return;
  }

  rows.forEach(r => {
    const tr = document.createElement('tr');

    tr.innerHTML = `
      <td>${r.codigo_empresa}</td>
      <td>${r.nome_empresa}</td>

      <td class="text-center"><strong>${r.qtd_usuarios}</strong></td>

      <td title="${r.observacao ?? ''}">
        ${r.observacao ?? '-'}
      </td>

      <td class="text-center">
        <span class="badge ${r.status === 'ATIVO' ? 'success' : 'danger'}">
          ${r.status}
        </span>
      </td>

      <td class="text-center">
        ${formatDateTime(r.atualizado_em ?? r.criado_em)}
      </td>

      <td class="acoes">
        <button class="btn ghost" data-edit title="Editar">✏️</button>
        ${
          r.status === 'INATIVO'
            ? `<button class="btn ghost danger" data-del title="Excluir">🗑</button>`
            : ''
        }
      </td>
    `;

    tr.querySelector('[data-edit]')?.addEventListener('click', () =>
      editarUsuarioWeb(r.id)
    );

    tr.querySelector('[data-del]')?.addEventListener('click', () =>
      excluirUsuarioWeb(r.id)
    );

    tbody.appendChild(tr);
  });

  renderPagination(usuariosWebState.rows.length);
}
/* ==========================
   PAGINAÇÃO (PADRÃO LOGINS)
========================== */
function renderPagination(total) {
  const wrap = document.getElementById('uwPagination');
  if (!wrap) return;

  wrap.innerHTML = '';

  /* Sem paginação quando for "all" */
  if (usuariosWebState.limit === 'all') {
    wrap.innerHTML = `<span class="pagination-info">
      Exibindo 1 até ${total} de ${total}
    </span>`;
    return;
  }

  const limit = usuariosWebState.limit;
  const page  = usuariosWebState.page;

  const from = total === 0 ? 0 : (page - 1) * limit + 1;
  const to   = Math.min(page * limit, total);

  const prevDisabled = page === 1;
  const nextDisabled = to >= total;

  wrap.innerHTML = `
    <div class="pagination-info">
      Exibindo ${from} até ${to} de ${total}
    </div>

    <div class="pagination-actions">
      <button ${prevDisabled ? 'disabled' : ''} id="uwPrevPage">←</button>
      <button ${nextDisabled ? 'disabled' : ''} id="uwNextPage">→</button>
    </div>
  `;

  document.getElementById('uwPrevPage')?.addEventListener('click', () => {
    if (usuariosWebState.page > 1) {
      usuariosWebState.page--;
      renderUsuariosWeb();
    }
  });

  document.getElementById('uwNextPage')?.addEventListener('click', () => {
    if (to < total) {
      usuariosWebState.page++;
      renderUsuariosWeb();
    }
  });
}


/* ==========================
   EVENTS
========================== */
function bindUsuariosWebEventsOnce() {
  if (document.body.dataset.usuariosWebBound === '1') return;
  document.body.dataset.usuariosWebBound = '1';

  document.getElementById('btnNovoUsuarioWeb')
    ?.addEventListener('click', novoUsuarioWeb);

  document.getElementById('uwSearch')
    ?.addEventListener('input', e => {
      usuariosWebState.q = e.target.value.trim();
      fetchUsuariosWeb();
    });

  document.getElementById('uwMinUsers')
    ?.addEventListener('input', e => {
      usuariosWebState.minUsers = e.target.value;
      fetchUsuariosWeb();
    });

  document.getElementById('uwStatus')
    ?.addEventListener('change', e => {
      usuariosWebState.status = e.target.value;
      fetchUsuariosWeb();
    });

  document.getElementById('uwLimit')
    ?.addEventListener('change', e => {
      usuariosWebState.limit = e.target.value === 'all'
        ? 'all'
        : Number(e.target.value);
      usuariosWebState.page = 1;
      renderUsuariosWeb();
    });

  /* Ordenação por coluna */
  document.querySelectorAll('#tblUsuariosWeb thead th[data-order]')
    .forEach(th => {
      th.addEventListener('click', () => {
        const col = th.dataset.order;

        usuariosWebState.orderDir =
          usuariosWebState.orderBy === col && usuariosWebState.orderDir === 'ASC'
            ? 'DESC'
            : 'ASC';

        usuariosWebState.orderBy = col;
        fetchUsuariosWeb();
      });
    });
}

/* ==========================
   MODAL / CRUD
========================== */
function novoUsuarioWeb() {
  openModal({
    title: 'Nova Empresa',
    entity: 'usuarios_web',
    fields: [
      { label: 'Empresa', name: 'nome_empresa', required: true },
      { label: 'Código', name: 'codigo_empresa', required: true },
      { label: 'Quantidade de Usuários', name: 'qtd_usuarios', type: 'number', required: true },
      { label: 'Observação', name: 'observacao', type: 'textarea' },
      {
        label: 'Status',
        name: 'status',
        type: 'select',
        value: 'ATIVO',
        options: [
          { value: 'ATIVO', label: 'ATIVO' },
          { value: 'INATIVO', label: 'INATIVO' }
        ]
      }
    ]
  });

  bindUsuarioWebSubmit();
}

async function editarUsuarioWeb(id) {
  const res = await apiFetch('backend/api_usuarios_web.php', {
    method: 'POST',
    body: { action: 'get', id }
  });

  if (!res.success) {
    showToast('Erro ao abrir registro', 'danger');
    return;
  }

  const r = res.row;

  openModal({
    title: 'Editar Empresa',
    entity: 'usuarios_web',
    id,
    fields: [
      { label: 'Empresa', name: 'nome_empresa', value: r.nome_empresa, required: true },
      { label: 'Código', name: 'codigo_empresa', value: r.codigo_empresa, required: true },
      { label: 'Quantidade de Usuários', name: 'qtd_usuarios', type: 'number', value: r.qtd_usuarios, required: true },
      { label: 'Observação', name: 'observacao', type: 'textarea', value: r.observacao },
      {
        label: 'Status',
        name: 'status',
        type: 'select',
        value: r.status,
        options: [
          { value: 'ATIVO', label: 'ATIVO' },
          { value: 'INATIVO', label: 'INATIVO' }
        ]
      }
    ]
  });

  bindUsuarioWebSubmit(id);
}

/* ==========================
   SUBMIT / DELETE
========================== */
function bindUsuarioWebSubmit(id = '') {
  modalForm.onsubmit = async e => {
    e.preventDefault();

    const data = {};
    modalForm.querySelectorAll('[name]').forEach(el => {
      data[el.name] = el.value;
    });

    const res = await apiFetch('backend/api_usuarios_web.php', {
      method: 'POST',
      body: { action: id ? 'update' : 'create', id, data }
    });

    if (!res.success) {
      showToast(res.message || 'Erro ao salvar', 'danger');
      return;
    }

    closeModal();
    showToast('Registro salvo com sucesso', 'success');
    fetchUsuariosWeb();
    loadUltimosUsuariosWeb();
  };
}

async function excluirUsuarioWeb(id) {
  if (!confirm('Excluir esta empresa?')) return;

  const res = await apiFetch('backend/api_usuarios_web.php', {
    method: 'POST',
    body: { action: 'delete', id }
  });

  if (!res.success) {
    showToast('Erro ao excluir', 'danger');
    return;
  }

  showToast('Registro removido', 'success');
  fetchUsuariosWeb();
  loadUltimosUsuariosWeb();
}

/* ==========================
   ÚLTIMOS USUÁRIOS WEB
========================== */
async function loadUltimosUsuariosWeb() {
  try {
    const res = await apiFetch('backend/api_usuarios_web.php', {
      method: 'POST',
      body: { action: 'last' }
    });

    if (!res.success) return;

    const tbody = document.querySelector('#tblUltimosUsuariosWeb tbody');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (!res.rows.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" style="text-align:center;opacity:.6">
            Nenhum registro encontrado
          </td>
        </tr>
      `;
      return;
    }

    res.rows.forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${r.id}</td>
        <td>${r.nome_empresa}</td>
        <td>${r.codigo_empresa}</td>
        <td><strong>${r.qtd_usuarios}</strong></td>
        <td>${r.observacao ?? '-'}</td>
        <td>
          <span class="badge ${r.status === 'ATIVO' ? 'success' : 'danger'}">
            ${r.status}
          </span>
        </td>
        <td>${formatDateTime(r.atualizado_em ?? r.criado_em)}</td>
      `;
      tbody.appendChild(tr);
    });

  } catch (e) {
    console.error(e);
  }
}

/* ==========================
   HELPERS
========================== */
function formatDateTime(dt) {
  if (!dt) return '-';
  return new Date(dt).toLocaleString('pt-BR');
}

/* ==========================
   EXPOSE
========================== */
window.loadUsuariosWeb = loadUsuariosWeb;
  