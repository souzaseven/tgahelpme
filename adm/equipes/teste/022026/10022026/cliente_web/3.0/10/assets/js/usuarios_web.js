/* =========================================================
   Usuários Web — Módulo
   Painel Unificado Clientes Web
   Autor: Anderson de Souza
========================================================= */

/* ==========================
   STATE
========================== */
let usuariosWebState = {
  q:        '',
  minUsers: '',
  status:   'ATIVO',
  orderBy:  'qtd_usuarios',
  orderDir: 'DESC',
  page:     1,
  pages:    1,
  limit:    10
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
   Paginação agora é server-side: page + limit vão no body,
   o backend faz o LIMIT/OFFSET e devolve apenas a página pedida.
   Antes, a API devolvia todos os registros e o JS paginava
   no cliente — problemático quando a tabela crescer.
========================== */
async function fetchUsuariosWeb() {
  try {
    const res = await apiFetch('backend/api_usuarios_web.php', {
      body: {
        action:    'list',
        q:         usuariosWebState.q,
        min_users: usuariosWebState.minUsers,
        status:    usuariosWebState.status,
        order_by:  usuariosWebState.orderBy,
        order_dir: usuariosWebState.orderDir,
        page:      usuariosWebState.page,
        limit:     usuariosWebState.limit === 'all' ? 99999 : usuariosWebState.limit
      }
    });

    if (!res.success) throw new Error(res.message || 'Erro ao carregar Usuários Web');

    updateUsuariosWebResumo(res.stats || {});
    renderUsuariosWeb(res.rows || [], res.total ?? 0);

  } catch (err) {
    console.error(err);
    showToast(err.message || 'Erro ao carregar Usuários Web', 'danger');
  }
}

/* ==========================
   RESUMO (MINI CARDS)
========================== */
function updateUsuariosWebResumo(stats) {
  const map = {
    uwTotal:    stats.total,
    uwAtivos:   stats.ativos,
    uwInativos: stats.inativos,
    uwUsuarios: stats.total_usuarios
  };

  Object.entries(map).forEach(([id, val]) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val ?? 0;
  });
}

