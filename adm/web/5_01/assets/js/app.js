/* =========================================================
   Painel Unificado - Clientes Web
   JS Principal (v3.2 FINAL)
   Autor: Anderson de Souza
========================================================= */

/* ==========================
   ELEMENTOS GLOBAIS
========================== */
const sidebar        = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const btnToggleSide  = document.getElementById('btnToggleSidebar');
const btnToggleTheme = document.getElementById('btnToggleTheme');
const btnRefresh     = document.getElementById('btnRefresh');

const pageTitle    = document.getElementById('pageTitle');
const pageSubtitle = document.getElementById('pageSubtitle');

/* Modal */
const modalBackdrop = document.getElementById('modalBackdrop');
const modalTitle    = document.getElementById('modalTitle');
const modalForm     = document.getElementById('modalForm');
const modalFields   = document.getElementById('modalFields');
const btnCloseModal = document.getElementById('btnCloseModal');
const btnCancelModal= document.getElementById('btnCancelModal');

/* Toast */
const toasts = document.getElementById('toasts');

/* ==========================
   STATE
========================== */
let currentTab = 'dashboard';

/* ==========================
   SIDEBAR (COLLAPSE / MOBILE)
========================== */
const isMobile = () => window.innerWidth <= 768;

function closeMobileSidebar() {
  sidebar.classList.remove('open');
  sidebarOverlay?.classList.remove('visible');
}

btnToggleSide?.addEventListener('click', () => {
  if (isMobile()) {
    sidebar.classList.toggle('open');
    sidebarOverlay?.classList.toggle('visible');
  } else {
    sidebar.classList.toggle('collapsed');
    localStorage.setItem(
      'sidebar',
      sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded'
    );
  }
});

sidebarOverlay?.addEventListener('click', closeMobileSidebar);

/* Carregar estado salvo */
(() => {
  const sidebarState = localStorage.getItem('sidebar');
  if (sidebarState === 'collapsed') {
    sidebar.classList.add('collapsed');
  }
})();

/* ==========================
   THEME (DARK / LIGHT)
========================== */
btnToggleTheme?.addEventListener('click', () => {
  document.body.classList.toggle('light');
  localStorage.setItem(
    'theme',
    document.body.classList.contains('light') ? 'light' : 'dark'
  );
});

/* Carregar tema salvo */
(() => {
  const theme = localStorage.getItem('theme');
  if (theme === 'light') {
    document.body.classList.add('light');
  }
})();

/* ==========================
   ROUTER (VIEWS)
========================== */
document.querySelectorAll('.menu-item').forEach(btn => {
  btn.addEventListener('click', () => {
    const tab = btn.dataset.tab;
    switchTab(tab);
  });
});

function switchTab(tab) {
  if (!tab) return;
  history.replaceState(null, '', `#${tab}`);
  currentTab = tab;

  /* menu ativo */
  document.querySelectorAll('.menu-item')
    .forEach(b => b.classList.remove('active'));

  document
    .querySelector(`.menu-item[data-tab="${tab}"]`)
    ?.classList.add('active');

  /* views */
  document.querySelectorAll('.view')
    .forEach(v => v.classList.add('hidden'));

  document
    .getElementById(`view-${tab}`)
    ?.classList.remove('hidden');

  /* títulos */
  setPageInfo(tab);

  /* carregar módulo */
  loadModule(tab);

  /* fechar sidebar no mobile */
  closeMobileSidebar();
}

function setPageInfo(tab) {
  const titles = {
    dashboard: ['Dashboard', 'Visão geral e saúde dos acessos'],
    logins: ['Empresas no WEB', 'Gerenciamento de acessos web'],
    mobiles: ['FV - API MOBILE', 'Monitoramento de conexões e serviços'],
    servidores: ['Servidores', 'Infraestrutura e health-checks'],
    whatsapp: ['WhatsApp', 'Clientes integrados ao WhatsApp'],
    pdvoff: ['PDV OFF', 'Controle de cadastros PDV Offline'],
    bi:     ['BI', 'Clientes integrados ao Business Intelligence'],
    'usuarios-web': ['Usuários Web', 'Controle de usuários por empresa'],
    relatorios: ['Relatórios', 'Análises e resumos de cadastros'],
    auditoria:  ['Auditoria',  'Histórico completo de ações no sistema'],
    exportar:   ['Exportar Dados', 'Exporte qualquer módulo em CSV com os filtros aplicados']
  };

  if (titles[tab]) {
    pageTitle.textContent = titles[tab][0];
    pageSubtitle.textContent = titles[tab][1];
  }
}


