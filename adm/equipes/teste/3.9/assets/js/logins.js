/* =========================================================
   Logins Web — Módulo
   Painel Unificado Clientes Web
   Autor: Anderson de Souza
========================================================= */

/* ==========================
   MÁSCARA CNPJ
========================== */
function maskCnpj(value) {
  const d = value.replace(/[^0-9]/g, '').slice(0, 14);
  if (d.length <= 2)  return d;
  if (d.length <= 5)  return d.slice(0, 2) + '.' + d.slice(2);
  if (d.length <= 8)  return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5);
  if (d.length <= 12) return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5, 8) + '/' + d.slice(8);
  return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5, 8) + '/' + d.slice(8, 12) + '-' + d.slice(12);
}

/* ==========================
   LAYOUT: integrações na lateral
   Move os campos já renderizados em #modalFields para um sub-grid
   e devolve um container lateral onde o bloco de Integrações entra.
========================== */
function criarSidebarIntegracoes() {
  const camposWrap = document.createElement('div');
  camposWrap.className = 'login-fields-grid';
  while (modalFields.firstChild) camposWrap.appendChild(modalFields.firstChild);
  modalFields.appendChild(camposWrap);
  modalFields.classList.add('has-sidebar');

  const sidebar = document.createElement('div');
  sidebar.className = 'integ-sidebar';
  modalFields.appendChild(sidebar);
  return sidebar;
}

function bindCnpjMask(input) {
  if (!input) return;
  input.addEventListener('input', function() {
    input.value = maskCnpj(input.value);
  });
}

/* ==========================
   STATE
========================== */
const LOGIN_FILTER_STORAGE_KEY = 'filtros_empresas_tga_web';

let loginState = {
  page:          1,
  pages:         1,
  limit:         10,
  q:             '',
  status:        [],
  versao:        [],
  regiao:        [],
  cidade:        [],
  estado:        [],
  exe:           [],
  integracao:    [],
  qtd_usuarios:  '',
  sortField:     '',
  sortDir:       'ASC'
};

/* instâncias MultiSelect para os filtros de Logins */
let msLoginStatus     = null;
let msLoginExe        = null;
let msLoginVersao     = null;
let msLoginRegiao     = null;
let msLoginCidade     = null;
let msLoginEstado     = null;
let msLoginIntegracao = null;

function _toArr(v) {
  if (Array.isArray(v)) return v;
  if (v && typeof v === 'string') return [v];
  return [];
}

function saveLoginFiltersToStorage() {
  const payload = {
    q:             loginState.q,
    status:        loginState.status,
    versao:        loginState.versao,
    regiao:        loginState.regiao,
    cidade:        loginState.cidade,
    estado:        loginState.estado,
    exe:           loginState.exe,
    integracao:    loginState.integracao,
    qtd_usuarios:  loginState.qtd_usuarios,
    limit:         loginState.limit
  };
  localStorage.setItem(LOGIN_FILTER_STORAGE_KEY, JSON.stringify(payload));
}

function loadLoginFiltersFromStorage() {
  try {
    const raw = localStorage.getItem(LOGIN_FILTER_STORAGE_KEY);
    if (!raw) return;
    const data = JSON.parse(raw);
    if (typeof data !== 'object' || data === null) return;

    if (typeof data.q === 'string') loginState.q = data.q;
    loginState.status     = _toArr(data.status);
    loginState.versao     = _toArr(data.versao);
    loginState.regiao     = _toArr(data.regiao);
    loginState.cidade     = _toArr(data.cidade);
    loginState.estado     = _toArr(data.estado);
    loginState.exe        = _toArr(data.exe);
    loginState.integracao = _toArr(data.integracao);
    if (typeof data.qtd_usuarios === 'string' || typeof data.qtd_usuarios === 'number') {
      loginState.qtd_usuarios = String(data.qtd_usuarios ?? '');
    }
    if (typeof data.limit === 'number' || typeof data.limit === 'string') {
      loginState.limit = Number(data.limit) || 10;
    }
  } catch (err) {
    console.warn('Falha ao carregar filtros de Logins Web', err);
  }
}

function applyLoginFiltersToInputs() {
  const search      = document.getElementById('loginSearch');
  const qtdUsuarios = document.getElementById('loginQtdUsuarios');
  const limit       = document.getElementById('loginLimit');

  if (search)      search.value      = loginState.q;
  if (qtdUsuarios) qtdUsuarios.value = loginState.qtd_usuarios || '';
  if (limit)       limit.value       = String(loginState.limit);

  if (msLoginStatus)     msLoginStatus.setValues(loginState.status);
  if (msLoginExe)        msLoginExe.setValues(loginState.exe);
  if (msLoginVersao)     msLoginVersao.setValues(loginState.versao);
  if (msLoginRegiao)     msLoginRegiao.setValues(loginState.regiao);
  if (msLoginCidade)     msLoginCidade.setValues(loginState.cidade);
  if (msLoginEstado)     msLoginEstado.setValues(loginState.estado);
  if (msLoginIntegracao) msLoginIntegracao.setValues(loginState.integracao);
}

