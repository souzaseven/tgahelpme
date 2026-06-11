/* =========================================================
   Dashboard — Widgets Configuráveis
   Arrastar para reordenar · Recolher · Ocultar · Personalizar
========================================================= */

const _DW_DEFS = [
  { id: 'kpis',        label: 'KPIs',                   icon: 'fas fa-th-large'   },
  { id: 'versoes',     label: 'Resumo de Versões',       icon: 'fas fa-cubes'      },
  { id: 'charts-main', label: 'Gráficos Principais',     icon: 'fas fa-chart-bar'  },
  { id: 'charts-duo',  label: 'Evolução e Calendário',   icon: 'fas fa-chart-area' },
  { id: 'tabelas',     label: 'Tabelas Recentes',        icon: 'fas fa-table'      },
];

const _DW_SELS = {
  'kpis':        '.grid.kpis',
  'versoes':     '.versoes-resumo-card',
  'charts-main': '.charts-grid',
  'charts-duo':  '.charts-duo',
  'tabelas':     '#dashTabelas',
};

const _DW_KEY      = 'dw_layout_v1';
const _DW_MODE_KEY = 'dw_mode_v1';   // 'fixed' | 'custom'
let   _dwDragSrc   = null;

/* ── Estado ─────────────────────────────────────────── */
function _dwLoad() {
  try { return JSON.parse(localStorage.getItem(_DW_KEY)) || {}; }
  catch { return {}; }
}
function _dwSave(state) {
  localStorage.setItem(_DW_KEY, JSON.stringify(state));
}

/* ── Modo fixo / personalizado ───────────────────────── */
function _dwSetMode(mode) {
  localStorage.setItem(_DW_MODE_KEY, mode);
  _dwApplyMode();
  if (mode === 'custom') openDashCustomizer();
  else                   closeDashCustomizer();
}

function _dwApplyMode() {
  const mode = localStorage.getItem(_DW_MODE_KEY) || 'fixed';
  const container = document.getElementById('dashWidgetContainer');
  const btnFixed  = document.getElementById('dwBtnFixed');
  const btnCustom = document.getElementById('dwBtnCustom');
  const custBtn   = document.getElementById('dwOpenCustBtn');

  if (mode === 'custom') {
    container?.classList.remove('dw-mode-fixed');
    btnFixed?.classList.remove('active');
    btnCustom?.classList.add('active');
    if (custBtn) custBtn.style.display = '';
  } else {
    container?.classList.add('dw-mode-fixed');
    btnFixed?.classList.add('active');
    btnCustom?.classList.remove('active');
    if (custBtn) custBtn.style.display = 'none';
    closeDashCustomizer();
  }

  /* Garante draggable=false em modo fixo (CSS não é suficiente) */
  document.querySelectorAll('#dashWidgetContainer .dash-widget').forEach(w => {
    w.setAttribute('draggable', mode === 'custom' ? 'true' : 'false');
  });
}

/* ── Inicialização ────────────────────────────────────
   Envolve cada seção estática em um wrapper arrastável.
─────────────────────────────────────────────────────── */
function initDashWidgets() {
  const view = document.getElementById('view-dashboard');
  if (!view || view.dataset.dwInit) return;
  view.dataset.dwInit = '1';

  /* Container principal */
  const container = document.createElement('div');
  container.id = 'dashWidgetContainer';

  /* Envolve cada seção */
  _DW_DEFS.forEach(def => {
    const target = view.querySelector(_DW_SELS[def.id]);
    if (!target) return;

    const wrap = document.createElement('div');
    wrap.className = 'dash-widget';
    wrap.dataset.widgetId = def.id;
    wrap.setAttribute('draggable', 'true');
    wrap.innerHTML = `
      <div class="dash-widget-bar">
        <span class="dw-grip" title="Arrastar para reposicionar">
          <i class="fas fa-grip-vertical"></i>
        </span>
        <i class="${def.icon} dw-icon"></i>
        <span class="dw-label">${def.label}</span>
        <div class="dw-btns">
          <button class="dw-btn dw-btn-collapse" title="Recolher / Expandir">
            <i class="fas fa-chevron-down"></i>
          </button>
          <button class="dw-btn dw-btn-hide" title="Ocultar widget">
            <i class="fas fa-eye-slash"></i>
          </button>
        </div>
      </div>
      <div class="dash-widget-body"></div>
    `;

    /* Move o alvo para dentro do wrapper */
    target.style.marginTop = '';
    target.parentNode.insertBefore(wrap, target);
    wrap.querySelector('.dash-widget-body').appendChild(target);

    /* Botões da barra */
    wrap.querySelector('.dw-btn-collapse').addEventListener('click', () => _dwToggleCollapse(def.id));
    wrap.querySelector('.dw-btn-hide').addEventListener('click', () => _dwHide(def.id));

    /* Drag & Drop */
    _dwBindDrag(wrap);
  });

  /* Move todos os widgets para dentro do container */
  const firstWidget = view.querySelector('.dash-widget');
  if (firstWidget) {
    view.insertBefore(container, firstWidget);
    [...view.querySelectorAll('.dash-widget')].forEach(w => container.appendChild(w));
  }

  /* Barra de modo: [Fixo] [Personalizado] */
  const toolbar = document.createElement('div');
  toolbar.className = 'dw-mode-bar';
  toolbar.innerHTML = `
    <span class="dw-mode-label">Layout:</span>
    <div class="dw-mode-btns">
      <button class="dw-mode-btn" id="dwBtnFixed"  onclick="_dwSetMode('fixed')">
        <i class="fas fa-lock"></i> Fixo
      </button>
      <button class="dw-mode-btn" id="dwBtnCustom" onclick="_dwSetMode('custom')">
        <i class="fas fa-sliders-h"></i> Personalizado
      </button>
    </div>
    <button class="btn ghost btn-sm dw-customize-btn" id="dwOpenCustBtn" onclick="openDashCustomizer()">
      <i class="fas fa-th-large"></i> Organizar widgets
    </button>
  `;
  view.insertBefore(toolbar, container);

  /* Aplica layout + modo salvos */
  _dwApplyLayout(container);
  _dwApplyMode();
}

