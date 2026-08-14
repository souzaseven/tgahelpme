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

/* ── Nomes dos estados (UF) ───────────────────────────────
   Usado para exibir o nome completo do estado em hover/tooltip
   sempre que só a sigla (ex.: "MT", "PA") aparece na tela.
───────────────────────────────────────────────────────── */
const UF_NOMES = {
  AC: 'Acre', AL: 'Alagoas', AP: 'Amapá', AM: 'Amazonas', BA: 'Bahia',
  CE: 'Ceará', DF: 'Distrito Federal', ES: 'Espírito Santo', GO: 'Goiás',
  MA: 'Maranhão', MT: 'Mato Grosso', MS: 'Mato Grosso do Sul', MG: 'Minas Gerais',
  PA: 'Pará', PB: 'Paraíba', PR: 'Paraná', PE: 'Pernambuco', PI: 'Piauí',
  RJ: 'Rio de Janeiro', RN: 'Rio Grande do Norte', RS: 'Rio Grande do Sul',
  RO: 'Rondônia', RR: 'Roraima', SC: 'Santa Catarina', SP: 'São Paulo',
  SE: 'Sergipe', TO: 'Tocantins'
};

/* Retorna "MT - Mato Grosso" (ou só a sigla, se não reconhecida) */
function nomeEstado(uf) {
  if (!uf) return '';
  const sigla = String(uf).trim().toUpperCase();
  const nome  = UF_NOMES[sigla];
  return nome ? `${sigla} - ${nome}` : sigla;
}

/* Retorna a sigla como <span title="Nome completo"> para hover nativo do navegador */
function estadoComTooltip(uf) {
  if (!uf) return '';
  const sigla = String(uf).trim().toUpperCase();
  const nome  = UF_NOMES[sigla];
  if (!nome) return esc(sigla);
  return `<span title="${esc(nome)}" style="cursor:help; border-bottom:1px dotted currentColor;">${esc(sigla)}</span>`;
}

function badgeEmpresaStatus(status) {
  if (!status) return '<span class="badge-status muted">—</span>';
  const isAtivo = status.toUpperCase() === 'ATIVO';
  const cls  = isAtivo ? 'badge-ativo' : 'badge-inativo';
  const txt  = isAtivo ? 'Ativa' : 'Inativa';
  return `<span class="badge-status ${cls}">${txt}</span>`;
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

    _lastDashRes = res; // define ANTES de applyDashFilter

    const k = res.kpis || {};

    /* ── KPIs fixos (sem filtro de status) ──────────── */
    const kpiMap = {
      kpiUsuariosCadastros: k.usuarios_web_cadastros ?? 0,
      kpiUsuariosAtivos:    k.usuarios_web_ativos    ?? 0,
      kpiUsuariosInativos:  k.usuarios_web_inativos  ?? 0,
      kpimobilesOn:         k.fv_total               ?? 0,
      kpiApis:              k.api_total              ?? 0,
      kpiMobile:            k.whatsapp_total         ?? 0,
      kpiPdvOff:            k.pdvoff_total           ?? 0,
      kpiBi:                k.bi_total               ?? 0
    };
    Object.entries(kpiMap).forEach(([id, val]) => {
      const el = document.getElementById(id);
      if (el) el.textContent = Number(val);
    });

    const elMobTotal = document.getElementById('kpimobilesTotal');
    if (elMobTotal) {
      elMobTotal.textContent = `Total: ${(k.fv_total ?? 0) + (k.api_total ?? 0)}`;
    }

    /* ── Taxa de adoção de cada integração (% das empresas ativas) ── */
    const totalAtivas = Number(k.logins_ativos ?? 0);
    const pctAtivas = valor => totalAtivas > 0 ? `${Math.round((Number(valor) / totalAtivas) * 100)}% das ativas` : '';

    const pctMap = {
      kpiConexoesTotal: k.fv_total      ?? 0,
      kpiApisSub:       k.api_total     ?? 0,
      kpiMobileSub:     k.whatsapp_total ?? 0,
      kpiPdvOffPct:     k.pdvoff_total  ?? 0,
      kpiBiPct:         k.bi_total      ?? 0
    };
    Object.entries(pctMap).forEach(([id, val]) => {
      const el = document.getElementById(id);
      if (el) el.textContent = pctAtivas(val);
    });

    initDashFilter();
    bindKpiCardsClick();

    /* ── Tabelas ─────────────────────────────────────── */
    renderDashTables(res);

    /* ── Charts (donuts já filtram por _dashFilter) ── */
    renderCharts(res);
    bindChartCadastrosFilters();
    loadChartCadastros();
    bindChartIntegracoesFilters();
    loadChartIntegracoes();
    bindChartUsuariosFilters();
    loadChartUsuarios();
    bindChartAtividadeSemanaFilters();
    loadChartAtividadeSemana();
    bindCalendarioHeatmapFilters();
    loadCalendarioHeatmap();

    /* ── Comparativo mensal nos KPIs ── */
    if (res.comparativo) renderKpiComparativo(res.comparativo);

    /* ── Aplica filtro global nos KPIs e versões ── */
    applyDashFilter();

  } catch (e) {
    console.error('Dashboard erro:', e);
    showToast(e.message || 'Erro ao carregar dashboard', 'danger');
  }
}

/* =========================================================
   RENDER TABELAS
========================================================= */
function renderDashTables(res) {
  if (res.last_logins)       safeRenderDashLogins(res.last_logins);
  if (res.last_usuarios_web) safeRenderUsuariosWeb(res.last_usuarios_web);
  if (res.last_mobile)       safeRenderDashMobile(res.last_mobile);
  if (res.last_whatsapp)     safeRenderDashWhats(res.last_whatsapp);
  if (res.last_pdvoff)       safeRenderDashPdvOff(res.last_pdvoff);
  if (res.last_bi)           safeRenderDashBi(res.last_bi);

  renderUpdCard('cardUpdLogins',   res.last_updated_logins,   r => `
    <div class="upd-row-top">
      <span class="badge ${r.status === 'ATIVO' ? 'success' : 'danger'}">${esc(r.status)}</span>
      <strong>${esc(r.nome_cliente)}</strong>
      <span class="upd-code">${esc(r.codigo_cliente)}</span>
    </div>
    <div class="upd-row-time"><i class="fas fa-clock"></i> ${esc(r.atualizado_em)}</div>`);

  renderUpdCard('cardUpdUsuarios', res.last_updated_usuarios, r => `
    <div class="upd-row-top">
      <span class="badge ${r.status === 'ATIVO' ? 'success' : 'danger'}">${esc(r.status)}</span>
      <strong>${esc(r.nome_empresa)}</strong>
      <span class="upd-code">${esc(r.codigo_empresa)}</span>
      <span class="badge info">${esc(r.qtd_usuarios)}</span>
    </div>
    <div class="upd-row-time"><i class="fas fa-clock"></i> ${esc(r.atualizado_em)}</div>`);

  renderUpdCard('cardUpdMobile',   res.last_updated_mobile,   r => `
    <div class="upd-row-top">
      <strong>${esc(r.cliente)}</strong>
      <span class="upd-code">${esc(r.cod_cliente)}</span>
      <span class="badge">${esc(r.tipo_acesso)}</span>
    </div>
    <div class="upd-row-time"><i class="fas fa-clock"></i> ${esc(r.atualizado_em)}</div>`);

  renderUpdCard('cardUpdWhatsapp', res.last_updated_whatsapp, r => `
    <div class="upd-row-top">
      <strong>${esc(r.cliente)}</strong>
      <span class="upd-code">${esc(r.cod_cliente)}</span>
    </div>
    <div class="upd-row-time"><i class="fas fa-clock"></i> ${esc(r.atualizado_em)}</div>`);

  renderUpdCard('cardUpdPdvOff',   res.last_updated_pdvoff,   r => `
    <div class="upd-row-top">
      <strong>${esc(r.cliente)}</strong>
      <span class="upd-code">${esc(r.cod_cliente)}</span>
    </div>
    <div class="upd-row-time"><i class="fas fa-clock"></i> ${esc(r.atualizado_em)}</div>`);

  renderUpdCard('cardUpdBi',       res.last_updated_bi,       r => `
    <div class="upd-row-top">
      <strong>${esc(r.cliente)}</strong>
      <span class="upd-code">${esc(r.cod_cliente)}</span>
    </div>
    <div class="upd-row-time"><i class="fas fa-clock"></i> ${esc(r.atualizado_em)}</div>`);
}

