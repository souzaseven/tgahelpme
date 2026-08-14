/* =========================================================
   WhatsApp - Módulo
   Painel Unificado Clientes Web (v3 FINAL)
   Autor: Anderson de Souza
   Correção DEFINITIVA: Paginação funcional
========================================================= */

let whatsVersaoPadrao = '';

let whatsState = {
  page:           1,
  pages:          1,
  limit:          10,
  q:              '',
  versao_app:     [],
  regiao:         [],
  versao_empresa: [],
  order_by:       'id',
  order_dir:      'DESC'
};

let msWhatsVersaoApp = null;
let msWhatsRegiao    = null;
let msWhatsVersaoEmp = null;

const WHATS_FILTER_KEY = 'filtros_whatsapp';

function _toArrWhats(v) {
  if (Array.isArray(v)) return v;
  if (v && typeof v === 'string') return [v];
  return [];
}

function saveWhatsFilters() {
  localStorage.setItem(WHATS_FILTER_KEY, JSON.stringify({
    q:              whatsState.q,
    versao_app:     whatsState.versao_app,
    regiao:         whatsState.regiao,
    versao_empresa: whatsState.versao_empresa,
    limit:          whatsState.limit
  }));
}

function loadWhatsFilters() {
  try {
    const raw = localStorage.getItem(WHATS_FILTER_KEY);
    if (!raw) return;
    const d = JSON.parse(raw);
    if (typeof d.q === 'string') whatsState.q = d.q;
    whatsState.versao_app     = _toArrWhats(d.versao_app);
    whatsState.regiao         = _toArrWhats(d.regiao);
    whatsState.versao_empresa = _toArrWhats(d.versao_empresa);
    if (d.limit) whatsState.limit = Number(d.limit) || 10;
  } catch {}
}

function applyWhatsFiltersToInputs() {
  const s = document.getElementById('whatsSearch');
  const l = document.getElementById('whatsLimit');
  if (s) s.value = whatsState.q;
  if (l) l.value = String(whatsState.limit);

  if (msWhatsVersaoApp) msWhatsVersaoApp.setValues(whatsState.versao_app);
  if (msWhatsRegiao)    msWhatsRegiao.setValues(whatsState.regiao);
  if (msWhatsVersaoEmp) msWhatsVersaoEmp.setValues(whatsState.versao_empresa);
}

function initWhatsMultiSelects() {
  msWhatsVersaoApp = new MultiSelect('whatsVersaoApp', {
    label:    'Versão EXE',
    options:  [],
    selected: whatsState.versao_app,
    onChange: vals => {
      whatsState.versao_app = vals;
      whatsState.page       = 1;
      saveWhatsFilters();
      loadWhatsapp();
    }
  });

  msWhatsRegiao = new MultiSelect('whatsRegiao', {
    label:    'Região',
    options:  [],
    selected: whatsState.regiao,
    onChange: vals => {
      whatsState.regiao = vals;
      whatsState.page   = 1;
      saveWhatsFilters();
      loadWhatsapp();
    }
  });

  msWhatsVersaoEmp = new MultiSelect('whatsVersaoEmp', {
    label:    'Versão Emp.',
    options:  [],
    selected: whatsState.versao_empresa,
    onChange: vals => {
      whatsState.versao_empresa = vals;
      whatsState.page           = 1;
      saveWhatsFilters();
      loadWhatsapp();
    }
  });
}

