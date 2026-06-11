/* =========================================================
   Exportar — CSV com seleção de filtros e colunas
   + Relatório de Movimentação Mensal de Usuários
========================================================= */

/* ── Colunas por módulo ──────────────────────────────── */
const _EXPORT_COLS = {
  logins: [
    { key: 'codigo_cliente', label: 'Código' },
    { key: 'nome_cliente',   label: 'Cliente' },
    { key: 'cnpj',           label: 'CNPJ' },
    { key: 'caminho_acesso', label: 'Caminho de Acesso' },
    { key: 'versao_padrao',  label: 'Versão' },
    { key: 'regiao',         label: 'Região' },
    { key: 'status',         label: 'Status' },
    { key: 'possui_exe',     label: 'Tem EXE' },
    { key: 'criado_em',      label: 'Cadastrado em' },
    { key: 'atualizado_em',  label: 'Atualizado em' }
  ],
  mobile: [
    { key: 'cod_cliente',    label: 'Código' },
    { key: 'cliente',        label: 'Cliente' },
    { key: 'acesso_server',  label: 'Servidor' },
    { key: 'porta',          label: 'Porta' },
    { key: 'tipo_acesso',    label: 'Tipo' },
    { key: 'versao_app',     label: 'Versão App' },
    { key: 'versao_empresa', label: 'Versão Empresa' },
    { key: 'regiao',         label: 'Região' },
    { key: 'criado_em',      label: 'Cadastrado em' },
    { key: 'atualizado_em',  label: 'Atualizado em' }
  ],
  whatsapp: [
    { key: 'cod_cliente',    label: 'Código' },
    { key: 'cliente',        label: 'Cliente' },
    { key: 'acesso_server',  label: 'Servidor' },
    { key: 'versao_app',     label: 'Versão App' },
    { key: 'versao_empresa', label: 'Versão Empresa' },
    { key: 'regiao',         label: 'Região' },
    { key: 'criado_em',      label: 'Cadastrado em' },
    { key: 'atualizado_em',  label: 'Atualizado em' }
  ],
  pdvoff: [
    { key: 'cod_cliente',    label: 'Código' },
    { key: 'cliente',        label: 'Cliente' },
    { key: 'acesso_server',  label: 'Servidor' },
    { key: 'versao_empresa', label: 'Versão' },
    { key: 'regiao',         label: 'Região' },
    { key: 'criado_em',      label: 'Cadastrado em' },
    { key: 'atualizado_em',  label: 'Atualizado em' }
  ],
  bi: [
    { key: 'cod_cliente',    label: 'Código' },
    { key: 'cliente',        label: 'Cliente' },
    { key: 'acesso_server',  label: 'Servidor' },
    { key: 'versao_empresa', label: 'Versão' },
    { key: 'regiao',         label: 'Região' },
    { key: 'criado_em',      label: 'Cadastrado em' },
    { key: 'atualizado_em',  label: 'Atualizado em' }
  ],
  'usuarios-web': [
    { key: 'nome_empresa',   label: 'Empresa' },
    { key: 'codigo_empresa', label: 'Código' },
    { key: 'qtd_usuarios',   label: 'Qtd Usuários' },
    { key: 'status',         label: 'Status' },
    { key: 'observacao',     label: 'Observação' },
    { key: 'criado_em',      label: 'Cadastrado em' },
    { key: 'atualizado_em',  label: 'Atualizado em' }
  ]
};

/* ── Body de cada módulo (monta com os filtros atuais) ── */
const _EXPORT_BODY = {
  logins: () => ({
    action: 'list', page: 1, limit: 9999,
    q: loginState.q, status: loginState.status, versao: loginState.versao,
    regiao: loginState.regiao, exe: loginState.exe, integracao: loginState.integracao,
    qtd_usuarios: loginState.qtd_usuarios,
    sortField: loginState.sortField, sortDir: loginState.sortDir
  }),
  mobile: () => ({
    action: 'list', page: 1, limit: 9999,
    q: mobileState.q, tipo: mobileState.tipo,
    versao_app: mobileState.versao_app, regiao: mobileState.regiao,
    versao_empresa: mobileState.versao_empresa,
    order_by: mobileState.order_by, order_dir: mobileState.order_dir
  }),
  whatsapp: () => ({
    action: 'list', page: 1, limit: 9999,
    q: whatsState.q, versao_app: whatsState.versao_app,
    regiao: whatsState.regiao, versao_empresa: whatsState.versao_empresa,
    order_by: whatsState.order_by, order_dir: whatsState.order_dir
  }),
  pdvoff: () => ({
    action: 'list', page: 1, limit: 9999,
    q: pdvOffState.q, regiao: pdvOffState.regiao,
    versao_empresa: pdvOffState.versao_empresa,
    order_by: pdvOffState.order_by, order_dir: pdvOffState.order_dir
  }),
  bi: () => ({
    action: 'list', page: 1, limit: 9999,
    q: biState.q, regiao: biState.regiao,
    versao_empresa: biState.versao_empresa,
    order_by: biState.order_by, order_dir: biState.order_dir
  }),
  'usuarios-web': () => ({
    action: 'list', page: 1, limit: 9999,
    q: usuariosWebState.q, min_users: usuariosWebState.minUsers,
    status: usuariosWebState.status,
    order_by: usuariosWebState.orderBy, order_dir: usuariosWebState.orderDir
  })
};