function renderUpdCard(containerId, rows, rowHtml) {
  const el = document.getElementById(containerId);
  if (!el) return;
  if (!rows || !rows.length) {
    el.innerHTML = '<div class="upd-empty"><i class="fas fa-inbox"></i> Nenhuma atualização</div>';
    return;
  }
  el.innerHTML = rows.map(r => `<div class="upd-row">${rowHtml(r)}</div>`).join('');
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
        <td colspan="99">
          <div class="table-empty">
            <i class="fas fa-inbox"></i>
            <p>Nenhum registro encontrado</p>
          </div>
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

/* ── Render Últimos Logins (customizado) ──────────────────
   Colunas: Código | Cliente+CNPJ | Versão | Usuários |
            Integrações | Status | Atualizado
───────────────────────────────────────────────────────── */
function safeRenderDashLogins(rows, retry = 0) {
  const tbody = document.querySelector('#tblDashLogins tbody');

  if (!tbody) {
    if (retry < 15) setTimeout(() => safeRenderDashLogins(rows, retry + 1), 150);
    return;
  }

  tbody.innerHTML = '';

  if (!rows?.length) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7">
          <div class="table-empty">
            <i class="fas fa-inbox"></i>
            <p>Nenhum registro encontrado</p>
          </div>
        </td>
      </tr>`;
    return;
  }

  rows.forEach(r => {
    const cnpjFormatado = r.cnpj ? maskCnpj(r.cnpj) : '';
    const qtdUsers      = Number(r.qtd_usuarios) || 0;

    const icones = [
      r.tem_mobile    == 1 ? '<i class="fas fa-mobile-alt" style="color:#2196f3" title="Mobile"></i>'        : '',
      r.tem_whatsapp  == 1 ? '<i class="fab fa-whatsapp"   style="color:#25D366" title="WhatsApp"></i>'      : '',
      r.tem_pdvoff    == 1 ? '<i class="fas fa-cash-register" style="color:#38bdf8" title="PDV OFF"></i>'    : '',
      r.tem_bi        == 1 ? '<i class="fas fa-chart-pie"  style="color:#a78bfa" title="BI"></i>'            : ''
    ].filter(Boolean).join(' ');

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${esc(r.codigo_cliente)}</td>
      <td>
        ${esc(r.nome_cliente)}
        ${cnpjFormatado ? `<br><small class="muted" style="font-size:.72rem">${esc(cnpjFormatado)}</small>` : ''}
      </td>
      <td>${esc(r.versao_padrao) || '-'}</td>
      <td class="text-center">
        ${qtdUsers > 0 ? `<span class="badge success">${qtdUsers}</span>` : '<span class="muted">—</span>'}
      </td>
      <td class="text-center" style="white-space:nowrap">
        ${icones || '<span class="muted">—</span>'}
      </td>
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
        <td colspan="5">
          <div class="table-empty">
            <i class="fas fa-inbox"></i>
            <p>Nenhum registro encontrado</p>
          </div>
        </td>
      </tr>`;
    return;
  }

  rows.forEach(r => {
    const cnpjUw = r.empresa_cnpj ? maskCnpj(r.empresa_cnpj) : '';
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
        ${esc(r.nome_empresa)}
        ${cnpjUw ? `<br><small class="muted" style="font-size:.72rem">${esc(cnpjUw)}</small>` : ''}
      </td>
      <td>${esc(r.codigo_empresa)}</td>
      <td class="text-center"><strong>${esc(r.qtd_usuarios)}</strong></td>
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

/* ── Render Últimos Mobile ────────────────────────────────
   Colunas: Código | Cliente+CNPJ | Servidor | Tipo | Atualizado
───────────────────────────────────────────────────────── */
function safeRenderDashMobile(rows, retry = 0) {
  const tbody = document.querySelector('#tblDashConexoes tbody');
  if (!tbody) {
    if (retry < 15) setTimeout(() => safeRenderDashMobile(rows, retry + 1), 150);
    return;
  }

  tbody.innerHTML = '';

  if (!rows?.length) {
    tbody.innerHTML = `<tr><td colspan="5"><div class="table-empty"><i class="fas fa-inbox"></i><p>Nenhum registro encontrado</p></div></td></tr>`;
    return;
  }

  rows.forEach(r => {
    const cnpj = r.empresa_cnpj ? maskCnpj(r.empresa_cnpj) : '';
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${esc(r.cod_cliente)}</td>
      <td>
        ${esc(r.cliente)}
        ${cnpj ? `<br><small class="muted" style="font-size:.72rem">${esc(cnpj)}</small>` : ''}
      </td>
      <td>${esc(r.acesso_server) || '-'}</td>
      <td><span class="badge">${esc(r.tipo_acesso)}</span></td>
      <td>${esc(r.atualizado_em)}</td>
    `;
    tbody.appendChild(tr);
  });
}

/* ── Render Últimos WhatsApp ─────────────────────────────
   Colunas: Código | Cliente+CNPJ | Servidor | Atualizado
───────────────────────────────────────────────────────── */
function safeRenderDashWhats(rows, retry = 0) {
  const tbody = document.querySelector('#tblDashWhats tbody');
  if (!tbody) {
    if (retry < 15) setTimeout(() => safeRenderDashWhats(rows, retry + 1), 150);
    return;
  }

  tbody.innerHTML = '';

  if (!rows?.length) {
    tbody.innerHTML = `<tr><td colspan="4"><div class="table-empty"><i class="fas fa-inbox"></i><p>Nenhum registro encontrado</p></div></td></tr>`;
    return;
  }

  rows.forEach(r => {
    const cnpj = r.empresa_cnpj ? maskCnpj(r.empresa_cnpj) : '';
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${esc(r.cod_cliente)}</td>
      <td>
        ${esc(r.cliente)}
        ${cnpj ? `<br><small class="muted" style="font-size:.72rem">${esc(cnpj)}</small>` : ''}
      </td>
      <td>${esc(r.acesso_server) || '-'}</td>
      <td>${esc(r.atualizado_em)}</td>
    `;
    tbody.appendChild(tr);
  });
}

/* ── Render Últimos PDV OFF ──────────────────────────────
   Colunas: Código | Cliente+CNPJ | Servidor | Atualizado
───────────────────────────────────────────────────────── */
function safeRenderDashPdvOff(rows, retry = 0) {
  const tbody = document.querySelector('#tblDashPdvOff tbody');
  if (!tbody) {
    if (retry < 15) setTimeout(() => safeRenderDashPdvOff(rows, retry + 1), 150);
    return;
  }

  tbody.innerHTML = '';

  if (!rows?.length) {
    tbody.innerHTML = `<tr><td colspan="4"><div class="table-empty"><i class="fas fa-inbox"></i><p>Nenhum registro encontrado</p></div></td></tr>`;
    return;
  }

  rows.forEach(r => {
    const cnpj = r.empresa_cnpj ? maskCnpj(r.empresa_cnpj) : '';
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${esc(r.cod_cliente)}</td>
      <td>
        ${esc(r.cliente)}
        ${cnpj ? `<br><small class="muted" style="font-size:.72rem">${esc(cnpj)}</small>` : ''}
      </td>
      <td>${esc(r.acesso_server) || '-'}</td>
      <td>${esc(r.atualizado_em)}</td>
    `;
    tbody.appendChild(tr);
  });
}

/* ── Render Últimos BI ───────────────────────────────────
   Colunas: Código | Cliente+CNPJ | Atualizado
───────────────────────────────────────────────────────── */
function safeRenderDashBi(rows, retry = 0) {
  const tbody = document.querySelector('#tblDashBi tbody');
  if (!tbody) {
    if (retry < 15) setTimeout(() => safeRenderDashBi(rows, retry + 1), 150);
    return;
  }

  tbody.innerHTML = '';

  if (!rows?.length) {
    tbody.innerHTML = `<tr><td colspan="3"><div class="table-empty"><i class="fas fa-inbox"></i><p>Nenhum registro encontrado</p></div></td></tr>`;
    return;
  }

  rows.forEach(r => {
    const cnpj = r.empresa_cnpj ? maskCnpj(r.empresa_cnpj) : '';
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${esc(r.cod_cliente)}</td>
      <td>
        ${esc(r.cliente)}
        ${cnpj ? `<br><small class="muted" style="font-size:.72rem">${esc(cnpj)}</small>` : ''}
      </td>
      <td>${esc(r.atualizado_em)}</td>
    `;
    tbody.appendChild(tr);
  });
}

/* =========================================================
   KPI COMPARATIVO MENSAL
========================================================= */
function renderKpiComparativo(comp) {
  const setDelta = (elId, c) => {
    const el = document.getElementById(elId);
    if (!el || !c) return;
    const diff = c.diff ?? (c.atual - c.anterior);
    if (c.atual === 0 && c.anterior === 0) {
      el.innerHTML = `<span class="kpi-delta kpi-delta--neutral">— sem novos este mês</span>`;
      return;
    }
    if (diff > 0) {
      el.innerHTML = `<span class="kpi-delta kpi-delta--up">↑ +${diff} este mês</span>`;
    } else if (diff < 0) {
      el.innerHTML = `<span class="kpi-delta kpi-delta--down">↓ ${Math.abs(diff)} este mês</span>`;
    } else {
      el.innerHTML = `<span class="kpi-delta kpi-delta--neutral">— igual ao mês passado</span>`;
    }
  };

  setDelta('deltaLogins',   comp.logins);
  setDelta('deltaMobileOn', comp.mobile_fv);
  setDelta('deltaApi',      comp.mobile_api);
  setDelta('deltaWhatsapp', comp.whatsapp);
  setDelta('deltaPdvOff',   comp.pdvoff);
  setDelta('deltaBi',       comp.bi);
  setDelta('deltaUsuarios', comp.usuarios);
}

/* =========================================================
   KPI CARDS CLICÁVEIS — navegam direto para a tela do módulo
========================================================= */
function bindKpiCardsClick() {
  if (document.body.dataset.kpiCardsBound === '1') return;
  document.body.dataset.kpiCardsBound = '1';

  document.querySelectorAll('.kpis [data-goto-tab]').forEach(card => {
    card.addEventListener('click', () => {
      const tab = card.dataset.gotoTab;
      if (tab && typeof switchTab === 'function') switchTab(tab);
    });
  });
}

/* =========================================================
   RESUMO DE VERSÕES + FILTRO GLOBAL
========================================================= */
let _versoesData = [];

function initDashFilter() {
  if (document.body.dataset.dashFilterBound === '1') return;
  document.body.dataset.dashFilterBound = '1';

  document.querySelectorAll('.dash-filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      _dashFilter = btn.dataset.filter;
      document.querySelectorAll('.dash-filter-btn')
        .forEach(b => b.classList.toggle('active', b.dataset.filter === _dashFilter));
      applyDashFilter();
    });
  });
}

