/* =========================================================
   FILAS – Evolux
   Autor: Anderson de Souza
========================================================= */
/*  
async function loadQueues() {
  const select = document.getElementById('queue_ids');
  if (!select) return;

  // Placeholder temporário
  select.innerHTML = `<option value="">Carregando...</option>`;

  try {
    const res = await fetch('backend/queues_list.php');
    const json = await res.json();

    if (!json.success) {
      select.innerHTML = `<option value="">Erro ao carregar filas</option>`;
      return;
    }

    // Limpa completamente (remove "Todas")
    select.innerHTML = ``;

    let defaultSelected = false;

    json.queues.forEach(q => {
      const opt = document.createElement('option');
      opt.value = q.id;
      opt.textContent = `${q.name} (${q.number || 's/ número'})`;

      // 🔥 Define Suporte Matriz como padrão
      if (q.number === '11000') {
        opt.selected = true;
        defaultSelected = true;
      }

      select.appendChild(opt);
    });

    // Segurança extra: se por algum motivo não marcou nada
    if (!defaultSelected && select.options.length > 0) {
      select.options[0].selected = true;
    }

  } catch {
    select.innerHTML = `<option value="">Falha na conexão</option>`;
  }
}
*/
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

    /*
      IDs reais das filas permitidas
      Suporte Matriz  -> id 87
      Chat / Whats    -> id 126
    */
    const IDS_TODAS = '87,126';

    /* 🔥 OPÇÃO TODAS (somente essas duas filas) */
    select.innerHTML = `
      <option value="${IDS_TODAS}">Todas</option>
    `;

    json.queues.forEach(q => {
      const opt = document.createElement('option');

      /* 🔥 USAR ID, NÃO NUMBER */
      opt.value = q.id;
      opt.textContent = `${q.name} (${q.number})`;

      select.appendChild(opt);
    });

    /* seleciona "Todas" por padrão */
    select.value = IDS_TODAS;

  } catch (e) {
    select.innerHTML = `<option value="">Falha na conexão</option>`;
  }
}
