/* =========================================================
   FILTROS – Painel Evolux
   Autor: Anderson de Souza
   Status: FINAL / PRODUÇÃO
========================================================= */

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
   HORAS PADRÃO
========================== */
function getDefaultTimes() {
  return {
    start_time: '00:00',
    end_time: '23:59'
  };
}

/* ==========================
   COLETA DE FILTROS
========================== */
function getFilters() {
  const defaults = getDefaultDates();
  const times = getDefaultTimes();

  const perPageEl = document.getElementById('per_page');
  const perPage = perPageEl ? perPageEl.value : 50;

  return {
    start_date: document.getElementById('start_date')?.value || defaults.start_date,
    end_date: document.getElementById('end_date')?.value || defaults.end_date,
    start_time: document.getElementById('start_time')?.value || times.start_time,
    end_time: document.getElementById('end_time')?.value || times.end_time,
    agent_id: document.getElementById('agent_id')?.value || '',
    queue_ids: document.getElementById('queue_ids')?.value || '',
    phone_number: document.getElementById('phone_number')?.value || '',
    limit: perPage === 'all' ? 50 : Number(perPage)
  };
}

/* ==========================
   DEBOUNCE
   Evita múltiplas requisições
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
========================== */
const autoLoadCalls = debounce(() => {
  if (typeof loadCalls === 'function') {
    loadCalls(1);
  }
}, 600);

/* ==========================
   EVENTOS DOS FILTROS
========================== */
function bindFilterEvents() {

const autoFields = [
  'start_date', 'end_date',
  'start_time', 'end_time',
  'agent_id', 'queue_ids', 'per_page'
];


  autoFields.forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.addEventListener('change', autoLoadCalls);
    }
  });

  /* Campo de texto (telefone) */
  const phone = document.getElementById('phone_number');
  if (phone) {
    phone.addEventListener('input', autoLoadCalls);
  }

  /* Botão Buscar (opcional / manual) */
  const btnBuscar = document.getElementById('btnBuscar');
  if (btnBuscar) {
    btnBuscar.addEventListener('click', () => {
      loadCalls(1);
    });
  }
}

/* ==========================
   INIT VISUAL
   Datas + Horas padrão
========================== */
document.addEventListener('DOMContentLoaded', () => {
  const defaults = getDefaultDates();
  const times = getDefaultTimes();

  const startDate = document.getElementById('start_date');
  const endDate   = document.getElementById('end_date');
  const startTime = document.getElementById('start_time');
  const endTime   = document.getElementById('end_time');

  if (startDate && !startDate.value) startDate.value = defaults.start_date;
  if (endDate && !endDate.value)     endDate.value   = defaults.end_date;

  if (startTime && !startTime.value) startTime.value = times.start_time;
  if (endTime && !endTime.value)     endTime.value   = times.end_time;
});
