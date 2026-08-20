/**
 * theme.js
 * Alternância entre tema escuro (padrão) e claro.
 *
 * O tema inicial já é aplicado por um script inline no <head> (antes do
 * primeiro paint, para evitar flash claro→escuro). Este arquivo cuida só da
 * interação: alternar ao clicar e persistir a escolha em localStorage.
 *
 * localStorage (não sessionStorage) é intencional aqui: preferência de tema
 * não é dado sensível, então pode — e deve — sobreviver ao fechar a aba.
 */

const TGA_THEME_KEY = 'tga_theme';

const TgaTheme = {
  get() {
    return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
  },

  set(theme) {
    if (theme === 'light') {
      document.documentElement.setAttribute('data-theme', 'light');
    } else {
      document.documentElement.removeAttribute('data-theme');
    }
    try { localStorage.setItem(TGA_THEME_KEY, theme); } catch (e) { /* ignora ambientes sem localStorage */ }
    this.updateToggleButton(theme);
  },

  toggle() {
    this.set(this.get() === 'dark' ? 'light' : 'dark');
  },

  updateToggleButton(theme) {
    const btn = document.getElementById('themeToggle');
    if (!btn) return;
    const icon = btn.querySelector('.theme-toggle__icon');
    const label = btn.querySelector('.theme-toggle__label');
    if (theme === 'dark') {
      if (icon) icon.textContent = '🌙';
      if (label) label.textContent = 'Escuro';
      btn.setAttribute('aria-pressed', 'true');
    } else {
      if (icon) icon.textContent = '☀️';
      if (label) label.textContent = 'Claro';
      btn.setAttribute('aria-pressed', 'false');
    }
  },
};

document.addEventListener('DOMContentLoaded', () => {
  TgaTheme.updateToggleButton(TgaTheme.get());
  document.getElementById('themeToggle').addEventListener('click', () => TgaTheme.toggle());
});
