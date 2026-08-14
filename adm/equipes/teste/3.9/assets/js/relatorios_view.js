/* =========================================================
   Relatórios — Módulo v2
   Painel Unificado Clientes Web
   Autor: Anderson de Souza
========================================================= */

let relatTabAtual = 'recentes';

let relatFiltros = {
  q:         '',
  status:    '',
  versao:    '',
  date_tipo: 'criado_em',
  date_ini:  '',
  date_fim:  ''
};

const RELAT_TABS = {
  recentes:       { cols: ['Código', 'Cliente', 'Versão', 'Status', 'Cadastrado em'] },
  inativos:       { cols: ['Código', 'Cliente', 'Versão', 'Atualizado em'] },
  sem_integracao: { cols: ['Código', 'Cliente', 'Versão', 'Status', 'Atualizado em'] },
  com_integracao: { cols: ['Código', 'Cliente', 'Versão', 'Status', 'Integrações', 'Atualizado em'] },
  por_versao:     { cols: ['Versão', 'Ativos', 'Inativos', 'Total'] },
  desatualizados: { cols: ['Código', 'Cliente', 'Versão', 'Status', 'Última atualização', 'Dias parado'] },
  com_exe:        { cols: ['Código', 'Cliente', 'Versão', 'EXE', 'Status', 'Atualizado em'] }
};

/* =========================================================
   HELPERS DE DATA
========================================================= */
function hojeStr() {
  return new Date().toISOString().slice(0, 10);
}

function offsetDias(n) {
  const d = new Date();
  d.setDate(d.getDate() + n);
  return d.toISOString().slice(0, 10);
}

function primeiroDiaMes() {
  const d = new Date();
  d.setDate(1);
  return d.toISOString().slice(0, 10);
}

function primeiroDiaAno() {
  const d = new Date();
  d.setMonth(0, 1);
  return d.toISOString().slice(0, 10);
}

function dataBR(str) {
  if (!str) return '';
  const [y, m, d] = str.split('-');
  return `${d}/${m}/${y}`;
}

/* =========================================================
   INIT
========================================================= */
function loadRelatorios() {
  initRelatTabs();
  initRelatFiltros();
  initQuickDates();
  loadRelatVersoes();
  carregarRelat(relatTabAtual);
}

/* =========================================================
   SUB-NAVEGAÇÃO
========================================================= */
function initRelatTabs() {
  if (document.body.dataset.relatTabsBound === '1') return;
  document.body.dataset.relatTabsBound = '1';

  document.querySelectorAll('.relat-tab').forEach(btn => {
    btn.addEventListener('click', () => {
      relatTabAtual = btn.dataset.relat;
      document.querySelectorAll('.relat-tab').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      carregarRelat(relatTabAtual);
    });
  });
}

/* =========================================================
   POPULAR VERSÕES
========================================================= */
async function loadRelatVersoes() {
  const sel = document.getElementById('relatVersao');
  if (!sel || sel.dataset.loaded) return;

  try {
    const res = await apiFetch('backend/api_login.php', { body: { action: 'versions' } });
    if (!res.success) return;

    res.versions.forEach(v => {
      const opt = document.createElement('option');
      opt.value       = v;
      opt.textContent = v;
      sel.appendChild(opt);
    });
    sel.dataset.loaded = '1';
  } catch (_) {}
}

