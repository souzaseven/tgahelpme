/* =========================================================
   MOBILE FV / API - Módulo
   Painel Unificado Clientes Web (v3 FINAL)
   Autor: Anderson de Souza
========================================================= */

let mobileApiFvVersao = '';

let mobileState = {
  page:           1,
  pages:          1,
  limit:          10,
  q:              '',
  tipo:           [],
  versao_app:     [],
  regiao:         [],
  versao_empresa: [],
  order_by:       'id',
  order_dir:      'DESC'
};

let msMobileTipo      = null;
let msMobileVersaoApp = null;
let msMobileRegiao    = null;
let msMobileVersaoEmp = null;

const MOBILES_FILTER_KEY = 'filtros_mobiles';

function _toArrMob(v) {
  if (Array.isArray(v)) return v;
  if (v && typeof v === 'string') return [v];
  return [];
}

function saveMobilesFilters() {
  localStorage.setItem(MOBILES_FILTER_KEY, JSON.stringify({
    q:              mobileState.q,
    tipo:           mobileState.tipo,
    versao_app:     mobileState.versao_app,
    regiao:         mobileState.regiao,
    versao_empresa: mobileState.versao_empresa,
    limit:          mobileState.limit
  }));
}

function loadMobilesFilters() {
  try {
    const raw = localStorage.getItem(MOBILES_FILTER_KEY);
    if (!raw) return;
    const d = JSON.parse(raw);
    if (typeof d.q === 'string') mobileState.q = d.q;
    mobileState.tipo           = _toArrMob(d.tipo);
    mobileState.versao_app     = _toArrMob(d.versao_app);
    mobileState.regiao         = _toArrMob(d.regiao);
    mobileState.versao_empresa = _toArrMob(d.versao_empresa);
    if (d.limit) mobileState.limit = Number(d.limit) || 10;
  } catch {}
}

function applyMobilesFiltersToInputs() {
  const s = document.getElementById('mobileSearch');
  const l = document.getElementById('mobileLimit');
  if (s) s.value = mobileState.q;
  if (l) l.value = String(mobileState.limit);

  if (msMobileTipo)      msMobileTipo.setValues(mobileState.tipo);
  if (msMobileVersaoApp) msMobileVersaoApp.setValues(mobileState.versao_app);
  if (msMobileRegiao)    msMobileRegiao.setValues(mobileState.regiao);
  if (msMobileVersaoEmp) msMobileVersaoEmp.setValues(mobileState.versao_empresa);
}

function initMobileMultiSelects() {
  msMobileTipo = new MultiSelect('mobileTipo', {
    label:   'Tipo',
    options: [
      { value: 'FV_SMART_CLIENT',    label: 'FV Smart Client' },
      { value: 'API_FORCA_DE_VENDA', label: 'API Força de Venda' }
    ],
    selected: mobileState.tipo,
    onChange: vals => {
      mobileState.tipo = vals;
      mobileState.page = 1;
      saveMobilesFilters();
      fetchMobiles();
    }
  });

  msMobileVersaoApp = new MultiSelect('mobileVersaoApp', {
    label:    'Versão EXE',
    options:  [],
    selected: mobileState.versao_app,
    onChange: vals => {
      mobileState.versao_app = vals;
      mobileState.page       = 1;
      saveMobilesFilters();
      fetchMobiles();
    }
  });

  msMobileRegiao = new MultiSelect('mobileRegiao', {
    label:    'Região',
    options:  [],
    selected: mobileState.regiao,
    onChange: vals => {
      mobileState.regiao = vals;
      mobileState.page   = 1;
      saveMobilesFilters();
      fetchMobiles();
    }
  });

  msMobileVersaoEmp = new MultiSelect('mobileVersaoEmp', {
    label:    'Versão Emp.',
    options:  [],
    selected: mobileState.versao_empresa,
    onChange: vals => {
      mobileState.versao_empresa = vals;
      mobileState.page           = 1;
      saveMobilesFilters();
      fetchMobiles();
    }
  });
}