function applyDashFilter() {
  if (!_lastDashRes) return;
  const k = _lastDashRes.kpis || {};
  const t = getChartTheme();

  /* ── KPI Empresas ── */
  const lbEl  = document.getElementById('kpiLoginsLabel');
  const valEl = document.getElementById('kpiLoginsAtivos');
  const subEl = document.getElementById('kpiLoginsTotal');

  const semInteg = k.logins_sem_integracao ?? 0;

  if (_dashFilter === 'ativos') {
    if (lbEl)  lbEl.textContent  = 'Empresas Ativas';
    if (valEl) valEl.textContent = k.logins_ativos ?? 0;
    if (subEl) subEl.textContent = `Total: ${k.logins_total ?? 0} | Inativos: ${k.logins_inativos ?? 0} | Com EXE: ${k.logins_com_exe ?? 0} | Sem integração: ${semInteg}`;
  } else if (_dashFilter === 'inativos') {
    if (lbEl)  lbEl.textContent  = 'Empresas Inativas';
    if (valEl) valEl.textContent = k.logins_inativos ?? 0;
    if (subEl) subEl.textContent = `Total: ${k.logins_total ?? 0} | Ativas: ${k.logins_ativos ?? 0}`;
  } else {
    if (lbEl)  lbEl.textContent  = 'Empresas (Total)';
    if (valEl) valEl.textContent = k.logins_total ?? 0;
    if (subEl) subEl.textContent = `Ativos: ${k.logins_ativos ?? 0} | Inativos: ${k.logins_inativos ?? 0} | Com EXE: ${k.logins_com_exe ?? 0} | Sem integração: ${semInteg}`;
  }

  /* ── KPI Usuários Web ── */
  const ubEl = document.getElementById('kpiUsuariosLabel');
  const uvEl = document.getElementById('kpiUsuariosTotal');

  if (_dashFilter === 'ativos') {
    if (ubEl) ubEl.textContent = 'Usuários Web';
    if (uvEl) uvEl.textContent = k.usuarios_web_ativos ?? 0;
  } else if (_dashFilter === 'inativos') {
    if (ubEl) ubEl.textContent = 'Usuários Web (Inativos)';
    if (uvEl) uvEl.textContent = k.usuarios_web_inativos ?? 0;
  } else {
    if (ubEl) ubEl.textContent = 'Usuários Web (Total)';
    if (uvEl) uvEl.textContent = k.usuarios_web_total ?? 0;
  }

  /* ── Versões card ── */
  renderVersoesCard(_lastDashRes.versoes_resumo || []);

  /* ── Donut charts ── */
  if (_charts.donut)        { _charts.donut.destroy();        delete _charts.donut; }
  if (_charts.donutRegioes) { _charts.donutRegioes.destroy();  delete _charts.donutRegioes; }
  if (_charts.donutCidades) { _charts.donutCidades.destroy();  delete _charts.donutCidades; }
  if (_charts.donutEstados) { _charts.donutEstados.destroy();  delete _charts.donutEstados; }
  renderDonutChart(_lastDashRes.versoes_resumo || [], t);
  renderDonutChartRegioes(_lastDashRes.regioes_resumo || [], t);
  renderDonutChartCidades(_lastDashRes.cidades_resumo || [], t);
  renderDonutChartEstados(_lastDashRes.estados_resumo || [], t);
}