/* =========================================================
   FILTROS
========================================================= */
function initRelatFiltros() {
  if (document.body.dataset.relatFiltrosBound === '1') return;
  document.body.dataset.relatFiltrosBound = '1';

  // ── Padrão: data de hoje ───────────────────────────────
  const hoje = hojeStr();
  relatFiltros.date_ini = hoje;
  relatFiltros.date_fim = hoje;

  const elIni = document.getElementById('relatDateIni');
  const elFim = document.getElementById('relatDateFim');
  if (elIni) elIni.value = hoje;
  if (elFim) elFim.value = hoje;

  document.querySelector('.btn-quick-date[data-quick="hoje"]')?.classList.add('active');

  // ── Aplicar ────────────────────────────────────────────
  const btnFiltrar = document.getElementById('btnRelatFiltrar');
  const btnLimpar  = document.getElementById('btnRelatLimpar');

  const aplicar = () => {
    relatFiltros.q         = document.getElementById('relatSearch')?.value.trim()  || '';
    relatFiltros.status    = document.getElementById('relatStatus')?.value          || '';
    relatFiltros.versao    = document.getElementById('relatVersao')?.value          || '';
    relatFiltros.date_tipo = document.getElementById('relatDateTipo')?.value        || 'criado_em';
    relatFiltros.date_ini  = document.getElementById('relatDateIni')?.value         || '';
    relatFiltros.date_fim  = document.getElementById('relatDateFim')?.value         || '';
    atualizarFiltroAtivoBadge();
    carregarRelat(relatTabAtual);
  };

  const aplicarDebounced = debounce(aplicar, 350);

  // Botão manual (mantido como atalho, mas não é mais obrigatório)
  btnFiltrar?.addEventListener('click', aplicar);

  // Enter nos campos de texto/data
  ['relatSearch', 'relatDateIni', 'relatDateFim'].forEach(id => {
    document.getElementById(id)?.addEventListener('keydown', e => {
      if (e.key === 'Enter') aplicar();
    });
  });

  // Auto-filtro: busca com debounce
  document.getElementById('relatSearch')?.addEventListener('input', aplicarDebounced);

  // Auto-filtro: selects disparam imediatamente
  ['relatStatus', 'relatVersao', 'relatDateTipo'].forEach(id => {
    document.getElementById(id)?.addEventListener('change', aplicar);
  });

  // Auto-filtro: datas desmarcam botão rápido e disparam imediatamente
  ['relatDateIni', 'relatDateFim'].forEach(id => {
    document.getElementById(id)?.addEventListener('change', () => {
      document.querySelectorAll('.btn-quick-date').forEach(b => b.classList.remove('active'));
      aplicar();
    });
  });

  // ── Limpar ─────────────────────────────────────────────
  btnLimpar?.addEventListener('click', () => {
    relatFiltros = {
      q: '', status: '', versao: '', date_tipo: 'criado_em', date_ini: '', date_fim: ''
    };

    const reset = {
      relatSearch:   '',
      relatStatus:   '',
      relatVersao:   '',
      relatDateTipo: 'criado_em',
      relatDateIni:  '',
      relatDateFim:  ''
    };
    Object.entries(reset).forEach(([id, val]) => {
      const el = document.getElementById(id);
      if (el) el.value = val;
    });

    document.querySelectorAll('.btn-quick-date').forEach(b => b.classList.remove('active'));
    atualizarFiltroAtivoBadge();
    carregarRelat(relatTabAtual);
  });
}

/* =========================================================
   PERÍODOS RÁPIDOS
========================================================= */
function initQuickDates() {
  if (document.body.dataset.relatQuickBound === '1') return;
  document.body.dataset.relatQuickBound = '1';

  document.querySelectorAll('.btn-quick-date').forEach(btn => {
    btn.addEventListener('click', () => {
      const quick = btn.dataset.quick;
      let ini = '', fim = hojeStr();

      switch (quick) {
        case 'hoje':  ini = hojeStr();       fim = hojeStr();       break;
        case 'ontem': ini = offsetDias(-1);  fim = offsetDias(-1);  break;
        case '7d':    ini = offsetDias(-6);  fim = hojeStr();       break;
        case '30d':   ini = offsetDias(-29); fim = hojeStr();       break;
        case 'mes':   ini = primeiroDiaMes(); fim = hojeStr();      break;
        case 'ano':   ini = primeiroDiaAno(); fim = hojeStr();      break;
      }

      const elIni = document.getElementById('relatDateIni');
      const elFim = document.getElementById('relatDateFim');
      if (elIni) elIni.value = ini;
      if (elFim) elFim.value = fim;

      relatFiltros.date_ini = ini;
      relatFiltros.date_fim = fim;

      document.querySelectorAll('.btn-quick-date').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      atualizarFiltroAtivoBadge();
      carregarRelat(relatTabAtual);
    });
  });
}

/* =========================================================
   BADGE DE FILTROS ATIVOS
========================================================= */
function atualizarFiltroAtivoBadge() {
  const btnLimpar = document.getElementById('btnRelatLimpar');
  const badge     = document.getElementById('relatFiltroAtivoBadge');

  const ativo =
    relatFiltros.q        !== '' ||
    relatFiltros.status   !== '' ||
    relatFiltros.versao   !== '' ||
    relatFiltros.date_ini !== '' ||
    relatFiltros.date_fim !== '';

  if (btnLimpar) btnLimpar.style.display = ativo ? 'inline-flex' : 'none';
  if (!badge) return;
  if (!ativo) { badge.innerHTML = ''; return; }

  const pills = [];

  if (relatFiltros.q) {
    pills.push(`<span class="relat-filtro-ativo"><i class="fas fa-search"></i> "${esc(relatFiltros.q)}"</span>`);
  }
  if (relatFiltros.status) {
    const ok = relatFiltros.status === 'ATIVO';
    pills.push(`<span class="relat-filtro-ativo"><i class="fas fa-toggle-on"></i> ${ok ? 'Ativos' : 'Inativos'}</span>`);
  }
  if (relatFiltros.versao) {
    pills.push(`<span class="relat-filtro-ativo"><i class="fas fa-code-branch"></i> v${esc(relatFiltros.versao)}</span>`);
  }
  if (relatFiltros.date_ini || relatFiltros.date_fim) {
    const tipoLabel = {
      criado_em:     'Criado em',
      atualizado_em: 'Alterado em',
      ambos:         'Criação ou alteração'
    }[relatFiltros.date_tipo] ?? '';

    const partes = [];
    if (relatFiltros.date_ini) partes.push(`de ${dataBR(relatFiltros.date_ini)}`);
    if (relatFiltros.date_fim) partes.push(`até ${dataBR(relatFiltros.date_fim)}`);
    pills.push(`<span class="relat-filtro-ativo"><i class="fas fa-calendar-alt"></i> ${tipoLabel}: ${partes.join(' ')}</span>`);
  }

  badge.innerHTML = `<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">${pills.join('')}</div>`;
}