/* ==========================
   LOAD
========================== */
async function loadMobiles() {
  const view = document.getElementById('view-mobiles');
  if (!view) return;

  loadMobilesFilters();

  if (!msMobileTipo) initMobileMultiSelects();

  applyMobilesFiltersToInputs();
  bindMobilesEventsOnce();

  const sel = document.getElementById('mobileLimit');
  if (sel) {
    const v = Number(sel.value);
    if (!Number.isNaN(v) && v > 0) mobileState.limit = v;
  }

  await Promise.all([loadMobileApiFvVersao(), loadMobileOptions()]);
  applyMobilesFiltersToInputs();
  await fetchMobiles();
}

/* ==========================
   VERSÃO PADRÃO API FV
========================== */
async function loadMobileApiFvVersao() {
  const el = document.getElementById('mobApiFvVersao');
  if (!el || el.dataset.loaded) return;

  try {
    const res = await apiFetch('backend/api_config.php', {
      method: 'POST',
      body: { action: 'get', chave: 'mobile_apifv_versao' }
    });
    if (res.success) {
      mobileApiFvVersao = res.valor || '';
      el.textContent = mobileApiFvVersao || '(não definida)';
      el.dataset.loaded = '1';
    }
  } catch {}
}

function alterarVersaoApiFv() {
  openModal({
    title: 'Versão Padrão API Força de Venda',
    entity: 'config-apifv',
    fields: [
      {
        label: 'Versão padrão (aplicada a todos os clientes API FV sem versão individual)',
        name: 'versao',
        value: mobileApiFvVersao
      }
    ]
  });

  modalForm.onsubmit = async (e) => {
    e.preventDefault();
    const inp  = modalForm.querySelector('[name="versao"]');
    const valor = inp ? inp.value.trim() : '';

    const res = await apiFetch('backend/api_config.php', {
      method: 'POST',
      body: { action: 'set', chave: 'mobile_apifv_versao', valor }
    });

    if (!res.success) { showToast(res.message || 'Erro ao salvar', 'danger'); return; }

    mobileApiFvVersao = valor;
    const el = document.getElementById('mobApiFvVersao');
    if (el) el.textContent = mobileApiFvVersao || '(não definida)';

    /* Reseta o dropdown de versão para incluir o novo padrão */
    const selVA = document.getElementById('mobileVersaoApp');
    if (selVA) { selVA.dataset.loaded = ''; selVA.innerHTML = '<option value="">Versão EXE: Todas</option>'; }

    showToast('Versão padrão API FV atualizada', 'success');
    closeModal();
    await loadMobileOptions();
    applyMobilesFiltersToInputs();
    fetchMobiles();
  };
}

/* ==========================
   OPTIONS (popula MultiSelects)
========================== */
async function loadMobileOptions() {
  if (!msMobileVersaoApp || msMobileVersaoApp._optLoaded) return;

  const res = await apiFetch('backend/api_mobile.php', {
    method: 'POST',
    body: { action: 'options' }
  });

  if (!res.success) return;

  const optsVA  = (res.versoes_app || []).map(v => ({ value: v, label: v }));
  const optsR   = (res.regioes     || []).map(v => ({ value: v, label: v }));
  const optsVE  = (res.versoes_emp || []).map(v => ({ value: v, label: v }));

  if (msMobileVersaoApp) { msMobileVersaoApp.setOptions(optsVA); msMobileVersaoApp.setValues(mobileState.versao_app); }
  if (msMobileRegiao)    { msMobileRegiao.setOptions(optsR);     msMobileRegiao.setValues(mobileState.regiao); }
  if (msMobileVersaoEmp) { msMobileVersaoEmp.setOptions(optsVE); msMobileVersaoEmp.setValues(mobileState.versao_empresa); }

  msMobileVersaoApp._optLoaded = true;
}

