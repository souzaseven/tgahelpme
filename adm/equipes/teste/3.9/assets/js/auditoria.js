/* =========================================================
   Auditoria — View + Notificações
========================================================= */

/* ==========================
   ESTADO
========================== */
let auditPag   = 1;
let auditPages = 1;
let auditFilters = { q: '', modulo: '', acao: '', de: '', ate: '' };
let auditDateInitialized = false;

// Timestamp da última leitura das notificações (salvo no localStorage)
const NOTIF_KEY = 'auditoria_last_seen';

function getLastSeen() {
  return localStorage.getItem(NOTIF_KEY) || '';
}

function setLastSeen(ts) {
  if (ts) localStorage.setItem(NOTIF_KEY, ts);
}

/* ==========================
   NOTIFICAÇÕES — POLLING
========================== */
let notifPollingTimer = null;

async function checkNotifications() {
  try {
    const since = getLastSeen();
    const data = await apiFetch('backend/api_auditoria.php', {
      body: { action: 'notifications', since, limit: 20 }
    });

    if (!data.success) return;

    const badge  = document.getElementById('notifBadge');
    const list   = document.getElementById('notifList');
    const rows   = data.rows || [];
    const total  = data.total_novas || 0;

    // Atualiza badge
    if (badge) {
      badge.textContent = total > 99 ? '99+' : (total || '');
      badge.classList.toggle('hidden', total === 0);
    }

    // Renderiza dropdown
    if (list) {
      if (rows.length === 0) {
        list.innerHTML = '<li class="notif-empty">Nenhuma atividade recente</li>';
      } else {
        list.innerHTML = rows.map(r => {
          const icon  = acaoIcon(r.acao);
          const color = acaoCor(r.acao);
          return `
            <li class="notif-item" onclick="irParaAuditoria()">
              <span class="notif-icon" style="color:${color}">${icon}</span>
              <div class="notif-info">
                <strong>${escHtml(r.descricao || r.acao)}</strong>
                <span>${escHtml(r.modulo)} • ${escHtml(r.usuario)}</span>
                <span class="notif-time">${escHtml(r.criado_em)}</span>
              </div>
            </li>
          `;
        }).join('');
      }
    }
  } catch (_) {
    // silencia erros de rede no polling
  }
}

function marcarNotificacoesLidas() {
  const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
  setLastSeen(now);

  const badge = document.getElementById('notifBadge');
  if (badge) {
    badge.textContent = '';
    badge.classList.add('hidden');
  }
}

function irParaAuditoria() {
  fecharDropdownNotif();
  marcarNotificacoesLidas();
  if (typeof switchTab === 'function') switchTab('auditoria');
}

function iniciarPollingNotificacoes() {
  checkNotifications();
  if (notifPollingTimer) clearInterval(notifPollingTimer);
  notifPollingTimer = setInterval(checkNotifications, 30_000);
}

/* ==========================
   DROPDOWN SININHO
========================== */
function toggleDropdownNotif() {
  const dd = document.getElementById('notifDropdown');
  if (!dd) return;
  if (dd.classList.contains('hidden')) {
    dd.classList.remove('hidden');
    marcarNotificacoesLidas();
    checkNotifications();
  } else {
    fecharDropdownNotif();
  }
}

function fecharDropdownNotif() {
  document.getElementById('notifDropdown')?.classList.add('hidden');
}

// Botão sininho — toggle sem borbulhar para o document
const _btnNotif  = document.getElementById('btnNotif');
const _notifWrap = document.getElementById('notifWrap');

if (_btnNotif) {
  _btnNotif.addEventListener('click', e => {
    e.stopPropagation();
    toggleDropdownNotif();
  });
}

// Cliques dentro do dropdown (ex.: "Ver tudo") não fecham sozinhos
if (_notifWrap) {
  _notifWrap.addEventListener('click', e => e.stopPropagation());
}

// Qualquer clique fora do wrap fecha o dropdown
document.addEventListener('click', () => fecharDropdownNotif());

// Tecla Escape também fecha
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') fecharDropdownNotif();
});