function renderVersoesCard(versoes) {
  _versoesData = versoes || [];

  const container = document.getElementById('kpiVersoesLista');
  if (!container) return;

  /* Pill fixa "Com EXE" — total geral (não filtra por Ativos/Inativos/Todos) */
  const comExe = _lastDashRes?.kpis?.logins_com_exe ?? 0;
  const exeHtml = `
    <div class="versao-item versao-item--exe">
      <span class="versao-nome"><i class="fas fa-desktop"></i> Com EXE</span>
      <span class="versao-total">${comExe}</span>
    </div>`;

  if (!_versoesData.length) {
    container.innerHTML = '<span style="opacity:.5">Nenhuma versão encontrada</span>' + exeHtml;
    return;
  }

  const mapa = {};
  _versoesData.forEach(row => {
    const isAtivo = row.status === 'ATIVO';
    if (_dashFilter === 'ativos'  && !isAtivo) return;
    if (_dashFilter === 'inativos' && isAtivo) return;
    if (!mapa[row.versao]) mapa[row.versao] = 0;
    mapa[row.versao] += Number(row.total);
  });

  const entries = Object.entries(mapa).sort((a, b) => b[1] - a[1]);

  if (!entries.length) {
    container.innerHTML = '<span style="opacity:.5">Nenhum registro encontrado</span>' + exeHtml;
    return;
  }

  const total = entries.reduce((acc, [, v]) => acc + v, 0);

  container.innerHTML = entries.map(([versao, qtd]) => {
    const pct = total > 0 ? ((qtd / total) * 100).toFixed(1) : '0.0';
    return `
      <div class="versao-item">
        <span class="versao-nome">${esc(versao)}</span>
        <span class="versao-total">${qtd}</span>
        <span class="versao-badge">${pct}%</span>
      </div>`;
  }).join('') + exeHtml;
}

/* =========================================================
   CHARTS (Chart.js)
========================================================= */
let _charts      = {};
let _lastDashRes = null;
let _dashFilter  = 'ativos'; // 'ativos' | 'inativos' | 'todos'
let _filtroCidadesEstado = null; // null = todas | 'MT', 'SP', ... = só cidades desse estado

let _chartCadastrosFiltros      = { periodo: '7d', agrupamento: 'dia', tipo: 'ambos' };
let _chartIntegracoesFiltros    = { periodo: '12m' };
let _chartUsuariosFiltros       = { periodo: '12m' };
let _chartAtividadeSemanaFiltros = { periodo: '12m', tipo: 'ambos' };

async function loadChartCadastros() {
  const t   = getChartTheme();
  const res = await apiFetch('backend/api_dashboard.php', {
    body: {
      action:      'chart_cadastros',
      periodo:     _chartCadastrosFiltros.periodo,
      agrupamento: _chartCadastrosFiltros.agrupamento,
      tipo:        _chartCadastrosFiltros.tipo
    }
  });

  if (!res.success) return;

  if (_charts.line) { _charts.line.destroy(); delete _charts.line; }
  renderLineChart(res.data || [], t, res.tipo || 'cadastros');
}

function bindChartCadastrosFilters() {
  if (document.body.dataset.chartCadastrosBound === '1') return;
  document.body.dataset.chartCadastrosBound = '1';

  document.querySelectorAll('[data-periodo]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-periodo]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      _chartCadastrosFiltros.periodo = btn.dataset.periodo;
      loadChartCadastros();
    });
  });

  const selAgrup = document.getElementById('chartCadastrosAgrup');
  if (selAgrup) {
    selAgrup.addEventListener('change', () => {
      _chartCadastrosFiltros.agrupamento = selAgrup.value;
      loadChartCadastros();
    });
  }

  document.querySelectorAll('[data-tipo]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-tipo]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      _chartCadastrosFiltros.tipo = btn.dataset.tipo;
      loadChartCadastros();
    });
  });
}

function getChartTheme() {
  const light = document.body.classList.contains('light');
  return {
    grid:   light ? 'rgba(0,0,0,.08)'   : 'rgba(255,255,255,.06)',
    tick:   light ? '#6b7280'            : '#9ca3af',
    legend: light ? '#374151'            : '#9ca3af'
  };
}

function renderCharts(res) {
  _lastDashRes = res;
  const t = getChartTheme();
  Object.values(_charts).forEach(c => c.destroy());
  _charts = {};
  renderDonutChart(res.versoes_resumo || [], t);
  renderBarChart(res.kpis || {}, t);
  renderDonutChartRegioes(res.regioes_resumo || [], t);
  renderDonutChartCidades(res.cidades_resumo || [], t);
  renderDonutChartEstados(res.estados_resumo || [], t);
  // donut charts já filtram por _dashFilter internamente
}

function rerenderCharts() {
  if (_lastDashRes) {
    renderCharts(_lastDashRes);
    loadChartCadastros();
    loadChartIntegracoes();
    loadChartUsuarios();
    loadChartAtividadeSemana();
    loadCalendarioHeatmap();
  }
}