const _EXPORT_URL = {
  logins:         'backend/api_login.php',
  mobile:         'backend/api_mobile.php',
  whatsapp:       'backend/api_whatsapp.php',
  pdvoff:         'backend/api_pdvoff.php',
  bi:             'backend/api_bi.php',
  'usuarios-web': 'backend/api_usuarios_web.php'
};

/* ── Módulos que têm campo status (ATIVO/INATIVO) ──── */
const _EXP_HAS_STATUS = {
  logins: true, mobile: false, whatsapp: false,
  pdvoff: false, bi: false, 'usuarios-web': true
};

/* ── Nomes legíveis dos módulos ─────────────────────── */
const _EXP_NAMES = {
  logins: 'Empresas Web', mobile: 'Mobile (FV + API)',
  whatsapp: 'WhatsApp', pdvoff: 'PDV OFF',
  bi: 'BI', 'usuarios-web': 'Usuários Web'
};

/* ── Estado interno do modal ────────────────────────── */
let _expFilterModulo = null;

/* ─────────────────────────────────────────────────────
   MODAL DE FILTROS — ABERTURA
───────────────────────────────────────────────────── */
function exportarCSV(modulo) {
  _expFilterModulo = modulo;

  /* Título */
  const titleEl = document.getElementById('expFilterTitle');
  if (titleEl) titleEl.textContent = `Exportar — ${_EXP_NAMES[modulo] || modulo}`;

  /* Status group: visível somente em módulos com status */
  const stGroup = document.getElementById('ef_status_group');
  if (stGroup) stGroup.style.display = _EXP_HAS_STATUS[modulo] ? '' : 'none';

  /* Limpa datas */
  const de  = document.getElementById('ef_de');
  const ate = document.getElementById('ef_ate');
  if (de)  de.value  = '';
  if (ate) ate.value = '';

  /* Redefine status checkboxes */
  const efAtivo   = document.getElementById('ef_ativo');
  const efInativo = document.getElementById('ef_inativo');
  if (efAtivo)   efAtivo.checked   = true;
  if (efInativo) efInativo.checked = true;

  /* Monta checkboxes de colunas + dropdowns sort/group */
  const wrap = document.getElementById('ef_cols_wrap');
  const sortColSel  = document.getElementById('ef_sort_col');
  const groupColSel = document.getElementById('ef_group_col');

  const cols = _EXPORT_COLS[modulo] || [];

  if (wrap) {
    wrap.innerHTML = '';
    cols.forEach(col => {
      const lbl = document.createElement('label');
      lbl.className = 'exp-filter-check';
      lbl.innerHTML = `<input type="checkbox" class="ef_col_cb" data-key="${col.key}" checked> ${col.label}`;
      wrap.appendChild(lbl);
    });
  }

  const colOpts = cols.map(c => `<option value="${c.key}">${c.label}</option>`).join('');
  if (sortColSel) {
    sortColSel.innerHTML  = '<option value="">— padrão do módulo —</option>' + colOpts;
    sortColSel.value      = '';
  }
  if (groupColSel) {
    groupColSel.innerHTML = '<option value="">— sem agrupamento —</option>' + colOpts;
    groupColSel.value     = '';
  }

  const sortDir = document.getElementById('ef_sort_dir');
  if (sortDir) sortDir.value = 'ASC';

  /* Botão confirmar */
  const confirmBtn = document.getElementById('expFilterConfirmBtn');
  if (confirmBtn) confirmBtn.onclick = _doFilteredExport;

  /* Exibe modal */
  const backdrop = document.getElementById('expFilterBackdrop');
  if (backdrop) {
    backdrop.classList.remove('hidden');
    backdrop.classList.add('show');
  }
}