async function fetchMobiles() {
  try {
    const res = await apiFetch('backend/api_mobile.php', {
      method: 'POST',
      body: {
        action:         'list',
        page:           mobileState.page,
        limit:          mobileState.limit,
        q:              mobileState.q,
        tipo:           mobileState.tipo,
        versao_app:     mobileState.versao_app,
        regiao:         mobileState.regiao,
        versao_empresa: mobileState.versao_empresa,
        order_by:       mobileState.order_by,
        order_dir:      mobileState.order_dir
      }
    });

    if (!res.success) {
      throw new Error(res.message || 'Erro ao carregar Mobile');
    }

    renderMobile(res.rows || []);
    updateMobilePager(res);
    updateMobileResumo(res.stats);

  } catch (e) {
    console.error(e);
    showToast(e.message || 'Erro ao carregar Mobile', 'danger');
  }
}

/* ==========================
   MINI CARDS
========================== */
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
   EVENTS (bind 1x)
========================== */
function bindMobilesEventsOnce() {
  if (document.body.dataset.mobilesBound === '1') return;
  document.body.dataset.mobilesBound = '1';

  /* ======================
     CLIQUES GERAIS
  ====================== */
  const btnAlterarApiFv = document.getElementById('btnAlterarVersaoApiFv');
  if (btnAlterarApiFv) btnAlterarApiFv.onclick = alterarVersaoApiFv;

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

    /* ======================
       ORDENAÇÃO (TH)
    ====================== */
    const th = t.closest('th[data-order]');
    if (th) {
      const field = th.dataset.order;

      if (!field) return;

      if (mobileState.order_by === field) {
        mobileState.order_dir =
          mobileState.order_dir === 'ASC' ? 'DESC' : 'ASC';
      } else {
        mobileState.order_by  = field;
        mobileState.order_dir = 'ASC';
      }

      mobileState.page = 1;
      fetchMobiles();
    }
  });

  /* ======================
     BUSCA
  ====================== */
  const fetchMobilesDebounced = debounce(fetchMobiles, 350);
  document.addEventListener('input', (e) => {
    const t = e.target;
    if (t?.id === 'mobileSearch') {
      mobileState.q = t.value;
      mobileState.page = 1;
      saveMobilesFilters();
      fetchMobilesDebounced();
    }
  });

  /* ======================
     LIMIT
  ====================== */
  document.addEventListener('change', (e) => {
    const t = e.target;
    if (t?.id === 'mobileLimit') {
      mobileState.limit = Number(t.value) || 10;
      mobileState.page  = 1;
      saveMobilesFilters();
      fetchMobiles();
    }
  });
}

