/* =========================================================
   FILTROS – Painel Evolux
   Autor: Anderson de Souza
   Status: FINAL / PRODUÇÃO
========================================================= */

/* ==========================
   CONTROLE DE INTERAÇÃO
========================== */
let userHasInteracted = false;

/* ==========================
   DATAS PADRÃO
   (Ontem → Hoje)
========================== */
function getDefaultDates() {
  const today = new Date();
  const yesterday = new Date();
  yesterday.setDate(today.getDate() - 1);

  const fmt = d => d.toISOString().split('T')[0];

  return {
    start_date: fmt(yesterday),
    end_date: fmt(today)
  };
}

/* ==========================
   COLETA DE FILTROS
========================== */
function getFilters() {
  const defaults = getDefaultDates();

  return {
    start_date: document.getElementById('start_date')?.value || defaults.start_date,
    end_date: document.getElementById('end_date')?.value || defaults.end_date,
    agent_id: document.getElementById('agent_id')?.value || '',
    queue_ids: document.getElementById('queue_ids')?.value || '',
    phone_number: document.getElementById('phone_number')?.value || ''
  };
}

/* ==========================
   DEBOUNCE
========================== */
function debounce(fn, delay = 600) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(null, args), delay);
  };
}

/* ==========================
   DISPARO AUTOMÁTICO
   (Somente após interação)
========================== */
const autoLoadCalls = debounce(() => {
  if (!userHasInteracted) return;
  if (typeof loadCalls === 'function') loadCalls(1);
}, 600);

/* ==========================
   EVENTOS DOS FILTROS
========================== */
function bindFilterEvents() {

  const autoFields = [
    'start_date',
    'end_date',
    'agent_id',
    'queue_ids'
  ];

  autoFields.forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.addEventListener('change', () => {
        userHasInteracted = true;
        autoLoadCalls();
      });
    }
  });

  const phone = document.getElementById('phone_number');
  if (phone) {
    phone.addEventListener('input', () => {
      userHasInteracted = true;
      autoLoadCalls();
    });
  }

  const btnBuscar = document.getElementById('btnBuscar');
  if (btnBuscar) {
    btnBuscar.addEventListener('click', () => {
      userHasInteracted = true;
      loadCalls(1);
    });
  }
}

/* ==========================
   INIT VISUAL
   (Somente aparência)
========================== */
document.addEventListener('DOMContentLoaded', () => {
  const defaults = getDefaultDates();

  const start = document.getElementById('start_date');
  const end   = document.getElementById('end_date');

  if (start && !start.value) start.value = defaults.start_date;
  if (end && !end.value)     end.value   = defaults.end_date;
});
