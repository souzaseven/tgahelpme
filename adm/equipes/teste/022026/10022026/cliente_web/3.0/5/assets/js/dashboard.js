/* =========================================================
   Dashboard — Integração com backend
   Painel Unificado Clientes Web
   Autor: Anderson de Souza
========================================================= */

/* ── Helper XSS ────────────────────────────────────────────
   Escapa qualquer valor antes de inserir em innerHTML.
   Uso: esc(valor) em vez de ${valor} diretamente.
   Sem isso, um nome de cliente com <script> ou <img onerror>
   executa no navegador de quem abre o dashboard.
───────────────────────────────────────────────────────── */
function esc(v) {
  if (v === null || v === undefined) return '-';
  return String(v)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#x27;');
}

/* =========================================================
   LOAD DASHBOARD
========================================================= */
async function loadDashboard() {
  try {
    const res = await apiFetch('backend/api_dashboard.php', {
      body: { action: 'summary' }
    });

    if (!res.success) throw new Error(res.message || 'Falha ao carregar dashboard');

    const k = res.kpis || {};

    /* ── KPIs: Logins Web ────────────────────────────── */
    const elLoginsAtivos = document.getElementById('kpiLoginsAtivos');
    const elLoginsTotal  = document.getElementById('kpiLoginsTotal');

    if (elLoginsAtivos) elLoginsAtivos.textContent = k.logins_ativos ?? 0;
    if (elLoginsTotal)  elLoginsTotal.textContent  =
      `Total: ${k.logins_total ?? 0} | Inativos: ${k.logins_inativos ?? 0} | Com EXE: ${k.logins_com_exe ?? 0}`;

    /* ── KPIs: Usuários Web ──────────────────────────── */
    const kpiMap = {
      kpiUsuariosTotal:    k.usuarios_web_total    ?? 0,
      kpiUsuariosCadastros: k.usuarios_web_cadastros ?? 0,
      kpiUsuariosAtivos:   k.usuarios_web_ativos   ?? 0,
      kpiUsuariosInativos: k.usuarios_web_inativos ?? 0,
      kpimobilesOn:        k.fv_total              ?? 0,
      kpiApis:             k.api_total             ?? 0,
      kpiMobile:           k.whatsapp_total        ?? 0,
      kpiPdvOff:           k.pdvoff_total          ?? 0
    };

    Object.entries(kpiMap).forEach(([id, val]) => {
      const el = document.getElementById(id);
      if (el) el.textContent = Number(val);
    });

    // Mobile tem subtotal próprio
    const elMobTotal = document.getElementById('kpimobilesTotal');
    if (elMobTotal) {
      elMobTotal.textContent = `Total: ${(k.fv_total ?? 0) + (k.api_total ?? 0)}`;
    }

    /* ── Tabelas ─────────────────────────────────────── */
    renderDashTables(res);

  } catch (e) {
    console.error('Dashboard erro:', e);
    showToast(e.message || 'Erro ao carregar dashboard', 'danger');
  }
}

/* =========================================================
   RENDER TABELAS
========================================================= */
function renderDashTables(res) {
  if (res.last_logins)       safeRenderTable('tblDashLogins',      res.last_logins);
  if (res.last_usuarios_web) safeRenderUsuariosWeb(res.last_usuarios_web);
  if (res.last_mobile)       safeRenderTable('tblDashConexoes',    res.last_mobile);
  if (res.last_whatsapp)     safeRenderTable('tblDashWhats',       res.last_whatsapp);
  if (res.last_pdvoff)       safeRenderTable('tblDashPdvOff',      res.last_pdvoff);
}

/* ── Render genérico ──────────────────────────────────────
   Antes: Object.values(row).map(v => `<td>${v}</td>`)
   Problema: valores do banco entravam direto no innerHTML.
   Agora: todos os valores passam por esc() antes.
───────────────────────────────────────────────────────── */
function renderTable(tableId, rows) {
  const tbody = document.querySelector(`#${tableId} tbody`);
  if (!tbody) return;

  tbody.innerHTML = '';

  if (!rows?.length) {
    tbody.innerHTML = `
      <tr>
        <td colspan="99" style="text-align:center; opacity:.6">
          Nenhum registro encontrado
        </td>
      </tr>`;
    return;
  }

  rows.forEach(row => {
    const tr = document.createElement('tr');
    tr.innerHTML = Object.values(row)
      .map(v => `<td>${esc(v)}</td>`)   // ← era ${v ?? '-'}, sem escape
      .join('');
    tbody.appendChild(tr);
  });
}

/* ── Safe render com retry ────────────────────────────── */
function safeRenderTable(tableId, rows, retry = 0) {
  const tbody = document.querySelector(`#${tableId} tbody`);

  if (!tbody) {
    if (retry < 15) setTimeout(() => safeRenderTable(tableId, rows, retry + 1), 150);
    return;
  }

  renderTable(tableId, rows);
}

/* ── Render Últimos Usuários Web ──────────────────────────
   Campos com dados do usuário passam por esc().
   O title= do <td> de observação também foi corrigido:
   antes podia injetar atributos com aspas no valor.
───────────────────────────────────────────────────────── */
function safeRenderUsuariosWeb(rows, retry = 0) {
  const tbody = document.querySelector('#tblDashUsuariosWeb tbody');

  if (!tbody) {
    if (retry < 15) setTimeout(() => safeRenderUsuariosWeb(rows, retry + 1), 150);
    return;
  }

  tbody.innerHTML = '';

  if (!rows?.length) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" style="text-align:center; opacity:.6">
          Nenhum registro encontrado
        </td>
      </tr>`;
    return;
  }

  rows.forEach(r => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${esc(r.id)}</td>
      <td>${esc(r.nome_empresa)}</td>
      <td>${esc(r.codigo_empresa)}</td>
      <td class="text-center"><strong>${esc(r.qtd_usuarios)}</strong></td>
      <td title="${esc(r.observacao)}">${esc(r.observacao) || '-'}</td>
      <td>
        <span class="badge ${r.status === 'ATIVO' ? 'success' : 'danger'}">
          ${esc(r.status)}
        </span>
      </td>
      <td>${esc(r.atualizado_em)}</td>
    `;
    tbody.appendChild(tr);
  });
}

/* =========================================================
   HELPERS
========================================================= */
function formatDateTime(dt) {
  if (!dt) return '-';
  const d = new Date(dt);
  if (isNaN(d.getTime())) return '-';
  return d.toLocaleString('pt-BR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit', second: '2-digit'
  });
}