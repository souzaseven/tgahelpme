/* =========================================================
   MOBILE FV / API - Módulo
   Painel Unificado Clientes Web (v3 FINAL)
   Autor: Anderson de Souza
========================================================= */

let mobileState = {
  page: 1,
  limit: 10,
  q: '',
  tipo: ''
};

/* ==========================
   INIT / LOAD
========================== */
async function loadMobiles() {
  const view = document.getElementById('view-mobiles');
  if (!view) return;

  if (!document.getElementById('tblMobile')) return;

  await fetchMobiles();
}

async function fetchMobiles() {
  try {
    const res = await apiFetch('backend/api_mobile.php', {
      method: 'POST',
      body: {
        action: 'list',
        page: mobileState.page,
        limit: mobileState.limit,
        q: mobileState.q,
        tipo: mobileState.tipo
      }
    });

    if (!res.success) {
      throw new Error(res.message || 'Erro ao carregar Mobile');
    }

    renderMobile(res.rows || []);
    updateMobilePager(res);

  } catch (e) {
    console.error(e);
    showToast(e.message || 'Erro ao carregar Mobile', 'danger');
  }
}

/* ==========================
   EVENTS (delegação global)
========================== */
function bindMobilesEventsOnce() {
  if (document.body.dataset.mobilesBound === '1') return;
  document.body.dataset.mobilesBound = '1';

  document.addEventListener('click', (e) => {
    const t = e.target;

    if (t?.id === 'btnNovoMobile') {
      novoMobile();
      return;
    }

    if (t?.id === 'mobilePrev') {
      if (mobileState.page > 1) {
        mobileState.page--;
        fetchMobiles();
      }
      return;
    }

    if (t?.id === 'mobileNext') {
      mobileState.page++;
      fetchMobiles();
      return;
    }
  });

  document.addEventListener('input', (e) => {
    const t = e.target;

    if (t?.id === 'mobileSearch') {
      mobileState.q = t.value;
      mobileState.page = 1;
      fetchMobiles();
    }
  });

  document.addEventListener('change', (e) => {
    const t = e.target;

    if (t?.id === 'mobileTipo') {
      mobileState.tipo = t.value;
      mobileState.page = 1;
      fetchMobiles();
    }

    /* ✅ LIMIT 10 / 50 / 100 / TUDO */
    if (t?.id === 'mobileLimit') {
      mobileState.limit = parseInt(t.value, 10) || 10;
      mobileState.page = 1;
      fetchMobiles();
    }
  });
}

/* ==========================
   RENDER
========================== */
function renderMobile(rows) {
  const tbody = document.querySelector('#tblMobile tbody');
  if (!tbody) return;

  tbody.innerHTML = '';

  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="6">Nenhum registro encontrado</td></tr>`;
    return;
  }

  rows.forEach(r => {
    const tipoLabel =
      r.tipo_acesso === 'FV_SMART_CLIENT' ? 'FV Smart Client' :
      r.tipo_acesso === 'API_FORCA_DE_VENDA' ? 'API Força de Venda' :
      (r.tipo_acesso || '-');

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${r.cod_cliente ?? ''}</td>
      <td>${r.cliente ?? ''}</td>
      <td>${r.acesso_server || '-'}</td>
      <td>${tipoLabel}</td>
      <td>${r.observacao || '-'}</td>
      <td>
        <button class="btn ghost" data-action="edit">✏️</button>
        <button class="btn ghost" data-action="del">🗑</button>
      </td>
    `;

    tr.querySelector('[data-action="edit"]')
      .addEventListener('click', () => editarMobile(r.id));

    tr.querySelector('[data-action="del"]')
      .addEventListener('click', () => excluirMobile(r.id));

    tbody.appendChild(tr);
  });
}

/* ==========================
   PAGINAÇÃO
========================== */
function updateMobilePager(res) {
  const elPage = document.getElementById('mobilePage');
  const elInfo = document.getElementById('mobileInfo');

  if (elPage) elPage.textContent = res.page ?? mobileState.page;
  if (elInfo) {
    elInfo.textContent =
      `Exibindo ${res.count_page ?? 0} de ${res.total ?? 0}`;
  }
}

