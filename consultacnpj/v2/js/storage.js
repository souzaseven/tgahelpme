/**
 * storage.js
 * Persistência local (localStorage): histórico de consultas recentes,
 * favoritos e preferência de tema. Não salva dados sensíveis além do
 * mínimo necessário para identificar a empresa (CNPJ, razão social,
 * nome fantasia).
 */

const Storage = (() => {

  const KEYS = {
    RECENTS: "cnpj_consulta_recentes",
    FAVORITES: "cnpj_consulta_favoritos",
    THEME: "cnpj_consulta_tema",
    CACHE: "cnpj_consulta_cache",
  };

  const MAX_RECENTS = 10;
  const CACHE_TTL_MS = 10 * 60 * 1000; // 10 minutos
  const CACHE_MAX_ENTRIES = 30;

  function safeGet(key) {
    try {
      const raw = localStorage.getItem(key);
      return raw ? JSON.parse(raw) : null;
    } catch (err) {
      console.warn(`Não foi possível ler "${key}" do localStorage.`, err);
      return null;
    }
  }

  function safeSet(key, value) {
    try {
      localStorage.setItem(key, JSON.stringify(value));
      return true;
    } catch (err) {
      console.warn(`Não foi possível salvar "${key}" no localStorage.`, err);
      return false;
    }
  }

  // ---------- RECENTES ----------

  function getRecents() {
    return safeGet(KEYS.RECENTS) || [];
  }

  function addRecent(entry) {
    // entry: { cnpj, razaoSocial, nomeFantasia, consultedAt }
    let list = getRecents().filter((item) => item.cnpj !== entry.cnpj);
    list.unshift(entry);
    list = list.slice(0, MAX_RECENTS);
    safeSet(KEYS.RECENTS, list);
    return list;
  }

  function removeRecent(cnpj) {
    const list = getRecents().filter((item) => item.cnpj !== cnpj);
    safeSet(KEYS.RECENTS, list);
    return list;
  }

  function clearRecents() {
    safeSet(KEYS.RECENTS, []);
  }

  // ---------- FAVORITOS ----------

  function getFavorites() {
    return safeGet(KEYS.FAVORITES) || [];
  }

  function isFavorite(cnpj) {
    return getFavorites().some((item) => item.cnpj === cnpj);
  }

  function toggleFavorite(entry) {
    // entry: { cnpj, razaoSocial, nomeFantasia }
    const list = getFavorites();
    const idx = list.findIndex((item) => item.cnpj === entry.cnpj);
    if (idx >= 0) {
      list.splice(idx, 1);
      safeSet(KEYS.FAVORITES, list);
      return { list, isFavorite: false };
    }
    list.unshift(entry);
    safeSet(KEYS.FAVORITES, list);
    return { list, isFavorite: true };
  }

  function removeFavorite(cnpj) {
    const list = getFavorites().filter((item) => item.cnpj !== cnpj);
    safeSet(KEYS.FAVORITES, list);
    return list;
  }

  // ---------- CACHE LOCAL DE CONSULTAS ----------
  // Evita uma nova ida à rede para um CNPJ consultado há pouco tempo.
  // Guarda apenas a última resposta por CNPJ, com expiração curta.

  function getCached(cnpj) {
    const cache = safeGet(KEYS.CACHE) || {};
    const entry = cache[cnpj];
    if (!entry) return null;
    if (Date.now() - entry.cachedAt > CACHE_TTL_MS) return null;
    return entry; // { data, cachedAt }
  }

  function setCached(cnpj, data) {
    const cache = safeGet(KEYS.CACHE) || {};
    cache[cnpj] = { data, cachedAt: Date.now() };

    const chaves = Object.keys(cache);
    if (chaves.length > CACHE_MAX_ENTRIES) {
      const maisAntiga = chaves.reduce((antiga, atual) =>
        cache[atual].cachedAt < cache[antiga].cachedAt ? atual : antiga
      );
      delete cache[maisAntiga];
    }

    safeSet(KEYS.CACHE, cache);
  }

  function clearCache() {
    safeSet(KEYS.CACHE, {});
  }

  // ---------- TEMA ----------

  function getTheme() {
    return safeGet(KEYS.THEME) || "auto";
  }

  function setTheme(theme) {
    safeSet(KEYS.THEME, theme);
  }

  return {
    getRecents,
    addRecent,
    removeRecent,
    clearRecents,
    getFavorites,
    isFavorite,
    toggleFavorite,
    removeFavorite,
    getCached,
    setCached,
    clearCache,
    getTheme,
    setTheme,
  };

})();
