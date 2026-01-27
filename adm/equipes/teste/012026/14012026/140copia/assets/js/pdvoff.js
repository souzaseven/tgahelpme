/* =========================================================
   PDV OFF - Módulo
   Painel Unificado Clientes Web (v3 FINAL)
   Autor: Anderson de Souza
========================================================= */

let pdvOffState = {
  page: 1,
  pages: 1,
  limit: 10,
  q: ''
};

/* ==========================
   LOAD
========================== */
async function loadPdvOff() {
  const view = document.getElementById('view-pdvoff');
  if (!view) return;

  // garante bind mesmo se HTML já existir
  bindPdvOffEventsOnce();

  // sincroniza limit com select (se existir)
  const sel = document.getElementById('pdvOffLimit');
  if (sel) {
    const v = Number(sel.value);
    if (!Number.isNaN(v) && v > 0) pdvOffState.limit = v;
  }

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
   EVENTS (1x)
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
      if (pdvOffState.page < pdvOffState.pages) {
        pdvOffState.page++;
        fetchPdvOff();
      }
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

    // ✅ LIMIT (10 / 50 / 100 / tudo)
    if (t?.id === 'pdvOffLimit') {
      pdvOffState.limit = Number(t.value) || 10;
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
      <td>${r.cod_cliente}</td>
      <td>${r.cliente}</td>
      <td>${r.acesso_server || '-'}</td>
      <td>${r.caixas ?? '-'}</td>
      <td>${r.observacao || '-'}</td>
      <td>
        <button class="btn ghost" data-action="edit" data-id="${r.id}">✏️</button>
        <button class="btn ghost" data-action="del" data-id="${r.id}">🗑</button>
      </td>
    `;

    tr.querySelector('[data-action="edit"]')
      .addEventListener('click', () => editarPdvOff(r.id));
    tr.querySelector('[data-action="del"]')
      .addEventListener('click', () => excluirPdvOff(r.id));

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
  if (elInfo) elInfo.textContent =
    `Exibindo ${res.count_page ?? 0} de ${res.total ?? 0}`;

  const btnPrev = document.getElementById('pdvOffPrev');
  const btnNext = document.getElementById('pdvOffNext');

  if (btnPrev) btnPrev.disabled = pdvOffState.page <= 1;
  if (btnNext) btnNext.disabled = pdvOffState.page >= pdvOffState.pages;
}

/* ==========================
   NOVO / EDITAR
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
}

/* ==========================
   SUBMIT
========================== */
function bindPdvOffSubmit(id = '') {
  modalForm.onsubmit = async e => {
    e.preventDefault();

    const data = {};
    modalForm.querySelectorAll('input[name]').forEach(i => {
      data[i.name] = i.value;
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
   EXCLUIR
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
   EXPOR FUNÇÕES
========================== */
window.loadPdvOff   = loadPdvOff;
window.novoPdvOff   = novoPdvOff;
window.editarPdvOff = editarPdvOff;
window.excluirPdvOff = excluirPdvOff;

/* ==========================
   BIND GLOBAL
========================== */
bindPdvOffEventsOnce();
