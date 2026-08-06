/* app.js — Igreja Renascer */

/* ===== TEMA ===== */
/* Dark mode é o padrão via :root. Claro ativado por [data-theme="light"]. */

function _getTema() {
    return localStorage.getItem('tema') === 'claro' ? 'claro' : 'escuro';
}

function aplicarTema(tema) {
    if (tema === 'claro') {
        document.documentElement.setAttribute('data-theme', 'light');
    } else {
        document.documentElement.removeAttribute('data-theme');
    }
    localStorage.setItem('tema', tema);
    _atualizarBotao(tema);
}

function alternarTema() {
    aplicarTema(_getTema() === 'escuro' ? 'claro' : 'escuro');
}

function _atualizarBotao(tema) {
    const btn = document.getElementById('btnTema');
    if (btn) btn.textContent = tema === 'escuro' ? '☀ Modo Claro' : '🌙 Modo Escuro';
}

/* ===== SIDEBAR MOBILE ===== */
function toggleSidebar() {
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebarOverlay');
    const isAberto = sidebar.classList.toggle('aberto');
    if (overlay) overlay.classList.toggle('ativo', isAberto);
}

/* ===== INIT ===== */
document.addEventListener('DOMContentLoaded', function () {
    _atualizarBotao(_getTema());

    /* Fecha sidebar mobile ao clicar no overlay */
    const overlay = document.getElementById('sidebarOverlay');
    if (overlay) overlay.addEventListener('click', toggleSidebar);

    /* Fecha sidebar mobile ao clicar num link dentro dela */
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.querySelectorAll('nav a').forEach(function (a) {
            a.addEventListener('click', function () {
                if (window.innerWidth <= 900) toggleSidebar();
            });
        });
    }
});
