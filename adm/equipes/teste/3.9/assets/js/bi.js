/* =========================================================
   BI — Módulo
   Painel Unificado Clientes Web
   Autor: Anderson de Souza
========================================================= */

let biState = {
  page:           1,
  pages:          1,
  limit:          10,
  q:              '',
  regiao:         [],
  versao_empresa: [],
  order_by:       'id',
  order_dir:      'DESC'
};

let msBiRegiao    = null;
let msBiVersaoEmp = null;

const BI_FILTER_KEY = 'filtros_bi';

function _toArrBi(v) {
  if (Array.isArray(v)) return v;
  if (v && typeof v === 'string') return [v];
  return [];
}

function saveBiFilters() {
  localStorage.setItem(BI_FILTER_KEY, JSON.stringify({
    q:              biState.q,
    regiao:         biState.regiao,
    versao_empresa: biState.versao_empresa,
    limit:          biState.limit
  }));
}

function loadBiFilters() {
  try {
    const raw = localStorage.getItem(BI_FILTER_KEY);
    if (!raw) return;
    const d = JSON.parse(raw);
    if (typeof d.q === 'string') biState.q = d.q;
    biState.regiao         = _toArrBi(d.regiao);
    biState.versao_empresa = _toArrBi(d.versao_empresa);
    if (d.limit) biState.limit = Number(d.limit) || 10;
  } catch {}
}

function applyBiFiltersToInputs() {
  const s = document.getElementById('biSearch');
  const l = document.getElementById('biLimit');
  if (s) s.value = biState.q;
  if (l) l.value = String(biState.limit);

  if (msBiRegiao)    msBiRegiao.setValues(biState.regiao);
  if (msBiVersaoEmp) msBiVersaoEmp.setValues(biState.versao_empresa);
}

function initBiMultiSelects() {
  msBiRegiao = new MultiSelect('biRegiao', {
    label:    'Região',
    options:  [],
    selected: biState.regiao,
    onChange: vals => {
      biState.regiao = vals;
      biState.page   = 1;
      saveBiFilters();
      fetchBi();
    }
  });

  msBiVersaoEmp = new MultiSelect('biVersaoEmp', {
    label:    'Versão Emp.',
    options:  [],
    selected: biState.versao_empresa,
    onChange: vals => {
      biState.versao_empresa = vals;
      biState.page           = 1;
      saveBiFilters();
      fetchBi();
    }
  });
}

/* ==========================
   LOAD
========================== */
async function loadBi() {
  const view = document.getElementById('view-bi');
  if (!view) return;

  loadBiFilters();

  if (!msBiRegiao) initBiMultiSelects();

  applyBiFiltersToInputs();
  bindBiEventsOnce();

  const sel = document.getElementById('biLimit');
  if (sel) {
    const v = sel.value;
    biState.limit = (v === 'all') ? 999999 : (Number(v) || 10);
  }

  await loadBiOptions();
  applyBiFiltersToInputs();
  await fetchBi();
}

/* ==========================
   OPTIONS (popula MultiSelects)
========================== */
async function loadBiOptions() {
  if (!msBiRegiao || msBiRegiao._optLoaded) return;

  const res = await apiFetch('backend/api_bi.php', {
    body: { action: 'options' }
  });

  if (!res.success) return;

  const optsR  = (res.regioes     || []).map(v => ({ value: v, label: v }));
  const optsVE = (res.versoes_emp || []).map(v => ({ value: v, label: v }));

  if (msBiRegiao)    { msBiRegiao.setOptions(optsR);     msBiRegiao.setValues(biState.regiao); }
  if (msBiVersaoEmp) { msBiVersaoEmp.setOptions(optsVE); msBiVersaoEmp.setValues(biState.versao_empresa); }

  msBiRegiao._optLoaded = true;
}

/* ==========================
   FETCH
========================== */
async function fetchBi() {
  try {
    const res = await apiFetch('backend/api_bi.php', {
      body: {
        action:         'list',
        page:           biState.page,
        limit:          biState.limit,
        q:              biState.q,
        regiao:         biState.regiao,
        versao_empresa: biState.versao_empresa,
        order_by:       biState.order_by,
        order_dir:      biState.order_dir
      }
    });

    if (!res.success) throw new Error(res.message || 'Erro ao carregar BI');

    renderBi(res.rows || []);
    updateBiPager(res);
    updateBiSortIcons();

  } catch (e) {
    console.error(e);
    showToast(e.message || 'Erro ao carregar BI', 'danger');
  }
}

