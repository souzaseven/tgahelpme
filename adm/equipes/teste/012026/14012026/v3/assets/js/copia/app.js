/* =========================================================
   Painel Unificado - Clientes Web
   JS Principal (v3.2 FINAL FIX)
   Autor: Anderson de Souza
========================================================= */

/* ==========================
   ELEMENTOS GLOBAIS
========================== */
const sidebar        = document.getElementById('sidebar');
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
   STATE GLOBAL
========================== */
let currentTab = 'dashboard';

/* ==========================
   SIDEBAR (COLLAPSE)
========================== */
btnToggleSide?.addEventListener('click', () => {
  sidebar.classList.toggle('collapsed');

  localStorage.setItem(
    'sidebar',
    sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded'
  );
});

/* Restaurar estado sidebar */
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

/* Restaurar tema */
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
  currentTab = tab;

  /* menu ativo */
  document.querySelectorAll('.menu-item')
    .forEach(b => b.classList.remove('active'));

  document
    .querySelector(`.menu-item[data-tab="${tab}"]`)
    ?.classList.add('active');

  /* esconder views */
  document.querySelectorAll('.view')
    .forEach(v => v.classList.add('hidden'));

  /* mostrar view atual */
  document
    .getElementById(`view-${tab}`)
    ?.classList.remove('hidden');

  /* títulos */
  setPageInfo(tab);

  /* carregar módulo */
  loadModule(tab);

  /* fechar sidebar no mobile */
  sidebar.classList.remove('open');
}

function setPageInfo(tab) {
  const titles = {
    dashboard: ['Dashboard', 'Visão geral e saúde dos acessos'],
    logins:    ['Logins Web', 'Gerenciamento de acessos web'],
    mobiles:  ['FV - API MOBILE', 'Monitoramento de conexões e serviços'],
    servidores:['Servidores', 'Infraestrutura e health-checks'],
    whatsapp:  ['WhatsApp', 'Clientes integrados ao WhatsApp'],
    pdvoff:    ['PDV OFF', 'Controle de cadastros PDV Offline']
  };

  if (titles[tab]) {
    pageTitle.textContent    = titles[tab][0];
    pageSubtitle.textContent = titles[tab][1];
  }
}

/* ==========================
   LOADERS POR MÓDULO
========================== */
function loadModule(tab) {
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

    if (tab === 'servidores' && typeof loadServidores === 'function') {
      loadServidores();
    }

    if (tab === 'whatsapp' && typeof loadWhatsapp === 'function') {
      loadWhatsapp();
    }

    if (tab === 'pdvoff' && typeof loadPdvOff === 'function') {
      loadPdvOff();
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

  document.getElementById('mEntity').value = entity || '';
  document.getElementById('mId').value = id || '';

  fields.forEach(f => {
    const div = document.createElement('div');
    div.className = `form-group ${f.full ? 'full' : ''}`;

    let fieldHtml = '';

    if (f.type === 'select') {
      fieldHtml = `
        <label>${f.label}</label>
        <select name="${f.name}" ${f.required ? 'required' : ''}>
          ${(f.options || []).map(opt => `
            <option value="${opt.value}" ${opt.value === f.value ? 'selected' : ''}>
              ${opt.label}
            </option>
          `).join('')}
        </select>
      `;
    } else if (f.full) {
      fieldHtml = `
        <label>${f.label}</label>
        <textarea name="${f.name}" rows="3" ${f.required ? 'required' : ''}>
${f.value ?? ''}
        </textarea>
      `;
    } else {
      fieldHtml = `
        <label>${f.label}</label>
        <input type="${f.type || 'text'}"
               name="${f.name}"
               value="${f.value ?? ''}"
               ${f.required ? 'required' : ''} />
      `;
    }

    div.innerHTML = fieldHtml;
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

btnCloseModal?.addEventListener('click', closeModal);
btnCancelModal?.addEventListener('click', closeModal);

modalBackdrop?.addEventListener('click', e => {
  if (e.target === modalBackdrop) closeModal();
});

/* ==========================
   TOAST SYSTEM
========================== */
function showToast(message, type = 'info', timeout = 3000) {
  const toast = document.createElement('div');
  toast.className = 'toast';

  const colors = {
    success: 'var(--color-success)',
    danger:  'var(--color-danger)',
    warning: 'var(--color-warning)',
    info:    'var(--color-primary)'
  };

  toast.style.borderLeftColor = colors[type] || colors.info;
  toast.textContent = message;

  toasts.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    setTimeout(() => toast.remove(), 300);
  }, timeout);
}

/* =========================================================
   Dashboard - Collapse Cards (persistente)
========================================================= */
function initDashboardCollapse() {
  document.querySelectorAll('.dashboard-collapsible').forEach(card => {
    const id = card.dataset.collapseId;
    if (!id) return;

    if (localStorage.getItem(`dash_collapse_${id}`) === '1') {
      card.classList.add('collapsed');
    }

    const header = card.querySelector('h3');
    if (!header) return;

    header.addEventListener('click', () => {
      card.classList.toggle('collapsed');
      localStorage.setItem(
        `dash_collapse_${id}`,
        card.classList.contains('collapsed') ? '1' : '0'
      );
    });
  });
}

/* ==========================
   INIT
========================== */
switchTab('dashboard');
