/* =========================================================
   Servidores — Módulo (Drag & Drop + CRUD)
   Painel Unificado Clientes Web
========================================================= */

let srvRows      = [];
let srvDragSrc   = null;
let srvEditId    = null;
let srvDragBound = false;
let srvViewLayout = localStorage.getItem('srv_view_layout') || 'grid';

/* ==================== LOAD ==================== */
async function loadServidores() {
  const view = document.getElementById('view-servidores');
  if (!view) return;

  buildSrvModal();
  bindSrvToolbar();
  await fetchServidores();
}

/* ==================== FETCH ==================== */
async function fetchServidores() {
  try {
    const res = await apiFetch('backend/api_servidores.php', {
      body: { action: 'list' }
    });
    if (!res.success) throw new Error(res.message);
    srvRows = res.rows || [];
    renderServidores(srvRows);
  } catch (e) {
    console.error(e);
  }
}

/* ==================== RENDER ==================== */
function renderServidores(rows) {
  const grid = document.getElementById('servidoresGrid');
  if (!grid) return;

  grid.innerHTML = '';

  if (!rows.length) {
    grid.innerHTML = `
      <div class="table-empty" style="grid-column:1/-1;padding:40px 0">
        <i class="fas fa-server"></i>
        <p>Nenhum card cadastrado. Clique em "Novo Card" para começar.</p>
      </div>`;
    return;
  }

  rows.forEach(r => grid.appendChild(buildCard(r)));
  applySrvLayout();
  bindSrvDragDrop();
}

function buildCard(r) {
  const card = document.createElement('div');
  card.className  = 'card server-section' + (r.full_width ? ' srv-card-full' : '');
  card.draggable  = true;
  card.dataset.id = r.id;

  const itens = Array.isArray(r.itens) ? r.itens : [];
  const links = Array.isArray(r.links) ? r.links : [];

  /* Cabeçalho */
  let html = `
    <div class="srv-card-top">
      <span class="srv-drag-handle" title="Arrastar para reordenar">⠿</span>
      <div class="srv-title-block">
        <h3>${esc(r.icone)} ${esc(r.titulo)}</h3>
        ${r.descricao ? `<p class="muted">${esc(r.descricao)}</p>` : ''}
      </div>
      <div class="srv-card-actions">
        <button class="btn ghost" data-srv-edit="${r.id}" title="Editar">✏️</button>
        <button class="btn ghost danger" data-srv-del="${r.id}" title="Excluir">🗑</button>
      </div>
    </div>`;

  /* Lista de itens */
  if (itens.length) {
    const gridClass = r.layout === 'grid' ? ' grid' : '';
    html += `<ul class="server-list${gridClass}">`;
    itens.forEach(item => {
      html += `<li>
        <strong>${esc(item.nome || '')}</strong>
        ${item.tag  ? `<span class="tag">${esc(item.tag)}</span>` : ''}
        ${item.desc ? `<small>${esc(item.desc)}</small>` : ''}
      </li>`;
    });
    html += '</ul>';
  }

  /* Links / botões */
  if (links.length) {
    html += '<div class="server-actions" style="gap:8px">';
    links.forEach(lk => {
      const dl  = lk.download ? ' download' : '';
      const tgt = lk.download ? '' : ' target="_blank" rel="noopener"';
      html += `<a href="${esc(lk.url || '#')}" class="btn primary"${dl}${tgt}>${esc(lk.label || 'Link')}</a>`;
    });
    html += '</div>';
  }

  card.innerHTML = html;

  card.querySelector('[data-srv-edit]')?.addEventListener('click', e => {
    e.stopPropagation();
    editarServidor(r.id);
  });
  card.querySelector('[data-srv-del]')?.addEventListener('click', e => {
    e.stopPropagation();
    excluirServidor(r.id);
  });

  return card;
}