function renderLineChart(data, t, tipo = 'cadastros') {
  const ctx = document.getElementById('chartCadastrosMes');
  if (!ctx || !window.Chart) return;

  let labels, datasets;

  if (tipo === 'ambos') {
    labels = data.map(d => d.label);
    datasets = [
      {
        label: 'Cadastros',
        data: data.map(d => Number(d.cadastros)),
        borderColor: '#38bdf8',
        backgroundColor: 'rgba(56,189,248,.08)',
        borderWidth: 2,
        pointBackgroundColor: '#38bdf8',
        pointRadius: 3,
        tension: 0.4,
        fill: false
      },
      {
        label: 'Alterações',
        data: data.map(d => Number(d.alteracoes)),
        borderColor: '#a78bfa',
        backgroundColor: 'rgba(167,139,250,.08)',
        borderWidth: 2,
        pointBackgroundColor: '#a78bfa',
        pointRadius: 3,
        tension: 0.4,
        fill: false
      }
    ];
  } else {
    const isAlt = tipo === 'alteracoes';
    labels = data.map(d => d.label);
    datasets = [{
      label: isAlt ? 'Alterações' : 'Cadastros',
      data: data.map(d => Number(d.total)),
      borderColor: isAlt ? '#a78bfa' : '#38bdf8',
      backgroundColor: isAlt ? 'rgba(167,139,250,.1)' : 'rgba(56,189,248,.1)',
      borderWidth: 2,
      pointBackgroundColor: isAlt ? '#a78bfa' : '#38bdf8',
      pointRadius: 3,
      tension: 0.4,
      fill: true
    }];
  }

  _charts.line = new Chart(ctx, {
    type: 'line',
    data: { labels, datasets },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: tipo === 'ambos', labels: { color: t.legend, font: { size: 11 } } }
      },
      scales: {
        x: { grid: { color: t.grid }, ticks: { color: t.tick, font: { size: 11 } } },
        y: { grid: { color: t.grid }, ticks: { color: t.tick, font: { size: 11 }, precision: 0 }, beginAtZero: true }
      }
    }
  });
}

function renderDonutChart(versoes, t) {
  const ctx = document.getElementById('chartVersoes');
  if (!ctx || !window.Chart) return;

  const mapa = {};
  versoes.forEach(r => {
    const isAtivo = r.status === 'ATIVO';
    if (_dashFilter === 'ativos'   && !isAtivo) return;
    if (_dashFilter === 'inativos' &&  isAtivo) return;
    mapa[r.versao] = (mapa[r.versao] || 0) + Number(r.total);
  });
  const sorted = Object.entries(mapa).sort((a, b) => b[1] - a[1]);
  const top    = sorted.slice(0, 5);
  const outros = sorted.slice(5).reduce((s, [, v]) => s + v, 0);
  if (outros > 0) top.push(['Outros', outros]);

  const COLORS = ['#38bdf8','#22c55e','#a78bfa','#f59e0b','#ef4444','#64748b'];

  _charts.donut = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: top.map(([v]) => v),
      datasets: [{ data: top.map(([, n]) => n), backgroundColor: COLORS, borderColor: 'transparent', borderWidth: 0 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '68%',
      plugins: {
        legend: { position: 'right', labels: { color: t.legend, font: { size: 11 }, padding: 12, boxWidth: 12 } }
      }
    }
  });
}

function renderBarChart(kpis, t) {
  const ctx = document.getElementById('chartModulos');
  if (!ctx || !window.Chart) return;
  _charts.bar = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['FV Smart', 'API FV', 'WhatsApp', 'PDV OFF', 'BI'],
      datasets: [{
        data: [kpis.fv_total ?? 0, kpis.api_total ?? 0, kpis.whatsapp_total ?? 0, kpis.pdvoff_total ?? 0, kpis.bi_total ?? 0],
        backgroundColor: ['rgba(56,189,248,.65)','rgba(34,197,94,.65)','rgba(37,211,102,.65)','rgba(56,189,248,.4)','rgba(167,139,250,.65)'],
        borderColor:     ['#38bdf8','#22c55e','#25D366','#38bdf8','#a78bfa'],
        borderWidth: 1, borderRadius: 6
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, ticks: { color: t.tick, font: { size: 12 } } },
        y: { grid: { color: t.grid }, ticks: { color: t.tick, font: { size: 11 }, precision: 0 }, beginAtZero: true }
      }
    }
  });
}

function renderDonutChartRegioes(regioes, t) {
  const ctx = document.getElementById('chartRegioes');
  if (!ctx || !window.Chart) return;

  const mapa = {};
  regioes.forEach(r => {
    const isAtivo = r.status === 'ATIVO';
    if (_dashFilter === 'ativos'   && !isAtivo) return;
    if (_dashFilter === 'inativos' &&  isAtivo) return;
    mapa[r.regiao] = (mapa[r.regiao] || 0) + Number(r.total);
  });
  const top = Object.entries(mapa).sort((a, b) => b[1] - a[1]);

  const COLORS = ['#38bdf8','#22c55e','#a78bfa','#f59e0b','#ef4444','#64748b','#fb923c','#e879f9','#34d399','#f97316','#60a5fa','#facc15'];

  _charts.donutRegioes = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: top.map(([r]) => r),
      datasets: [{ data: top.map(([, n]) => n), backgroundColor: COLORS, borderColor: 'transparent', borderWidth: 0 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '68%',
      plugins: {
        legend: { position: 'right', labels: { color: t.legend, font: { size: 11 }, padding: 12, boxWidth: 12 } }
      }
    }
  });
}

/* Gera N cores distintas: usa a paleta fixa primeiro e, se faltar (muitas
   cidades cadastradas), completa por rotação de matiz (ângulo áureo), que
   mantém as cores bem separadas entre si mesmo em grandes quantidades. */
function gerarCores(qtd, paletaBase) {
  const cores = paletaBase.slice(0, qtd);
  let i = cores.length;
  while (cores.length < qtd) {
    const hue = (i * 137.508) % 360;
    cores.push(`hsl(${hue.toFixed(0)}, 62%, 56%)`);
    i++;
  }
  return cores;
}

/* Legenda em HTML (fora do canvas) — ao contrário da legenda nativa do
   Chart.js, pode rolar quando os itens não couberem na altura disponível,
   permitindo listar TODOS os itens em vez de esconder o excedente.
   Clique alterna a visibilidade da fatia; hover destaca no gráfico.
   `itens` é um array de [label, valor, estado] (estado é opcional). */
function renderLegendaHtml(chart, containerId, itens) {
  const el = document.getElementById(containerId);
  if (!el) return;

  const cores = chart.data.datasets[0].backgroundColor;

  el.innerHTML = itens.map(([label, valor, estado], i) => {
    const tituloCompleto = estado ? `${label} - ${estado}` : label;
    return `
    <div class="chart-legend-item" data-idx="${i}" title="${esc(tituloCompleto)}">
      <span class="chart-legend-dot" style="background:${cores[i]}"></span>
      <span class="chart-legend-label">${esc(label)}</span>
      <span class="chart-legend-value">${valor}</span>
    </div>
  `;
  }).join('');

  el.querySelectorAll('.chart-legend-item').forEach(item => {
    const idx = Number(item.dataset.idx);

    item.addEventListener('click', () => {
      const visivelAntes = chart.getDataVisibility(idx);
      chart.toggleDataVisibility(idx);
      chart.update();
      item.classList.toggle('is-hidden', visivelAntes);
    });

    item.addEventListener('mouseenter', () => {
      chart.tooltip.setActiveElements([{ datasetIndex: 0, index: idx }], { x: 0, y: 0 });
      chart.update();
    });
    item.addEventListener('mouseleave', () => {
      chart.tooltip.setActiveElements([], { x: 0, y: 0 });
      chart.update();
    });
  });
}

