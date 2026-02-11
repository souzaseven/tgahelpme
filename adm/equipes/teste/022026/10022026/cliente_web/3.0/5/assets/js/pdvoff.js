/* =========================================================
   PDV OFF - Módulo
   Painel Unificado Clientes Web (v4 FINAL)
   Autor: Anderson de Souza
   Feature: Ordenação por coluna
========================================================= */

let pdvOffState = {
  page: 1,
  pages: 1,
  limit: 10,
  q: '',
  order_by: 'id',
  order_dir: 'DESC'
};

/* ==========================
   LOAD
========================== */
async function loadPdvOff() {
  const view = document.getElementById('view-pdvoff');
  if (!view) return;

  bindPdvOffEventsOnce();

  const sel = document.getElementById('pdvOffLimit');
  if (sel) {
    const v = sel.value;
    pdvOffState.limit = (v === 'all') ? 999999 : (Number(v) || 10);
  }

  await fetchPdvOff();
}

/* ==========================
   FETCH
========================== */
async function fetchPdvOff() {
  try {
    const res = await apiFetch('backend/api_pdvoff.php', {
      method: 'POST',
      body: {
        action: 'list',
        page: pdvOffState.page,
        limit: pdvOffState.limit,
        q: pdvOffState.q,
        order_by: pdvOffState.order_by,
        order_dir: pdvOffState.order_dir
      }
    });

    if (!res.success) {
      throw new Error(res.message || 'Erro ao carregar PDV OFF');
    }

    renderPdvOff(res.rows || []);
    updatePdvOffPager(res);
    updatePdvOffSortIcons();

  } catch (e) {
    console.error(e);
    showToast(e.message || 'Erro ao carregar PDV OFF', 'danger');
  }
}

/* ==========================
   EVENTS (1x)
========================== */
function bindPdvOffEventsOnce() {
  if (document.body.dataset.pdvoffBound === '1') return;
  document.body.dataset.pdvoffBound = '1';

  /* CLIQUES */
  document.addEventListener('click', (e) => {
    const t = e.target;

    /* NOVO */
    if (t?.id === 'btnNovoPdvOff') {
      novoPdvOff();
      return;
    }

    /* PAGINAÇÃO */
    if (t?.id === 'pdvOffPrev') {
      if (pdvOffState.page > 1) {
        pdvOffState.page--;
        fetchPdvOff();
      }
      return;
    }

    if (t?.id === 'pdvOffNext') {
      if (pdvOffState.page < pdvOffState.pages) {
        pdvOffState.page++;
        fetchPdvOff();
      }
      return;
    }

    /* ORDENAÇÃO */
    const th = t.closest('th[data-order]');
    if (th) {
      const col = th.dataset.order;

      if (pdvOffState.order_by === col) {
        pdvOffState.order_dir =
          pdvOffState.order_dir === 'ASC' ? 'DESC' : 'ASC';
      } else {
        pdvOffState.order_by = col;
        pdvOffState.order_dir = 'ASC';
      }

      pdvOffState.page = 1;
      fetchPdvOff();
    }
  });

  /* BUSCA */
  document.addEventListener('input', (e) => {
    const t = e.target;
    if (t?.id === 'pdvOffSearch') {
      pdvOffState.q = t.value;
      pdvOffState.page = 1;
      fetchPdvOff();
    }
  });

  /* LIMIT */
  document.addEventListener('change', (e) => {
    const t = e.target;
    if (t?.id === 'pdvOffLimit') {
      const v = t.value;
      pdvOffState.limit = (v === 'all') ? 999999 : (Number(v) || 10);
      pdvOffState.page = 1;
      fetchPdvOff();
    }
  });
}

/* ==========================
   RENDER TABELA
========================== */
function renderPdvOff(rows) {
  const tbody = document.querySelector('#tblPdvOff tbody');
  if (!tbody) return;

  tbody.innerHTML = '';

  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="6">Nenhum registro encontrado</td></tr>`;
    return;
  }

  rows.forEach(r => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${r.cod_cliente}</td>
      <td>${r.cliente}</td>
      <td>${r.acesso_server || '-'}</td>
      <td>${r.caixas ?? '-'}</td>
      <td title="${r.observacao ?? ''}">${r.observacao || '-'}</td>
      <td>
        <button class="btn ghost" data-action="edit" data-id="${r.id}">✏️</button>
        <button class="btn ghost" data-action="del" data-id="${r.id}">🗑</button>
      </td>
    `;

    tr.querySelector('[data-action="edit"]')
      ?.addEventListener('click', () => editarPdvOff(r.id));
    tr.querySelector('[data-action="del"]')
      ?.addEventListener('click', () => excluirPdvOff(r.id));

    tbody.appendChild(tr);
  });
}

