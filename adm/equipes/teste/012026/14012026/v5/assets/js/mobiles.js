  /* =========================================================
    MOBILE FV / API - Módulo
    Painel Unificado Clientes Web (v3 FINAL)
    Autor: Anderson de Souza
  ========================================================= */

  let mobileState = {
    page: 1,
    pages: 1,
    limit: 10,
    q: '',
    tipo: ''
  };

  /* ==========================
    LOAD
  ========================== */
  async function loadMobiles() {
    const view = document.getElementById('view-mobiles');
    if (!view) return;

    // garante bind mesmo se HTML já existir
    bindMobilesEventsOnce();

    // sincroniza limit com select (se existir)
    const sel = document.getElementById('mobileLimit');
    if (sel) {
      const v = Number(sel.value);
      if (!Number.isNaN(v) && v > 0) mobileState.limit = v;
    }

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

    // 🔹 TABELA
    renderMobile(res.rows || []);

    // 🔹 PAGINAÇÃO
    updateMobilePager(res);

    // 🔹 MINI CARDS (AQUI ESTAVA FALTANDO)
    updateMobileResumo(res.stats);

  } catch (e) {
    console.error(e);
    showToast(e.message || 'Erro ao carregar Mobile', 'danger');
  }
}
function updateMobileResumo(stats) {
  if (!stats) return;

  const elTotal = document.getElementById('mobTotal');
  const elApi   = document.getElementById('mobApi');
  const elFv    = document.getElementById('mobFv');

  if (elTotal) elTotal.textContent = stats.total ?? 0;
  if (elApi)   elApi.textContent   = stats.api ?? 0;
  if (elFv)    elFv.textContent    = stats.fv ?? 0;
}


  /* ==========================
    EVENTS (1x)
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
        if (mobileState.page < mobileState.pages) {
          mobileState.page++;
          fetchMobiles();
        }
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

      // ✅ LIMIT (10 / 50 / 100 / tudo)
      if (t?.id === 'mobileLimit') {
        mobileState.limit = Number(t.value) || 10;
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
      tbody.innerHTML = `<tr><td colspan="7">Nenhum registro encontrado</td></tr>`;

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
<td>${r.porta ?? ''}</td>

        <td>${tipoLabel}</td>
        <td>${r.observacao || '-'}</td>
        <td>
          <button class="btn ghost" data-action="edit" data-id="${r.id}">✏️</button>
          <button class="btn ghost" data-action="del" data-id="${r.id}">🗑</button>
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
    mobileState.page  = res.page ?? mobileState.page;
    mobileState.pages = res.pages ?? 1;

    const elPage = document.getElementById('mobilePage');
    const elInfo = document.getElementById('mobileInfo');

    if (elPage) elPage.textContent = mobileState.page;
    if (elInfo) elInfo.textContent =
      `Exibindo ${res.count_page ?? 0} de ${res.total ?? 0}`;

    const btnPrev = document.getElementById('mobilePrev');
    const btnNext = document.getElementById('mobileNext');

    if (btnPrev) btnPrev.disabled = mobileState.page <= 1;
    if (btnNext) btnNext.disabled = mobileState.page >= mobileState.pages;
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
  { label: 'Porta', name: 'porta' },

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
  { label: 'Porta', name: 'porta', value: r.porta },

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
      modalForm.querySelectorAll('input[name], select[name]').forEach(i => {
        data[i.name] = i.value;
      });
    if (data.porta && isNaN(Number(data.porta))) {
      showToast('Porta deve ser numérica', 'warning');
      return;
    }
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
    EXPOR FUNÇÕES
  ========================== */
  window.loadMobiles   = loadMobiles;
  window.novoMobile   = novoMobile;
  window.editarMobile = editarMobile;
  window.excluirMobile = excluirMobile;

  /* ==========================
    BIND GLOBAL
  ========================== */
  bindMobilesEventsOnce();