/* ── Drag & Drop ─────────────────────────────────────── */
function _dwBindDrag(wrap) {
  /* Drag inicia apenas pelo grip (handle) */
  const grip = wrap.querySelector('.dw-grip');
  grip.addEventListener('mousedown', () => wrap.setAttribute('draggable', 'true'));
  wrap.addEventListener('mouseup', () => {});

  wrap.addEventListener('dragstart', e => {
    if ((localStorage.getItem(_DW_MODE_KEY) || 'fixed') !== 'custom') {
      e.preventDefault();
      return;
    }
    _dwDragSrc = wrap;
    e.dataTransfer.effectAllowed = 'move';
    setTimeout(() => wrap.classList.add('dw--dragging'), 0);
  });

  wrap.addEventListener('dragend', () => {
    wrap.classList.remove('dw--dragging');
    document.querySelectorAll('.dw--over').forEach(el => el.classList.remove('dw--over'));
    _dwSaveOrder();
    if (typeof rerenderCharts === 'function') setTimeout(rerenderCharts, 80);
  });

  wrap.addEventListener('dragover', e => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    if (_dwDragSrc && _dwDragSrc !== wrap) {
      document.querySelectorAll('.dw--over').forEach(el => el.classList.remove('dw--over'));
      wrap.classList.add('dw--over');
    }
  });

  wrap.addEventListener('dragleave', () => wrap.classList.remove('dw--over'));

  wrap.addEventListener('drop', e => {
    e.preventDefault();
    e.stopPropagation();
    if (!_dwDragSrc || _dwDragSrc === wrap) return;
    wrap.classList.remove('dw--over');

    const container = document.getElementById('dashWidgetContainer');
    const siblings  = [...container.children];
    const si = siblings.indexOf(_dwDragSrc);
    const di = siblings.indexOf(wrap);
    if (si < di) container.insertBefore(_dwDragSrc, wrap.nextSibling);
    else         container.insertBefore(_dwDragSrc, wrap);
  });
}

/* ── Salvar ordem atual ──────────────────────────────── */
function _dwSaveOrder() {
  const container = document.getElementById('dashWidgetContainer');
  if (!container) return;
  const order = [...container.querySelectorAll('.dash-widget')].map(w => w.dataset.widgetId);
  const state = _dwLoad();
  state.order = order;
  _dwSave(state);
}

/* ── Recolher / Expandir ─────────────────────────────── */
function _dwToggleCollapse(id) {
  const w = document.querySelector(`.dash-widget[data-widget-id="${id}"]`);
  if (!w) return;
  const isCollapsed = w.classList.toggle('dw--collapsed');
  const state = _dwLoad();
  const set = new Set(state.collapsed || []);
  isCollapsed ? set.add(id) : set.delete(id);
  state.collapsed = [...set];
  _dwSave(state);
  if (!isCollapsed && typeof rerenderCharts === 'function') setTimeout(rerenderCharts, 80);
}

/* ── Ocultar widget ──────────────────────────────────── */
function _dwHide(id) {
  const w = document.querySelector(`.dash-widget[data-widget-id="${id}"]`);
  if (w) w.style.display = 'none';
  const state = _dwLoad();
  const set = new Set(state.hidden || []);
  set.add(id);
  state.hidden = [...set];
  _dwSave(state);
  _dwRefreshCustomizer();
}