/* Botões de filtro por estado acima do gráfico de cidades: "Todos" + um
   botão por estado com cidade cadastrada. Clicar filtra o gráfico/legenda
   para mostrar só as cidades daquele estado (reaproveita applyDashFilter,
   que já destrói e recria os donuts). */
function renderFiltroCidadesEstado(cidades) {
  const el = document.getElementById('filtroCidadesEstados');
  if (!el) return;

  const estados = [...new Set(
    cidades.map(r => (r.estado || '').trim().toUpperCase()).filter(Boolean)
  )].sort();

  const btnTodos = `<button type="button" class="chart-period-btn${!_filtroCidadesEstado ? ' active' : ''}" data-estado-cidade="">Todos</button>`;
  const btnsUf   = estados.map(uf => `
    <button type="button" class="chart-period-btn${_filtroCidadesEstado === uf ? ' active' : ''}"
      data-estado-cidade="${uf}" title="${esc(nomeEstado(uf))}">${esc(uf)}</button>
  `).join('');

  el.innerHTML = btnTodos + btnsUf;

  el.querySelectorAll('[data-estado-cidade]').forEach(btn => {
    btn.addEventListener('click', () => {
      _filtroCidadesEstado = btn.dataset.estadoCidade || null;
      applyDashFilter();
    });
  });
}

function renderDonutChartCidades(cidades, _t) {
  const ctx = document.getElementById('chartCidades');
  if (!ctx || !window.Chart) return;

  renderFiltroCidadesEstado(cidades);

  /* Agrupa por cidade ignorando maiúsculas/minúsculas, para que a mesma
     cidade cadastrada com grafias diferentes (ex.: "Cuiabá" e "CUIABÁ")
     não vire duas fatias — mantém a primeira grafia e o primeiro estado
     encontrados para exibir "Cidade - Estado" no tooltip. */
  const mapa = {};
  cidades.forEach(r => {
    const isAtivo = r.status === 'ATIVO';
    if (_dashFilter === 'ativos'   && !isAtivo) return;
    if (_dashFilter === 'inativos' &&  isAtivo) return;

    const estado = (r.estado || '').trim();
    if (_filtroCidadesEstado && estado.toUpperCase() !== _filtroCidadesEstado) return;

    const nome = (r.cidade || '').trim();
    if (!nome) return;
    const chave = nome.toLowerCase();
    if (!mapa[chave]) mapa[chave] = { label: nome, estado, total: 0 };
    if (!mapa[chave].estado && estado) mapa[chave].estado = estado;
    mapa[chave].total += Number(r.total);
  });

  /* Mostra TODAS as cidades cadastradas (sem agrupar o excedente em "Outras") */
  const top = Object.values(mapa)
    .sort((a, b) => b.total - a.total)
    .map(v => [v.label, v.total, v.estado]);

  const COLORS = ['#38bdf8','#22c55e','#a78bfa','#f59e0b','#ef4444','#64748b','#fb923c','#e879f9','#34d399','#f97316','#60a5fa','#facc15'];
  const cores  = gerarCores(top.length, COLORS);

  _charts.donutCidades = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: top.map(([c]) => c),
      datasets: [{ data: top.map(([, n]) => n), backgroundColor: cores, borderColor: 'transparent', borderWidth: 0 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '68%',
      plugins: {
        legend: { display: false }, // legenda própria em HTML (ver renderLegendaHtml), com rolagem
        tooltip: {
          callbacks: {
            label: (c) => {
              const estado = top[c.dataIndex][2];
              return ` ${c.label}${estado ? ' - ' + estado : ''}: ${c.parsed}`;
            }
          }
        }
      }
    }
  });

  renderLegendaHtml(_charts.donutCidades, 'legendCidades', top);

  renderChartCidadesDoEstado(cidades);
}

/* Card extra "Cidades do estado" — só aparece quando um estado específico
   está selecionado no filtro acima do gráfico de cidades (some de volta
   ao clicar em "Todos"). Mostra as cidades daquele estado num gráfico de
   PIZZA (sem o furo do donut), separado do gráfico geral de cidades. */
function renderChartCidadesDoEstado(cidades) {
  const card = document.getElementById('cardCidadesDoEstado');
  if (!card) return;

  if (!_filtroCidadesEstado) {
    card.style.display = 'none';
    if (_charts.pieCidadesEstado) { _charts.pieCidadesEstado.destroy(); delete _charts.pieCidadesEstado; }
    return;
  }

  const ctx = document.getElementById('chartCidadesDoEstado');
  if (!ctx || !window.Chart) return;

  card.style.display = '';

  const tituloEl = document.getElementById('tituloCidadesDoEstado');
  if (tituloEl) tituloEl.textContent = `Cidades — ${nomeEstado(_filtroCidadesEstado)}`;

  const mapa = {};
  cidades.forEach(r => {
    const isAtivo = r.status === 'ATIVO';
    if (_dashFilter === 'ativos'   && !isAtivo) return;
    if (_dashFilter === 'inativos' &&  isAtivo) return;
    if ((r.estado || '').trim().toUpperCase() !== _filtroCidadesEstado) return;

    const nome = (r.cidade || '').trim();
    if (!nome) return;
    const chave = nome.toLowerCase();
    if (!mapa[chave]) mapa[chave] = { label: nome, total: 0 };
    mapa[chave].total += Number(r.total);
  });

  const top = Object.values(mapa)
    .sort((a, b) => b.total - a.total)
    .map(v => [v.label, v.total]);

  const COLORS = ['#38bdf8','#22c55e','#a78bfa','#f59e0b','#ef4444','#64748b','#fb923c','#e879f9','#34d399','#f97316','#60a5fa','#facc15'];
  const cores  = gerarCores(top.length, COLORS);

  if (_charts.pieCidadesEstado) { _charts.pieCidadesEstado.destroy(); delete _charts.pieCidadesEstado; }

  _charts.pieCidadesEstado = new Chart(ctx, {
    type: 'pie',
    data: {
      labels: top.map(([c]) => c),
      datasets: [{ data: top.map(([, n]) => n), backgroundColor: cores, borderColor: 'transparent', borderWidth: 0 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false }, // legenda própria em HTML (ver renderLegendaHtml)
        tooltip: { callbacks: { label: (c) => ` ${c.label}: ${c.parsed}` } }
      }
    }
  });

  renderLegendaHtml(_charts.pieCidadesEstado, 'legendCidadesDoEstado', top);
}