/* ==========================
   CARREGAR VIEW AUDITORIA
========================== */
async function loadAuditoria(resetPage = true) {
  if (resetPage) auditPag = 1;

  // Na primeira carga, define hoje como data padrão nos filtros
  if (!auditDateInitialized) {
    auditDateInitialized = true;
    const today = new Date().toISOString().slice(0, 10);
    const elDe  = document.getElementById('auditFiltroDe');
    const elAte = document.getElementById('auditFiltroAte');
    if (elDe && !elDe.value) { elDe.value = today; auditFilters.de = today; }
    if (elAte && !elAte.value) { elAte.value = today; auditFilters.ate = today; }
  }

  const tbody   = document.getElementById('auditTbody');
  const paginEl = document.getElementById('auditPaginacao');

  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="7" class="loading-cell"><i class="fas fa-spinner fa-spin"></i> Carregando…</td></tr>';

  try {
    const data = await apiFetch('backend/api_auditoria.php', {
      body: {
        action: 'list',
        page:   auditPag,
        limit:  20,
        ...auditFilters
      }
    });

    if (!data.success) throw new Error(data.message);

    auditPages = data.pages || 1;

    // Preenche selects de filtro
    popularSelectFiltro('auditFiltroModulo', data.modulos  || [], 'Todos os módulos');
    popularSelectFiltro('auditFiltroUsuario', data.usuarios || [], 'Todos os usuários');

    if (!data.rows || data.rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="empty-cell">Nenhum registro encontrado</td></tr>';
      if (paginEl) paginEl.innerHTML = '';
      return;
    }

    tbody.innerHTML = data.rows.map(r => {
      const acaoBadge = `<span class="audit-badge audit-${r.acao.toLowerCase()}">${acaoLabel(r.acao)}</span>`;
      const diff      = renderDiff(r.dados_antes, r.dados_depois);
      return `
        <tr>
          <td data-label="Data">${escHtml(r.criado_em)}</td>
          <td data-label="Usuário"><strong>${escHtml(r.usuario)}</strong></td>
          <td data-label="Ação">${acaoBadge}</td>
          <td data-label="Módulo">${escHtml(r.modulo)}</td>
          <td data-label="Descrição">${escHtml(r.descricao || '—')}</td>
          <td data-label="Alterações" class="audit-diff-cell">${diff}</td>
          <td data-label="IP" class="audit-ip">${escHtml(r.ip || '—')}</td>
        </tr>
      `;
    }).join('');

    renderPaginacaoAudit(data.total, data.page, data.pages);

  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="7" class="empty-cell" style="color:var(--color-danger)">${escHtml(err.message)}</td></tr>`;
  }
}

function popularSelectFiltro(id, opcoes, placeholder) {
  const sel = document.getElementById(id);
  if (!sel) return;
  const valAtual = sel.value;
  sel.innerHTML = `<option value="">${placeholder}</option>` +
    opcoes.map(o => `<option value="${escHtml(o)}" ${o === valAtual ? 'selected' : ''}>${escHtml(o)}</option>`).join('');
}

/* ==========================
   DIFF ANTES / DEPOIS
========================== */
function renderDiff(antes, depois) {
  if (!antes && !depois) return '<span class="text-muted">—</span>';

  if (!antes) {
    // CREATE
    return Object.entries(depois || {})
      .map(([k, v]) => `<div class="diff-row diff-add"><span class="diff-key">${escHtml(k)}</span>: <span class="diff-val">${escHtml(String(v ?? ''))}</span></div>`)
      .join('');
  }

  if (!depois) {
    // DELETE
    return Object.entries(antes || {})
      .map(([k, v]) => `<div class="diff-row diff-del"><span class="diff-key">${escHtml(k)}</span>: <span class="diff-val">${escHtml(String(v ?? ''))}</span></div>`)
      .join('');
  }

  // UPDATE — só mostra campos que mudaram
  const keys   = new Set([...Object.keys(antes), ...Object.keys(depois)]);
  const linhas = [];

  for (const k of keys) {
    const va = String(antes[k]  ?? '');
    const vd = String(depois[k] ?? '');
    if (va !== vd) {
      linhas.push(`
        <div class="diff-row diff-upd">
          <span class="diff-key">${escHtml(k)}</span>:
          <span class="diff-before">${escHtml(va)}</span>
          <span class="diff-arrow">→</span>
          <span class="diff-after">${escHtml(vd)}</span>
        </div>
      `);
    }
  }

  return linhas.length ? linhas.join('') : '<span class="text-muted">Sem alterações</span>';
}

/* ==========================
   PAGINAÇÃO
========================== */
function renderPaginacaoAudit(total, page, pages) {
  const el = document.getElementById('auditPaginacao');
  if (!el) return;

  const info = `<span class="pag-info">${total} registro${total !== 1 ? 's' : ''} • Pág. ${page}/${pages}</span>`;
  const prev = `<button class="btn ghost btn-sm" ${page <= 1 ? 'disabled' : ''} onclick="auditGoPage(${page - 1})">‹ Anterior</button>`;
  const next = `<button class="btn ghost btn-sm" ${page >= pages ? 'disabled' : ''} onclick="auditGoPage(${page + 1})">Próxima ›</button>`;

  el.innerHTML = `<div class="pag-row">${prev}${info}${next}</div>`;
}

function auditGoPage(p) {
  auditPag = p;
  loadAuditoria(false);
}

/* ==========================
   FILTROS
========================== */
function aplicarFiltrosAudit() {
  auditFilters.q       = document.getElementById('auditBusca')?.value.trim()      || '';
  auditFilters.modulo  = document.getElementById('auditFiltroModulo')?.value      || '';
  auditFilters.acao    = document.getElementById('auditFiltroAcao')?.value        || '';
  auditFilters.de      = document.getElementById('auditFiltroDe')?.value          || '';
  auditFilters.ate     = document.getElementById('auditFiltroAte')?.value         || '';
  loadAuditoria(true);
}

function limparFiltrosAudit() {
  ['auditBusca', 'auditFiltroModulo', 'auditFiltroAcao', 'auditFiltroDe', 'auditFiltroAte']
    .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
  auditFilters = { q: '', modulo: '', acao: '', de: '', ate: '' };
  loadAuditoria(true);
}

/* ==========================
   HELPERS
========================== */
function acaoLabel(acao) {
  return { CREATE: 'Criação', UPDATE: 'Atualização', DELETE: 'Exclusão' }[acao] || acao;
}

function acaoIcon(acao) {
  return { CREATE: '➕', UPDATE: '✏️', DELETE: '🗑️' }[acao] || '📋';
}

function acaoCor(acao) {
  return { CREATE: '#22c55e', UPDATE: '#38bdf8', DELETE: '#ef4444' }[acao] || '#a78bfa';
}

function escHtml(s) {
  return String(s ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
