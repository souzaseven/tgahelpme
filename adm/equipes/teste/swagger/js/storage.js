/**
 * storage.js
 * Responsável por guardar o token de sessão da API.
 *
 * Regra de segurança: o token vive apenas em sessionStorage (some ao fechar
 * a aba/navegador) e NUNCA em localStorage. A senha nunca é persistida em
 * nenhum lugar — nem sessionStorage, nem console, nem histórico.
 */

const TGA_STORAGE_KEY = 'tga_api_token';

const TgaStorage = {
  saveToken(token) {
    sessionStorage.setItem(TGA_STORAGE_KEY, token);
  },

  getToken() {
    return sessionStorage.getItem(TGA_STORAGE_KEY);
  },

  clearToken() {
    sessionStorage.removeItem(TGA_STORAGE_KEY);
  },

  hasToken() {
    return !!this.getToken();
  },
};
