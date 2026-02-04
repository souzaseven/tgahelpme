/* =========================================================
   Dashboard - Integração com backend
   Painel Unificado Clientes Web (v6.2 FINAL)
   Autor: Anderson de Souza
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

    /* =====================================================
       KPIs — LOGINS WEB
    ===================================================== */
 const elLoginsAtivos = document.getElementById('kpiLoginsAtivos');
const elLoginsTotal  = document.getElementById('kpiLoginsTotal');

if (elLoginsAtivos) {
  elLoginsAtivos.textContent = k.logins_ativos ?? 0;
}

if (elLoginsTotal) {
  elLoginsTotal.textContent =
    `Total: ${k.logins_total ?? 0} | Inativos: ${k.logins_inativos ?? 0} | Com EXE: ${k.logins_com_exe ?? 0}`;
}


    /* =====================================================
       KPI — USUÁRIOS WEB (OFICIAL / ÚNICO)
       🔒 Backend é a verdade absoluta
    ===================================================== */
    const elTotalUsuarios = document.getElementById('kpiUsuariosTotal');
    const elCadastros     = document.getElementById('kpiUsuariosCadastros');
    const elAtivos        = document.getElementById('kpiUsuariosAtivos');
    const elInativos      = document.getElementById('kpiUsuariosInativos');

    if (elTotalUsuarios) {
      elTotalUsuarios.textContent = Number(k.usuarios_web_total ?? 0);
    }

    if (elCadastros) {
      elCadastros.textContent = Number(k.usuarios_web_cadastros ?? 0);
    }

    if (elAtivos) {
      elAtivos.textContent = Number(k.usuarios_web_ativos ?? 0);
    }

    if (elInativos) {
      elInativos.textContent = Number(k.usuarios_web_inativos ?? 0);
    }

    /* =====================================================
       KPIs — MOBILE (FV + API)
    ===================================================== */
    const elMobOn    = document.getElementById('kpimobilesOn');
    const elMobTotal = document.getElementById('kpimobilesTotal');

    if (elMobOn) {
      elMobOn.textContent = k.fv_total ?? 0;
    }

    if (elMobTotal) {
      elMobTotal.textContent =
        `Total: ${(k.fv_total ?? 0) + (k.api_total ?? 0)}`;
    }

    /* =====================================================
       KPI — API FORÇA DE VENDAS
    ===================================================== */
    const elApi = document.getElementById('kpiApis');
    if (elApi) {
      elApi.textContent = k.api_total ?? 0;
    }

    /* =====================================================
       KPI — WHATSAPP
    ===================================================== */
    const elWhats = document.getElementById('kpiMobile');
    if (elWhats) {
      elWhats.textContent = k.whatsapp_total ?? 0;
    }

    /* =====================================================
       KPI — PDV OFF
    ===================================================== */
    const elPdv = document.getElementById('kpiPdvOff');
    if (elPdv) {
      elPdv.textContent = k.pdvoff_total ?? 0;
    }

    /* =====================================================
       TABELAS — DASHBOARD
    ===================================================== */
    renderDashTables(res);

  } catch (e) {
    console.error('Dashboard erro:', e);
    showToast(e.message || 'Erro ao carregar dashboard', 'danger');
  }
}

/* =========================================================
   Render genérico de tabelas
========================================================= */
function renderTable(tableId, rows) {
  const tbody = document.querySelector(`#${tableId} tbody`);
  if (!tbody) return;

  tbody.innerHTML = '';

  if (!rows || !rows.length) {
    tbody.innerHTML = `
      <tr>
        <td colspan="99" style="text-align:center; opacity:.6">
          Nenhum registro encontrado
        </td>
      </tr>
    `;
    return;
  }

  rows.forEach(row => {
    const tr = document.createElement('tr');
    tr.innerHTML = Object.values(row)
      .map(v => `<td>${v ?? '-'}</td>`)
      .join('');
    tbody.appendChild(tr);
  });
}

/* =====================================================
   Render — Dashboard
===================================================== */
function renderDashTables(res) {

  if (res.last_logins) {
    safeRenderTable('tblDashLogins', res.last_logins);
  }

  if (res.last_usuarios_web) {
    safeRenderUsuariosWeb(res.last_usuarios_web);
  }

  if (res.last_mobile) {
    safeRenderTable('tblDashConexoes', res.last_mobile);
  }

  if (res.last_whatsapp) {
    safeRenderTable('tblDashWhats', res.last_whatsapp);
  }

  if (res.last_pdvoff) {
    safeRenderTable('tblDashPdvOff', res.last_pdvoff);
  }
}

/* =====================================================
   Safe render genérico
===================================================== */
function safeRenderTable(tableId, rows, retry = 0) {
  const tbody = document.querySelector(`#${tableId} tbody`);

  if (!tbody) {
    if (retry < 15) {
      setTimeout(() => safeRenderTable(tableId, rows, retry + 1), 150);
    }
    return;
  }

  renderTable(tableId, rows);
}

/* =====================================================
   Safe render — Últimos Usuários Web
===================================================== */
function safeRenderUsuariosWeb(rows, retry = 0) {
  const tbody = document.querySelector('#tblDashUsuariosWeb tbody');

  if (!tbody) {
    if (retry < 15) {
      setTimeout(() => safeRenderUsuariosWeb(rows, retry + 1), 150);
    }
    return;
  }

  tbody.innerHTML = '';

  if (!rows || !rows.length) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" style="text-align:center; opacity:.6">
          Nenhum registro encontrado
        </td>
      </tr>
    `;
    return;
  }

  rows.forEach(r => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${r.id}</td>
      <td>${r.nome_empresa}</td>
      <td>${r.codigo_empresa}</td>
      <td class="text-center"><strong>${r.qtd_usuarios}</strong></td>
      <td title="${r.observacao ?? ''}">
        ${r.observacao ? r.observacao : '-'}
      </td>
      <td>
        <span class="badge ${r.status === 'ATIVO' ? 'success' : 'danger'}">
          ${r.status}
        </span>
      </td>
      <td>${r.atualizado_em ?? '-'}</td>
    `;
    tbody.appendChild(tr);
  });
}

/* =====================================================
   Helpers
===================================================== */
function formatDateTime(dt) {
  if (!dt) return '-';

  const d = new Date(dt);
  if (isNaN(d.getTime())) return '-';

  return d.toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  });
}
