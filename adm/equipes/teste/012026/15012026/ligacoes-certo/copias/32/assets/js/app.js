/* =========================================================
   Painel de Ligações – Evolux
   JS Principal (FINAL)
   Autor: Anderson de Souza
========================================================= */

let currentPage = 1;
let lastPage    = 1;

/* ==========================
   EVENTOS
========================== */
document.getElementById('btnBuscar').addEventListener('click', () => {
  loadCalls(1);
});

document.getElementById('btnPrev').addEventListener('click', () => {
  if (currentPage > 1) loadCalls(currentPage - 1);
});

document.getElementById('btnNext').addEventListener('click', () => {
  if (currentPage < lastPage) loadCalls(currentPage + 1);
});

/* ==========================
   BUSCA PRINCIPAL
========================== */
async function loadCalls(page = 1) {
  const tbody = document.getElementById('callsBody');
  tbody.innerHTML = `<tr><td colspan="7" class="empty">Carregando...</td></tr>`;

  const params = new URLSearchParams({
    start_date: document.getElementById('start_date').value,
    end_date:   document.getElementById('end_date').value,
    agent_id:   document.getElementById('agent_id').value,
    queue_ids:  document.getElementById('queue_ids').value,
    phone_number: document.getElementById('phone_number').value,
    limit: document.getElementById('per_page').value === 'all'
      ? 50
      : document.getElementById('per_page').value,
    page
  });

  const res = await fetch(`backend/calls_history.php?${params}`);
  const json = await res.json();

  if (!json.success) {
    tbody.innerHTML = `<tr><td colspan="7" class="empty">${json.error}</td></tr>`;
    return;
  }

  const calls = json.data.data.calls;
  const pagination = json.data.pagination;

  currentPage = page;
  lastPage = Math.ceil(pagination.total / pagination.limit);

  renderTable(calls);
  updateKPIs(calls);
  updatePagination(pagination);
}

/* ==========================
   TABELA
========================== */
function renderTable(calls) {
  const tbody = document.getElementById('callsBody');

  if (!calls.length) {
    tbody.innerHTML = `<tr><td colspan="7" class="empty">Nenhum registro encontrado</td></tr>`;
    return;
  }

  tbody.innerHTML = calls.map(call => `
    <tr>
      <td>${formatDate(call.date_join)}</td>
      <td>${call.agent_name ?? '-'}</td>
      <td>${call.queue_name ?? '-'}</td>
      <td>${call.receiver_number ?? '-'}</td>
      <td>${formatDuration(call.call_duration)}</td>
      <td>${call.end_by_description}</td>
      <td>
        ${call.download_audio && call.download_audio.startsWith('http')
          ? `<a class="btn-download" href="${call.download_audio}" target="_blank">🎧</a>`
          : '-'}
      </td>
    </tr>
  `).join('');
}

/* ==========================
   KPIs
========================== */
function updateKPIs(calls) {
  document.getElementById('kpiTotal').textContent = calls.length;

  const atendidas = calls.filter(c => c.call_duration > 0).length;
  document.getElementById('kpiAtendidas').textContent = atendidas;
  document.getElementById('kpiNaoAtendidas').textContent = calls.length - atendidas;
}

/* ==========================
   PAGINAÇÃO
========================== */
function updatePagination(p) {
  document.getElementById('paginationInfo').textContent =
    `Página ${currentPage} de ${lastPage}`;
}

/* ==========================
   HELPERS
========================== */
function formatDate(iso) {
  if (!iso) return '-';
  return new Date(iso).toLocaleString('pt-BR');
}

function formatDuration(sec) {
  if (!sec || sec === 0) return '00:00';
  const m = Math.floor(sec / 60).toString().padStart(2, '0');
  const s = (sec % 60).toString().padStart(2, '0');
  return `${m}:${s}`;
}
