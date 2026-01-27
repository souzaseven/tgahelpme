/* =========================================================
   WhatsApp - Módulo
   Painel Unificado Clientes Web (v3 FINAL)
   Autor: Anderson de Souza
========================================================= */

let whatsState = {
  page: 1,
  limit: 10,
  q: ''
};

/* ==========================
   LOAD
========================== */
async function loadWhatsapp() {
  const view = document.getElementById('view-whatsapp');
  if (!view) return;

  if (!document.getElementById('tblWhats')) return;

  await fetchWhats();
}

async function fetchWhats() {
  try {
    const res = await apiFetch('backend/api_whatsapp.php', {
      method: 'POST',
      body: {
        action: 'list',
        page: whatsState.page,
        limit: whatsState.limit,
        q: whatsState.q
      }
    });

    if (!res.success) {
      throw new Error(res.message || 'Erro ao carregar WhatsApp');
    }

    renderWhats(res.rows || []);
    updateWhatsPager(res);

  } catch (e) {
    console.error(e);
    showToast(e.message || 'Erro ao carregar WhatsApp', 'danger');
  }
}

/* ==========================
   EVENTS (delegação global)
========================== */
function bindWhatsEventsOnce() {
  if (document.body.dataset.whatsBound === '1') return;
  document.body.dataset.whatsBound = '1';

  document.addEventListener('click', (e) => {
    const t = e.target;

    if (t?.id === 'btnNovoWhats') {
      novoWhats();
      return;
    }

    if (t?.id === 'whatsPrev') {
      if (whatsState.page > 1) {
        whatsState.page--;
        fetchWhats();
      }
      return;
    }

    if (t?.id === 'whatsNext') {
      whatsState.page++;
      fetchWhats();
      return;
    }

    if (t?.dataset?.action === 'edit') {
      editarWhats(t.dataset.id);
      return;
    }

    if (t?.dataset?.action === 'del') {
      excluirWhats(t.dataset.id);
      return;
    }
  });

  document.addEventListener('input', (e) => {
    const t = e.target;
    if (t?.id === 'whatsSearch') {
      whatsState.q = t.value;
      whatsState.page = 1;
      fetchWhats();
    }
  });

  document.addEventListener('change', (e) => {
    const t = e.target;

    /* ✅ LIMIT 10 / 50 / 100 / TUDO */
    if (t?.id === 'whatsLimit') {
      whatsState.limit = parseInt(t.value, 10) || 10;
      whatsState.page = 1;
      fetchWhats();
    }
  });
}

/* ==========================
   RENDER
========================== */
function renderWhats(rows) {
  const tbody = document.querySelector('#tblWhats tbody');
  if (!tbody) return;

  tbody.innerHTML = '';

  if (!rows.length) {
    tbody.innerHTML =
      `<tr><td colspan="5">Nenhum registro encontrado</td></tr>`;
    return;
  }

  rows.forEach(r => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${r.cod_cliente ?? ''}</td>
      <td>${r.cliente ?? ''}</td>
      <td>${r.acesso_server || '-'}</td>
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
function updateWhatsPager(res) {
  const elPage = document.getElementById('whatsPage');
  const elInfo = document.getElementById('whatsInfo');

  if (elPage) elPage.textContent = res.page ?? whatsState.page;
  if (elInfo) {
    elInfo.textContent =
      `Exibindo ${res.count_page ?? 0} de ${res.total ?? 0}`;
  }
}

/* ==========================
   MODAL
========================== */
function novoWhats() {
  openModal({
    title: 'Novo WhatsApp',
    entity: 'whatsapp',
    fields: [
      { label: 'Código do Cliente', name: 'cod_cliente', required: true },
      { label: 'Cliente', name: 'cliente', required: true },
      { label: 'Servidor', name: 'acesso_server', value: 'AD' },
      { label: 'Observação', name: 'observacao', full: true }
    ]
  });

  bindWhatsSubmit();
}

async function editarWhats(id) {
  try {
    const res = await apiFetch('backend/api_whatsapp.php', {
      method: 'POST',
      body: { action: 'get', id }
    });

    if (!res.success) {
      showToast('Erro ao abrir registro', 'danger');
      return;
    }

    const r = res.row;

    openModal({
      title: 'Editar WhatsApp',
      entity: 'whatsapp',
      id,
      fields: [
        { label: 'Código do Cliente', name: 'cod_cliente', value: r.cod_cliente, required: true },
        { label: 'Cliente', name: 'cliente', value: r.cliente, required: true },
        { label: 'Servidor', name: 'acesso_server', value: r.acesso_server },
        { label: 'Observação', name: 'observacao', value: r.observacao, full: true }
      ]
    });

    bindWhatsSubmit(id);
  } catch (e) {
    console.error(e);
    showToast('Erro ao abrir registro', 'danger');
  }
}

/* ==========================
   SUBMIT
========================== */
function bindWhatsSubmit(id = '') {
  modalForm.onsubmit = async (e) => {
    e.preventDefault();

    const data = {};
    modalForm
      .querySelectorAll('input[name], textarea[name]')
      .forEach(i => data[i.name] = i.value);

    try {
      const res = await apiFetch('backend/api_whatsapp.php', {
        method: 'POST',
        body: {
          action: id ? 'update' : 'create',
          id,
          data
        }
      });

      if (!res.success) throw new Error(res.message || 'Erro ao salvar');

      showToast('WhatsApp salvo com sucesso', 'success');
      closeModal();
      fetchWhats();

    } catch (err) {
      console.error(err);
      showToast(err.message || 'Erro ao salvar WhatsApp', 'danger');
    }
  };
}

/* ==========================
   EXCLUIR
========================== */
async function excluirWhats(id) {
  if (!confirm('Excluir este registro WhatsApp?')) return;

  try {
    const res = await apiFetch('backend/api_whatsapp.php', {
      method: 'POST',
      body: { action: 'delete', id }
    });

    if (!res.success) throw new Error(res.message || 'Erro ao excluir');

    showToast('Registro removido', 'success');
    fetchWhats();

  } catch (e) {
    console.error(e);
    showToast(e.message || 'Erro ao excluir', 'danger');
  }
}

/* ==========================
   EXPOR FUNÇÕES
========================== */
window.loadWhatsapp = loadWhatsapp;
window.novoWhats = novoWhats;
window.editarWhats = editarWhats;
window.excluirWhats = excluirWhats;

/* ==========================
   BIND GLOBAL (1x)
========================== */
bindWhatsEventsOnce();