/* ==========================
   CACHE DE MÓDULOS (5 min)
========================== */
const MODULE_CACHE_TTL = 5 * 60 * 1000;
const moduleCacheTime  = {};
let   forceReload      = false;

function isModuleFresh(tab) {
  if (forceReload) return false;
  const last = moduleCacheTime[tab];
  return !!last && (Date.now() - last) < MODULE_CACHE_TTL;
}

/* ==========================
   LOADERS POR MÓDULO
========================== */
function loadModule(tab) {
  if (isModuleFresh(tab)) return;
  moduleCacheTime[tab] = Date.now();
  forceReload = false;
  try {

    if (tab === 'dashboard' && typeof loadDashboard === 'function') {
      loadDashboard();
    }

    if (tab === 'logins' && typeof loadLogins === 'function') {
      loadLogins();
    }

    if (tab === 'mobiles' && typeof loadMobiles === 'function') {
      loadMobiles();
    }

    // ✅ NOVO MÓDULO — USUÁRIOS WEB
    if (tab === 'usuarios-web' && typeof loadUsuariosWeb === 'function') {
      loadUsuariosWeb();
    }

    if (tab === 'whatsapp' && typeof loadWhatsapp === 'function') {
      loadWhatsapp();
    }

    if (tab === 'pdvoff' && typeof loadPdvOff === 'function') {
      loadPdvOff();
    }

    if (tab === 'bi' && typeof loadBi === 'function') {
      loadBi();
    }

    if (tab === 'servidores' && typeof loadServidores === 'function') {
      loadServidores();
    }

    if (tab === 'relatorios' && typeof loadRelatorios === 'function') {
      loadRelatorios();
    }

    if (tab === 'auditoria' && typeof loadAuditoria === 'function') {
      loadAuditoria();
    }

    if (tab === 'exportar' && typeof loadExportar === 'function') {
      loadExportar();
    }

  } catch (e) {
    console.error(`Erro ao carregar módulo ${tab}:`, e);
    showToast('Erro ao carregar dados', 'danger');
  }
}


/* ==========================
   REFRESH
========================== */
btnRefresh?.addEventListener('click', () => {
  forceReload = true;
  moduleCacheTime[currentTab] = 0;
  autoRefreshSeconds = AUTO_REFRESH_MINUTES * 60;
  updateTimerUI();
  showToast('Atualizando dados...', 'info');
  loadModule(currentTab);
});
/* ==========================
   MODAL (GENÉRICO)
========================== */
function openModal({ title, entity, id = '', fields = [] }) {
  modalTitle.textContent = title || '';
  modalForm.reset();
  modalFields.innerHTML = '';

  // meta
  document.getElementById('mEntity').value = entity || '';
  document.getElementById('mId').value = id || '';

  fields.forEach(f => {
    const div = document.createElement('div');
    div.className = `form-group ${f.full ? 'full' : ''}`;

    // Botão de cópia para campos editáveis (não info, não hidden)
    const copyBtn = (!f.hidden && f.type !== 'info')
      ? `<button type="button" class="btn-copy-field modal-copy-btn" data-copy-target="${f.name}" title="Copiar"><i class="fas fa-copy"></i></button>`
      : '';

    let fieldHtml = '';

    /* ===== INFO (read-only) ===== */
    if (f.type === 'info') {
      fieldHtml = `
        <label>${f.label}</label>
        <div class="modal-info-value">${f.value ?? '-'}</div>
      `;
    }

    /* ===== SELECT ===== */
    else if (f.type === 'select') {
      fieldHtml = `
        <label class="field-label-with-copy">${f.label}${copyBtn}</label>
        <select name="${f.name}" ${f.required ? 'required' : ''}>
          ${(f.options || []).map(opt => `
            <option value="${opt.value}"
              ${opt.value === f.value ? 'selected' : ''}>
              ${opt.label}
            </option>
          `).join('')}
        </select>
      `;
    }

    /* ===== TEXTAREA ===== */
    else if (f.type === 'textarea' || f.full) {
      fieldHtml = `
        <label class="field-label-with-copy">${f.label}${copyBtn}</label>
        <textarea name="${f.name}" rows="3"
          ${f.required ? 'required' : ''}>${f.value ?? ''}</textarea>
      `;
    }

    /* ===== INPUT PADRÃO ===== */
    else {
      fieldHtml = `
        <label class="field-label-with-copy">${f.label}${copyBtn}</label>
        <input
          type="${f.type || 'text'}"
          name="${f.name}"
          value="${f.value ?? ''}"
          ${f.placeholder ? `placeholder="${f.placeholder}"` : ''}
          ${f.required ? 'required' : ''}
        />
      `;
    }

    div.innerHTML = fieldHtml;
    if (f.hidden) div.style.display = 'none';
    modalFields.appendChild(div);
  });

  modalBackdrop.classList.remove('hidden');
  modalBackdrop.classList.add('show');
}

