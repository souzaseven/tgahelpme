/**
 * TGA Carreiras — Toast
 * FASE 2 — Design System
 *
 * Notificações temporárias no canto da tela. Uso:
 *   mostrarToast('Candidatura enviada com sucesso!', 'success');
 *   mostrarToast('Não foi possível salvar.', 'danger');
 *
 * Tipos aceitos: 'info' (padrão) | 'success' | 'warning' | 'danger'
 */

(function () {
    'use strict';

    const DURACAO_PADRAO_MS = 4500;

    const icones = {
        info: 'ℹ',
        success: '✓',
        warning: '⚠',
        danger: '✕',
    };

    function obterContainer() {
        let container = document.getElementById('tga-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'tga-toast-container';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'true');
            container.style.position = 'fixed';
            container.style.top = 'var(--space-4)';
            container.style.right = 'var(--space-4)';
            container.style.zIndex = 'var(--z-toast)';
            container.style.display = 'flex';
            container.style.flexDirection = 'column';
            container.style.gap = 'var(--space-3)';
            container.style.maxWidth = 'min(360px, calc(100vw - 2rem))';
            document.body.appendChild(container);
        }
        return container;
    }

    function mostrarToast(mensagem, tipo, duracaoMs) {
        tipo = tipo && icones[tipo] ? tipo : 'info';
        duracaoMs = duracaoMs || DURACAO_PADRAO_MS;

        const container = obterContainer();

        const toast = document.createElement('div');
        toast.className = 'alert alert--' + tipo;
        toast.style.boxShadow = 'var(--shadow-md)';
        toast.setAttribute('role', tipo === 'danger' ? 'alert' : 'status');

        toast.innerHTML =
            '<span class="alert__icon" aria-hidden="true">' + icones[tipo] + '</span>' +
            '<span class="alert__body"></span>';
        toast.querySelector('.alert__body').textContent = mensagem;

        container.appendChild(toast);

        const remover = () => {
            toast.style.transition = 'opacity 150ms ease';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 150);
        };

        const timeoutId = setTimeout(remover, duracaoMs);

        toast.addEventListener('click', () => {
            clearTimeout(timeoutId);
            remover();
        });
    }

    window.mostrarToast = mostrarToast;
})();