/* ==================== DRAG & DROP ==================== */
function bindSrvDragDrop() {
  if (srvDragBound) return;
  srvDragBound = true;

  const grid = document.getElementById('servidoresGrid');

  grid.addEventListener('dragstart', e => {
    if (e.target.closest('button, a')) { e.preventDefault(); return; }
    const card = e.target.closest('.server-section[data-id]');
    if (!card) return;
    srvDragSrc = card;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', card.dataset.id);
    setTimeout(() => card.classList.add('dragging'), 0);
  });

  grid.addEventListener('dragover', e => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    const card = e.target.closest('.server-section[data-id]');
    if (card && card !== srvDragSrc) {
      grid.querySelectorAll('.server-section').forEach(c => c.classList.remove('drag-over'));
      card.classList.add('drag-over');
    }
  });

  grid.addEventListener('dragleave', e => {
    if (!grid.contains(e.relatedTarget)) {
      grid.querySelectorAll('.server-section').forEach(c => c.classList.remove('drag-over'));
    }
  });

  grid.addEventListener('drop', e => {
    e.preventDefault();
    const target = e.target.closest('.server-section[data-id]');
    if (!target || !srvDragSrc || target === srvDragSrc) {
      grid.querySelectorAll('.server-section').forEach(c => c.classList.remove('drag-over','dragging'));
      return;
    }
    const cards  = [...grid.querySelectorAll('.server-section[data-id]')];
    const srcIdx = cards.indexOf(srvDragSrc);
    const tgtIdx = cards.indexOf(target);
    if (srcIdx < tgtIdx) target.after(srvDragSrc);
    else target.before(srvDragSrc);

    grid.querySelectorAll('.server-section').forEach(c => c.classList.remove('drag-over','dragging'));
    saveServidoresOrder();
  });

  grid.addEventListener('dragend', () => {
    grid.querySelectorAll('.server-section').forEach(c => c.classList.remove('dragging','drag-over'));
    srvDragSrc = null;
  });
}

async function saveServidoresOrder() {
  const ids = [...document.querySelectorAll('#servidoresGrid .server-section[data-id]')]
    .map(el => el.dataset.id);
  try {
    await apiFetch('backend/api_servidores.php', { body: { action: 'reorder', ids } });
    showToast('Ordem salva', 'success');
  } catch {
    showToast('Erro ao salvar ordem', 'danger');
  }
}

/* ==================== TOOLBAR ==================== */
function bindSrvToolbar() {
  document.getElementById('btnNovoServidor')
    ?.addEventListener('click', () => openSrvModal());

  document.querySelectorAll('.srv-layout-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      srvViewLayout = btn.dataset.layout;
      localStorage.setItem('srv_view_layout', srvViewLayout);
      updateSrvLayoutBtns();
      applySrvLayout();
    });
  });

  updateSrvLayoutBtns();
}

function updateSrvLayoutBtns() {
  document.querySelectorAll('.srv-layout-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.layout === srvViewLayout);
  });
}

function applySrvLayout() {
  const grid = document.getElementById('servidoresGrid');
  if (!grid) return;
  grid.classList.toggle('layout-list', srvViewLayout === 'list');
  grid.classList.toggle('layout-grid', srvViewLayout === 'grid');
}

