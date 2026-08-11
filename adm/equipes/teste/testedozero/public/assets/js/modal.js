/**
 * TGA Carreiras — Modal
 * FASE 2 — Design System
 *
 * Uso (HTML):
 *   <button data-modal-open="meu-modal">Abrir</button>
 *   <div class="modal-overlay" id="meu-modal" hidden>
 *     <div class="modal" role="dialog" aria-modal="true" aria-labelledby="meu-modal-titulo">
 *       <div class="modal__header">
 *         <h2 class="modal__title" id="meu-modal-titulo">Título</h2>
 *         <button class="modal__close" data-modal-close aria-label="Fechar">&times;</button>
 *       </div>
 *       <div class="modal__body">...</div>
 *     </div>
 *   </div>
 *
 * Uso (JS): abrirModal('meu-modal') / fecharModal('meu-modal')
 */

(function () {
    'use strict';

    let elementoComFocoAnterior = null;

    function abrirModal(id) {
        const overlay = document.getElementById(id);
        if (!overlay) return;

        elementoComFocoAnterior = document.activeElement;

        overlay.hidden = false;
        document.body.style.overflow = 'hidden';

        const primeiroFocavel = overlay.querySelector(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        if (primeiroFocavel) {
            primeiroFocavel.focus();
        }
    }

    function fecharModal(id) {
        const overlay = document.getElementById(id);
        if (!overlay) return;

        overlay.hidden = true;
        document.body.style.overflow = '';

        if (elementoComFocoAnterior instanceof HTMLElement) {
            elementoComFocoAnterior.focus();
        }
    }

    function fecharModalAberto() {
        const overlay = document.querySelector('.modal-overlay:not([hidden])');
        if (overlay) {
            fecharModal(overlay.id);
        }
    }

    document.addEventListener('click', function (evento) {
        const gatilhoAbrir = evento.target.closest('[data-modal-open]');
        if (gatilhoAbrir) {
            abrirModal(gatilhoAbrir.getAttribute('data-modal-open'));
            return;
        }

        const gatilhoFechar = evento.target.closest('[data-modal-close]');
        if (gatilhoFechar) {
            fecharModalAberto();
            return;
        }

        // Clique no overlay (fora da caixa do modal) fecha.
        if (evento.target.classList.contains('modal-overlay')) {
            fecharModal(evento.target.id);
        }
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape') {
            fecharModalAberto();
        }
    });

    // Expõe globalmente para uso a partir de outros scripts (ex.: form.js
    // fechando o modal após submissão bem-sucedida, em fases futuras).
    window.abrirModal = abrirModal;
    window.fecharModal = fecharModal;
})();
