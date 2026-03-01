const API = 'api/contas.php';

const el = (id) => document.getElementById(id);
const fmtBRL = (n) => (Number(n || 0)).toLocaleString('pt-BR', { style:'currency', currency:'BRL' });

const state = {
  mes: new Date().toISOString().slice(0,7), // YYYY-MM
  q: '',
  tipo: '',
  pago: '',
  categoria: '',
  ordem: 'vencimento_asc',
  data: []
};

function openModal(edit=false){
  el('modal').setAttribute('aria-hidden','false');
  el('modalTitle').textContent = edit ? 'Editar Conta' : 'Nova Conta';
}
function closeModal(){
  el('modal').setAttribute('aria-hidden','true');
  el('form').reset();
  el('mId').value = '';
  el('mPago').checked = false;
  el('mDataPag').value = '';
}

function getFiltersFromUI(){
  state.mes = el('fMes').value || state.mes;
  state.q = el('fQ').value.trim();
  state.tipo = el('fTipo').value;
  state.pago = el('fPago').value;
  state.categoria = el('fCategoria').value;
  state.ordem = el('fOrdem').value;
}

async function apiList(){
  const qs = new URLSearchParams({
    action: 'list',
    mes: state.mes,
    q: state.q,
    tipo: state.tipo,
    pago: state.pago,
    categoria: state.categoria,
    ordem: state.ordem,
  });

  const r = await fetch(`${API}?${qs.toString()}`);
  const j = await r.json();
  if(!j.success) throw new Error(j.message || 'Falha ao listar');
  state.data = j.data || [];

  // KPIs
  el('kpiReceitas').textContent = fmtBRL(j.kpi?.receitas);
  el('kpiDespesas').textContent = fmtBRL(j.kpi?.despesas);
  el('kpiSaldo').textContent = fmtBRL(j.kpi?.saldo);
  el('kpiPendente').textContent = fmtBRL(j.kpi?.total_pendente);

  // Categorias dropdown
  const catSel = el('fCategoria');
  const current = catSel.value;
  catSel.innerHTML = `<option value="">Todas</option>`;
  (j.categorias || []).forEach(c => {
    const opt = document.createElement('option');
    opt.value = c.categoria;
    opt.textContent = `${c.categoria} (${c.qtd})`;
    catSel.appendChild(opt);
  });
  catSel.value = current;

  renderTable();
}

function renderTable(){
  const tbody = el('tbody');
  tbody.innerHTML = '';

  el('empty').style.display = state.data.length ? 'none' : 'block';

  state.data.forEach(row => {
    const tr = document.createElement('tr');

    const badge = row.pago == 1
      ? `<span class="badge"><span class="dot ok"></span>Pago</span>`
      : `<span class="badge"><span class="dot"></span>Pendente</span>`;

    tr.innerHTML = `
      <td>${badge}</td>
      <td><strong>${escapeHtml(row.titulo)}</strong>${row.observacao ? `<div style="color:var(--muted);font-size:12px;margin-top:4px">${escapeHtml(row.observacao)}</div>` : ''}</td>
      <td>${escapeHtml(row.categoria || 'Geral')}</td>
      <td>${row.tipo === 'receita' ? 'Receita' : 'Despesa'}</td>
      <td>${row.vencimento}</td>
      <td>${fmtBRL(row.valor)}</td>
      <td class="right">
        <div class="actions">
          <button class="btn-mini" data-act="toggle" data-id="${row.id}" data-pago="${row.pago}">${row.pago==1?'Marcar pendente':'Marcar pago'}</button>
          <button class="btn-mini" data-act="edit" data-id="${row.id}">Editar</button>
          <button class="btn-mini" data-act="del" data-id="${row.id}">Excluir</button>
        </div>
      </td>
    `;

    tbody.appendChild(tr);
  });
}

function escapeHtml(str){
  return String(str ?? '')
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'","&#039;");
}

function fillModal(row){
  el('mId').value = row.id;
  el('mTitulo').value = row.titulo || '';
  el('mCategoria').value = row.categoria || '';
  el('mTipo').value = row.tipo || 'despesa';
  el('mValor').value = row.valor || '';
  el('mVenc').value = row.vencimento || '';
  el('mPago').checked = row.pago == 1;
  el('mForma').value = row.forma_pagamento || '';
  el('mDataPag').value = row.data_pagamento || '';
  el('mObs').value = row.observacao || '';
}

async function apiCreate(payload){
  const r = await fetch(`${API}?action=create`, {
    method: 'POST',
    headers: { 'Content-Type':'application/json' },
    body: JSON.stringify(payload)
  });
  const j = await r.json();
  if(!j.success) throw new Error(j.message || 'Falha ao criar');
}