function closeModal() {
  modalBackdrop.classList.remove('show');
  modalBackdrop.classList.add('hidden');
  modalFields.innerHTML = '';
  modalForm.reset();
}

/* Eventos modal */
btnCloseModal?.addEventListener('click', closeModal);
btnCancelModal?.addEventListener('click', closeModal);

modalBackdrop?.addEventListener('click', e => {
  if (e.target === modalBackdrop) {
    closeModal();
  }
});

/* ==========================
   TOAST SYSTEM
========================== */
function showToast(message, type = 'info', timeout = 7000) {
  const toast = document.createElement('div');
  toast.className = 'toast';

  const colors = {
    success: 'var(--color-success)',
    danger: 'var(--color-danger)',
    warning: 'var(--color-warning)',
    info: 'var(--color-primary)'
  };

  toast.style.borderLeftColor = colors[type] || colors.info;
  toast.textContent = message;

  toasts.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    setTimeout(() => toast.remove(), 300);
  }, timeout);
}

/* ==========================
   INIT
========================== */
const _initialTab = (window.location.hash.slice(1) || 'dashboard').replace(/[^a-z-]/g, '');
switchTab(_initialTab);

/* ==========================
   AUTO REFRESH (30 MIN)
========================== */
const AUTO_REFRESH_MINUTES = 5;
let autoRefreshSeconds = AUTO_REFRESH_MINUTES * 60;
let autoRefreshInterval = null;

const timerEl = document.getElementById('autoRefreshTimer');

function formatTime(sec){
  const m = String(Math.floor(sec / 60)).padStart(2, '0');
  const s = String(sec % 60).padStart(2, '0');
  return `${m}:${s}`;
}

function updateTimerUI(){
  if (!timerEl) return;
  timerEl.innerHTML = `<i class="fas fa-sync-alt"></i> Atualiza em ${formatTime(autoRefreshSeconds)}`;
}

function startAutoRefresh(){
  if (autoRefreshInterval) clearInterval(autoRefreshInterval);

  updateTimerUI();

  autoRefreshInterval = setInterval(() => {
    autoRefreshSeconds--;

    if (autoRefreshSeconds <= 0) {
      autoRefreshSeconds = AUTO_REFRESH_MINUTES * 60;

      showToast('Atualização automática executada', 'info');
      loadModule(currentTab); // 🔥 mesma lógica do botão
    }

    updateTimerUI();
  }, 1000);
}

/* Inicia automaticamente */
startAutoRefresh();

/* ==========================
   CONFIRM DIALOG
========================== */
function confirmDialog(message, { title = 'Confirmar', confirmLabel = 'Confirmar', danger = true } = {}) {
  return new Promise(resolve => {
    const backdrop = document.getElementById('confirmBackdrop');
    const msgEl    = document.getElementById('confirmMessage');
    const titleEl  = document.getElementById('confirmTitle');
    const btnOk    = document.getElementById('confirmOk');
    const btnCan   = document.getElementById('confirmCancel');

    titleEl.textContent = title;
    msgEl.textContent   = message;
    btnOk.textContent   = confirmLabel;
    btnOk.className     = `btn ${danger ? 'danger' : 'primary'}`;

    backdrop.classList.remove('hidden');
    backdrop.classList.add('show');

    const finish = result => {
      backdrop.classList.remove('show');
      backdrop.classList.add('hidden');
      btnOk.onclick  = null;
      btnCan.onclick = null;
      backdrop.onclick = null;
      resolve(result);
    };

    btnOk.onclick    = () => finish(true);
    btnCan.onclick   = () => finish(false);
    backdrop.onclick = e => { if (e.target === backdrop) finish(false); };
  });
}

