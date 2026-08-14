/* =========================================================
   PDV OFF - Módulo
   Painel Unificado Clientes Web (v4 FINAL)
   Autor: Anderson de Souza
   Feature: Ordenação por coluna
========================================================= */

let pdvOffState = {
  page:           1,
  pages:          1,
  limit:          10,
  q:              '',
  regiao:         [],
  versao_empresa: [],
  order_by:       'id',
  order_dir:      'DESC'
};

let msPdvOffRegiao    = null;
let msPdvOffVersaoEmp = null;

const PDVOFF_FILTER_KEY = 'filtros_pdvoff';

function _toArrPdv(v) {
  if (Array.isArray(v)) return v;
  if (v && typeof v === 'string') return [v];
  return [];
}

function savePdvOffFilters() {
  localStorage.setItem(PDVOFF_FILTER_KEY, JSON.stringify({
    q:              pdvOffState.q,
    regiao:         pdvOffState.regiao,
    versao_empresa: pdvOffState.versao_empresa,
    limit:          pdvOffState.limit
  }));
}

function loadPdvOffFilters() {
  try {
    const raw = localStorage.getItem(PDVOFF_FILTER_KEY);
    if (!raw) return;
    const d = JSON.parse(raw);
    if (typeof d.q === 'string') pdvOffState.q = d.q;
    pdvOffState.regiao         = _toArrPdv(d.regiao);
    pdvOffState.versao_empresa = _toArrPdv(d.versao_empresa);
    if (d.limit) pdvOffState.limit = Number(d.limit) || 10;
  } catch {}
}

function applyPdvOffFiltersToInputs() {
  const s = document.getElementById('pdvOffSearch');
  const l = document.getElementById('pdvOffLimit');
  if (s) s.value = pdvOffState.q;
  if (l) l.value = String(pdvOffState.limit);

  if (msPdvOffRegiao)    msPdvOffRegiao.setValues(pdvOffState.regiao);
  if (msPdvOffVersaoEmp) msPdvOffVersaoEmp.setValues(pdvOffState.versao_empresa);
}

function initPdvOffMultiSelects() {
  msPdvOffRegiao = new MultiSelect('pdvOffRegiao', {
    label:    'Região',
    options:  [],
    selected: pdvOffState.regiao,
    onChange: vals => {
      pdvOffState.regiao = vals;
      pdvOffState.page   = 1;
      savePdvOffFilters();
      fetchPdvOff();
    }
  });

  msPdvOffVersaoEmp = new MultiSelect('pdvOffVersaoEmp', {
    label:    'Versão Emp.',
    options:  [],
    selected: pdvOffState.versao_empresa,
    onChange: vals => {
      pdvOffState.versao_empresa = vals;
      pdvOffState.page           = 1;
      savePdvOffFilters();
      fetchPdvOff();
    }
  });
}

/* ==========================
   LOAD
========================== */
async function loadPdvOff() {
  const view = document.getElementById('view-pdvoff');
  if (!view) return;

  loadPdvOffFilters();

  if (!msPdvOffRegiao) initPdvOffMultiSelects();

  applyPdvOffFiltersToInputs();
  bindPdvOffEventsOnce();

  const sel = document.getElementById('pdvOffLimit');
  if (sel) {
    const v = sel.value;
    pdvOffState.limit = (v === 'all') ? 999999 : (Number(v) || 10);
  }

  await loadPdvOffOptions();
  applyPdvOffFiltersToInputs();
  await fetchPdvOff();
}

/* ==========================
   OPTIONS (popula MultiSelects)
========================== */
async function loadPdvOffOptions() {
  if (!msPdvOffRegiao || msPdvOffRegiao._optLoaded) return;

  const res = await apiFetch('backend/api_pdvoff.php', {
    method: 'POST',
    body: { action: 'options' }
  });

  if (!res.success) return;

  const optsR  = (res.regioes     || []).map(v => ({ value: v, label: v }));
  const optsVE = (res.versoes_emp || []).map(v => ({ value: v, label: v }));

  if (msPdvOffRegiao)    { msPdvOffRegiao.setOptions(optsR);     msPdvOffRegiao.setValues(pdvOffState.regiao); }
  if (msPdvOffVersaoEmp) { msPdvOffVersaoEmp.setOptions(optsVE); msPdvOffVersaoEmp.setValues(pdvOffState.versao_empresa); }

  msPdvOffRegiao._optLoaded = true;

}

/* ==========================
   FETCH
========================== */
async function fetchPdvOff() {
  try {
    const res = await apiFetch('backend/api_pdvoff.php', {
      method: 'POST',
      body: {
        action:         'list',
        page:           pdvOffState.page,
        limit:          pdvOffState.limit,
        q:              pdvOffState.q,
        regiao:         pdvOffState.regiao,
        versao_empresa: pdvOffState.versao_empresa,
        order_by:       pdvOffState.order_by,
        order_dir:      pdvOffState.order_dir
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
  const fetchPdvOffDebounced = debounce(fetchPdvOff, 350);
  document.addEventListener('input', (e) => {
    const t = e.target;
    if (t?.id === 'pdvOffSearch') {
      pdvOffState.q = t.value;
      pdvOffState.page = 1;
      savePdvOffFilters();
      fetchPdvOffDebounced();
    }
  });

  /* LIMIT */
  document.addEventListener('change', (e) => {
    const t = e.target;
    if (t?.id === 'pdvOffLimit') {
      const v = t.value;
      pdvOffState.limit = (v === 'all') ? 999999 : (Number(v) || 10);
      pdvOffState.page  = 1;
      savePdvOffFilters();
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
    tbody.innerHTML = `<tr><td colspan="10"><div class="table-empty"><i class="fas fa-inbox"></i><p>Nenhum registro encontrado</p></div></td></tr>`;
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
      <td>${esc(r.caixas ?? '') || '-'}</td>
      <td>
        ${esc(r.regiao) || '-'}
        ${cidadeEstado ? `<br><small class="muted">${cidadeEstado}</small>` : ''}
      </td>
      <td>${esc(r.versao_padrao) || '-'}</td>
      <td title="${esc(r.caminho_acesso ?? '')}">${esc(r.caminho_acesso) || '-'}</td>
      <td title="${esc(r.observacao ?? '')}">${esc(r.observacao) || '-'}</td>
      <td>
        ${badgeEmpresaStatus(r.empresa_status)}
        ${r.empresa_cnpj ? `<br><small class="muted" style="font-size:.72rem">${esc(maskCnpj(r.empresa_cnpj))}</small>` : ''}
      </td>
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
      { label: 'Região (empresa)', type: 'info', value: r.regiao || '—' },
      { label: 'Cidade / Estado (empresa)', type: 'info', value: [r.cidade, r.estado].filter(Boolean).join(' | ') || '—' },
      { label: 'Versão (empresa)', type: 'info', value: r.versao_padrao || '—' },
      { label: 'Caminho (empresa)', type: 'info', value: r.caminho_acesso || '—', full: true },
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
  const ok = await confirmDialog('Excluir este registro PDV OFF?', { title: 'Excluir PDV OFF' });
  if (!ok) return;

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