/* =========================================================
   LOAD DE DADOS
========================================================= */
async function carregarRelat(action) {
  const kpiEl     = document.getElementById('relatKpi');
  const contentEl = document.getElementById('relatContent');
  if (!contentEl) return;

  contentEl.innerHTML = `
    <div class="relat-loading">
      <i class="fas fa-spinner fa-spin"></i> Carregando...
    </div>`;
  if (kpiEl) kpiEl.innerHTML = '';

  try {
    const res = await apiFetch('backend/api_relatorios.php', {
      body: {
        action,
        q:         relatFiltros.q,
        status:    relatFiltros.status,
        versao:    relatFiltros.versao,
        date_tipo: relatFiltros.date_tipo,
        date_ini:  relatFiltros.date_ini,
        date_fim:  relatFiltros.date_fim
      }
    });

    if (!res.success) throw new Error(res.message || 'Erro ao carregar');

    if (kpiEl) renderRelatKpi(kpiEl, action, res);
    renderRelatTable(contentEl, action, res.rows || []);

  } catch (e) {
    contentEl.innerHTML = `<p style="color:var(--color-danger);padding:16px">${e.message}</p>`;
    showToast('Erro ao carregar relatório', 'danger');
  }
}

/* =========================================================
   KPI BADGE
========================================================= */
function renderRelatKpi(el, action, res) {
  const total     = res.total ?? 0;
  const temFiltro = relatFiltros.date_ini !== '' || relatFiltros.date_fim !== '';

  const labels = {
    recentes:       temFiltro
      ? `${total} resultado${total !== 1 ? 's' : ''} no período`
      : `${total} empresa${total !== 1 ? 's' : ''} nos últimos 30 dias`,
    inativos:       `${total} empresa${total !== 1 ? 's' : ''} inativa${total !== 1 ? 's' : ''}`,
    sem_integracao: `${total} empresa${total !== 1 ? 's' : ''} sem nenhuma integração`,
    com_integracao: `${total} empresa${total !== 1 ? 's' : ''} com integração ativa`,
    por_versao:     `${total} versão${total !== 1 ? 'ões' : ''} encontrada${total !== 1 ? 's' : ''}`,
    desatualizados: `${total} empresa${total !== 1 ? 's' : ''} sem atualização há 90+ dias`,
    com_exe:        `${total} empresa${total !== 1 ? 's' : ''} com EXE configurado`
  };

  const colors = {
    recentes:       'var(--color-success)',
    inativos:       'var(--color-danger)',
    sem_integracao: 'var(--color-warning)',
    com_integracao: 'var(--color-primary)',
    por_versao:     'var(--color-primary)',
    desatualizados: '#f97316',
    com_exe:        '#a78bfa'
  };

  const cor = colors[action] ?? 'var(--color-primary)';
  el.innerHTML = `
    <div class="relat-kpi-badge" style="border-color:${cor}">
      <span class="relat-kpi-num" style="color:${cor}">${total}</span>
      <span class="relat-kpi-label">${labels[action] ?? ''}</span>
    </div>`;
}