/* ==================== MODAL ==================== */
function buildSrvModal() {
  if (document.getElementById('srvModal')) return;

  const el = document.createElement('div');
  el.id        = 'srvModal';
  el.className = 'srv-modal-backdrop';
  el.innerHTML = `
    <div class="srv-modal-dialog">
      <div class="srv-modal-header">
        <h3 id="srvModalTitle">Novo Card</h3>
        <button class="btn ghost" id="srvModalClose" type="button">✕</button>
      </div>

      <div class="srv-modal-body">
        <div class="srv-field-row">
          <div class="srv-field-group srv-field-icon">
            <label>Ícone</label>
            <input id="srvIcone" type="text" maxlength="4" placeholder="🖥️">
          </div>
          <div class="srv-field-group" style="flex:1">
            <label>Título <span style="color:#ff5555">*</span></label>
            <input id="srvTitulo" type="text" placeholder="Ex: Servidor Principal">
          </div>
        </div>

        <div class="srv-field-group">
          <label>Descrição</label>
          <input id="srvDesc" type="text" placeholder="Subtítulo exibido abaixo do título">
        </div>

        <div class="srv-field-group">
          <label>Layout dos itens</label>
          <select id="srvLayout">
            <option value="list">Lista vertical</option>
            <option value="grid">Grade (grid)</option>
          </select>
        </div>

        <label class="srv-full-width-label">
          <input type="checkbox" id="srvFullWidth">
          <span>Largura completa <small>(ocupa a linha inteira na vista em grade)</small></span>
        </label>

        <div class="srv-section-title">
          <span>Itens / Máquinas</span>
          <button class="btn ghost srv-add-row" id="srvAddItem" type="button">+ Adicionar item</button>
        </div>
        <div class="srv-rows-header srv-rows-header-items">
          <span>Nome</span><span>Tag</span><span>Descrição</span><span></span>
        </div>
        <div id="srvItensRows"></div>

        <div class="srv-section-title" style="margin-top:20px">
          <span>Links / Botões de ação</span>
          <button class="btn ghost srv-add-row" id="srvAddLink" type="button">+ Adicionar link</button>
        </div>
        <div class="srv-rows-header srv-rows-header-links">
          <span>Label</span><span>URL</span><span>Download?</span><span></span>
        </div>
        <div id="srvLinksRows"></div>
      </div>

      <div class="srv-modal-footer">
        <button class="btn" id="srvModalCancel" type="button">Cancelar</button>
        <button class="btn primary" id="srvModalSave" type="button">Salvar</button>
      </div>
    </div>`;

  document.body.appendChild(el);

  el.querySelector('#srvModalClose').addEventListener('click',  closeSrvModal);
  el.querySelector('#srvModalCancel').addEventListener('click', closeSrvModal);
  el.querySelector('#srvModalSave').addEventListener('click',   saveSrvModal);
  el.querySelector('#srvAddItem').addEventListener('click',  () => addSrvItemRow());
  el.querySelector('#srvAddLink').addEventListener('click',  () => addSrvLinkRow());

  el.addEventListener('click', e => { if (e.target === el) closeSrvModal(); });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && el.classList.contains('open')) closeSrvModal();
  });
}

function openSrvModal(data = null) {
  const modal = document.getElementById('srvModal');
  if (!modal) return;

  srvEditId = data ? data.id : null;
  document.getElementById('srvModalTitle').textContent = data ? 'Editar Card' : 'Novo Card';
  document.getElementById('srvIcone').value      = data?.icone     || '🖥️';
  document.getElementById('srvTitulo').value     = data?.titulo    || '';
  document.getElementById('srvDesc').value       = data?.descricao || '';
  document.getElementById('srvLayout').value     = data?.layout    || 'list';
  document.getElementById('srvFullWidth').checked = !!(data?.full_width);

  document.getElementById('srvItensRows').innerHTML = '';
  document.getElementById('srvLinksRows').innerHTML = '';

  (Array.isArray(data?.itens) ? data.itens : []).forEach(item =>
    addSrvItemRow(item.nome, item.tag, item.desc)
  );
  (Array.isArray(data?.links) ? data.links : []).forEach(lk =>
    addSrvLinkRow(lk.label, lk.url, lk.download)
  );

  modal.classList.add('open');
  setTimeout(() => document.getElementById('srvTitulo')?.focus(), 50);
}

function closeSrvModal() {
  document.getElementById('srvModal')?.classList.remove('open');
  srvEditId = null;
}

/* ── Linhas dinâmicas ── */
function addSrvItemRow(nome = '', tag = '', desc = '') {
  const row = document.createElement('div');
  row.className = 'srv-item-row';
  row.innerHTML = `
    <input type="text" class="srv-item-nome" placeholder="Nome (ex: SRVAD)" value="${esc(nome)}">
    <input type="text" class="srv-item-tag"  placeholder="Tag" value="${esc(tag)}" style="flex:0 0 90px">
    <input type="text" class="srv-item-desc" placeholder="Descrição" value="${esc(desc)}">
    <button class="srv-remove-row" type="button" title="Remover">✕</button>`;
  row.querySelector('.srv-remove-row').addEventListener('click', () => row.remove());
  document.getElementById('srvItensRows').appendChild(row);
}

