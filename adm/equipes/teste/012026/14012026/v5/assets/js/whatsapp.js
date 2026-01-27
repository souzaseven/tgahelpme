/* =========================================================
   WhatsApp - Módulo
   Painel Unificado Clientes Web (v3 FINAL)
   Autor: Anderson de Souza
   Correção DEFINITIVA: Paginação funcional
========================================================= */

let whatsState = {
  page: 1,
  pages: 1,
  limit: 10,
  q: ''
};

/* ==========================
   LOAD
========================== */
async function loadWhatsapp() {
  const view = document.getElementById('view-whatsapp');
  if (!view) return;

  bindWhatsEventsOnce();

  const sel = document.getElementById('whatsLimit');
  if (sel) {
    const v = Number(sel.value);
    if (!Number.isNaN(v) && v > 0) whatsState.limit = v;
  }

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

  } catch (err) {
    console.error(err);
    showToast(err.message || 'Erro ao carregar WhatsApp', 'danger');
  }
}

/* ==========================
   EVENTS (bind único)
========================== */
function bindWhatsEventsOnce() {
  if (document.body.dataset.whatsBound === '1') return;
  document.body.dataset.whatsBound = '1';

  const btnNovo   = document.getElementById('btnNovoWhats');
  const inpSearch = document.getElementById('whatsSearch');
  const btnPrev   = document.getElementById('whatsPrev');
  const btnNext   = document.getElementById('whatsNext');
  const selLimit  = document.getElementById('whatsLimit');

  if (btnNovo) btnNovo.onclick = novoWhats;

  if (inpSearch) {
    inpSearch.oninput = (e) => {
      whatsState.q = e.target.value;
      whatsState.page = 1;
      loadWhatsapp();
    };
  }

  if (selLimit) {
    selLimit.onchange = (e) => {
      whatsState.limit = Number(e.target.value) || 10;
      whatsState.page = 1;
      loadWhatsapp();
    };
  }

  if (btnPrev) {
    btnPrev.onclick = () => {
      if (whatsState.page > 1) {
        whatsState.page--;
        loadWhatsapp();
      }
    };
  }

  if (btnNext) {
    btnNext.onclick = () => {
      if (whatsState.page < whatsState.pages) {
        whatsState.page++;
        loadWhatsapp();
      }
    };
  }
}

/* ==========================
   RENDER
========================== */
function renderWhats(rows) {
  const tbody = document.querySelector('#tblWhats tbody');
  if (!tbody) return;

  tbody.innerHTML = '';

  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="5">Nenhum registro encontrado</td></tr>`;
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
        <button class="btn ghost" onclick="editarWhats(${r.id})">✏️</button>
        <button class="btn ghost" onclick="excluirWhats(${r.id})">🗑</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

/* ==========================
   PAGINAÇÃO (FRONTEND AUTÔNOMO)
========================== */
function updateWhatsPager(res) {
  const total = Number(res.total) || 0;

  // 🔥 cálculo REAL das páginas
  whatsState.pages = Math.max(
    1,
    Math.ceil(total / whatsState.limit)
  );

  const elPage = document.getElementById('whatsPage');
  const elInfo = document.getElementById('whatsInfo');

  if (elPage) elPage.textContent = whatsState.page;

  if (elInfo) {
    elInfo.textContent = `Exibindo ${res.count_page ?? 0} de ${total}`;
  }

  const btnPrev = document.getElementById('whatsPrev');
  const btnNext = document.getElementById('whatsNext');

  if (btnPrev) btnPrev.disabled = whatsState.page <= 1;
  if (btnNext) btnNext.disabled = whatsState.page >= whatsState.pages;
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
}

/* ==========================
   SUBMIT
========================== */
function bindWhatsSubmit(id = '') {
  modalForm.onsubmit = async e => {
    e.preventDefault();

    const data = {};
    modalForm.querySelectorAll('input[name], textarea[name]').forEach(i => {
      data[i.name] = i.value;
    });

    const res = await apiFetch('backend/api_whatsapp.php', {
      method: 'POST',
      body: {
        action: id ? 'update' : 'create',
        id,
        data
      }
    });

    if (!res.success) {
      showToast(res.message || 'Erro ao salvar', 'danger');
      return;
    }

    showToast('WhatsApp salvo com sucesso', 'success');
    closeModal();
    loadWhatsapp();
  };
}

/* ==========================
   DELETE
========================== */
async function excluirWhats(id) {
  if (!confirm('Excluir este registro WhatsApp?')) return;

  await apiFetch('backend/api_whatsapp.php', {
    method: 'POST',
    body: { action: 'delete', id }
  });

  showToast('Registro removido', 'success');
  loadWhatsapp();
}

/* ==========================
   EXPORT
========================== */
window.loadWhatsapp = loadWhatsapp;
window.editarWhats  = editarWhats;
window.excluirWhats = excluirWhats;
window.novoWhats    = novoWhats;