/* ─────────────────────────────────────────────────────
   MODAL DE FILTROS — FECHAMENTO
───────────────────────────────────────────────────── */
function closeExpFilterModal() {
  const el = document.getElementById('expFilterBackdrop');
  if (!el) return;
  el.classList.remove('show');
  el.classList.add('hidden');
}

/* ─────────────────────────────────────────────────────
   EXPORTAR COM FILTROS APLICADOS
───────────────────────────────────────────────────── */
async function _doFilteredExport() {
  const modulo = _expFilterModulo;
  if (!modulo) return;

  closeExpFilterModal();

  /* Colunas selecionadas */
  const allCols  = _EXPORT_COLS[modulo] || [];
  const selected = new Set(
    [...document.querySelectorAll('.ef_col_cb:checked')].map(cb => cb.dataset.key)
  );
  const cols = selected.size > 0 ? allCols.filter(c => selected.has(c.key)) : allCols;

  if (cols.length === 0) { showToast('Selecione ao menos uma coluna', 'warning'); return; }

  /* Período */
  const de  = document.getElementById('ef_de')?.value  || '';
  const ate = document.getElementById('ef_ate')?.value || '';

  /* Status */
  const statusSel = [
    document.getElementById('ef_ativo')?.checked   && 'ATIVO',
    document.getElementById('ef_inativo')?.checked && 'INATIVO'
  ].filter(Boolean);

  /* Desabilita botão inline se existir */
  const btn = document.querySelector(`[data-exp-modulo="${modulo}"]`);
  if (btn) { btn.disabled = true; btn.textContent = 'Exportando...'; }

  showToast('Exportando dados — aguarde...', 'info', 4000);

  try {
    const res = await apiFetch(_EXPORT_URL[modulo], { body: _EXPORT_BODY[modulo]() });
    if (!res.success) throw new Error(res.message || 'Falha ao buscar dados');

    let rows = res.rows;

    /* Filtro de data (client-side) */
    if (de || ate) {
      rows = rows.filter(r => {
        const raw = String(r.criado_em || '');
        let ds;
        if (/^\d{4}-\d{2}-\d{2}/.test(raw)) {
          ds = raw.substring(0, 10);
        } else if (/^\d{2}\/\d{2}\/\d{4}/.test(raw)) {
          const [dd, mm, yyyy] = raw.split('/');
          ds = `${yyyy}-${mm}-${dd}`;
        } else {
          return true;
        }
        if (de  && ds < de)  return false;
        if (ate && ds > ate) return false;
        return true;
      });
    }

    /* Filtro de status (client-side) */
    if (_EXP_HAS_STATUS[modulo] && statusSel.length > 0 && statusSel.length < 2) {
      rows = rows.filter(r => statusSel.includes(String(r.status || '').toUpperCase()));
    }

    /* Ordenação + Agrupamento (client-side) */
    const sortCol  = document.getElementById('ef_sort_col')?.value  || '';
    const sortDir  = document.getElementById('ef_sort_dir')?.value  || 'ASC';
    const groupCol = document.getElementById('ef_group_col')?.value || '';

    const cmpStr = (a, b, key, dir = 'ASC') => {
      const va = String(a[key] ?? '').toLowerCase();
      const vb = String(b[key] ?? '').toLowerCase();
      const c  = va.localeCompare(vb, 'pt-BR', { numeric: true });
      return dir === 'DESC' ? -c : c;
    };

    if (groupCol || sortCol) {
      rows.sort((a, b) => {
        /* grupo primeiro (sempre ASC para clusterar), depois coluna de ordem */
        if (groupCol && sortCol && groupCol !== sortCol) {
          return cmpStr(a, b, groupCol) || cmpStr(a, b, sortCol, sortDir);
        }
        if (groupCol) return cmpStr(a, b, groupCol);
        return cmpStr(a, b, sortCol, sortDir);
      });
    }

    const date = new Date().toLocaleDateString('pt-BR').replace(/\//g, '-');
    _csvDownload(rows, cols, `${modulo}_${date}.csv`, groupCol);
    showToast(`✓ ${rows.length} registros exportados`, 'success');
  } catch (e) {
    showToast('Erro na exportação: ' + (e.message || ''), 'danger');
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-download"></i> CSV'; }
  }
}

/* ─────────────────────────────────────────────────────
   DOWNLOAD CSV GENÉRICO
───────────────────────────────────────────────────── */
function _csvDownload(rows, cols, filename, groupCol = '') {
  const header = cols.map(c => `"${c.label}"`).join(';');
  const lines  = [];

  if (groupCol) {
    const groupLabel = cols.find(c => c.key === groupCol)?.label || groupCol;
    let lastGroup = null;
    rows.forEach(r => {
      const gVal = String(r[groupCol] ?? '');
      if (gVal !== lastGroup) {
        if (lastGroup !== null) lines.push(''); // linha em branco entre grupos
        lines.push(`"── ${groupLabel}: ${gVal} ──"`);
        lastGroup = gVal;
      }
      lines.push(cols.map(c => `"${String(r[c.key] ?? '').replace(/"/g, '""')}"`).join(';'));
    });
  } else {
    rows.forEach(r => {
      lines.push(cols.map(c => `"${String(r[c.key] ?? '').replace(/"/g, '""')}"`).join(';'));
    });
  }

  const csv  = '﻿' + [header, ...lines].join('\r\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url  = URL.createObjectURL(blob);
  const a    = Object.assign(document.createElement('a'), { href: url, download: filename });
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

/* ─────────────────────────────────────────────────────
   RELATÓRIO DE MOVIMENTAÇÃO MENSAL
───────────────────────────────────────────────────── */
async function exportarMovimentacao() {
  const mesEl = document.getElementById('expMovMes');
  const mes   = mesEl?.value || '';

  if (!mes) {
    showToast('Selecione o mês do relatório', 'warning');
    return;
  }

  const btn = document.getElementById('btnExpMovimentacao');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando...'; }

  showToast('Gerando relatório de movimentação...', 'info', 5000);

  try {
    const res = await apiFetch('backend/api_movimentacao.php', {
      body: { action: 'movimentacao_mensal', mes }
    });

    if (!res.success) throw new Error(res.message || 'Erro ao gerar relatório');

    /* Nome legível do mês */
    const [ano, m] = mes.split('-');
    const nomeMes  = new Date(parseInt(ano), parseInt(m) - 1, 1)
      .toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
      .toUpperCase();

    const L = [];
    L.push('"QUALLIT - RELATORIO DE MOVIMENTACAO MENSAL"');
    L.push(`"Periodo: ${nomeMes}"`);
    L.push('');
    L.push('"USUARIOS CRIADOS"');
    L.push('');

    if (res.criados.length === 0) {
      L.push('"Nenhum usuario criado neste periodo"');
    } else {
      res.criados.forEach(emp => {
        const nota = emp.note ? ` (${emp.note})` : '';
        L.push(`"${emp.empresa}${nota}";"${emp.qtd} usuario(s)"`);
        L.push('"Username";"Nome";"Data Criacao";"Data Desativacao"');
        emp.usuarios.forEach(u => {
          L.push(`"${u.username}";"${u.nome}";"${u.data_cria}";"${u.data_desativ}"`);
        });
        L.push('');
      });
    }

    L.push(`"Total de usuarios criados: ${res.total_criados}"`);
    L.push('');
    L.push('');
    L.push('"USUARIOS DESATIVADOS"');
    L.push('');

    if (res.desativados.length === 0) {
      L.push('"Nenhum usuario desativado neste periodo"');
    } else {
      res.desativados.forEach(emp => {
        L.push(`"${emp.empresa}";"${emp.qtd} usuario(s)"`);
        L.push('"Username";"Nome";"Data Criacao";"Data Desativacao"');
        emp.usuarios.forEach(u => {
          L.push(`"${u.username}";"${u.nome}";"${u.data_cria}";"${u.data_desativ}"`);
        });
        L.push('');
      });
    }

    L.push(`"Total de usuarios desativados: ${res.total_desativados}"`);

    const csv  = '﻿' + L.join('\r\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = Object.assign(document.createElement('a'), {
      href:     url,
      download: `movimentacao_${mes}.csv`
    });
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);

    showToast(
      `✓ Relatório gerado — ${res.total_criados} criados, ${res.total_desativados} desativados`,
      'success'
    );

  } catch (e) {
    showToast('Erro: ' + (e.message || ''), 'danger');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-file-csv"></i> Gerar Relatório';
    }
  }
}

/* ── Loader da aba Exportar ─────────────────────────── */
function loadExportar() {
  /* Pré-seleciona o mês atual no seletor de movimentação */
  const mesEl = document.getElementById('expMovMes');
  if (mesEl && !mesEl.value) {
    const now = new Date();
    mesEl.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  }

  /* Fecha modal de filtro ao clicar no backdrop */
  const backdrop = document.getElementById('expFilterBackdrop');
  if (backdrop && !backdrop._hasClickHandler) {
    backdrop._hasClickHandler = true;
    backdrop.addEventListener('click', e => {
      if (e.target === backdrop) closeExpFilterModal();
    });
  }
}
