/* =========================================================
   APP – Painel de Ligações Evolux
   Autor: Anderson de Souza
   Status: FINAL / PRODUÇÃO
========================================================= */

let currentPage = 1;
let lastPage = 1;

/* ==========================
   ESTADO DA ORDENAÇÃO
========================== */
let currentCalls = [];
let sortState = {
  column: null,
  direction: 'asc'
};

/* ==========================
   BUSCA PRINCIPAL
========================== */
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

  /* guarda dados atuais */
  currentCalls = calls;

  currentPage = page;

  if (pagination && pagination.total && pagination.limit) {
    lastPage = Math.ceil(pagination.total / pagination.limit);
  } else {
    lastPage = 1;
  }

  renderTable(currentCalls);
  updateKPIs(currentCalls);
  updatePagination(currentPage, lastPage);
}

/* ==========================
   ORDENAÇÃO
========================== */
function sortCalls(column) {

  if (sortState.column === column) {
    sortState.direction = sortState.direction === 'asc' ? 'desc' : 'asc';
  } else {
    sortState.column = column;
    sortState.direction = 'asc';
  }

  const dir = sortState.direction === 'asc' ? 1 : -1;

  currentCalls = [...currentCalls].sort((a, b) => {
    switch (column) {
      case 'date':
        return (new Date(a.date_join) - new Date(b.date_join)) * dir;

      case 'agent':
        return (a.agent_name || '').localeCompare(b.agent_name || '') * dir;

      case 'queue':
        return (a.queue_name || '').localeCompare(b.queue_name || '') * dir;

      case 'number':
        return (a.receiver_number || '').localeCompare(b.receiver_number || '') * dir;

      case 'duration':
        return ((a.call_duration || 0) - (b.call_duration || 0)) * dir;

      case 'end':
        return (a.end_by_description || '').localeCompare(b.end_by_description || '') * dir;

      default:
        return 0;
    }
  });

  renderTable(currentCalls);
}

/* ==========================
   PAGINAÇÃO
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

  /* ativa ordenação nos headers */
  document.querySelectorAll('th[data-sort]').forEach(th => {
    th.style.cursor = 'pointer';
    th.addEventListener('click', () => {
      sortCalls(th.dataset.sort);
    });
  });
});
