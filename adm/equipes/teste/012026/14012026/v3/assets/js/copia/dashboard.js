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

    if (!res.success) {
      throw new Error(res.message || 'Erro ao carregar dashboard');
    }

    /* ==========================
       KPIs
    ========================== */
    const k = res.kpis || {};

    setText('kpiLoginsAtivos', k.logins_ativos ?? 0);
    setText('kpiLoginsTotal', `Total: ${k.logins_total ?? 0}`);

    setText('kpimobilesOn', k.fv_total ?? 0);
    setText('kpiConexoesTotal', `Total: ${(k.fv_total ?? 0) + (k.api_total ?? 0)}`);

    setText('kpiApis', k.api_total ?? 0);
    setText('kpiMobile', k.whatsapp_total ?? 0);
    setText('kpiPdvOff', k.pdvoff_total ?? 0);

    /* ==========================
       LISTAS (ÚLTIMOS 3)
    ========================== */
    const listas = res.listas || {};

    renderRecent('tblRankLogins', listas.logins);
    renderRecent('tblRankFV', listas.fv_smart);
    renderRecent('tblRankApi', listas.api_forca);
    renderRecent('tblRankWhatsapp', listas.whatsapp);
    renderRecent('tblRankPdvOff', listas.pdvoff);

    initDashboardCollapse();

  } catch (e) {
    console.error('Dashboard erro:', e);
    showToast(e.message || 'Erro ao carregar dashboard', 'danger');
  }
}

/* ==========================
   Render Últimos Cadastros
========================== */
function renderRecent(tableId, rows = []) {
  const tbody = document.querySelector(`#${tableId} tbody`);
  if (!tbody) return;

  tbody.innerHTML = '';

  if (!Array.isArray(rows) || !rows.length) {
    tbody.innerHTML = `
      <tr>
        <td colspan="3">Nenhum registro</td>
      </tr>
    `;
    return;
  }

  rows.slice(0, 3).forEach((r, i) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${i + 1}</td>
      <td>${r.cod_cliente ?? '-'}</td>
      <td>${r.cliente ?? '-'}</td>
      <td>${r.atualizado_em ?? '-'}</td>
    `;
    tbody.appendChild(tr);
  });
}

/* ==========================
   COLLAPSE (Persistente)
========================== */
function initDashboardCollapse() {
  document.querySelectorAll('.dashboard-collapsible').forEach(card => {
    const id = card.dataset.collapseId;
    if (!id) return;

    const header = card.querySelector('.dash-collapse-title');
    const icon   = card.querySelector('.collapse-ico');

    const saved = localStorage.getItem(`dash_collapse_${id}`);
    if (saved === '1') {
      card.classList.add('collapsed');
      if (icon) icon.textContent = '⌃';
    } else {
      if (icon) icon.textContent = '⌄';
    }

    if (!header) return;

    header.style.cursor = 'pointer';

    header.onclick = () => {
      card.classList.toggle('collapsed');
      const collapsed = card.classList.contains('collapsed');

      if (icon) {
        icon.textContent = collapsed ? '⌃' : '⌄';
      }

      localStorage.setItem(
        `dash_collapse_${id}`,
        collapsed ? '1' : '0'
      );
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
