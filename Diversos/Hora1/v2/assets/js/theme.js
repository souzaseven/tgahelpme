// Alternância de tema. O tema inicial já é aplicado por um script inline no
// <head> (evita o flash de tema errado); aqui só tratamos o botão.

import { t } from './i18n.js';

const root = document.documentElement;
const COLORS = { dark: '#131210', light: '#f7f4ee' };

export const currentTheme = () => (root.dataset.theme === 'light' ? 'light' : 'dark');

function sync(btn, label) {
    const dark = currentTheme() === 'dark';
    if (label) label.textContent = dark ? t.themeToLight : t.themeToDark;
    if (btn) btn.setAttribute('aria-pressed', String(dark));

    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', COLORS[currentTheme()]);
}

export function initTheme() {
    const btn = document.querySelector('.theme-toggle');
    const label = document.querySelector('.theme-toggle__label');
    if (!btn) return;

    sync(btn, label);

    btn.addEventListener('click', () => {
        const next = currentTheme() === 'dark' ? 'light' : 'dark';
        root.dataset.theme = next;
        try {
            localStorage.setItem('theme', next);
        } catch {
            /* localStorage indisponível */
        }
        sync(btn, label);
    });
}