/* ==========================
   PAGINAÇÃO
========================== */
function updatePdvOffPager(res) {
  pdvOffState.page  = res.page ?? pdvOffState.page;
  pdvOffState.pages = res.pages ?? 1;

  const elPage = document.getElementById('pdvOffPage');
  const elInfo = document.getElementById('pdvOffInfo');

  if (elPage) elPage.textContent = pdvOffState.page;
  if (elInfo) elInfo.textContent = `Exibindo ${res.count_page ?? 0} de ${res.total ?? 0}`;

  const btnPrev = document.getElementById('pdvOffPrev');
  const btnNext = document.getElementById('pdvOffNext');

  if (btnPrev) btnPrev.disabled = pdvOffState.page <= 1;
  if (btnNext) btnNext.disabled = pdvOffState.page >= pdvOffState.pages;
}

/* ==========================
   ÍCONES DE ORDENAÇÃO
========================== */
function updatePdvOffSortIcons() {
  document.querySelectorAll('#tblPdvOff th[data-order]').forEach(th => {
    th.classList.remove('asc', 'desc');

    if (th.dataset.order === pdvOffState.order_by) {
      th.classList.add(
        pdvOffState.order_dir === 'ASC' ? 'asc' : 'desc'
      );
    }
  });
}

/* ==========================
   CRUD
========================== */
function novoPdvOff() {
  openModal({
    title: 'Novo PDV OFF',
    entity: 'pdvoff',
    fields: [
      { label: 'Código do Cliente', name: 'cod_cliente', required: true },
      { label: 'Cliente', name: 'cliente', required: true },
      { label: 'Servidor', name: 'acesso_server', value: 'AD' },
      { label: 'Quantidade de Caixas', name: 'caixas', type: 'number' },
      { label: 'Observação', name: 'observacao', type: 'textarea', full: true }
    ]
  });

  bindPdvOffSubmit();
}

async function editarPdvOff(id) {
  const res = await apiFetch('backend/api_pdvoff.php', {
    method: 'POST',
    body: { action: 'get', id }
  });

  if (!res.success) {
    showToast('Erro ao abrir registro', 'danger');
    return;
  }

  const r = res.row;

  openModal({
    title: 'Editar PDV OFF',
    entity: 'pdvoff',
    id,
    fields: [
      { label: 'Código do Cliente', name: 'cod_cliente', value: r.cod_cliente, required: true },
      { label: 'Cliente', name: 'cliente', value: r.cliente, required: true },
      { label: 'Servidor', name: 'acesso_server', value: r.acesso_server },
      { label: 'Quantidade de Caixas', name: 'caixas', type: 'number', value: r.caixas },
      { label: 'Observação', name: 'observacao', type: 'textarea', value: r.observacao, full: true }
    ]
  });

  bindPdvOffSubmit(id);
}

/* ==========================
   SUBMIT
========================== */
function bindPdvOffSubmit(id = '') {
  modalForm.onsubmit = async e => {
    e.preventDefault();

    const data = {};
    modalForm.querySelectorAll('[name]').forEach(el => {
      data[el.name] = (el.value ?? '').toString().trim();
    });

    try {
      const res = await apiFetch('backend/api_pdvoff.php', {
        method: 'POST',
        body: {
          action: id ? 'update' : 'create',
          id,
          data
        }
      });

      if (!res.success) throw new Error(res.message || 'Erro ao salvar');

      showToast('Registro PDV OFF salvo com sucesso', 'success');
      closeModal();
      fetchPdvOff();

    } catch (err) {
      console.error(err);
      showToast(err.message || 'Erro ao salvar', 'danger');
    }
  };
}

/* ==========================
   DELETE
========================== */
async function excluirPdvOff(id) {
  if (!confirm('Excluir este registro PDV OFF?')) return;

  try {
    const res = await apiFetch('backend/api_pdvoff.php', {
      method: 'POST',
      body: { action: 'delete', id }
    });

    if (!res.success) throw new Error(res.message || 'Falha ao excluir');

    showToast('Registro PDV OFF removido', 'success');
    fetchPdvOff();

  } catch (e) {
    console.error(e);
    showToast(e.message || 'Erro ao excluir', 'danger');
  }
}

/* ==========================
   EXPORT
========================== */
window.loadPdvOff    = loadPdvOff;
window.novoPdvOff    = novoPdvOff;
window.editarPdvOff  = editarPdvOff;
window.excluirPdvOff = excluirPdvOff;

/* ==========================
   BIND GLOBAL
========================== */
bindPdvOffEventsOnce();