function renderDonutChartEstados(estados, t) {
  const ctx = document.getElementById('chartEstados');
  if (!ctx || !window.Chart) return;

  const mapa = {};
  estados.forEach(r => {
    const isAtivo = r.status === 'ATIVO';
    if (_dashFilter === 'ativos'   && !isAtivo) return;
    if (_dashFilter === 'inativos' &&  isAtivo) return;
    mapa[r.estado] = (mapa[r.estado] || 0) + Number(r.total);
  });
  const top = Object.entries(mapa).sort((a, b) => b[1] - a[1]);

  const COLORS = ['#38bdf8','#22c55e','#a78bfa','#f59e0b','#ef4444','#64748b','#fb923c','#e879f9','#34d399','#f97316','#60a5fa','#facc15'];

  _charts.donutEstados = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: top.map(([e]) => e),
      datasets: [{ data: top.map(([, n]) => n), backgroundColor: COLORS, borderColor: 'transparent', borderWidth: 0 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '68%',
      plugins: {
        legend: {
          position: 'right',
          labels: { color: t.legend, font: { size: 11 }, padding: 12, boxWidth: 12 },
          /* Hover no item da legenda (sigla) também abre o tooltip com o nome do estado */
          onHover: (evt, legendItem, legend) => {
            legend.chart.canvas.style.cursor = 'pointer';
            legend.chart.tooltip.setActiveElements(
              [{ datasetIndex: 0, index: legendItem.index }],
              { x: evt.x, y: evt.y }
            );
            legend.chart.update();
          },
          onLeave: (_evt, _legendItem, legend) => {
            legend.chart.canvas.style.cursor = 'default';
            legend.chart.tooltip.setActiveElements([], { x: 0, y: 0 });
            legend.chart.update();
          }
        },
        /* Tooltip ao passar o mouse sobre a fatia do gráfico: mostra "MT - Mato Grosso: 5" */
        tooltip: {
          callbacks: {
            label: (ctx) => ` ${nomeEstado(ctx.label)}: ${ctx.parsed}`
          }
        }
      }
    }
  });
}

/* =========================================================
   CHART — EVOLUÇÃO DE INTEGRAÇÕES
========================================================= */
async function loadChartIntegracoes() {
  const t   = getChartTheme();
  const res = await apiFetch('backend/api_dashboard.php', {
    body: { action: 'chart_integracoes', periodo: _chartIntegracoesFiltros.periodo }
  });
  if (!res.success) return;
  if (_charts.lineInteg) { _charts.lineInteg.destroy(); delete _charts.lineInteg; }
  renderLineChartIntegracoes(res.data || [], t);
}

function bindChartIntegracoesFilters() {
  if (document.body.dataset.chartIntegBound === '1') return;
  document.body.dataset.chartIntegBound = '1';

  document.querySelectorAll('[data-integ-periodo]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-integ-periodo]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      _chartIntegracoesFiltros.periodo = btn.dataset.integPeriodo;
      loadChartIntegracoes();
    });
  });
}

function renderLineChartIntegracoes(data, t) {
  const ctx = document.getElementById('chartIntegracoesEvolucao');
  if (!ctx || !window.Chart) return;

  _charts.lineInteg = new Chart(ctx, {
    type: 'line',
    data: {
      labels: data.map(d => d.label),
      datasets: [
        {
          label: 'WhatsApp',
          data: data.map(d => Number(d.whatsapp)),
          borderColor: '#25D366',
          backgroundColor: 'rgba(37,211,102,.07)',
          borderWidth: 2,
          pointBackgroundColor: '#25D366',
          pointRadius: 3,
          tension: 0.4,
          fill: false
        },
        {
          label: 'Mobile ON (FV Smart)',
          data: data.map(d => Number(d.mobile_on)),
          borderColor: '#2196f3',
          backgroundColor: 'rgba(33,150,243,.07)',
          borderWidth: 2,
          pointBackgroundColor: '#2196f3',
          pointRadius: 3,
          tension: 0.4,
          fill: false
        },
        {
          label: 'Mobile OFF (API FV)',
          data: data.map(d => Number(d.mobile_off)),
          borderColor: '#9e9e9e',
          backgroundColor: 'rgba(158,158,158,.07)',
          borderWidth: 2,
          pointBackgroundColor: '#9e9e9e',
          pointRadius: 3,
          tension: 0.4,
          fill: false
        },
        {
          label: 'PDV OFF',
          data: data.map(d => Number(d.pdvoff)),
          borderColor: '#38bdf8',
          backgroundColor: 'rgba(56,189,248,.07)',
          borderWidth: 2,
          pointBackgroundColor: '#38bdf8',
          pointRadius: 3,
          tension: 0.4,
          fill: false
        },
        {
          label: 'BI',
          data: data.map(d => Number(d.bi ?? 0)),
          borderColor: '#a78bfa',
          backgroundColor: 'rgba(167,139,250,.07)',
          borderWidth: 2,
          pointBackgroundColor: '#a78bfa',
          pointRadius: 3,
          tension: 0.4,
          fill: false
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: true,
          labels: { color: t.legend, font: { size: 11 }, padding: 16, boxWidth: 12 }
        }
      },
      scales: {
        x: { grid: { color: t.grid }, ticks: { color: t.tick, font: { size: 11 } } },
        y: {
          grid: { color: t.grid },
          ticks: { color: t.tick, font: { size: 11 }, precision: 0 },
          beginAtZero: true
        }
      }
    }
  });
}

/* =========================================================
   CHART — USUÁRIOS ADICIONADOS / REMOVIDOS
========================================================= */
async function loadChartUsuarios() {
  const t   = getChartTheme();
  const res = await apiFetch('backend/api_dashboard.php', {
    body: { action: 'chart_usuarios', periodo: _chartUsuariosFiltros.periodo }
  });
  if (!res.success) return;
  if (_charts.barUsuarios) { _charts.barUsuarios.destroy(); delete _charts.barUsuarios; }
  renderBarChartUsuarios(res.data || [], t);
}

function bindChartUsuariosFilters() {
  if (document.body.dataset.chartUsuariosBound === '1') return;
  document.body.dataset.chartUsuariosBound = '1';

  document.querySelectorAll('[data-usuarios-periodo]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-usuarios-periodo]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      _chartUsuariosFiltros.periodo = btn.dataset.usuariosPeriodo;
      loadChartUsuarios();
    });
  });
}

function renderBarChartUsuarios(data, t) {
  const ctx = document.getElementById('chartUsuarios');
  if (!ctx || !window.Chart) return;

  _charts.barUsuarios = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: data.map(d => d.label),
      datasets: [
        {
          label: 'Adicionados',
          data: data.map(d => Number(d.adicionados)),
          backgroundColor: 'rgba(56,189,248,.65)',
          borderColor: '#38bdf8',
          borderWidth: 1,
          borderRadius: 5
        },
        {
          label: 'Removidos',
          data: data.map(d => Number(d.removidos)),
          backgroundColor: 'rgba(239,68,68,.55)',
          borderColor: '#ef4444',
          borderWidth: 1,
          borderRadius: 5
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: true,
          labels: { color: t.legend, font: { size: 11 }, padding: 16, boxWidth: 12 }
        }
      },
      scales: {
        x: { grid: { color: t.grid }, ticks: { color: t.tick, font: { size: 11 } } },
        y: {
          grid: { color: t.grid },
          ticks: { color: t.tick, font: { size: 11 }, precision: 0 },
          beginAtZero: true
        }
      }
    }
  });
}

