/* =========================================================
   APP – Painel de Ligações Evolux
   Autor: Anderson de Souza
   Status: FINAL / PRODUÇÃO
========================================================= */

let currentPage = 1;
let lastPage = 1;

async function loadCalls(page = 1) {
  const tbody = document.getElementById('callsBody');
  tbody.innerHTML = `<tr><td colspan="7" class="empty">Carregando...</td></tr>`;

  const filters = getFilters();

  /* ==========================
     PARAMS (SEM VAZIOS)
  ========================== */
  const params = new URLSearchParams();

  params.append('start_date', filters.start_date);
  params.append('end_date', filters.end_date);
  params.append('page', page);

  if (filters.agent_id) {
    params.append('agent_id', filters.agent_id);
  }

  if (filters.queue_ids) {
    params.append('queue_ids', filters.queue_ids);
  }

  if (filters.phone_number) {
    params.append('phone_number', filters.phone_number);
  }

  const res = await fetch(`backend/calls_history.php?${params}`);
  const json = await res.json();

  if (!json.success) {
    tbody.innerHTML = `<tr><td colspan="7" class="empty">${json.error}</td></tr>`;
    return;
  }

  const calls = json.data?.data?.calls || [];
  const pagination = json.data?.pagination || null;

  currentPage = page;

  if (pagination && pagination.total && pagination.limit) {
    lastPage = Math.ceil(pagination.total / pagination.limit);
  } else {
    lastPage = 1;
  }

  renderTable(calls);
  updateKPIs(calls);
  updatePagination(currentPage, lastPage);
}

/* ==========================
   CONTROLES DE PAGINAÇÃO
========================== */
document.getElementById('btnPrev').onclick = () => {
  if (currentPage > 1) loadCalls(currentPage - 1);
};

document.getElementById('btnNext').onclick = () => {
  if (currentPage < lastPage) loadCalls(currentPage + 1);
};

/* ==========================
   INIT (SEM BUSCA AUTOMÁTICA)
========================== */
document.addEventListener('DOMContentLoaded', () => {
  bindFilterEvents();
  loadQueues();
  loadAgents();

  // ❌ NÃO carrega dados aqui
  // ❌ NÃO simula clique
});
