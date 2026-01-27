/* =========================================================
   Dashboard - Integração com backend
   Painel Unificado Clientes Web (v3 FINAL)
   Autor: Anderson de Souza
========================================================= */

async function loadDashboard() {
  try {
    const res = await apiFetch('backend/api_dashboard.php', {
      method: 'POST',
      body: { action: 'summary' }
    });

    if (!res || !res.success) {
      console.error('Resposta inválida da API:', res);
      throw new Error(res?.message || 'Falha ao carregar dashboard');
    }

    /* ==========================
       KPIs
    ========================== */
    const k = res.kpis || {};

    setText('kpiLoginsAtivos',  k.logins_ativos  ?? 0);
    setText('kpiLoginsTotal',   k.logins_total   ?? '');

    // FV / API Mobile (se não existir no backend, mantém 0)
    setText('kpimobilesOn',     k.fv_total       ?? 0);
    setText('kpiConexoesTotal', '');

    setText('kpiApis',          k.api_total      ?? 0);
    setText('kpiApisSub',       '');

    // WhatsApp → APENAS UM NÚMERO (corrige bug visual do print)
    setText('kpiMobile',        k.whatsapp_total ?? 0);
    setText('kpiMobileSub',     '');

    setText('kpiPdvOff',        k.pdvoff_total   ?? 0);

    /* ==========================
       LISTAS (TOP 3)
       ⚠ nomes exatamente iguais ao backend
    ========================== */
    const l = res.listas || {};

    fillTable('dash-logins',   l.logins);
    fillTable('dash-fv',       l.fv_smart);
    fillTable('dash-api',      l.api_forca);
    fillTable('dash-whatsapp', l.whatsapp);
    fillTable('dash-pdvoff',   l.pdvoff);

    initDashboardCollapse();

  } catch (e) {
    console.error('Erro ao carregar dashboard:', e);
    showToast('Erro ao carregar dashboard', 'danger');
  }
}

/* ==========================
   Render tabelas TOP 3
========================== */
function fillTable(id, rows = []) {
  const tbody = document.getElementById(id);
  if (!tbody) return;

  tbody.innerHTML = '';

  if (!Array.isArray(rows) || !rows.length) {
    tbody.innerHTML = `<tr><td colspan="3">Nenhum registro</td></tr>`;
    return;
  }

  rows.slice(0, 3).forEach(r => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${r.cod_cliente ?? '-'}</td>
      <td>${r.cliente ?? '-'}</td>
      <td>${r.atualizado_em ?? '-'}</td>
    `;
    tbody.appendChild(tr);
  });
}
function initDashboardCollapse() {
  document.querySelectorAll('.dashboard-collapsible').forEach(card => {
    const id = card.dataset.collapseId;
    if (!id) return;

    const icon   = card.querySelector('.collapse-ico');
    const header = card.querySelector('.dash-collapse-title');
    const body   = card.querySelector('.collapse-body');

    if (!header || !body) return;

    // Aplica estado salvo
    const saved = localStorage.getItem(`dash_collapse_${id}`);
    const startCollapsed = saved === '1';

    body.style.display = startCollapsed ? 'none' : 'block';
    icon.textContent = startCollapsed ? '⌃' : '⌄';

    // Click para alternar
    header.style.cursor = 'pointer';
    header.onclick = () => {
      const collapsed = body.style.display !== 'none';
      body.style.display = collapsed ? 'none' : 'block';
      icon.textContent = collapsed ? '⌃' : '⌄';
      localStorage.setItem(`dash_collapse_${id}`, collapsed ? '1' : '0');
    };
  });
}


/* ==========================
   Helpers
========================== */
function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

/* ==========================
   EXPORT
========================== */
window.loadDashboard = loadDashboard;