function addSrvLinkRow(label = '', url = '', download = false) {
  const row = document.createElement('div');
  row.className = 'srv-link-row';
  row.innerHTML = `
    <input type="text" class="srv-link-label" placeholder="Label (ex: Baixar VPN)" value="${esc(label)}">
    <input type="text" class="srv-link-url"   placeholder="https://..." value="${esc(url)}">
    <label class="srv-link-dl-label">
      <input type="checkbox" class="srv-link-download" ${download ? 'checked' : ''}> Download
    </label>
    <button class="srv-remove-row" type="button" title="Remover">✕</button>`;
  row.querySelector('.srv-remove-row').addEventListener('click', () => row.remove());
  document.getElementById('srvLinksRows').appendChild(row);
}

/* ── Coleta o formulário ── */
function collectSrvForm() {
  return {
    titulo:     document.getElementById('srvTitulo').value.trim(),
    icone:      document.getElementById('srvIcone').value.trim() || '🖥️',
    descricao:  document.getElementById('srvDesc').value.trim(),
    layout:     document.getElementById('srvLayout').value,
    full_width: document.getElementById('srvFullWidth').checked,
    itens: [...document.querySelectorAll('#srvItensRows .srv-item-row')].map(r => ({
      nome: r.querySelector('.srv-item-nome').value.trim(),
      tag:  r.querySelector('.srv-item-tag').value.trim(),
      desc: r.querySelector('.srv-item-desc').value.trim()
    })).filter(i => i.nome),
    links: [...document.querySelectorAll('#srvLinksRows .srv-link-row')].map(r => ({
      label:    r.querySelector('.srv-link-label').value.trim(),
      url:      r.querySelector('.srv-link-url').value.trim(),
      download: r.querySelector('.srv-link-download').checked
    })).filter(l => l.label || l.url)
  };
}

/* ── Salva (create ou update) ── */
async function saveSrvModal() {
  const data = collectSrvForm();
  if (!data.titulo) {
    showToast('Título obrigatório', 'danger');
    document.getElementById('srvTitulo').focus();
    return;
  }

  const btn = document.getElementById('srvModalSave');
  btn.disabled = true;
  try {
    await apiFetch('backend/api_servidores.php', {
      body: { action: srvEditId ? 'update' : 'create', id: srvEditId, data }
    });
    closeSrvModal();
    showToast(srvEditId ? 'Card atualizado' : 'Card criado', 'success');
    srvDragBound = false; // permite rebind após re-render
    await fetchServidores();
  } catch (e) {
    showToast(e.message || 'Erro ao salvar', 'danger');
  } finally {
    btn.disabled = false;
  }
}

/* ==================== EDIT / DELETE ==================== */
async function editarServidor(id) {
  try {
    const res = await apiFetch('backend/api_servidores.php', {
      body: { action: 'get', id }
    });
    if (!res.success) throw new Error(res.message);
    openSrvModal(res.row);
  } catch (e) {
    showToast(e.message || 'Erro ao abrir registro', 'danger');
  }
}

async function excluirServidor(id) {
  const row  = srvRows.find(r => r.id == id);
  const nome = row?.titulo || `ID ${id}`;
  const ok   = await confirmDialog(`Excluir o card "${nome}"? Esta ação não pode ser desfeita.`, { title: 'Excluir Card' });
  if (!ok) return;

  try {
    await apiFetch('backend/api_servidores.php', { body: { action: 'delete', id } });
    showToast('Card removido', 'success');
    srvDragBound = false;
    await fetchServidores();
  } catch (e) {
    showToast(e.message || 'Erro ao excluir', 'danger');
  }
}

/* ==================== EXPOSE ==================== */
window.loadServidores = loadServidores;