function initLoginMultiSelects() {
  msLoginStatus = new MultiSelect('loginStatus', {
    label:   'Status',
    options: [
      { value: 'ATIVO',   label: 'ATIVO' },
      { value: 'INATIVO', label: 'INATIVO' }
    ],
    selected: loginState.status,
    onChange: vals => {
      loginState.status = vals;
      loginState.page   = 1;
      saveLoginFiltersToStorage();
      loadLogins();
    }
  });

  msLoginExe = new MultiSelect('loginExe', {
    label:   'EXE',
    options: [
      { value: '1', label: 'Com EXE' },
      { value: '0', label: 'Sem EXE' }
    ],
    selected: loginState.exe,
    onChange: vals => {
      loginState.exe  = vals;
      loginState.page = 1;
      saveLoginFiltersToStorage();
      loadLogins();
    }
  });

  msLoginVersao = new MultiSelect('loginVersao', {
    label:   'Versão',
    options: [],
    selected: loginState.versao,
    onChange: vals => {
      loginState.versao = vals;
      loginState.page   = 1;
      saveLoginFiltersToStorage();
      loadLogins();
    }
  });

  msLoginRegiao = new MultiSelect('loginRegiao', {
    label:   'Região',
    options: [],
    selected: loginState.regiao,
    onChange: vals => {
      loginState.regiao = vals;
      loginState.page   = 1;
      saveLoginFiltersToStorage();
      loadLogins();
    }
  });

  msLoginCidade = new MultiSelect('loginCidade', {
    label:   'Cidade',
    options: [],
    selected: loginState.cidade,
    onChange: vals => {
      loginState.cidade = vals;
      loginState.page   = 1;
      saveLoginFiltersToStorage();
      loadLogins();
    }
  });

  msLoginEstado = new MultiSelect('loginEstado', {
    label:   'Estado',
    options: [],
    selected: loginState.estado,
    onChange: vals => {
      loginState.estado = vals;
      loginState.page   = 1;
      saveLoginFiltersToStorage();
      loadLogins();
    }
  });

  msLoginIntegracao = new MultiSelect('loginIntegracao', {
    label:   'Integração',
    options: [
      { value: 'any',      label: 'Com alguma integração' },
      { value: 'none',     label: 'Sem integração' },
      { value: 'mobile',   label: 'Com Mobile' },
      { value: 'whatsapp', label: 'Com WhatsApp' },
      { value: 'pdvoff',   label: 'Com PDV OFF' },
      { value: 'bi',       label: 'Com BI' }
    ],
    selected: loginState.integracao,
    onChange: vals => {
      loginState.integracao = vals;
      loginState.page       = 1;
      saveLoginFiltersToStorage();
      loadLogins();
    }
  });
}

/* ==========================
   LOAD
========================== */
async function loadLogins() {
  try {
    const view = document.getElementById('view-logins');
    if (!view) return;

    loadLoginFiltersFromStorage();

    /* Inicializa MultiSelects apenas uma vez */
    if (!msLoginStatus) initLoginMultiSelects();

    bindLoginEventsOnce();
    applyLoginFiltersToInputs();
    bindTableSort();
    loadVersoes();
    loadRegioes();
    loadCidades();
    loadEstados();

    const selLimit = document.getElementById('loginLimit');
    if (selLimit) {
      const v = Number(selLimit.value);
      if (!Number.isNaN(v) && v > 0) loginState.limit = v;
    }

    const res = await apiFetch('backend/api_login.php', {
      body: {
        action:        'list',
        page:          loginState.page,
        limit:         loginState.limit,
        q:             loginState.q,
        status:        loginState.status,
        versao:        loginState.versao,
        regiao:        loginState.regiao,
        cidade:        loginState.cidade,
        estado:        loginState.estado,
        exe:           loginState.exe,
        integracao:    loginState.integracao,
        qtd_usuarios:  loginState.qtd_usuarios,
        sortField:     loginState.sortField,
        sortDir:       loginState.sortDir
      }
    });

    if (!res.success) throw new Error(res.message || 'Erro ao carregar logins');

    updateLoginResumo(res.stats);
    renderLogins(res.rows || []);
    updateLoginPager(res);

  } catch (err) {
    console.error(err);
    showToast('Erro ao carregar Logins Web', 'danger');
  }
}

/* ==========================
   RESUMO (MINI CARDS)
========================== */
function updateLoginResumo(stats) {
  if (!stats) return;

  const map = {
    statTotal:       `Total: ${stats.total    ?? 0}`,
    statAtivos:      `Ativos: ${stats.ativos  ?? 0}`,
    statInativos:    `Inativos: ${stats.inativos ?? 0}`,
    statExe:         `Com EXE: ${stats.com_exe ?? 0}`,
    statMobileFv:    `Com Mobile FV (Smart Client): ${stats.com_mobile_fv    ?? 0}`,
    statMobileForca: `Com Mobile Força de Vendas (API Web): ${stats.com_mobile_forca ?? 0}`,
    statWhatsapp:    `Com WhatsApp: ${stats.com_whatsapp ?? 0}`,
    statPdvoff:      `Com PDV OFF: ${stats.com_pdvoff ?? 0}`,
    statBi:          `Com BI: ${stats.com_bi ?? 0}`
  };

  Object.entries(map).forEach(([id, text]) => {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
  });
}

/* ==========================
   EVENTS (bind 1x)
========================== */
function bindLoginEventsOnce() {
  if (document.body.dataset.loginsBound === '1') return;
  document.body.dataset.loginsBound = '1';

  document.getElementById('btnNovoLogin').onclick = novoLogin;

  document.getElementById('loginSearch').oninput = debounce(e => {
    loginState.q    = e.target.value;
    loginState.page = 1;
    saveLoginFiltersToStorage();
    loadLogins();
  }, 350);

  document.getElementById('loginLimit').onchange = e => {
    loginState.limit = Number(e.target.value) || 10;
    loginState.page  = 1;
    saveLoginFiltersToStorage();
    loadLogins();
  };

  document.getElementById('loginPrev').onclick = () => {
    if (loginState.page > 1) { loginState.page--; loadLogins(); }
  };

  document.getElementById('loginNext').onclick = () => {
    if (loginState.page < loginState.pages) { loginState.page++; loadLogins(); }
  };

  const inputQtdUsuarios = document.getElementById('loginQtdUsuarios');
  if (inputQtdUsuarios) {
    inputQtdUsuarios.oninput = debounce(e => {
      loginState.qtd_usuarios = e.target.value;
      loginState.page         = 1;
      saveLoginFiltersToStorage();
      loadLogins();
    }, 400);
  }
}

