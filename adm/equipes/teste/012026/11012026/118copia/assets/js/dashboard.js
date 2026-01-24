/* =========================================================
   Dashboard - Integração com backend
   Versão alinhada com MOBILE / PDV OFF / WHATSAPP
========================================================= */

async function loadDashboard() {
  try {
    const res = await apiFetch('backend/api_dashboard.php', {
      body: { action: 'summary' }
    });

    if (!res.success) {
      throw new Error(res.message || 'Falha ao carregar dashboard');
    }

    const k = res.kpis || {};

    /* ==========================
       KPIs — LOGINS
    ========================== */
    const elLoginsAtivos = document.getElementById('kpiLoginsAtivos');
    const elLoginsTotal  = document.getElementById('kpiLoginsTotal');

    if (elLoginsAtivos) elLoginsAtivos.textContent = k.logins_ativos ?? 0;
    if (elLoginsTotal)  elLoginsTotal.textContent  = `Total: ${k.logins_total ?? 0}`;

    /* ==========================
       KPIs — MOBILE
    ========================== */
    const elMobOn    = document.getElementById('kpimobilesOn');
    const elMobTotal = document.getElementById('kpimobilesTotal');

    if (elMobOn)    elMobOn.textContent    = k.fv_total ?? 0;
    if (elMobTotal) elMobTotal.textContent = `Total: ${(k.fv_total ?? 0) + (k.api_total ?? 0)}`;

    /* ==========================
       KPIs — API FORÇA DE VENDAS
    ========================== */
    const elApi = document.getElementById('kpiApis');
    if (elApi) elApi.textContent = k.api_total ?? 0;

    /* ==========================
       KPIs — WHATSAPP
    ========================== */
    const elWhats = document.getElementById('kpiMobile');
    if (elWhats) elWhats.textContent = k.whatsapp_total ?? 0;

    /* ==========================
       KPIs — PDV OFF
    ========================== */
    const elPdv = document.getElementById('kpiPdvOff');
    if (elPdv) elPdv.textContent = k.pdvoff_total ?? 0;

    /* ==========================
       TABELAS (SE EXISTIREM)
    ========================== */
    if (res.last_logins) {
      renderTable('tblDashLogins', res.last_logins);
    }

 if (res.last_mobile) {
  renderTable('tblDashConexoes', res.last_mobile);
}

if (res.last_whatsapp) {
  renderTable('tblDashWhats', res.last_whatsapp);
}

if (res.last_pdvoff) {
  renderTable('tblDashPdvOff', res.last_pdvoff);
}


  } catch (e) {
    console.error('Dashboard erro:', e);
    showToast(e.message || 'Erro ao carregar dashboard', 'danger');
  }
}

/* ==========================
   Render genérico de tabela
========================== */
function renderTable(tableId, rows) {
  const tbody = document.querySelector(`#${tableId} tbody`);
  if (!tbody) return;

  tbody.innerHTML = '';

  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="99">Nenhum registro</td></tr>`;
    return;
  }

  rows.forEach(r => {
    const tr = document.createElement('tr');
    tr.innerHTML = Object.values(r)
      .map(v => `<td>${v ?? ''}</td>`)
      .join('');
    tbody.appendChild(tr);
  });
}
