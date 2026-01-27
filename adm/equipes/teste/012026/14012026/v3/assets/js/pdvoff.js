/* =========================================================
   PDV OFF - Módulo
   Painel Unificado Clientes Web (v3 FINAL)
   Autor: Anderson de Souza
========================================================= */

let pdvOffState = {
  page: 1,
  limit: 10,
  q: ''
};

/* ==========================
   LOAD
========================== */
async function loadPdvOff() {
  const view = document.getElementById('view-pdvoff');
  if (!view) return;

  if (!document.getElementById('tblPdvOff')) return;

  await fetchPdvOff();
}

async function fetchPdvOff() {
  try {
    const res = await apiFetch('backend/api_pdvoff.php', {
      method: 'POST',
      body: {
        action: 'list',
        page: pdvOffState.page,
        limit: pdvOffState.limit,
        q: pdvOffState.q
      }
    });

    if (!res.success) {
      throw new Error(res.message || 'Erro ao carregar PDV OFF');
    }

    renderPdvOff(res.rows || []);
    updatePdvOffPager(res);

  } catch (e) {
    console.error(e);
    showToast(e.message || 'Erro ao carregar PDV OFF', 'danger');
  }
}

/* ==========================
   EVENTS (delegação global)
========================== */
function bindPdvOffEventsOnce() {
  if (document.body.dataset.pdvoffBound === '1') return;
  document.body.dataset.pdvoffBound = '1';

  document.addEventListener('click', (e) => {
    const t = e.target;

    if (t?.id === 'btnNovoPdvOff') {
      novoPdvOff();
      return;
    }

    if (t?.id === 'pdvOffPrev') {
      if (pdvOffState.page > 1) {
        pdvOffState.page--;
        fetchPdvOff();
      }
      return;
    }

    if (t?.id === 'pdvOffNext') {
      pdvOffState.page++;
      fetchPdvOff();
      return;
    }

    if (t?.dataset?.action === 'edit') {
      editarPdvOff(t.dataset.id);
      return;
    }

    if (t?.dataset?.action === 'del') {
      excluirPdvOff(t.dataset.id);
      return;
    }
  });

  document.addEventListener('input', (e) => {
    const t = e.target;
    if (t?.id === 'pdvOffSearch') {
      pdvOffState.q = t.value;
      pdvOffState.page = 1;
      fetchPdvOff();
    }
  });

  document.addEventListener('change', (e) => {
    const t = e.target;
    if (t?.id === 'pdvOffLimit') {
      pdvOffState.limit = parseInt(t.value, 10) || 10;
      pdvOffState.page = 1;
      fetchPdvOff();
    }
  });
}

/* ==========================
   RENDER
========================== */
function renderPdvOff(rows) {
  const tbody = document.querySelector('#tblPdvOff tbody');
  if (!tbody) return;

  tbody.innerHTML = '';

  if (!rows.length) {
    tbody.innerHTML =
      `<tr><td colspan="6">Nenhum registro encontrado</td></tr>`;
    return;
  }

  rows.forEach(r => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${r.cod_cliente ?? ''}</td>
      <td>${r.cliente ?? ''}</td>
      <td>${r.acesso_server || '-'}</td>
      <td>${r.caixas ?? '-'}</td>
      <td>${r.observacao || '-'}</td>
      <td>
        <button class="btn ghost" data-action="edit" data-id="${r.id}">✏️</button>
        <button class="btn ghost" data-action="del" data-id="${r.id}">🗑</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

/* ==========================
   PAGINAÇÃO
========================== */
function updatePdvOffPager(res) {
  const elPage = document.getElementById('pdvOffPage');
  const elInfo = document.getElementById('pdvOffInfo');

  if (elPage) elPage.textContent = res.page ?? pdvOffState.page;
  if (elInfo) {
    elInfo.textContent =
      `Exibindo ${res.count_page ?? 0} de ${res.total ?? 0}`;
  }
}

/* ==========================
   MODAL
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
      { label: 'Observação', name: 'observacao', full: true }
    ]
  });

  bindPdvOffSubmit();
}

async function editarPdvOff(id) {
  try {
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
        { label: 'Observação', name: 'observacao', value: r.observacao, full: true }
      ]
    });

    bindPdvOffSubmit(id);
  } catch (e) {
    console.error(e);
    showToast('Erro ao abrir registro', 'danger');
  }
}

/* ==========================
   SUBMIT
========================== */
function bindPdvOffSubmit(id = '') {
  modalForm.onsubmit = async (e) => {
    e.preventDefault();

    const data = {};
    modalForm
      .querySelectorAll('input[name], textarea[name]')
      .forEach(i => data[i.name] = i.value);

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

      showToast('PDV OFF salvo com sucesso', 'success');
      closeModal();
      fetchPdvOff();

    } catch (err) {
      console.error(err);
      showToast(err.message || 'Erro ao salvar PDV OFF', 'danger');
    }
  };
}

/* ==========================
   EXCLUIR
========================== */
async function excluirPdvOff(id) {
  if (!confirm('Excluir este registro PDV OFF?')) return;

  try {
    const res = await apiFetch('backend/api_pdvoff.php', {
      method: 'POST',
      body: { action: 'delete', id }
    });

    if (!res.success) throw new Error(res.message || 'Erro ao excluir');

    showToast('Registro PDV OFF removido', 'success');
    fetchPdvOff();

  } catch (e) {
    console.error(e);
    showToast(e.message || 'Erro ao excluir', 'danger');
  }
}

/* ==========================
   EXPOR
========================== */
window.loadPdvOff = loadPdvOff;
window.novoPdvOff = novoPdvOff;
window.editarPdvOff = editarPdvOff;
window.excluirPdvOff = excluirPdvOff;

/* ==========================
   BIND GLOBAL (1x)
========================== */
bindPdvOffEventsOnce();