/* ==========================
   ORDENAÇÃO POR COLUNA
========================== */
function bindTableSort() {
  if (document.body.dataset.loginsSortBound === '1') return;
  document.body.dataset.loginsSortBound = '1';

  document.querySelectorAll('#tblLogins th[data-sort]').forEach(th => {
    th.style.cursor = 'pointer';
    th.addEventListener('click', () => {
      const field = th.dataset.sort;
      if (loginState.sortField === field) {
        loginState.sortDir = loginState.sortDir === 'ASC' ? 'DESC' : 'ASC';
      } else {
        loginState.sortField = field;
        loginState.sortDir   = 'ASC';
      }
      loadLogins();
    });
  });
}

/* ==========================
   FILTRO "FANTASMA"
   Se um valor salvo (localStorage) não existe mais entre as opções
   carregadas do banco, o checkbox correspondente nunca é marcado —
   o chip mostra "Todos", mas o valor inválido continua sendo enviado
   ao backend e zera a listagem silenciosamente. Esta função remove
   valores inválidos do estado assim que as opções reais chegam.
========================== */
function podarFiltroInvalido(field, ms, valoresValidos) {
  const validSet = new Set(valoresValidos);
  const atual  = loginState[field];
  const podado = atual.filter(v => validSet.has(v));

  if (podado.length !== atual.length) {
    console.warn(`Filtro "${field}" tinha valor(es) inválido(s) — removendo:`, atual.filter(v => !validSet.has(v)));
    loginState[field] = podado;
    saveLoginFiltersToStorage();
    ms.setValues(podado);
    loadLogins();
    return true;
  }

  ms.setValues(atual);
  return false;
}

/* ==========================
   LOAD VERSÕES
========================== */
async function loadVersoes() {
  if (!msLoginVersao || msLoginVersao._versoesLoaded) return;

  const res = await apiFetch('backend/api_login.php', {
    body: { action: 'versions' }
  });

  if (!res.success) return;

  const opts = (res.versions || []).map(v => ({ value: v, label: v }));
  msLoginVersao.setOptions(opts);
  msLoginVersao._versoesLoaded = true;
  podarFiltroInvalido('versao', msLoginVersao, opts.map(o => o.value));
}

/* ==========================
   LOAD REGIÕES
========================== */
async function loadRegioes() {
  if (!msLoginRegiao || msLoginRegiao._regioesLoaded) return;

  const res = await apiFetch('backend/api_login.php', {
    body: { action: 'regions' }
  });

  if (!res.success) return;

  const opts = (res.regions || []).map(r => ({ value: r, label: r }));
  msLoginRegiao.setOptions(opts);
  msLoginRegiao._regioesLoaded = true;
  podarFiltroInvalido('regiao', msLoginRegiao, opts.map(o => o.value));
}

/* ==========================
   LOAD CIDADES
========================== */
async function loadCidades() {
  if (!msLoginCidade || msLoginCidade._cidadesLoaded) return;

  const res = await apiFetch('backend/api_login.php', {
    body: { action: 'cities' }
  });

  if (!res.success) return;

  const opts = [
    { value: '__none__', label: '— Sem cidade —' },
    ...(res.cities || []).map(c => ({ value: c, label: c }))
  ];
  msLoginCidade.setOptions(opts);
  msLoginCidade._cidadesLoaded = true;
  podarFiltroInvalido('cidade', msLoginCidade, opts.map(o => o.value));
}

/* ==========================
   LOAD ESTADOS
========================== */
async function loadEstados() {
  if (!msLoginEstado || msLoginEstado._estadosLoaded) return;

  const res = await apiFetch('backend/api_login.php', {
    body: { action: 'states' }
  });

  if (!res.success) return;

  const opts = [
    { value: '__none__', label: '— Sem estado —' },
    ...(res.states || []).map(e => ({ value: e, label: e }))
  ];
  msLoginEstado.setOptions(opts);
  msLoginEstado._estadosLoaded = true;
  podarFiltroInvalido('estado', msLoginEstado, opts.map(o => o.value));
}

