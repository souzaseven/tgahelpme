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
      <td>
  <span class="finalizacao-tooltip"
        data-tooltip="${getFinalizacaoHelp(call.end_by_description)}">
    ${call.end_by_description || '-'}
  </span>
</td>

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
/*
function formatDuration(sec) {
  if (!sec) return '00:00';
  const m = String(Math.floor(sec / 60)).padStart(2, '0');
  const s = String(sec % 60).padStart(2, '0');
  return `${m}:${s}`;
}*/
function formatDuration(sec) {
  if (!sec || sec <= 0) return '00:00:00';

  const h = Math.floor(sec / 3600);
  const m = Math.floor((sec % 3600) / 60);
  const s = sec % 60;

  return [
    String(h).padStart(2, '0'),
    String(m).padStart(2, '0'),
    String(s).padStart(2, '0')
  ].join(':');
}


const FINALIZACAO_HELP = {
  'Abandono': 'Cliente desligou antes de ser atendido.',
  'Originador Desligou': 'Quem iniciou a ligação encerrou a chamada.',
  'Operador Desligou': 'O atendente encerrou a ligação.',
  'Destinatário Desligou': 'O número chamado encerrou a ligação.',
  'Timeout': 'A chamada excedeu o tempo limite.',
  'Transferir': 'A chamada foi transferida para outro agente ou fila.',
  'Transferência': 'A chamada foi transferida para outro agente ou fila.',
  'Vazio': 'Finalização não registrada pela plataforma.',
  'Desconhecido': 'Motivo de encerramento não identificado.',
  'Exit With Key': 'Cliente encerrou usando tecla no atendimento.',
  'NPS': 'Chamada finalizada após pesquisa NPS.',
  'CSAT': 'Finalizada após pesquisa de satisfação.',
  'CSAT e NPS': 'Pesquisa de satisfação e NPS.',
  'Ocupado': 'Número chamado estava ocupado.',
  'Operadora de Telefonia': 'Encerramento por falha da operadora.',
  'Callback': 'Chamada finalizada para retorno posterior.'
};

function getFinalizacaoHelp(text) {
  return FINALIZACAO_HELP[text] || 'Finalização sem descrição disponível.';
}
