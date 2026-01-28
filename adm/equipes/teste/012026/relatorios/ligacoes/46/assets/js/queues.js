/* =========================================================
   FILAS – Evolux
========================================================= */

async function loadQueues() {
  const select = document.getElementById('queue_ids');
  if (!select) return;

  select.innerHTML = `<option value="">Carregando...</option>`;

  try {
    const res = await fetch('backend/queues_list.php');
    const json = await res.json();

    if (!json.success) {
      select.innerHTML = `<option value="">Erro ao carregar filas</option>`;
      return;
    }

    select.innerHTML = `<option value="">Todas</option>`;

    json.queues.forEach(q => {
      const opt = document.createElement('option');
      opt.value = q.id;
      opt.textContent = `${q.name} (${q.number || 's/ número'})`;
      select.appendChild(opt);
    });

  } catch {
    select.innerHTML = `<option value="">Falha na conexão</option>`;
  }
}
