/*
 * favorites.js — lista propria de conversas favoritas (nao mexe no WhatsApp).
 * Puro: `favorites` e um array de { key, name, addedAt }.
 */

const MAX_FAVORITES = 200;

/** Esta conversa esta nos favoritos? */
export function isFavorite(favorites, key) {
  if (!key || !Array.isArray(favorites)) return false;
  return favorites.some((f) => f.key === key);
}

/**
 * Adiciona ou remove a conversa dos favoritos.
 * @returns {{ favorites: object[], added: boolean }}
 */
export function toggleFavorite(favorites, key, name) {
  const list = Array.isArray(favorites) ? favorites.slice() : [];
  if (!key) return { favorites: list, added: false };

  const idx = list.findIndex((f) => f.key === key);
  if (idx >= 0) {
    list.splice(idx, 1);
    return { favorites: list, added: false };
  }
  list.unshift({ key, name: String(name || "").trim(), addedAt: Date.now() });
  if (list.length > MAX_FAVORITES) list.length = MAX_FAVORITES;
  return { favorites: list, added: true };
}

/** Remove um favorito pela chave. */
export function removeFavorite(favorites, key) {
  return (Array.isArray(favorites) ? favorites : []).filter((f) => f.key !== key);
}

/** Ordena para exibicao: por nome (pt-BR); sem nome vai para o fim. */
export function sortFavorites(favorites) {
  return (Array.isArray(favorites) ? favorites.slice() : []).sort((a, b) => {
    const an = (a.name || "").trim();
    const bn = (b.name || "").trim();
    if (!an && !bn) return 0;
    if (!an) return 1;
    if (!bn) return -1;
    return an.localeCompare(bn, "pt-BR");
  });
}

export { MAX_FAVORITES };
