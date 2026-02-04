/* =========================================================
   Logins Web - Módulo
   Painel Unificado Clientes Web (v3 FINAL)
   Autor: Anderson de Souza
   Correção FINAL: eventos + paginação funcional
========================================================= */

/* ==========================
   STATE
========================== */
let loginState = {
  page: 1,
  pages: 1,
  limit: 10,
  q: '',
  status: '',
  versao: '',
 exe: ''
};
let sortField = '';
let sortDir   = 'ASC';
function bindTableSort() {
  if (document.body.dataset.loginsSortBound === '1') return;
  document.body.dataset.loginsSortBound = '1';

  document.querySelectorAll('#tblLogins th[data-sort]').forEach(th => {
    th.style.cursor = 'pointer';

    th.addEventListener('click', () => {
      const field = th.dataset.sort;

      if (sortField === field) {
        sortDir = sortDir === 'ASC' ? 'DESC' : 'ASC';
      } else {
        sortField = field;
        sortDir   = 'ASC';
      }

      loadLogins();
    });
  });
}

/* ==========================
   LOAD
========================== */
async function loadLogins() {
  try {
    const view = document.getElementById('view-logins');
    if (!view) return;

    bindLoginEventsOnce();
    bindTableSort();
    loadVersoes();

    const selLimit = document.getElementById('loginLimit');
    if (selLimit) {
      const v = Number(selLimit.value);
      if (!Number.isNaN(v) && v > 0) {
        loginState.limit = v;
      }
    }

    const res = await apiFetch('backend/api_login.php', {
      method: 'POST',
      body: {
        action: 'list',
        page: loginState.page,
        limit: loginState.limit,
        q: loginState.q,
        status: loginState.status,
        versao: loginState.versao,
        exe: loginState.exe,
        sortField,
        sortDir
      }
    });

    if (!res.success) {
      throw new Error(res.message || 'Erro ao carregar logins');
    }

    // ✅ ATUALIZA MINI RESUMO (Total / Ativos / Inativos / EXE)
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

  const elTotal    = document.getElementById('statTotal');
  const elAtivos   = document.getElementById('statAtivos');
  const elInativos = document.getElementById('statInativos');
  const elExe      = document.getElementById('statExe');

  if (elTotal)    elTotal.textContent    = `Total: ${stats.total ?? 0}`;
  if (elAtivos)   elAtivos.textContent   = `Ativos: ${stats.ativos ?? 0}`;
  if (elInativos) elInativos.textContent = `Inativos: ${stats.inativos ?? 0}`;
  if (elExe)      elExe.textContent      = `Com EXE: ${stats.com_exe ?? 0}`;
}



/* ==========================
   EVENTS (bind 1x)
========================== */
function bindLoginEventsOnce() {
  if (document.body.dataset.loginsBound === '1') return;
  document.body.dataset.loginsBound = '1';

  document.getElementById('btnNovoLogin').onclick = novoLogin;

  document.getElementById('loginSearch').oninput = e => {
    loginState.q = e.target.value;
    loginState.page = 1;
    loadLogins();
  };

  document.getElementById('loginStatus').onchange = e => {
    loginState.status = e.target.value;
    loginState.page = 1;
    loadLogins();
  };

  document.getElementById('loginVersao').onchange = e => {
    loginState.versao = e.target.value;
    loginState.page = 1;
    loadLogins();
  };

  document.getElementById('loginLimit').onchange = e => {
    loginState.limit = Number(e.target.value) || 10;
    loginState.page = 1;
    loadLogins();
  };

  document.getElementById('loginPrev').onclick = () => {
    if (loginState.page > 1) {
      loginState.page--;
      loadLogins();
    }
  };

const selExe = document.getElementById('loginExe');
if (selExe) {
  selExe.onchange = e => {
    loginState.exe = e.target.value;
    loginState.page = 1;
    loadLogins();
  };
}



  document.getElementById('loginNext').onclick = () => {
    if (loginState.page < loginState.pages) {
      loginState.page++;
      loadLogins();
    }

  };
}

/* ==========================
   LOAD VERSÕES
========================== */
async function loadVersoes() {
  const select = document.getElementById('loginVersao');
  if (!select || select.dataset.loaded) return;

  const res = await apiFetch('backend/api_login.php', {
    method: 'POST',
    body: { action: 'versions' }
  });

  if (!res.success) return;

  res.versions.forEach(v => {
    const opt = document.createElement('option');
    opt.value = v;
    opt.textContent = v;
    select.appendChild(opt);
  });

  select.dataset.loaded = '1';
}

/* ==========================
   RENDER
========================== */
function renderLogins(rows) {
  const tbody = document.querySelector('#tblLogins tbody');
  if (!tbody) return;

  tbody.innerHTML = '';

  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="8">Nenhum registro encontrado</td></tr>`;
    return;
  }

  rows.forEach(r => {
    const ativo = r.status === 'ATIVO';

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${r.codigo_cliente}</td>
      <td>${r.nome_cliente}</td>
      <td>${r.caminho_acesso || '-'}</td>
      <td>${r.versao_padrao || '-'}</td>

      <td>
        ${
          r.possui_exe == 1
            ? `<span class="badge info">EXE</span><br>
               <small>${r.exe_nome}</small>`
            : '-'
        }
      </td>

      <td>
        <span class="badge ${ativo ? 'success' : 'danger'}">
          ${r.status}
        </span>
      </td>

      <!-- ✅ NOVA COLUNA -->
      <td>
        <small class="muted">
          ${r.atualizado_em || '-'}
        </small>
      </td>

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

  loginState.pages = Math.max(
    1,
    Math.ceil(total / loginState.limit)
  );

  const start = total === 0
    ? 0
    : (loginState.page - 1) * loginState.limit + 1;

  const end = Math.min(
    loginState.page * loginState.limit,
    total
  );

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
   NOVO
==========================
function novoLogin() {
  openModal({
    title: 'Novo Login Web',
    entity: 'login',
    fields: [
      { label: 'Código do Cliente', name: 'codigo_cliente', required: true },
      { label: 'Nome do Cliente', name: 'nome_cliente', required: true },
      { label: 'Caminho de Acesso', name: 'caminho_acesso' },
      { label: 'Versão Padrão', name: 'versao_padrao' },
      { label: 'Status', name: 'status', value: 'ATIVO' }
    ]
  });

  bindLoginSubmit();
} */
async function novoLogin() {
  openModal({
    title: 'Novo Login Web',
    entity: 'login',
    fields: [
      {
        label: 'Código do Cliente',
        name: 'codigo_cliente',
        required: true
      },

      {
        label: 'Nome do Cliente',
        name: 'nome_cliente',
        required: true
      },

      {
        label: 'Caminho de Acesso',
        name: 'caminho_acesso',
        value: 's:\\tga\\' 
      },

      {
        label: 'Versão Padrão',
        name: 'versao_padrao_select',
        type: 'select',
        value: '25.12', // ✅ default
        options: await getVersoesOptions()
      },

      {
        label: 'Versão (manual)',
        name: 'versao_padrao_manual',
        value: '',
        hidden: true 
      },

      {
        label: 'Possui EXE?',
        name: 'possui_exe',
        type: 'select',
        value: '0',
        options: [
          { value: '0', label: 'NÃO' },
          { value: '1', label: 'SIM' }
        ]
      },

      {
        label: 'Nome do EXE',
        name: 'exe_nome',
        hidden: true
      },

      {
        label: 'Status',
        name: 'status',
        type: 'select',
        value: 'ATIVO',
        options: [
          { value: 'ATIVO', label: 'ATIVO' },
          { value: 'INATIVO', label: 'INATIVO' }
        ]
      }
    ]
  });

  toggleVersaoManual(); 
  toggleExeField();   
autoPreencherCaminhoPorCodigo();  
  bindLoginSubmit();
}

function autoPreencherCaminhoPorCodigo() {
  const codigo  = modalForm.querySelector('[name="codigo_cliente"]');
  const caminho = modalForm.querySelector('[name="caminho_acesso"]');

  if (!codigo || !caminho) return;

  const base = 's:\\tga\\';

  const aplicar = () => {
    const cod = codigo.value.trim();

    if (cod) {
      caminho.value = `${base}${cod}\\dados\\`;
    } else {
      caminho.value = base;
    }
  };

  codigo.addEventListener('input', aplicar);
  aplicar(); // aplica imediatamente ao abrir o modal
}


async function getVersoesOptions() {
  const res = await apiFetch('backend/api_login.php', {
    method: 'POST',
    body: { action: 'versions' }
  });

  if (!res.success) return [];

  return [
    { value: '', label: '— Selecionar —' },
    ...res.versions.map(v => ({
      value: v,
      label: v
    })),
    { value: '__manual__', label: '➕ Informar manualmente' }
  ];
}



/* ==========================
   EDITAR
========================== 
async function editarLogin(id) {
  const res = await apiFetch('backend/api_login.php', {
    method: 'POST',
    body: { action: 'get', id }
  });

  if (!res.success) {
    showToast('Erro ao abrir registro', 'danger');
    return;
  }

  const r = res.row;

  openModal({
    title: 'Editar Login Web',
    entity: 'login',
    id,
    fields: [
      { label: 'Código do Cliente', name: 'codigo_cliente', value: r.codigo_cliente, required: true },
      { label: 'Nome do Cliente', name: 'nome_cliente', value: r.nome_cliente, required: true },
      { label: 'Caminho de Acesso', name: 'caminho_acesso', value: r.caminho_acesso },
      { label: 'Versão Padrão', name: 'versao_padrao', value: r.versao_padrao },
      { label: 'Status', name: 'status', value: r.status }
    ]
  });

  bindLoginSubmit(id);
}*/
async function editarLogin(id) {
  const res = await apiFetch('backend/api_login.php', {
    method: 'POST',
    body: { action: 'get', id }
  });

  if (!res.success) {
    showToast('Erro ao abrir registro', 'danger');
    return;
  }

  const r = res.row;
openModal({
  title: 'Editar Login Web',
  entity: 'login',
  id,
  fields: [
    { label: 'Código do Cliente', name: 'codigo_cliente', value: r.codigo_cliente, required: true },
    { label: 'Nome do Cliente', name: 'nome_cliente', value: r.nome_cliente, required: true },
    { label: 'Caminho de Acesso', name: 'caminho_acesso', value: r.caminho_acesso },

    {
      label: 'Versão Padrão',
      name: 'versao_padrao_select',
      type: 'select',
      value: r.versao_padrao || '',
      options: await getVersoesOptions()
    },

    {
      label: 'Versão (manual)',
      name: 'versao_padrao_manual',
      value: '',
      hidden: true
    },

    {
      label: 'Possui EXE?',
      name: 'possui_exe',
      type: 'select',
      value: r.possui_exe == 1 ? '1' : '0',
      options: [
        { value: '0', label: 'NÃO' },
        { value: '1', label: 'SIM' }
      ]
    },

    {
      label: 'Nome do EXE',
      name: 'exe_nome',
      value: r.exe_nome || '',
      hidden: r.possui_exe != 1
    },

    {
      label: 'Status',
      name: 'status',
      type: 'select',
      value: r.status || 'ATIVO',
      options: [
        { value: 'ATIVO', label: 'ATIVO' },
        { value: 'INATIVO', label: 'INATIVO' }
      ]
    }
  ]
});

toggleExeField();
toggleVersaoManual();
bindLoginSubmit(id);
}

function toggleVersaoManual() {
  const sel   = modalForm.querySelector('[name="versao_padrao_select"]');
  const input = modalForm.querySelector('[name="versao_padrao_manual"]');

  if (!sel || !input) return;

  const group = input.closest('.form-group');

  const apply = () => {
    if (sel.value === '__manual__') {
      group.style.display = 'block';
      input.required = true;

      // ✅ CAMPO SEMPRE LIMPO AO ESCOLHER MANUAL
      input.value = '';
      input.focus();
    } else {
      group.style.display = 'none';
      input.required = false;
      input.value = '';
    }
  };

  sel.addEventListener('change', apply);
  apply();
}




function toggleExeField() {
  const sel = modalForm.querySelector('[name="possui_exe"]');
  const exe = modalForm.querySelector('[name="exe_nome"]');

  if (!sel || !exe) return;

  // ⚠️ aqui está o ponto-chave
  const formGroup = exe.closest('.form-group');

  const apply = () => {
    if (sel.value === '1') {
      formGroup.style.display = 'block';
      exe.required = true;
    } else {
      formGroup.style.display = 'none';
      exe.required = false;
      exe.value = '';
    }
  };

  sel.addEventListener('change', apply);
  apply(); // aplica imediatamente ao abrir
}


/* ==========================
   SUBMIT
==========================
function bindLoginSubmit(id = '') {
  modalForm.onsubmit = async e => {
    e.preventDefault();

    const data = {};
    modalForm.querySelectorAll('input[name]').forEach(i => {
      data[i.name] = i.value;
    });

    try {
      const res = await apiFetch('backend/api_login.php', {
        method: 'POST',
        body: {
          action: id ? 'update' : 'create',
          id,
          data
        }
      });

      if (!res.success) {
        throw new Error(res.message || 'Erro ao salvar');
      }

      showToast('Login salvo com sucesso', 'success');
      closeModal();
      loadLogins();

    } catch (err) {
      console.error(err);
      showToast(err.message || 'Erro ao salvar login', 'danger');
    }
  };
}  */function bindLoginSubmit(id = '') {
  modalForm.onsubmit = async e => {
    e.preventDefault();

    const data = {};

    // pega INPUTS, SELECTS e TEXTAREAS
    modalForm.querySelectorAll('[name]').forEach(el => {
      data[el.name] = el.value;
    });

    /* ==========================
       RESOLVE VERSÃO PADRÃO
       ANTES DE ENVIAR
    ========================== */
    if (data.versao_padrao_select === '__manual__') {
      data.versao_padrao = data.versao_padrao_manual || '';
    } else {
      data.versao_padrao = data.versao_padrao_select || '';
    }

    delete data.versao_padrao_select;
    delete data.versao_padrao_manual;

    try {
      const res = await apiFetch('backend/api_login.php', {
        method: 'POST',
        body: {
          action: id ? 'update' : 'create',
          id,
          data
        }
      });

      if (!res.success) {
        throw new Error(res.message || 'Erro ao salvar');
      }

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
  if (!confirm('Excluir este login?')) return;

  const res = await apiFetch('backend/api_login.php', {
    method: 'POST',
    body: { action: 'delete', id }
  });

  if (!res.success) {
    showToast('Erro ao excluir login', 'danger');
    return;
  }

  showToast('Login removido', 'success');
  loadLogins();
}
