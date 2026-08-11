/**
 * TGA Carreiras — UI genérica
 * FASE 2 — Design System
 *
 * Comportamentos pequenos e reaproveitáveis que não merecem um arquivo
 * próprio ainda: dropdown e toggle da sidebar em telas estreitas.
 *
 * Dropdown (HTML):
 *   <div class="dropdown">
 *     <button data-dropdown-toggle aria-haspopup="true" aria-expanded="false">Ações</button>
 *     <div class="dropdown__menu" hidden>...</div>
 *   </div>
 */

(function () {
    'use strict';

    function fecharTodosDropdowns(exceto) {
        document.querySelectorAll('.dropdown__menu').forEach((menu) => {
            if (menu !== exceto) {
                menu.hidden = true;
                const gatilho = menu.parentElement.querySelector('[data-dropdown-toggle]');
                if (gatilho) gatilho.setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.addEventListener('click', function (evento) {
        const gatilho = evento.target.closest('[data-dropdown-toggle]');
        if (gatilho) {
            const menu = gatilho.parentElement.querySelector('.dropdown__menu');
            if (!menu) return;

            const estaAberto = !menu.hidden;
            fecharTodosDropdowns();
            menu.hidden = estaAberto;
            gatilho.setAttribute('aria-expanded', String(!estaAberto));
            return;
        }

        // Clique fora de qualquer dropdown fecha todos.
        if (!evento.target.closest('.dropdown')) {
            fecharTodosDropdowns();
        }

        // Toggle da sidebar em telas estreitas (dashboard shell).
        const gatilhoSidebar = evento.target.closest('[data-sidebar-toggle]');
        if (gatilhoSidebar) {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                sidebar.classList.toggle('sidebar--aberta');
            }
        }
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape') {
            fecharTodosDropdowns();
        }
    });
})();