/* ── Exibir widget ───────────────────────────────────── */
function _dwShow(id) {
  const w = document.querySelector(`.dash-widget[data-widget-id="${id}"]`);
  if (w) w.style.display = '';
  const state = _dwLoad();
  const set = new Set(state.hidden || []);
  set.delete(id);
  state.hidden = [...set];
  _dwSave(state);
  _dwRefreshCustomizer();
  if (typeof rerenderCharts === 'function') setTimeout(rerenderCharts, 80);
}

/* ── Aplicar layout salvo ─────────────────────────────── */
function _dwApplyLayout(container) {
  const { order = [], hidden = [], collapsed = [] } = _dwLoad();

  /* Reordenar */
  if (order.length) {
    order.forEach(id => {
      const w = container.querySelector(`.dash-widget[data-widget-id="${id}"]`);
      if (w) container.appendChild(w);
    });
  }

  /* Ocultar */
  hidden.forEach(id => {
    const w = container.querySelector(`.dash-widget[data-widget-id="${id}"]`);
    if (w) w.style.display = 'none';
  });

  /* Colapsar */
  collapsed.forEach(id => {
    const w = container.querySelector(`.dash-widget[data-widget-id="${id}"]`);
    if (w) w.classList.add('dw--collapsed');
  });
}

/* ── Abrir painel Personalizar ────────────────────────── */
function openDashCustomizer() {
  let panel = document.getElementById('dashCustomizer');

  if (!panel) {
    panel = document.createElement('div');
    panel.id = 'dashCustomizer';
    panel.className = 'dw-customizer';
    panel.innerHTML = `
      <div class="dw-cust-head">
        <span><i class="fas fa-sliders-h"></i> Personalizar Dashboard</span>
        <button class="icon-btn" onclick="closeDashCustomizer()">✕</button>
      </div>
      <p class="dw-cust-hint">
        Use <i class="fas fa-eye-slash"></i> para ocultar um widget.<br>
        Arraste pelo <i class="fas fa-grip-vertical"></i> para reordenar.
      </p>
      <div id="dwCustList" class="dw-cust-list"></div>
      <div class="dw-cust-footer">
        <button class="btn ghost btn-sm" onclick="_dwReset()">
          <i class="fas fa-undo"></i> Restaurar padrão
        </button>
      </div>
    `;
    document.body.appendChild(panel);

    /* Fecha ao clicar fora */
    document.addEventListener('click', e => {
      if (panel.classList.contains('open')
          && !panel.contains(e.target)
          && !e.target.closest('.dw-customize-btn')) {
        closeDashCustomizer();
      }
    }, true);
  }

  _dwRefreshCustomizer();
  panel.classList.add('open');
}

function closeDashCustomizer() {
  document.getElementById('dashCustomizer')?.classList.remove('open');
}

/* ── Atualiza a lista do painel ───────────────────────── */
function _dwRefreshCustomizer() {
  const list = document.getElementById('dwCustList');
  if (!list) return;

  const container = document.getElementById('dashWidgetContainer');
  const { hidden = [] } = _dwLoad();
  const hiddenSet = new Set(hidden);

  /* Segue a ordem atual do DOM */
  const order = container
    ? [...container.querySelectorAll('.dash-widget')].map(w => w.dataset.widgetId)
    : _DW_DEFS.map(d => d.id);

  list.innerHTML = '';
  order.forEach(id => {
    const def = _DW_DEFS.find(d => d.id === id);
    if (!def) return;
    const isHidden = hiddenSet.has(id);

    const row = document.createElement('div');
    row.className = `dw-cust-row${isHidden ? ' dw-cust-row--off' : ''}`;
    row.innerHTML = `
      <i class="${def.icon} dw-cust-icon"></i>
      <span class="dw-cust-label">${def.label}</span>
      <button class="dw-cust-toggle" title="${isHidden ? 'Exibir' : 'Ocultar'}">
        <i class="fas ${isHidden ? 'fa-eye' : 'fa-eye-slash'}"></i>
      </button>
    `;
    row.querySelector('.dw-cust-toggle').addEventListener('click', () => {
      isHidden ? _dwShow(id) : _dwHide(id);
    });
    list.appendChild(row);
  });
}

/* ── Restaurar layout padrão ─────────────────────────── */
function _dwReset() {
  localStorage.removeItem(_DW_KEY);
  location.reload();
}

/* ── Inicializa ao carregar a página ─────────────────── */
document.addEventListener('DOMContentLoaded', initDashWidgets);