/* ==========================
   RENDER TABELA
========================== */
function renderMobile(rows) {
  const tbody = document.querySelector('#tblMobile tbody');
  if (!tbody) return;

  tbody.innerHTML = '';

  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="12"><div class="table-empty"><i class="fas fa-inbox"></i><p>Nenhum registro encontrado</p></div></td></tr>`;
    return;
  }

  rows.forEach(r => {
    const tipoLabel =
      r.tipo_acesso === 'FV_SMART_CLIENT' ? 'FV Smart Client' :
      r.tipo_acesso === 'API_FORCA_DE_VENDA' ? 'API Força de Venda' :
      (r.tipo_acesso || '-');

    const cod    = esc(r.cod_cliente ?? '');
    const cli    = esc(r.cliente ?? '');
    const srv    = esc(r.acesso_server ?? '');
    const cidadeEstado = [r.cidade ? esc(r.cidade) : '', estadoComTooltip(r.estado)].filter(Boolean).join(' | ');

    const isApiFv = r.tipo_acesso === 'API_FORCA_DE_VENDA';
    const versaoCell = r.versao
      ? esc(r.versao)
      : (isApiFv && mobileApiFvVersao ? `<span class="muted">${esc(mobileApiFvVersao)}</span>` : '-');

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><span class="cell-with-copy">${cod}<button class="btn-copy" data-copy="${cod}" title="Copiar"><i class="fas fa-copy"></i></button></span></td>
      <td><span class="cell-with-copy">${cli}<button class="btn-copy" data-copy="${cli}" title="Copiar"><i class="fas fa-copy"></i></button></span></td>
      <td><span class="cell-with-copy">${srv || '-'}${srv ? `<button class="btn-copy" data-copy="${srv}" title="Copiar"><i class="fas fa-copy"></i></button>` : ''}</span></td>
      <td>${esc(r.porta ?? '') || '-'}</td>
      <td>${tipoLabel}</td>
      <td>${versaoCell}</td>
      <td>
        ${esc(r.regiao) || '-'}
        ${cidadeEstado ? `<br><small class="muted">${cidadeEstado}</small>` : ''}
      </td>
      <td>${esc(r.versao_padrao) || '-'}</td>
      <td title="${esc(r.caminho_acesso ?? '')}">${esc(r.caminho_acesso) || '-'}</td>
      <td>${esc(r.observacao) || '-'}</td>
      <td>
        ${badgeEmpresaStatus(r.empresa_status)}
        ${r.empresa_cnpj ? `<br><small class="muted" style="font-size:.72rem">${esc(maskCnpj(r.empresa_cnpj))}</small>` : ''}
      </td>
      <td>
        <button class="btn-row" data-action="edit" data-id="${r.id}">Editar</button>
        <button class="btn-row danger" data-action="del" data-id="${r.id}">Excluir</button>
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
  if (elInfo) {
    elInfo.textContent = `Exibindo ${res.count_page ?? 0} de ${res.total ?? 0}`;
  }

  const btnPrev = document.getElementById('mobilePrev');
  const btnNext = document.getElementById('mobileNext');

  if (btnPrev) btnPrev.disabled = mobileState.page <= 1;
  if (btnNext) btnNext.disabled = mobileState.page >= mobileState.pages;
}

/* ==========================
   NOVO / EDITAR
========================== */
function novoMobile() {
  const hintVersao = mobileApiFvVersao
    ? `Versão EXE (API FV: vazio = padrão: ${mobileApiFvVersao})`
    : 'Versão EXE (API FV: vazio = usa a versão padrão)';

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
      { label: hintVersao, name: 'versao' },
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
        {
          label: (r.tipo_acesso === 'API_FORCA_DE_VENDA' && mobileApiFvVersao)
            ? `Versão EXE (vazio = padrão: ${mobileApiFvVersao})`
            : 'Versão EXE (vazio = usa a versão padrão)',
          name: 'versao',
          value: r.versao
        },
        { label: 'Região (empresa)', type: 'info', value: r.regiao || '—' },
        { label: 'Cidade / Estado (empresa)', type: 'info', value: [r.cidade, r.estado].filter(Boolean).join(' | ') || '—' },
        { label: 'Versão (empresa)', type: 'info', value: r.versao_padrao || '—' },
        { label: 'Caminho (empresa)', type: 'info', value: r.caminho_acesso || '—', full: true },
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
   SUBMIT (CORRIGIDO)
========================== */
function bindMobileSubmit(id = '') {
  modalForm.onsubmit = async (e) => {
    e.preventDefault();

    const data = {};
    modalForm
      .querySelectorAll('input[name], select[name], textarea[name]')
      .forEach(el => {
        data[el.name] = el.value;
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
  const ok = await confirmDialog('Excluir este registro Mobile?', { title: 'Excluir Mobile' });
  if (!ok) return;

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
   EXPORT
========================== */
window.loadMobiles    = loadMobiles;
window.novoMobile    = novoMobile;
window.editarMobile  = editarMobile;
window.excluirMobile = excluirMobile;

/* ==========================
   BIND GLOBAL
========================== */
bindMobilesEventsOnce();