/* ==========================
   EVENTS (1x)
========================== */
function bindBiEventsOnce() {
  if (document.body.dataset.biBound === '1') return;
  document.body.dataset.biBound = '1';

  document.addEventListener('click', (e) => {
    const t = e.target;

    if (t?.id === 'btnNovoBi') { novoBi(); return; }

    if (t?.id === 'biPrev') {
      if (biState.page > 1) { biState.page--; fetchBi(); }
      return;
    }

    if (t?.id === 'biNext') {
      if (biState.page < biState.pages) { biState.page++; fetchBi(); }
      return;
    }

    const th = t.closest('th[data-order]');
    if (th && document.getElementById('tblBi')?.contains(th)) {
      const col = th.dataset.order;
      if (biState.order_by === col) {
        biState.order_dir = biState.order_dir === 'ASC' ? 'DESC' : 'ASC';
      } else {
        biState.order_by  = col;
        biState.order_dir = 'ASC';
      }
      biState.page = 1;
      fetchBi();
    }
  });

  const fetchBiDebounced = debounce(fetchBi, 350);
  document.addEventListener('input', (e) => {
    const t = e.target;
    if (t?.id === 'biSearch') {
      biState.q = t.value;
      biState.page = 1;
      saveBiFilters();
      fetchBiDebounced();
    }
  });

  document.addEventListener('change', (e) => {
    const t = e.target;
    if (t?.id === 'biLimit') {
      const v = t.value;
      biState.limit = (v === 'all') ? 999999 : (Number(v) || 10);
      biState.page = 1;
      saveBiFilters();
      fetchBi();
    }
  });
}

/* ==========================
   RENDER TABELA
========================== */
function renderBi(rows) {
  const tbody = document.querySelector('#tblBi tbody');
  if (!tbody) return;

  tbody.innerHTML = '';

  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="8"><div class="table-empty"><i class="fas fa-inbox"></i><p>Nenhum registro encontrado</p></div></td></tr>`;
    return;
  }

  rows.forEach(r => {
    const cod = esc(r.cod_cliente ?? '');
    const cli = esc(r.cliente ?? '');
    const srv = esc(r.acesso_server ?? '');
    const cidadeEstado = [r.cidade ? esc(r.cidade) : '', estadoComTooltip(r.estado)].filter(Boolean).join(' | ');

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><span class="cell-with-copy">${cod}<button class="btn-copy" data-copy="${cod}" title="Copiar"><i class="fas fa-copy"></i></button></span></td>
      <td><span class="cell-with-copy">${cli}<button class="btn-copy" data-copy="${cli}" title="Copiar"><i class="fas fa-copy"></i></button></span></td>
      <td><span class="cell-with-copy">${srv || '-'}${srv ? `<button class="btn-copy" data-copy="${srv}" title="Copiar"><i class="fas fa-copy"></i></button>` : ''}</span></td>
      <td>
        ${esc(r.regiao) || '-'}
        ${cidadeEstado ? `<br><small class="muted">${cidadeEstado}</small>` : ''}
      </td>
      <td>${esc(r.versao_padrao) || '-'}</td>
      <td>
        ${badgeEmpresaStatus(r.empresa_status)}
        ${r.empresa_cnpj ? `<br><small class="muted" style="font-size:.72rem">${esc(maskCnpj(r.empresa_cnpj))}</small>` : ''}
      </td>
      <td>${esc(r.atualizado_em) || esc(r.criado_em) || '-'}</td>
      <td>
        <button class="btn ghost" data-action="edit" data-id="${r.id}">✏️</button>
        <button class="btn ghost" data-action="del"  data-id="${r.id}">🗑</button>
      </td>
    `;

    tr.querySelector('[data-action="edit"]')
      ?.addEventListener('click', () => editarBi(r.id));
    tr.querySelector('[data-action="del"]')
      ?.addEventListener('click', () => excluirBi(r.id));

    tbody.appendChild(tr);
  });
}