/* ==========================
   RENDER TABELA
========================== */
function renderUsuariosWeb(rows, total) {
  const tbody = document.querySelector('#tblUsuariosWeb tbody');
  if (!tbody) return;

  tbody.innerHTML = '';

  if (!rows.length) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" style="text-align:center;opacity:.6">
          Nenhum registro encontrado
        </td>
      </tr>`;
    renderPagination(0);
    return;
  }

  rows.forEach(r => {
    const tr = document.createElement('tr');

    // Campos de texto passam por esc() — evita XSS via dados do banco
    tr.innerHTML = `
      <td>${esc(r.codigo_empresa)}</td>
      <td>${esc(r.nome_empresa)}</td>
      <td class="text-center"><strong>${esc(r.qtd_usuarios)}</strong></td>
      <td title="${esc(r.observacao)}">${esc(r.observacao) || '-'}</td>
      <td class="text-center">
        <span class="badge ${r.status === 'ATIVO' ? 'success' : 'danger'}">
          ${esc(r.status)}
        </span>
      </td>
      <td class="text-center">
        ${formatDateTime(r.atualizado_em ?? r.criado_em)}
      </td>
      <td class="acoes">
        <button class="btn ghost" data-edit title="Editar">✏️</button>
        ${r.status === 'INATIVO'
          ? `<button class="btn ghost danger" data-del title="Excluir">🗑</button>`
          : ''}
      </td>
    `;

    tr.querySelector('[data-edit]')?.addEventListener('click', () => editarUsuarioWeb(r.id));
    tr.querySelector('[data-del]')?.addEventListener('click', () => excluirUsuarioWeb(r.id));

    tbody.appendChild(tr);
  });

  renderPagination(total);
}

/* ==========================
   PAGINAÇÃO
========================== */
function renderPagination(total) {
  const wrap = document.getElementById('uwPagination');
  if (!wrap) return;

  wrap.innerHTML = '';

  if (usuariosWebState.limit === 'all') {
    wrap.innerHTML = `<span class="pagination-info">Exibindo ${total} de ${total}</span>`;
    return;
  }

  const limit = usuariosWebState.limit;
  const page  = usuariosWebState.page;
  const from  = total === 0 ? 0 : (page - 1) * limit + 1;
  const to    = Math.min(page * limit, total);
  const pages = Math.max(1, Math.ceil(total / limit));

  usuariosWebState.pages = pages;

  wrap.innerHTML = `
    <div class="pagination-info">Exibindo ${from} até ${to} de ${total}</div>
    <div class="pagination-actions">
      <button id="uwPrevPage" ${page <= 1    ? 'disabled' : ''}>←</button>
      <button id="uwNextPage" ${page >= pages ? 'disabled' : ''}>→</button>
    </div>
  `;

  document.getElementById('uwPrevPage')?.addEventListener('click', () => {
    if (usuariosWebState.page > 1) {
      usuariosWebState.page--;
      fetchUsuariosWeb();
    }
  });

  document.getElementById('uwNextPage')?.addEventListener('click', () => {
    if (usuariosWebState.page < usuariosWebState.pages) {
      usuariosWebState.page++;
      fetchUsuariosWeb();
    }
  });
}

/* ==========================
   EVENTS (bind 1x)
========================== */
function bindUsuariosWebEventsOnce() {
  if (document.body.dataset.usuariosWebBound === '1') return;
  document.body.dataset.usuariosWebBound = '1';

  document.getElementById('btnNovoUsuarioWeb')
    ?.addEventListener('click', novoUsuarioWeb);

  document.getElementById('uwSearch')
    ?.addEventListener('input', e => {
      usuariosWebState.q    = e.target.value.trim();
      usuariosWebState.page = 1;
      fetchUsuariosWeb();
    });

  document.getElementById('uwMinUsers')
    ?.addEventListener('input', e => {
      usuariosWebState.minUsers = e.target.value;
      usuariosWebState.page     = 1;
      fetchUsuariosWeb();
    });

  document.getElementById('uwStatus')
    ?.addEventListener('change', e => {
      usuariosWebState.status = e.target.value;
      usuariosWebState.page   = 1;
      fetchUsuariosWeb();
    });

  document.getElementById('uwLimit')
    ?.addEventListener('change', e => {
      usuariosWebState.limit = e.target.value === 'all' ? 'all' : Number(e.target.value);
      usuariosWebState.page  = 1;
      fetchUsuariosWeb();
    });

  /* Ordenação por coluna */
  document.querySelectorAll('#tblUsuariosWeb thead th[data-order]').forEach(th => {
    th.addEventListener('click', () => {
      const col = th.dataset.order;
      usuariosWebState.orderDir =
        usuariosWebState.orderBy === col && usuariosWebState.orderDir === 'ASC'
          ? 'DESC' : 'ASC';
      usuariosWebState.orderBy  = col;
      usuariosWebState.page     = 1;
      fetchUsuariosWeb();
    });
  });
}

/* ==========================
   NOVO / EDITAR
========================== */
function novoUsuarioWeb() {
  openModal({
    title:  'Nova Empresa',
    entity: 'usuarios_web',
    fields: [
      { label: 'Empresa',                name: 'nome_empresa',  required: true },
      { label: 'Código',                 name: 'codigo_empresa', required: true },
      { label: 'Quantidade de Usuários', name: 'qtd_usuarios',  type: 'number', required: true },
      { label: 'Observação',             name: 'observacao',    type: 'textarea' },
      {
        label: 'Status', name: 'status', type: 'select', value: 'ATIVO',
        options: [{ value: 'ATIVO', label: 'ATIVO' }, { value: 'INATIVO', label: 'INATIVO' }]
      }
    ]
  });
  bindUsuarioWebSubmit();
}

async function editarUsuarioWeb(id) {
  const res = await apiFetch('backend/api_usuarios_web.php', {
    body: { action: 'get', id }
  });

  if (!res.success) { showToast('Erro ao abrir registro', 'danger'); return; }

  const r = res.row;

  openModal({
    title: 'Editar Empresa', entity: 'usuarios_web', id,
    fields: [
      { label: 'Empresa',                name: 'nome_empresa',  value: r.nome_empresa,  required: true },
      { label: 'Código',                 name: 'codigo_empresa', value: r.codigo_empresa, required: true },
      { label: 'Quantidade de Usuários', name: 'qtd_usuarios',  type: 'number', value: r.qtd_usuarios, required: true },
      { label: 'Observação',             name: 'observacao',    type: 'textarea', value: r.observacao },
      {
        label: 'Status', name: 'status', type: 'select', value: r.status,
        options: [{ value: 'ATIVO', label: 'ATIVO' }, { value: 'INATIVO', label: 'INATIVO' }]
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
    modalForm.querySelectorAll('[name]').forEach(el => { data[el.name] = el.value; });

    const res = await apiFetch('backend/api_usuarios_web.php', {
      body: { action: id ? 'update' : 'create', id, data }
    });

    if (!res.success) { showToast(res.message || 'Erro ao salvar', 'danger'); return; }

    closeModal();
    showToast('Registro salvo com sucesso', 'success');
    fetchUsuariosWeb();
    loadUltimosUsuariosWeb();
  };
}

async function excluirUsuarioWeb(id) {
  if (!confirm('Excluir esta empresa?')) return;

  const res = await apiFetch('backend/api_usuarios_web.php', {
    body: { action: 'delete', id }
  });

  if (!res.success) { showToast('Erro ao excluir', 'danger'); return; }

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
        </tr>`;
      return;
    }

    res.rows.forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${esc(r.id)}</td>
        <td>${esc(r.nome_empresa)}</td>
        <td>${esc(r.codigo_empresa)}</td>
        <td><strong>${esc(r.qtd_usuarios)}</strong></td>
        <td>${esc(r.observacao) || '-'}</td>
        <td>
          <span class="badge ${r.status === 'ATIVO' ? 'success' : 'danger'}">
            ${esc(r.status)}
          </span>
        </td>
        <td>${formatDateTime(r.atualizado_em ?? r.criado_em)}</td>
      `;
      tbody.appendChild(tr);
    });

  } catch (e) { console.error(e); }
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