/* ==========================
   NOVO / EDITAR
========================== */
function novoMobile() {
  openModal({
    title: 'Novo Mobile',
    entity: 'mobile',
    fields: [
      { label: 'Código do Cliente', name: 'cod_cliente', required: true },
      { label: 'Cliente', name: 'cliente', required: true },
      { label: 'Servidor', name: 'acesso_server' },
      {
        label: 'Tipo de Acesso',
        name: 'tipo_acesso',
        type: 'select',
        required: true,
        options: [
          { value: 'FV_SMART_CLIENT', label: 'FV Smart Client' },
          { value: 'API_FORCA_DE_VENDA', label: 'API Força de Venda' }
        ]
      },
      { label: 'Observação', name: 'observacao', full: true }
    ]
  });

  bindMobileSubmit();
}

async function editarMobile(id) {
  try {
    const res = await apiFetch('backend/api_mobile.php', {
      method: 'POST',
      body: { action: 'get', id }
    });

    if (!res.success) {
      showToast('Erro ao abrir registro', 'danger');
      return;
    }

    const r = res.row;

    openModal({
      title: 'Editar Mobile',
      entity: 'mobile',
      id,
      fields: [
        { label: 'Código do Cliente', name: 'cod_cliente', value: r.cod_cliente, required: true },
        { label: 'Cliente', name: 'cliente', value: r.cliente, required: true },
        { label: 'Servidor', name: 'acesso_server', value: r.acesso_server },
        {
          label: 'Tipo de Acesso',
          name: 'tipo_acesso',
          type: 'select',
          required: true,
          value: r.tipo_acesso,
          options: [
            { value: 'FV_SMART_CLIENT', label: 'FV Smart Client' },
            { value: 'API_FORCA_DE_VENDA', label: 'API Força de Venda' }
          ]
        },
        { label: 'Observação', name: 'observacao', value: r.observacao, full: true }
      ]
    });

    bindMobileSubmit(id);
  } catch (e) {
    console.error(e);
    showToast('Erro ao abrir registro', 'danger');
  }
}

/* ==========================
   SUBMIT
========================== */
function bindMobileSubmit(id = '') {
  modalForm.onsubmit = async (e) => {
    e.preventDefault();

    const data = {};
    modalForm.querySelectorAll('input[name], select[name], textarea[name]')
      .forEach(i => data[i.name] = i.value);

    try {
      const res = await apiFetch('backend/api_mobile.php', {
        method: 'POST',
        body: {
          action: id ? 'update' : 'create',
          id,
          data
        }
      });

      if (!res.success) throw new Error(res.message || 'Erro ao salvar');

      showToast('Mobile salvo com sucesso', 'success');
      closeModal();
      fetchMobiles();

    } catch (err) {
      console.error(err);
      showToast(err.message || 'Erro ao salvar Mobile', 'danger');
    }
  };
}

/* ==========================
   EXCLUIR
========================== */
async function excluirMobile(id) {
  if (!confirm('Excluir este registro Mobile?')) return;

  try {
    const res = await apiFetch('backend/api_mobile.php', {
      method: 'POST',
      body: { action: 'delete', id }
    });

    if (!res.success) throw new Error(res.message || 'Falha ao excluir');

    showToast('Registro Mobile removido', 'success');
    fetchMobiles();

  } catch (e) {
    console.error(e);
    showToast(e.message || 'Erro ao excluir', 'danger');
  }
}

/* ==========================
   EXPOR FUNÇÕES (segurança)
========================== */
window.loadMobiles = loadMobiles;
window.novoMobile = novoMobile;
window.editarMobile = editarMobile;
window.excluirMobile = excluirMobile;

/* ==========================
   BIND GLOBAL (1x)
========================== */
bindMobilesEventsOnce();