async function apiUpdate(id, payload){
  const r = await fetch(`${API}?action=update&id=${encodeURIComponent(id)}`, {
    method: 'PUT',
    headers: { 'Content-Type':'application/json' },
    body: JSON.stringify(payload)
  });
  const j = await r.json();
  if(!j.success) throw new Error(j.message || 'Falha ao atualizar');
}

async function apiDelete(id){
  const r = await fetch(`${API}?action=delete&id=${encodeURIComponent(id)}`, { method: 'DELETE' });
  const j = await r.json();
  if(!j.success) throw new Error(j.message || 'Falha ao excluir');
}

async function apiTogglePago(id, pago){
  const r = await fetch(`${API}?action=toggle_pago&id=${encodeURIComponent(id)}`, {
    method: 'PATCH',
    headers: { 'Content-Type':'application/json' },
    body: JSON.stringify({ pago })
  });
  const j = await r.json();
  if(!j.success) throw new Error(j.message || 'Falha ao atualizar status');
}

function payloadFromModal(){
  const pago = el('mPago').checked ? 1 : 0;
  return {
    titulo: el('mTitulo').value.trim(),
    categoria: el('mCategoria').value.trim() || 'Geral',
    tipo: el('mTipo').value,
    valor: Number(el('mValor').value || 0),
    vencimento: el('mVenc').value,
    pago,
    forma_pagamento: el('mForma').value.trim(),
    data_pagamento: el('mDataPag').value || null,
    observacao: el('mObs').value.trim(),
  };
}

// Events
document.addEventListener('click', async (e) => {
  const t = e.target;

  if (t.id === 'btnNova') {
    closeModal();
    // pré-preencher vencimento para mês selecionado
    const day = String(new Date().getDate()).padStart(2,'0');
    el('mVenc').value = `${state.mes}-${day}`;
    openModal(false);
  }

  if (t.id === 'btnFechar' || t.id === 'btnCancelar') closeModal();

  if (t.id === 'btnFiltrar') {
    getFiltersFromUI();
    await safeRefresh();
  }

  if (t.id === 'btnLimpar') {
    el('fQ').value = '';
    el('fTipo').value = '';
    el('fPago').value = '';
    el('fCategoria').value = '';
    el('fOrdem').value = 'vencimento_asc';
    getFiltersFromUI();
    await safeRefresh();
  }

  // tabela ações
  if (t.dataset?.act) {
    const id = t.dataset.id;
    const act = t.dataset.act;

    if (act === 'edit') {
      const row = state.data.find(x => String(x.id) === String(id));
      if (!row) return;
      fillModal(row);
      openModal(true);
    }

    if (act === 'del') {
      if (!confirm('Excluir esta conta?')) return;
      await safe(async () => {
        await apiDelete(id);
        await safeRefresh();
      });
    }

    if (act === 'toggle') {
      const pagoAtual = Number(t.dataset.pago || 0);
      const novo = pagoAtual === 1 ? 0 : 1;
      await safe(async () => {
        await apiTogglePago(id, novo);
        await safeRefresh();
      });
    }
  }
});

el('form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const id = el('mId').value;
  const payload = payloadFromModal();

  await safe(async () => {
    if (id) await apiUpdate(id, payload);
    else await apiCreate(payload);
    closeModal();
    await safeRefresh();
  });
});

function toast(msg){
  // simples, sem lib
  const d = document.createElement('div');
  d.textContent = msg;
  d.style.position='fixed';
  d.style.right='16px';
  d.style.bottom='16px';
  d.style.padding='12px 14px';
  d.style.border='1px solid rgba(255,255,255,.12)';
  d.style.background='rgba(15,23,42,.9)';
  d.style.color='white';
  d.style.borderRadius='12px';
  d.style.boxShadow='0 16px 40px rgba(0,0,0,.35)';
  d.style.zIndex='9999';
  document.body.appendChild(d);
  setTimeout(()=>{ d.style.opacity='0'; d.style.transition='opacity .25s'; }, 1800);
  setTimeout(()=> d.remove(), 2200);
}

async function safe(fn){
  try { await fn(); toast('OK ✅'); }
  catch(err){ console.error(err); alert(err.message || 'Erro'); }
}
async function safeRefresh(){
  try { await apiList(); }
  catch(err){ console.error(err); alert(err.message || 'Erro ao carregar'); }
}

// init
(function init(){
  el('fMes').value = state.mes;
  safeRefresh();
})();