/* =========================================================
   CHART — ATIVIDADE POR DIA DA SEMANA
========================================================= */
async function loadChartAtividadeSemana() {
  const t   = getChartTheme();
  const res = await apiFetch('backend/api_dashboard.php', {
    body: {
      action:  'chart_atividade_semana',
      periodo: _chartAtividadeSemanaFiltros.periodo
    }
  });
  if (!res.success) return;
  if (_charts.semana) { _charts.semana.destroy(); delete _charts.semana; }
  renderBarChartAtividadeSemana(res.data || [], t);
}

function bindChartAtividadeSemanaFilters() {
  if (document.body.dataset.chartSemanaBound === '1') return;
  document.body.dataset.chartSemanaBound = '1';

  document.querySelectorAll('[data-semana-periodo]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-semana-periodo]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      _chartAtividadeSemanaFiltros.periodo = btn.dataset.semanaPeriodo;
      loadChartAtividadeSemana();
    });
  });

  const sel = document.getElementById('chartSemanaTipo');
  if (sel) {
    sel.addEventListener('change', () => {
      _chartAtividadeSemanaFiltros.tipo = sel.value;
      loadChartAtividadeSemana();
    });
  }
}

function renderBarChartAtividadeSemana(data, t) {
  const ctx = document.getElementById('chartAtividadeSemana');
  if (!ctx || !window.Chart) return;

  const tipo = _chartAtividadeSemanaFiltros.tipo;
  const labels = data.map(d => d.label);

  let datasets;
  if (tipo === 'cadastros') {
    datasets = [{
      label: 'Cadastros',
      data: data.map(d => Number(d.cadastros)),
      backgroundColor: 'rgba(56,189,248,.65)',
      borderColor: '#38bdf8',
      borderWidth: 1,
      borderRadius: 6
    }];
  } else if (tipo === 'alteracoes') {
    datasets = [{
      label: 'Alterações',
      data: data.map(d => Number(d.alteracoes)),
      backgroundColor: 'rgba(167,139,250,.65)',
      borderColor: '#a78bfa',
      borderWidth: 1,
      borderRadius: 6
    }];
  } else {
    datasets = [
      {
        label: 'Cadastros',
        data: data.map(d => Number(d.cadastros)),
        backgroundColor: 'rgba(56,189,248,.65)',
        borderColor: '#38bdf8',
        borderWidth: 1,
        borderRadius: 6
      },
      {
        label: 'Alterações',
        data: data.map(d => Number(d.alteracoes)),
        backgroundColor: 'rgba(167,139,250,.65)',
        borderColor: '#a78bfa',
        borderWidth: 1,
        borderRadius: 6
      }
    ];
  }

  _charts.semana = new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: tipo === 'ambos',
          labels: { color: t.legend, font: { size: 11 }, padding: 16, boxWidth: 12 }
        },
        tooltip: {
          callbacks: {
            footer: items => {
              const total = items.reduce((s, i) => s + i.raw, 0);
              return `Total: ${total}`;
            }
          }
        }
      },
      scales: {
        x: { grid: { color: t.grid }, ticks: { color: t.tick, font: { size: 12 } } },
        y: {
          grid: { color: t.grid },
          ticks: { color: t.tick, font: { size: 11 }, precision: 0 },
          beginAtZero: true
        }
      }
    }
  });
}

/* =========================================================
   CALENDÁRIO MENSAL DE ATIVIDADE
========================================================= */
let _calMes = (() => {
  const n = new Date();
  return `${n.getFullYear()}-${String(n.getMonth() + 1).padStart(2, '0')}`;
})();

async function loadCalendarioHeatmap() {
  const res = await apiFetch('backend/api_dashboard.php', {
    body: { action: 'chart_calendario', periodo: 'mes', mes: _calMes }
  });
  if (!res.success) return;
  _renderCalMes(res.data || [], _calMes);
  _updateCalNavLabel();
}

function bindCalendarioHeatmapFilters() {
  if (document.body.dataset.chartCalBound === '1') return;
  document.body.dataset.chartCalBound = '1';

  document.getElementById('calNavPrev')?.addEventListener('click', () => {
    const [y, m] = _calMes.split('-').map(Number);
    const d = new Date(y, m - 2, 1);
    _calMes = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    loadCalendarioHeatmap();
  });

  document.getElementById('calNavNext')?.addEventListener('click', () => {
    const [y, m] = _calMes.split('-').map(Number);
    const d = new Date(y, m, 1);
    _calMes = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    loadCalendarioHeatmap();
  });
}

function _updateCalNavLabel() {
  const [y, m] = _calMes.split('-').map(Number);
  const label = new Date(y, m - 1, 1)
    .toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
  const el = document.getElementById('calNavLabel');
  if (el) el.textContent = label.charAt(0).toUpperCase() + label.slice(1);

  /* Desabilita "próximo" se já é o mês atual */
  const now = new Date();
  const isCurrent = y === now.getFullYear() && m === (now.getMonth() + 1);
  const btnNext = document.getElementById('calNavNext');
  if (btnNext) btnNext.disabled = isCurrent;
}

function _renderCalMes(data, mesStr) {
  const wrap = document.getElementById('calHeatmapWrap');
  if (!wrap) return;

  /* Mapa de atividade por dia */
  const mapa = {};
  data.forEach(d => {
    mapa[d.dia] = { c: +d.cadastros, a: +d.alteracoes };
  });

  const [ano, mes] = mesStr.split('-').map(Number);
  const hoje       = new Date(); hoje.setHours(0, 0, 0, 0);
  const diasNoMes  = new Date(ano, mes, 0).getDate();
  const inicioSem  = new Date(ano, mes - 1, 1).getDay(); // 0=Dom

  const NOMES_DIA = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

  let html = '<div class="cal-mg">';

  /* Cabeçalho de dias */
  NOMES_DIA.forEach(n => { html += `<div class="cal-mg-header">${n}</div>`; });

  /* Células vazias antes do dia 1 */
  for (let i = 0; i < inicioSem; i++) html += '<div class="cal-mg-cell cal-mg-empty"></div>';

  /* Dias do mês */
  for (let d = 1; d <= diasNoMes; d++) {
    const ds      = `${ano}-${String(mes).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const act     = mapa[ds];
    const isHoje  = hoje.getFullYear() === ano && hoje.getMonth() + 1 === mes && hoje.getDate() === d;
    const isFut   = new Date(ano, mes - 1, d) > hoje;

    const dots = act && !isFut
      ? `<div class="cal-mg-dots">
           ${act.c > 0 ? `<span class="cal-dot cal-dot--create" title="${act.c} cadastro(s)"></span>` : ''}
           ${act.a > 0 ? `<span class="cal-dot cal-dot--update" title="${act.a} alteração(ões)"></span>` : ''}
         </div>`
      : '<div class="cal-mg-dots"></div>';

    const title = act
      ? `${ds}: ${act.c} cadastro(s), ${act.a} alteração(ões)`
      : ds;

    html += `
      <div class="cal-mg-cell${isHoje ? ' cal-mg-today' : ''}${act && !isFut ? ' cal-mg-active' : ''}" title="${title}">
        <span class="cal-mg-num${isHoje ? ' cal-mg-num--today' : ''}">${d}</span>
        ${dots}
      </div>`;
  }

  html += '</div>';
  wrap.innerHTML = html;
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