'use strict';

// ─── Tema ─────────────────────────────────────────────────────────────────────
// Aplica o tema salvo antes da primeira pintura, evitando flash do tema errado.
// Este script deve ser incluído logo após a tag <body> abrir.
if ((localStorage.getItem('tema') ?? 'dark') === 'dark') {
    document.body.classList.add('dark');
}

function aplicarTema(tema) {
    document.body.classList.toggle('dark', tema === 'dark');
    const btn = document.getElementById('btn-tema');
    if (btn) btn.textContent = tema === 'dark' ? '☀️' : '🌙';
}

function alternarTema() {
    const atual = document.body.classList.contains('dark') ? 'dark' : 'light';
    const novo  = atual === 'dark' ? 'light' : 'dark';
    localStorage.setItem('tema', novo);
    aplicarTema(novo);
}

document.addEventListener('DOMContentLoaded', () => {
    aplicarTema(localStorage.getItem('tema') ?? 'dark');
    const btnTema = document.getElementById('btn-tema');
    if (btnTema) btnTema.addEventListener('click', alternarTema);
});

// ─── Toast ────────────────────────────────────────────────────────────────────
let toastTimer;
function mostrarToast(msg, tipo = 'info', duracaoMs = 3500) {
    const el = document.getElementById('toast');
    if (!el) return;
    el.textContent = msg;
    el.className   = `toast toast-${tipo}`;
    el.hidden      = false;
    requestAnimationFrame(() => el.classList.add('show'));
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        el.classList.remove('show');
        setTimeout(() => { el.hidden = true; }, 300);
    }, duracaoMs);
}