/* =========================================================
   TABELA
========================================================= */
function renderRelatTable(el, action, rows) {
  const cfg = RELAT_TABS[action];
  if (!cfg) return;

  if (!rows.length) {
    el.innerHTML = `
      <div class="table-empty" style="padding:48px 20px">
        <i class="fas fa-inbox"></i>
        <p>Nenhum registro encontrado com os filtros aplicados.</p>
      </div>`;
    return;
  }

  const ths = cfg.cols.map(c => `<th>${c}</th>`).join('');

  const trs = rows.map(r => {
    let cells = '';

    if (action === 'recentes') {
      cells = `
        <td><span class="cell-with-copy">${esc(r.codigo_cliente)}<button class="btn-copy" data-copy="${esc(r.codigo_cliente)}" title="Copiar"><i class="fas fa-copy"></i></button></span></td>
        <td>${esc(r.nome_cliente)}</td>
        <td>${esc(r.versao_padrao || '—')}</td>
        <td>${badgeStatus(r.status)}</td>
        <td><small class="muted">${esc(r.criado_em)}</small></td>`;

    } else if (action === 'inativos') {
      cells = `
        <td><span class="cell-with-copy">${esc(r.codigo_cliente)}<button class="btn-copy" data-copy="${esc(r.codigo_cliente)}" title="Copiar"><i class="fas fa-copy"></i></button></span></td>
        <td>${esc(r.nome_cliente)}</td>
        <td>${esc(r.versao_padrao || '—')}</td>
        <td><small class="muted">${esc(r.atualizado_em)}</small></td>`;

    } else if (action === 'sem_integracao') {
      cells = `
        <td><span class="cell-with-copy">${esc(r.codigo_cliente)}<button class="btn-copy" data-copy="${esc(r.codigo_cliente)}" title="Copiar"><i class="fas fa-copy"></i></button></span></td>
        <td>${esc(r.nome_cliente)}</td>
        <td>${esc(r.versao_padrao || '—')}</td>
        <td>${badgeStatus(r.status)}</td>
        <td><small class="muted">${esc(r.atualizado_em)}</small></td>`;

    } else if (action === 'com_integracao') {
      const icons = [
        Number(r.total_mobile) > 0
          ? `<i class="fas fa-mobile-alt" style="color:#2196f3" title="Mobile (${r.total_mobile})"></i>`
          : '',
        Number(r.total_whats) > 0
          ? `<i class="fab fa-whatsapp" style="color:#25D366" title="WhatsApp (${r.total_whats})"></i>`
          : '',
        Number(r.total_pdvoff) > 0
          ? `<i class="fas fa-cash-register" style="color:#38bdf8" title="PDV OFF (${r.total_pdvoff})"></i>`
          : ''
      ].filter(Boolean).join(' ');

      cells = `
        <td><span class="cell-with-copy">${esc(r.codigo_cliente)}<button class="btn-copy" data-copy="${esc(r.codigo_cliente)}" title="Copiar"><i class="fas fa-copy"></i></button></span></td>
        <td>${esc(r.nome_cliente)}</td>
        <td>${esc(r.versao_padrao || '—')}</td>
        <td>${badgeStatus(r.status)}</td>
        <td class="integracoes-cell">${icons || '<span style="opacity:.3">—</span>'}</td>
        <td><small class="muted">${esc(r.atualizado_em)}</small></td>`;

    } else if (action === 'por_versao') {
      cells = `
        <td><strong>${esc(r.versao)}</strong></td>
        <td><span style="color:var(--color-success)">${r.ativos}</span></td>
        <td><span style="color:var(--color-danger)">${r.inativos}</span></td>
        <td><strong>${r.total}</strong></td>`;

    } else if (action === 'desatualizados') {
      const dias = Number(r.dias_sem_atualizacao);
      const cor  = dias > 180 ? 'var(--color-danger)' : '#f97316';
      cells = `
        <td><span class="cell-with-copy">${esc(r.codigo_cliente)}<button class="btn-copy" data-copy="${esc(r.codigo_cliente)}" title="Copiar"><i class="fas fa-copy"></i></button></span></td>
        <td>${esc(r.nome_cliente)}</td>
        <td>${esc(r.versao_padrao || '—')}</td>
        <td>${badgeStatus(r.status)}</td>
        <td><small class="muted">${esc(r.ultima_atualizacao)}</small></td>
        <td><strong style="color:${cor}">${dias}d</strong></td>`;

    } else if (action === 'com_exe') {
      cells = `
        <td><span class="cell-with-copy">${esc(r.codigo_cliente)}<button class="btn-copy" data-copy="${esc(r.codigo_cliente)}" title="Copiar"><i class="fas fa-copy"></i></button></span></td>
        <td>${esc(r.nome_cliente)}</td>
        <td>${esc(r.versao_padrao || '—')}</td>
        <td><span class="badge info">${esc(r.exe_nome || 'EXE')}</span></td>
        <td>${badgeStatus(r.status)}</td>
        <td><small class="muted">${esc(r.atualizado_em)}</small></td>`;
    }

    return `<tr>${cells}</tr>`;
  }).join('');

  el.innerHTML = `
    <div class="table-wrap">
      <table class="table">
        <thead><tr>${ths}</tr></thead>
        <tbody>${trs}</tbody>
      </table>
    </div>`;
}

/* =========================================================
   HELPERS
========================================================= */
function badgeStatus(status) {
  const ok = String(status).toUpperCase() === 'ATIVO';
  return `<span class="badge ${ok ? 'success' : 'danger'}">${esc(status)}</span>`;
}