/* ==========================
   RENDER TABELA
========================== */
function renderLogins(rows) {
  const tbody = document.querySelector('#tblLogins tbody');
  if (!tbody) return;

  tbody.innerHTML = '';

  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="12"><div class="table-empty"><i class="fas fa-inbox"></i><p>Nenhum registro encontrado</p></div></td></tr>`;
    return;
  }

  rows.forEach(r => {
    const tituloMobile   = ['Mobile',   r.info_mobile].filter(Boolean).join('\n');
    const tituloWhatsapp = ['WhatsApp', r.info_whatsapp].filter(Boolean).join('\n');
    const tituloPdvoff   = ['PDV OFF',  r.info_pdvoff].filter(Boolean).join('\n');
    const tituloBi       = ['BI',       r.info_bi].filter(Boolean).join('\n');

    const integracoes = [
      r.tem_mobile   == 1 ? `<i class="fas fa-mobile-alt"    style="color:#2196f3" title="${esc(tituloMobile)}"></i>`   : '',
      r.tem_whatsapp == 1 ? `<i class="fab fa-whatsapp"      style="color:#25D366" title="${esc(tituloWhatsapp)}"></i>` : '',
      r.tem_pdvoff   == 1 ? `<i class="fas fa-cash-register" style="color:#38bdf8" title="${esc(tituloPdvoff)}"></i>`   : '',
      r.tem_bi       == 1 ? `<i class="fas fa-chart-pie"     style="color:#a78bfa" title="${esc(tituloBi)}"></i>`       : ''
    ].filter(Boolean).join(' ');

    const tr = document.createElement('tr');
    const cnpjFormatado = r.cnpj ? maskCnpj(r.cnpj) : '';
    const obs = esc(r.observacao_usuario);
    const cidadeEstado = [r.cidade ? esc(r.cidade) : '', estadoComTooltip(r.estado)].filter(Boolean).join(' | ');
    tr.innerHTML = `
      <td><span class="cell-with-copy">${esc(r.codigo_cliente)}<button class="btn-copy" data-copy="${esc(r.codigo_cliente)}" title="Copiar"><i class="fas fa-copy"></i></button></span></td>
      <td><span class="cell-with-copy">${esc(r.nome_cliente)}<button class="btn-copy" data-copy="${esc(r.nome_cliente)}" title="Copiar"><i class="fas fa-copy"></i></button></span></td>
      <td><span class="cell-with-copy">${esc(cnpjFormatado) || '-'}${cnpjFormatado ? `<button class="btn-copy" data-copy="${esc(cnpjFormatado)}" title="Copiar CNPJ"><i class="fas fa-copy"></i></button>` : ''}</span></td>
      <td><span class="cell-with-copy">${esc(r.caminho_acesso) || '-'}${r.caminho_acesso ? `<button class="btn-copy" data-copy="${esc(r.caminho_acesso)}" title="Copiar"><i class="fas fa-copy"></i></button>` : ''}</span></td>
      <td>${esc(r.versao_padrao)  || '-'}</td>
      <td>
        ${esc(r.regiao) || '-'}
        ${cidadeEstado ? `<br><small class="muted">${cidadeEstado}</small>` : ''}
      </td>
      <td class="text-center">
        ${Number(r.qtd_usuarios_vinculados) > 0
          ? `<span class="badge info">${esc(r.qtd_usuarios_vinculados)}</span>`
          : '<span style="opacity:.3">—</span>'}
        ${obs ? `<br><small class="uw-obs" title="${obs}">${obs}</small>` : ''}
      </td>
      <td>
        ${r.possui_exe == 1
          ? `<span class="badge info">EXE</span><br><small>${esc(r.exe_nome)}</small>`
          : '-'}
      </td>
      <td class="integracoes-cell">${integracoes || '<span style="opacity:.3">—</span>'}</td>
      <td>
        <span class="badge ${r.status === 'ATIVO' ? 'success' : 'danger'}">
          ${esc(r.status)}
        </span>
      </td>
      <td><small class="muted">${esc(r.atualizado_em) || '-'}</small></td>
      <td>
        <button class="btn ghost" onclick="editarLogin(${r.id})">✏️</button>
        <button class="btn ghost" onclick="excluirLogin(${r.id})">🗑</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

/* ==========================
   PAGINAÇÃO
========================== */
function updateLoginPager(res) {
  const total = Number(res.total) || 0;

  loginState.pages = Math.max(1, Math.ceil(total / loginState.limit));

  const start = total === 0 ? 0 : (loginState.page - 1) * loginState.limit + 1;
  const end   = Math.min(loginState.page * loginState.limit, total);

  const elPage = document.getElementById('loginPage');
  const elInfo = document.getElementById('loginInfo');

  if (elPage) elPage.textContent = loginState.page;
  if (elInfo) elInfo.textContent = `Exibindo ${start} até ${end} de ${total}`;

  const btnPrev = document.getElementById('loginPrev');
  const btnNext = document.getElementById('loginNext');

  if (btnPrev) btnPrev.disabled = loginState.page <= 1;
  if (btnNext) btnNext.disabled = loginState.page >= loginState.pages;
}

/* ==========================
   NOVO LOGIN
========================== */
async function novoLogin() {
  openModal({
    title: 'Novo Login Web',
    entity: 'login',
    fields: [
      { label: 'Código do Cliente', name: 'codigo_cliente', required: true },
      { label: 'Nome do Cliente',   name: 'nome_cliente',   required: true },
      { label: 'CNPJ',              name: 'cnpj',           placeholder: '00.000.000/0000-00' },
      { label: 'Qtd. Usuários',     name: 'qtd_usuarios',   type: 'number', value: '0' },
      { label: 'Observação',        name: 'observacao',     type: 'textarea', full: true, placeholder: 'Informações adicionais sobre os usuários desta empresa...' },
      { label: 'Caminho de Acesso', name: 'caminho_acesso', value: 's:\\tga\\' },
      {
        label:   'Versão Padrão',
        name:    'versao_padrao_select',
        type:    'select',
        value:   '25.12',
        options: await getVersoesOptions()
      },
      { label: 'Versão (manual)', name: 'versao_padrao_manual', value: '', hidden: true },
      {
        label:   'Região',
        name:    'regiao_select',
        type:    'select',
        value:   '',
        options: await getRegioesOptions()
      },
      { label: 'Região (manual)', name: 'regiao_manual', value: '', hidden: true },
      { label: 'Cidade', name: 'cidade', placeholder: 'Ex: Tangará da Serra' },
      { label: 'Estado', name: 'estado', value: 'MT', placeholder: 'Ex: MT' },
      {
        label:   'Possui EXE?',
        name:    'possui_exe',
        type:    'select',
        value:   '0',
        options: [{ value: '0', label: 'NÃO' }, { value: '1', label: 'SIM' }]
      },
      { label: 'Nome do EXE', name: 'exe_nome', hidden: true },
      {
        label:   'Status',
        name:    'status',
        type:    'select',
        value:   'ATIVO',
        options: [{ value: 'ATIVO', label: 'ATIVO' }, { value: 'INATIVO', label: 'INATIVO' }]
      }
    ]
  });

  toggleVersaoManual();
  toggleRegiaoManual();
  toggleExeField();
  autoPreencherCaminhoPorCodigo();
  autoPreencherNomeClientePorCodigo();
  autoPreencherEstadoPorCidade();
  bindCnpjMask(modalForm.querySelector('[name="cnpj"]'));
  agruparRegiaoCidadeEstado();

  // Checkboxes de integrações (todos desmarcados no cadastro)
  const integSection = document.createElement('div');
  integSection.className = 'form-group full integ-checkboxes-group';
  integSection.innerHTML = `
    <label>Integrações</label>
    <div class="integ-checkboxes">
      <label class="integ-check-label">
        <input type="checkbox" name="integ_whatsapp">
        <i class="fab fa-whatsapp" style="color:#25D366"></i> WhatsApp
      </label>
      <label class="integ-check-label">
        <input type="checkbox" name="integ_mobile_on">
        <i class="fas fa-mobile-alt" style="color:#2196f3"></i> Mobile ON <small>(FV Smart)</small>
      </label>
      <label class="integ-check-label">
        <input type="checkbox" name="integ_mobile_off">
        <i class="fas fa-mobile-alt" style="color:#9e9e9e"></i> Mobile OFF <small>(API FV)</small>
      </label>
      <label class="integ-check-label">
        <input type="checkbox" name="integ_pdvoff">
        <i class="fas fa-cash-register" style="color:#38bdf8"></i> PDV OFF
      </label>
      <label class="integ-check-label">
        <input type="checkbox" name="integ_bi">
        <i class="fas fa-chart-pie" style="color:#a78bfa"></i> BI
      </label>
    </div>
    <div class="integ-porta-group" style="display:none; margin-top:10px">
      <label style="font-size:12px;color:rgba(234,242,255,.65);margin-bottom:4px;display:block">
        Porta — Mobile ON (FV Smart)
      </label>
      <input type="number" name="porta_mobile_on" placeholder="Ex: 8080" min="1" max="65535" style="width:150px">
    </div>
  `;
  criarSidebarIntegracoes().appendChild(integSection);
  bindPortaMobileOnToggle(integSection);

  bindLoginSubmit();
}

/* ==========================
   EDITAR LOGIN
========================== */
async function editarLogin(id) {
  const res = await apiFetch('backend/api_login.php', {
    body: { action: 'get', id }
  });

  if (!res.success) {
    showToast('Erro ao abrir registro', 'danger');
    return;
  }

  const r = res.row;

  const regioesOpts = await getRegioesOptions();
  const regiaoExisteNaLista = regioesOpts.some(o => o.value === r.regiao);

  const qtdUsers      = Number(r.qtd_usuarios_vinculados ?? 0);
  const numRegistros  = Number(r.qtd_registros_usuarios ?? 0);
  const podeEditar    = numRegistros <= 1;

  const campoUsuarios = podeEditar
    ? { label: 'Qtd. Usuários', name: 'qtd_usuarios', type: 'number', value: String(qtdUsers) }
    : { label: 'Usuários vinculados', name: '_qtd_usuarios', type: 'info',
        value: `👥 ${qtdUsers} (${numRegistros} registros — edite em Usuários Web)` };

  openModal({
    title:  'Editar Login Web',
    entity: 'login',
    id,
    fields: [
      { label: 'Código do Cliente', name: 'codigo_cliente', value: r.codigo_cliente, required: true },
      { label: 'Nome do Cliente',   name: 'nome_cliente',   value: r.nome_cliente,   required: true },
      { label: 'CNPJ',              name: 'cnpj',           value: r.cnpj ? maskCnpj(r.cnpj) : '', placeholder: '00.000.000/0000-00' },
      campoUsuarios,
      { label: 'Observação', name: 'observacao', type: 'textarea', full: true,
        value: r.observacao_usuario || '',
        placeholder: 'Informações adicionais sobre os usuários desta empresa...' },
      { label: 'Caminho de Acesso', name: 'caminho_acesso', value: r.caminho_acesso },
      {
        label:   'Versão Padrão',
        name:    'versao_padrao_select',
        type:    'select',
        value:   r.versao_padrao || '',
        options: await getVersoesOptions()
      },
      { label: 'Versão (manual)', name: 'versao_padrao_manual', value: '', hidden: true },
      {
        label:   'Região',
        name:    'regiao_select',
        type:    'select',
        value:   regiaoExisteNaLista ? (r.regiao || '') : (r.regiao ? '__manual__' : ''),
        options: regioesOpts
      },
      { label: 'Região (manual)', name: 'regiao_manual', value: regiaoExisteNaLista ? '' : (r.regiao || ''), hidden: regiaoExisteNaLista || !r.regiao },
      { label: 'Cidade', name: 'cidade', value: r.cidade || '', placeholder: 'Ex: Tangará da Serra' },
      { label: 'Estado', name: 'estado', value: r.estado || 'MT', placeholder: 'Ex: MT' },
      {
        label:   'Possui EXE?',
        name:    'possui_exe',
        type:    'select',
        value:   r.possui_exe == 1 ? '1' : '0',
        options: [{ value: '0', label: 'NÃO' }, { value: '1', label: 'SIM' }]
      },
      { label: 'Nome do EXE', name: 'exe_nome', value: r.exe_nome || '', hidden: r.possui_exe != 1 },
      {
        label:   'Status',
        name:    'status',
        type:    'select',
        value:   r.status || 'ATIVO',
        options: [{ value: 'ATIVO', label: 'ATIVO' }, { value: 'INATIVO', label: 'INATIVO' }]
      }
    ]
  });

  toggleExeField();
  toggleVersaoManual();
  toggleRegiaoManual();
  autoPreencherEstadoPorCidade(id);
  bindCnpjMask(modalForm.querySelector('[name="cnpj"]'));
  agruparRegiaoCidadeEstado();

  // Seção de checkboxes de integrações
  const portaAtual  = r.porta_mobile_on ? String(r.porta_mobile_on) : '';
  const monChecked  = r.tem_mobile_on == 1;

  const integSection = document.createElement('div');
  integSection.className = 'form-group full integ-checkboxes-group';
  integSection.innerHTML = `
    <label>Integrações</label>
    <div class="integ-checkboxes">
      <label class="integ-check-label">
        <input type="checkbox" name="integ_whatsapp" ${r.tem_whatsapp == 1 ? 'checked' : ''}>
        <i class="fab fa-whatsapp" style="color:#25D366"></i> WhatsApp
      </label>
      <label class="integ-check-label">
        <input type="checkbox" name="integ_mobile_on" ${monChecked ? 'checked' : ''}>
        <i class="fas fa-mobile-alt" style="color:#2196f3"></i> Mobile ON <small>(FV Smart)</small>
      </label>
      <label class="integ-check-label">
        <input type="checkbox" name="integ_mobile_off" ${r.tem_mobile_off == 1 ? 'checked' : ''}>
        <i class="fas fa-mobile-alt" style="color:#9e9e9e"></i> Mobile OFF <small>(API FV)</small>
      </label>
      <label class="integ-check-label">
        <input type="checkbox" name="integ_pdvoff" ${r.tem_pdvoff == 1 ? 'checked' : ''}>
        <i class="fas fa-cash-register" style="color:#38bdf8"></i> PDV OFF
      </label>
      <label class="integ-check-label">
        <input type="checkbox" name="integ_bi" ${r.tem_bi == 1 ? 'checked' : ''}>
        <i class="fas fa-chart-pie" style="color:#a78bfa"></i> BI
      </label>
    </div>
    <div class="integ-porta-group" style="display:${monChecked ? 'block' : 'none'}; margin-top:10px">
      <label style="font-size:12px;color:rgba(234,242,255,.65);margin-bottom:4px;display:block">
        Porta — Mobile ON (FV Smart)
      </label>
      <input type="number" name="porta_mobile_on" value="${esc(portaAtual)}" placeholder="Ex: 8080" min="1" max="65535" style="width:150px">
    </div>
    <small class="integ-hint">Marcar adiciona um registro inicial; desmarcar remove todos os registros da integração para esta empresa.</small>
  `;
  criarSidebarIntegracoes().appendChild(integSection);
  bindPortaMobileOnToggle(integSection);

  if (res.usuarios && res.usuarios.length > 0) {
    renderUsuariosEmpresaSection(res.usuarios);
  }

  bindLoginSubmit(id);
}

/* ==========================
   SUBMIT
========================== */
function bindLoginSubmit(id = '') {
  modalForm.onsubmit = async e => {
    e.preventDefault();

    const data = {};
    modalForm.querySelectorAll('[name]').forEach(el => { data[el.name] = el.value; });

    // Resolve versão: select normal ou entrada manual
    data.versao_padrao = data.versao_padrao_select === '__manual__'
      ? (data.versao_padrao_manual || '')
      : (data.versao_padrao_select || '');

    delete data.versao_padrao_select;
    delete data.versao_padrao_manual;

    // Resolve região: select normal ou entrada manual
    data.regiao = data.regiao_select === '__manual__'
      ? (data.regiao_manual || '')
      : (data.regiao_select || '');

    delete data.regiao_select;
    delete data.regiao_manual;

    // Coleta checkboxes de integração (cadastro e edição)
    const integracoesPayload = {
      whatsapp:        !!(modalForm.querySelector('[name="integ_whatsapp"]')?.checked),
      mobile_on:       !!(modalForm.querySelector('[name="integ_mobile_on"]')?.checked),
      mobile_off:      !!(modalForm.querySelector('[name="integ_mobile_off"]')?.checked),
      pdvoff:          !!(modalForm.querySelector('[name="integ_pdvoff"]')?.checked),
      bi:              !!(modalForm.querySelector('[name="integ_bi"]')?.checked),
      porta_mobile_on:   modalForm.querySelector('[name="porta_mobile_on"]')?.value || ''
    };

    try {
      const payload = { action: id ? 'update' : 'create', id, data, integracoes: integracoesPayload };

      const res = await apiFetch('backend/api_login.php', { body: payload });

      if (!res.success) throw new Error(res.message || 'Erro ao salvar');

      showToast('Login salvo com sucesso', 'success');
      closeModal();
      loadLogins();

    } catch (err) {
      console.error(err);
      showToast(err.message || 'Erro ao salvar login', 'danger');
    }
  };
}

/* ==========================
   EXCLUIR
========================== */
async function excluirLogin(id) {
  // 1. Verificar dados associados
  const check = await apiFetch('backend/api_login.php', { body: { action: 'delete_check', id } });
  if (!check.success) { showToast('Erro ao verificar empresa', 'danger'); return; }

  // Montar mensagem com lista de associações (se houver)
  let msg = `Excluir a empresa "${check.nome}"? Esta ação não pode ser desfeita.`;
  if (check.has_children) {
    const partes = [];
    if (check.qtd_usuarios > 0) partes.push(`${check.qtd_usuarios} usuário(s) vinculado(s)`);
    if (check.whatsapp)   partes.push('WhatsApp');
    if (check.mobile_on)  partes.push('Mobile ON');
    if (check.mobile_off) partes.push('Mobile OFF');
    if (check.pdvoff)     partes.push('PDV OFF');
    if (check.bi)         partes.push('BI');
    msg += ` Esta empresa possui: ${partes.join(', ')}.`;
  }

  // 2. Confirmar exclusão da empresa
  const ok = await confirmDialog(msg, { title: 'Excluir Empresa', confirmLabel: 'Excluir', danger: true });
  if (!ok) return;

  // 3. Se há associações, perguntar se quer excluir junto
  let deleteChildren = false;
  if (check.has_children) {
    deleteChildren = await confirmDialog(
      'Deseja excluir também os dados associados (usuários e integrações)?',
      { title: 'Excluir dados associados?', confirmLabel: 'Excluir tudo', danger: true }
    );
    // "Cancelar" = manter associações, apenas exclui a empresa
  }

  // 4. Executar exclusão
  const res = await apiFetch('backend/api_login.php', {
    body: { action: 'delete', id, delete_children: deleteChildren }
  });

  if (!res.success) {
    showToast(res.message || 'Erro ao excluir empresa', 'danger');
    return;
  }

  showToast('Empresa removida com sucesso', 'success');
  loadLogins();
}

/* ==========================
   HELPERS — MODAL
========================== */
async function getVersoesOptions() {
  const res = await apiFetch('backend/api_login.php', {
    body: { action: 'versions' }
  });

  if (!res.success) return [];

  return [
    { value: '',          label: '— Selecionar —' },
    ...res.versions.map(v => ({ value: v, label: v })),
    { value: '__manual__', label: '➕ Informar manualmente' }
  ];
}

async function getRegioesOptions() {
  const res = await apiFetch('backend/api_login.php', {
    body: { action: 'regions' }
  });

  if (!res.success) return [];

  return [
    { value: '',          label: '— Selecionar —' },
    ...res.regions.map(r => ({ value: r, label: r })),
    { value: '__manual__', label: '➕ Informar manualmente' }
  ];
}

function toggleVersaoManual() {
  const sel   = modalForm.querySelector('[name="versao_padrao_select"]');
  const input = modalForm.querySelector('[name="versao_padrao_manual"]');
  if (!sel || !input) return;

  const group = input.closest('.form-group');

  const apply = () => {
    const manual = sel.value === '__manual__';
    group.style.display = manual ? 'block' : 'none';
    input.required      = manual;
    input.value         = '';
    if (manual) input.focus();
  };

  sel.addEventListener('change', apply);
  apply();
}

function toggleRegiaoManual() {
  const sel   = modalForm.querySelector('[name="regiao_select"]');
  const input = modalForm.querySelector('[name="regiao_manual"]');
  if (!sel || !input) return;

  const group = input.closest('.form-group');

  const apply = () => {
    const manual = sel.value === '__manual__';
    group.style.display = manual ? 'block' : 'none';
    input.required      = manual;
    input.value         = '';
    if (manual) input.focus();
  };

  sel.addEventListener('change', apply);
  apply();
}

function toggleExeField() {
  const sel = modalForm.querySelector('[name="possui_exe"]');
  const exe = modalForm.querySelector('[name="exe_nome"]');
  if (!sel || !exe) return;

  const group = exe.closest('.form-group');

  const apply = () => {
    const tem = sel.value === '1';
    group.style.display = tem ? 'block' : 'none';
    exe.required        = tem;
    if (!tem) exe.value = '';
  };

  sel.addEventListener('change', apply);
  apply();
}

function autoPreencherCaminhoPorCodigo() {
  const codigo  = modalForm.querySelector('[name="codigo_cliente"]');
  const caminho = modalForm.querySelector('[name="caminho_acesso"]');
  if (!codigo || !caminho) return;

  const base = 's:\\tga\\';

  const aplicar = () => {
    const cod = codigo.value.trim();
    caminho.value = cod ? `${base}${cod}\\dados\\` : base;
  };

  codigo.addEventListener('input', aplicar);
  aplicar();
}

/* ==========================
   AUTO-PREENCHER "Nome do Cliente" com MAT / <código> /
   Só entra em ação enquanto o campo estiver vazio ou ainda
   contiver exatamente o prefixo gerado — assim que o usuário
   digitar o nome de fato, o auto-preenchimento para de mexer.
========================== */
function autoPreencherNomeClientePorCodigo() {
  const codigo = modalForm.querySelector('[name="codigo_cliente"]');
  const nome   = modalForm.querySelector('[name="nome_cliente"]');
  if (!codigo || !nome) return;

  const montarPrefixo = cod => cod ? `MAT / ${cod} / ` : '';
  let prefixoAnterior = montarPrefixo(codigo.value.trim());

  const aplicar = () => {
    const novoPrefixo = montarPrefixo(codigo.value.trim());
    if (nome.value === '' || nome.value === prefixoAnterior) {
      nome.value = novoPrefixo;
    }
    prefixoAnterior = novoPrefixo;
  };

  codigo.addEventListener('input', aplicar);
}

/* ==========================
   AUTO-PREENCHER "Estado" ao digitar uma cidade já cadastrada
   Ao sair do campo Cidade, verifica se essa cidade já existe em outro
   registro (ignorando maiúsc./minúsc.) e, se existir, copia o estado
   dela para o campo Estado — evita cadastrar a mesma cidade em estados
   diferentes por engano/digitação.
========================== */
function autoPreencherEstadoPorCidade(id = '') {
  const cidadeInput = modalForm.querySelector('[name="cidade"]');
  const estadoInput = modalForm.querySelector('[name="estado"]');
  if (!cidadeInput || !estadoInput) return;

  cidadeInput.addEventListener('blur', async () => {
    const cidade = cidadeInput.value.trim();
    if (!cidade) return;

    const res = await apiFetch('backend/api_login.php', {
      body: { action: 'estado_da_cidade', cidade, excluir_id: id || '' }
    });

    if (res.success && res.estado) {
      estadoInput.value = res.estado;
    }
  });
}

/* ==========================
   AGRUPAR Região | Cidade | Estado em uma linha só
   Move os 3 campos (já renderizados pelo openModal) para dentro de um
   wrapper .rce-row com flexbox próprio — assim a largura de cada um é
   controlada isoladamente (Região maior, Cidade média, Estado bem
   estreito), sem sofrer influência da grade principal do formulário.
   Precisa rodar ANTES de criarSidebarIntegracoes() mover os campos.
========================== */
function agruparRegiaoCidadeEstado() {
  const campoRegiao = modalForm.querySelector('[name="regiao_select"]')?.closest('.form-group');
  const campoCidade = modalForm.querySelector('[name="cidade"]')?.closest('.form-group');
  const campoEstado = modalForm.querySelector('[name="estado"]')?.closest('.form-group');
  if (!campoRegiao || !campoCidade || !campoEstado) return;

  const linha = document.createElement('div');
  linha.className = 'form-group full rce-row';

  campoRegiao.classList.add('rce-col', 'rce-col--regiao');
  campoCidade.classList.add('rce-col', 'rce-col--cidade');
  campoEstado.classList.add('rce-col', 'rce-col--estado');

  campoRegiao.parentNode.insertBefore(linha, campoRegiao);
  linha.appendChild(campoRegiao);
  linha.appendChild(campoCidade);
  linha.appendChild(campoEstado);
}

/* ==========================
   USUÁRIOS VINCULADOS — Toggle status
========================== */
function renderUsuariosEmpresaSection(usuarios) {
  const section = document.createElement('div');
  section.className = 'form-group full';
  section.id = 'secaoUsuariosEmpresa';

  const rows = usuarios.map(u => `
    <tr id="rowUser${u.id}">
      <td style="padding:6px 8px">${Number(u.qtd_usuarios)} usuário(s)</td>
      <td style="padding:6px 8px"><small>${esc(u.observacao || '—')}</small></td>
      <td style="padding:6px 8px">
        <span class="badge ${u.status === 'ATIVO' ? 'success' : 'danger'}" id="badgeUser${u.id}">
          ${esc(u.status)}
        </span>
      </td>
      <td style="padding:6px 8px">
        <button type="button" class="btn ghost" id="btnToggleUser${u.id}"
          onclick="toggleStatusUsuarioEmpresa(${u.id})">
          ${u.status === 'ATIVO' ? '⏸ Inativar' : '▶ Ativar'}
        </button>
      </td>
    </tr>
  `).join('');

  section.innerHTML = `
    <label style="margin-bottom:8px;display:block">👥 Usuários vinculados</label>
    <table style="width:100%;font-size:13px;border-collapse:collapse;background:rgba(255,255,255,.03);border-radius:6px;overflow:hidden">
      <thead>
        <tr style="border-bottom:1px solid rgba(255,255,255,.08)">
          <th style="text-align:left;padding:6px 8px;font-weight:600;color:rgba(234,242,255,.5);font-size:11px">Qtd.</th>
          <th style="text-align:left;padding:6px 8px;font-weight:600;color:rgba(234,242,255,.5);font-size:11px">Observação</th>
          <th style="text-align:left;padding:6px 8px;font-weight:600;color:rgba(234,242,255,.5);font-size:11px">Status</th>
          <th style="text-align:left;padding:6px 8px;font-weight:600;color:rgba(234,242,255,.5);font-size:11px">Ação</th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>
  `;

  modalFields.appendChild(section);
}

async function toggleStatusUsuarioEmpresa(userId) {
  const btn   = document.getElementById(`btnToggleUser${userId}`);
  const badge = document.getElementById(`badgeUser${userId}`);
  if (btn) btn.disabled = true;

  try {
    const res = await apiFetch('backend/api_login.php', {
      body: { action: 'toggle_usuario_status', id: userId }
    });
    if (!res.success) throw new Error(res.message || 'Erro ao alterar status');

    const novoStatus = res.novo_status;
    const ativo      = novoStatus === 'ATIVO';

    if (badge) {
      badge.textContent = novoStatus;
      badge.className   = `badge ${ativo ? 'success' : 'danger'}`;
    }
    if (btn) {
      btn.textContent = ativo ? '⏸ Inativar' : '▶ Ativar';
      btn.disabled    = false;
    }

    showToast(`Usuário ${ativo ? 'ativado' : 'inativado'} com sucesso`, 'success');

  } catch (err) {
    if (btn) btn.disabled = false;
    showToast(err.message || 'Erro ao alterar status', 'danger');
  }
}

function bindPortaMobileOnToggle(container) {
  const chk    = container.querySelector('[name="integ_mobile_on"]');
  const grupo  = container.querySelector('.integ-porta-group');
  const input  = container.querySelector('[name="porta_mobile_on"]');
  if (!chk || !grupo || !input) return;

  chk.addEventListener('change', () => {
    grupo.style.display = chk.checked ? 'block' : 'none';
    input.required      = chk.checked;
    if (!chk.checked) input.value = '';
  });
}