/* ==========================
   PAGINAÇÃO
========================== */
function updateBiPager(res) {
  biState.page  = res.page  ?? biState.page;
  biState.pages = res.pages ?? 1;

  const elPage = document.getElementById('biPage');
  const elInfo = document.getElementById('biInfo');

  if (elPage) elPage.textContent = biState.page;
  if (elInfo) elInfo.textContent = `Exibindo ${res.count_page ?? 0} de ${res.total ?? 0}`;

  const btnPrev = document.getElementById('biPrev');
  const btnNext = document.getElementById('biNext');

  if (btnPrev) btnPrev.disabled = biState.page <= 1;
  if (btnNext) btnNext.disabled = biState.page >= biState.pages;
}

/* ==========================
   ÍCONES DE ORDENAÇÃO
========================== */
function updateBiSortIcons() {
  document.querySelectorAll('#tblBi th[data-order]').forEach(th => {
    th.classList.remove('asc', 'desc');
    if (th.dataset.order === biState.order_by) {
      th.classList.add(biState.order_dir === 'ASC' ? 'asc' : 'desc');
    }
  });
}

/* ==========================
   CRUD
========================== */
function novoBi() {
  openModal({
    title: 'Novo BI',
    entity: 'bi',
    fields: [
      { label: 'Código do Cliente', name: 'cod_cliente',   required: true },
      { label: 'Cliente',           name: 'cliente',       required: true },
      { label: 'Servidor',          name: 'acesso_server', value: 'AD' }
    ]
  });

  bindBiSubmit();
}

async function editarBi(id) {
  const res = await apiFetch('backend/api_bi.php', {
    body: { action: 'get', id }
  });

  if (!res.success) {
    showToast('Erro ao abrir registro', 'danger');
    return;
  }

  const r = res.row;

  openModal({
    title: 'Editar BI',
    entity: 'bi',
    id,
    fields: [
      { label: 'Código do Cliente',  name: 'cod_cliente',   value: r.cod_cliente,   required: true },
      { label: 'Cliente',            name: 'cliente',       value: r.cliente,       required: true },
      { label: 'Servidor',           name: 'acesso_server', value: r.acesso_server },
      { label: 'Região (empresa)',   type: 'info',          value: r.regiao        || '—' },
      { label: 'Cidade / Estado (empresa)', type: 'info',   value: [r.cidade, r.estado].filter(Boolean).join(' | ') || '—' },
      { label: 'Versão (empresa)',   type: 'info',          value: r.versao_padrao || '—' },
      { label: 'Caminho (empresa)',  type: 'info',          value: r.caminho_acesso || '—', full: true }
    ]
  });

  bindBiSubmit(id);
}

/* ==========================
   SUBMIT
========================== */
function bindBiSubmit(id = '') {
  modalForm.onsubmit = async e => {
    e.preventDefault();

    const data = {};
    modalForm.querySelectorAll('[name]').forEach(el => {
      data[el.name] = (el.value ?? '').toString().trim();
    });

    try {
      const res = await apiFetch('backend/api_bi.php', {
        body: { action: id ? 'update' : 'create', id, data }
      });

      if (!res.success) throw new Error(res.message || 'Erro ao salvar');

      showToast('Registro BI salvo com sucesso', 'success');
      closeModal();
      fetchBi();

    } catch (err) {
      console.error(err);
      showToast(err.message || 'Erro ao salvar', 'danger');
    }
  };
}

/* ==========================
   DELETE
========================== */
async function excluirBi(id) {
  const ok = await confirmDialog('Excluir este registro BI?', { title: 'Excluir BI' });
  if (!ok) return;

  try {
    const res = await apiFetch('backend/api_bi.php', {
      body: { action: 'delete', id }
    });

    if (!res.success) throw new Error(res.message || 'Falha ao excluir');

    showToast('Registro BI removido', 'success');
    fetchBi();

  } catch (e) {
    console.error(e);
    showToast(e.message || 'Erro ao excluir', 'danger');
  }
}

/* ==========================
   EXPORT
========================== */
window.loadBi    = loadBi;
window.novoBi    = novoBi;
window.editarBi  = editarBi;
window.excluirBi = excluirBi;

bindBiEventsOnce();