/* ==========================
   LOAD
========================== */
async function loadWhatsapp() {
  const view = document.getElementById('view-whatsapp');
  if (!view) return;

  loadWhatsFilters();

  if (!msWhatsVersaoApp) initWhatsMultiSelects();

  applyWhatsFiltersToInputs();
  bindWhatsEventsOnce();

  const sel = document.getElementById('whatsLimit');
  if (sel) {
    const v = Number(sel.value);
    if (!Number.isNaN(v) && v > 0) whatsState.limit = v;
  }

  await Promise.all([loadWhatsVersaoPadrao(), loadWhatsOptions()]);
  applyWhatsFiltersToInputs();

  try {
    const res = await apiFetch('backend/api_whatsapp.php', {
      method: 'POST',
      body: {
        action:         'list',
        page:           whatsState.page,
        limit:          whatsState.limit,
        q:              whatsState.q,
        versao_app:     whatsState.versao_app,
        regiao:         whatsState.regiao,
        versao_empresa: whatsState.versao_empresa,
        order_by:       whatsState.order_by,
        order_dir:      whatsState.order_dir
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
   VERSÃO PADRÃO
========================== */
async function loadWhatsVersaoPadrao() {
  const el = document.getElementById('whatsVersaoPadraoDisplay');
  if (!el || el.dataset.loaded) return;

  try {
    const res = await apiFetch('backend/api_config.php', {
      method: 'POST',
      body: { action: 'get', chave: 'whatsapp_versao' }
    });
    if (res.success) {
      whatsVersaoPadrao = res.valor || '';
      el.textContent = whatsVersaoPadrao || '(não definida)';
      el.dataset.loaded = '1';
    }
  } catch {}
}

function alterarVersaoPadraoWhats() {
  openModal({
    title: 'Versão Padrão WhatsApp',
    entity: 'config-whats',
    fields: [
      {
        label: 'Versão padrão (aplicada a todos os clientes sem versão individual)',
        name: 'versao',
        value: whatsVersaoPadrao
      }
    ]
  });

  modalForm.onsubmit = async (e) => {
    e.preventDefault();
    const inp  = modalForm.querySelector('[name="versao"]');
    const valor = inp ? inp.value.trim() : '';

    const res = await apiFetch('backend/api_config.php', {
      method: 'POST',
      body: { action: 'set', chave: 'whatsapp_versao', valor }
    });

    if (!res.success) { showToast(res.message || 'Erro ao salvar', 'danger'); return; }

    whatsVersaoPadrao = valor;
    const el = document.getElementById('whatsVersaoPadraoDisplay');
    if (el) el.textContent = whatsVersaoPadrao || '(não definida)';

    /* Reseta o MultiSelect de versão para incluir o novo padrão */
    if (msWhatsVersaoApp) { msWhatsVersaoApp._optLoaded = false; }

    showToast('Versão padrão WhatsApp atualizada', 'success');
    closeModal();
    await loadWhatsOptions();
    applyWhatsFiltersToInputs();
    loadWhatsapp();
  };
}

/* ==========================
   OPTIONS (popula MultiSelects)
========================== */
async function loadWhatsOptions() {
  if (!msWhatsVersaoApp || msWhatsVersaoApp._optLoaded) return;

  const res = await apiFetch('backend/api_whatsapp.php', {
    method: 'POST',
    body: { action: 'options' }
  });

  if (!res.success) return;

  const optsVA = (res.versoes_app || []).map(v => ({ value: v, label: v }));
  const optsR  = (res.regioes     || []).map(v => ({ value: v, label: v }));
  const optsVE = (res.versoes_emp || []).map(v => ({ value: v, label: v }));

  if (msWhatsVersaoApp) { msWhatsVersaoApp.setOptions(optsVA); msWhatsVersaoApp.setValues(whatsState.versao_app); }
  if (msWhatsRegiao)    { msWhatsRegiao.setOptions(optsR);     msWhatsRegiao.setValues(whatsState.regiao); }
  if (msWhatsVersaoEmp) { msWhatsVersaoEmp.setOptions(optsVE); msWhatsVersaoEmp.setValues(whatsState.versao_empresa); }

  msWhatsVersaoApp._optLoaded = true;
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

  const btnAlterarVersao = document.getElementById('btnAlterarVersaoWhats');
  if (btnAlterarVersao) btnAlterarVersao.onclick = alterarVersaoPadraoWhats;

  if (inpSearch) {
    inpSearch.oninput = debounce((e) => {
      whatsState.q = e.target.value;
      whatsState.page = 1;
      saveWhatsFilters();
      loadWhatsapp();
    }, 350);
  }

  if (selLimit) {
    selLimit.onchange = (e) => {
      whatsState.limit = Number(e.target.value) || 10;
      whatsState.page = 1;
      saveWhatsFilters();
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

  /* ==========================
     ORDENAÇÃO POR COLUNA
  ========================== */
  document.addEventListener('click', (e) => {
    const th = e.target.closest('#tblWhats th[data-order]');
    if (!th) return;

    const col = th.dataset.order;

    if (whatsState.order_by === col) {
      whatsState.order_dir =
        whatsState.order_dir === 'ASC' ? 'DESC' : 'ASC';
    } else {
      whatsState.order_by  = col;
      whatsState.order_dir = 'ASC';
    }

    whatsState.page = 1;
    loadWhatsapp();
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
    tbody.innerHTML = `<tr><td colspan="10"><div class="table-empty"><i class="fas fa-inbox"></i><p>Nenhum registro encontrado</p></div></td></tr>`;
    return;
  }

  rows.forEach(r => {
    const cod = esc(r.cod_cliente ?? '');
    const cli = esc(r.cliente ?? '');
    const srv = esc(r.acesso_server ?? '');
    const cidadeEstado = [r.cidade ? esc(r.cidade) : '', estadoComTooltip(r.estado)].filter(Boolean).join(' | ');

    const versaoCell = r.versao
      ? esc(r.versao)
      : (whatsVersaoPadrao ? `<span class="muted">${esc(whatsVersaoPadrao)}</span>` : '-');

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><span class="cell-with-copy">${cod}<button class="btn-copy" data-copy="${cod}" title="Copiar"><i class="fas fa-copy"></i></button></span></td>
      <td><span class="cell-with-copy">${cli}<button class="btn-copy" data-copy="${cli}" title="Copiar"><i class="fas fa-copy"></i></button></span></td>
      <td><span class="cell-with-copy">${srv || '-'}${srv ? `<button class="btn-copy" data-copy="${srv}" title="Copiar"><i class="fas fa-copy"></i></button>` : ''}</span></td>
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
        <button class="btn-row" onclick="editarWhats(${r.id})">Editar</button>
        <button class="btn-row danger" onclick="excluirWhats(${r.id})">Excluir</button>
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
  const hintVersao = whatsVersaoPadrao
    ? `Versão EXE (vazio = padrão: ${whatsVersaoPadrao})`
    : 'Versão EXE (vazio = usa a versão padrão)';

  openModal({
    title: 'Novo WhatsApp',
    entity: 'whatsapp',
    fields: [
      { label: 'Código do Cliente', name: 'cod_cliente', required: true },
      { label: 'Cliente', name: 'cliente', required: true },
      { label: 'Servidor', name: 'acesso_server', value: 'AD' },
      { label: hintVersao, name: 'versao' },
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
      { label: whatsVersaoPadrao ? `Versão EXE (vazio = padrão: ${whatsVersaoPadrao})` : 'Versão EXE (vazio = usa a versão padrão)', name: 'versao', value: r.versao },
      { label: 'Região (empresa)', type: 'info', value: r.regiao || '—' },
      { label: 'Cidade / Estado (empresa)', type: 'info', value: [r.cidade, r.estado].filter(Boolean).join(' | ') || '—' },
      { label: 'Versão (empresa)', type: 'info', value: r.versao_padrao || '—' },
      { label: 'Caminho (empresa)', type: 'info', value: r.caminho_acesso || '—', full: true },
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
  const ok = await confirmDialog('Excluir este registro WhatsApp?', { title: 'Excluir WhatsApp' });
  if (!ok) return;

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
