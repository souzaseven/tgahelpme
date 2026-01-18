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

  if (!document.getElementById('tblWhats')) {
    view.innerHTML = `
      <div class="card">

        <div class="toolbar">
          <div class="search">
            <input id="whatsSearch"
                   placeholder="Buscar por código, cliente, servidor">
          </div>

          <div class="filters">
            <button class="btn primary" id="btnNovoWhats">
              ➕ Novo WhatsApp
            </button>
          </div>
        </div>

        <div class="table-wrap">
          <table class="table" id="tblWhats">
            <thead>
              <tr>
                <th>Código</th>
                <th>Cliente</th>
                <th>Servidor</th>
                <th>Observação</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

        <div class="pager">
          <span id="whatsInfo" class="muted"></span>
          <div>
            <button class="btn ghost" id="whatsPrev">←</button>
            <span class="badge" id="whatsPage">1</span>
            <button class="btn ghost" id="whatsNext">→</button>
          </div>
        </div>

      </div>
    `;

    bindWhatsEvents();
  }

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
    showToast('Erro ao carregar WhatsApp', 'danger');
    return;
  }

  renderWhats(res.rows || []);
  updateWhatsPager(res);
}

/* ==========================
   EVENTS
========================== */
function bindWhatsEvents() {
  document.getElementById('btnNovoWhats').onclick = novoWhats;

  document.getElementById('whatsSearch').oninput = e => {
    whatsState.q = e.target.value;
    whatsState.page = 1;
    loadWhatsapp();
  };

  document.getElementById('whatsPrev').onclick = () => {
    if (whatsState.page > 1) {
      whatsState.page--;
      loadWhatsapp();
    }
  };

  document.getElementById('whatsNext').onclick = () => {
    whatsState.page++;
    loadWhatsapp();
  };
}

/* ==========================
   RENDER
========================== */
function renderWhats(rows) {
  const tbody = document.querySelector('#tblWhats tbody');
  tbody.innerHTML = '';

  if (!rows.length) {
    tbody.innerHTML =
      `<tr><td colspan="5">Nenhum registro encontrado</td></tr>`;
    return;
  }

  rows.forEach(r => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${r.cod_cliente}</td>
      <td>${r.cliente}</td>
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
   PAGINAÇÃO
========================== */
function updateWhatsPager(res) {
  document.getElementById('whatsPage').textContent = res.page;
  document.getElementById('whatsInfo').textContent =
    `Exibindo ${res.count_page} de ${res.total}`;
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
      {
  label: 'Servidor',
  name: 'acesso_server',
  value: 'AD'
},
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
    modalForm.querySelectorAll('input[name]').forEach(i => {
      data[i.name] = i.value;
    });

    try {
      const res = await apiFetch('backend/api_whatsapp.php', {
        method: 'POST',
        body: {
          action: id ? 'update' : 'create',
          id,
          data
        }
      });

      if (!res.success) throw new Error(res.message);

      showToast('WhatsApp salvo com sucesso', 'success');
      closeModal();
      loadWhatsapp();

    } catch (err) {
      console.error(err);
      showToast(err.message || 'Erro ao salvar WhatsApp', 'danger');
    }
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
