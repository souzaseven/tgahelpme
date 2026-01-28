/* =========================================================
   AGENTES – Evolux
========================================================= */

let allAgents = [];

async function loadAgents() {
  const select = document.getElementById('agent_id');
  if (!select) return;

  select.innerHTML = `<option value="">Carregando...</option>`;

  try {
    const res = await fetch('backend/agents_list.php');
    const json = await res.json();

    if (!json.success) {
      select.innerHTML = `<option value="">Erro ao carregar agentes</option>`;
      return;
    }

    allAgents = json.agents;
    renderAgentsByQueue();

  } catch {
    select.innerHTML = `<option value="">Falha na conexão</option>`;
  }
}

function renderAgentsByQueue() {
  const select = document.getElementById('agent_id');
  const queueId = document.getElementById('queue_ids').value;

  select.innerHTML = `<option value="">Todos</option>`;

  let filtered = allAgents;

  if (queueId) {
    filtered = allAgents.filter(a =>
      Array.isArray(a.queues) && a.queues.includes(Number(queueId))
    );
  }

  filtered.forEach(a => {
    const opt = document.createElement('option');
    opt.value = a.id;
    opt.textContent = a.name;
    select.appendChild(opt);
  });
}

document.getElementById('queue_ids')
  ?.addEventListener('change', renderAgentsByQueue);
