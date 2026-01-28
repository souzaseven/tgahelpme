/* =========================================================
   UI – Tabela, KPIs e Helpers
========================================================= */

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
        ${call.download_audio?.startsWith('http')
          ? `<a class="btn-download" href="${call.download_audio}" target="_blank">🎧</a>`
          : '-'}
      </td>
    </tr>
  `).join('');
}

function updateKPIs(calls) {
  document.getElementById('kpiTotal').textContent = calls.length;

  const atendidas = calls.filter(c => c.call_duration > 0).length;
  document.getElementById('kpiAtendidas').textContent = atendidas;
  document.getElementById('kpiNaoAtendidas').textContent =
    calls.length - atendidas;
}

function updatePagination(currentPage, lastPage) {
  document.getElementById('paginationInfo').textContent =
    `Página ${currentPage} de ${lastPage}`;

  document.getElementById('btnPrev').disabled = currentPage <= 1;
  document.getElementById('btnNext').disabled = currentPage >= lastPage;
}

/* Helpers */
function formatDate(iso) {
  return iso ? new Date(iso).toLocaleString('pt-BR') : '-';
}

function formatDuration(sec) {
  if (!sec) return '00:00';
  const m = String(Math.floor(sec / 60)).padStart(2, '0');
  const s = String(sec % 60).padStart(2, '0');
  return `${m}:${s}`;
}
