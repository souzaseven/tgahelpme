/*
 * quick-replies.js — logica pura das respostas rapidas.
 * Sem DOM, sem chrome.* — so funcoes testaveis.
 */

/** Filtra respostas por texto livre (atalho, titulo ou corpo). */
export function findByQuery(replies, query) {
  const q = String(query || "").trim().toLowerCase();
  if (!q) return replies.slice(0, 100);
  return replies
    .filter((r) => {
      return (
        (r.shortcut || "").toLowerCase().includes(q) ||
        (r.title || "").toLowerCase().includes(q) ||
        (r.text || "").toLowerCase().includes(q)
      );
    })
    .slice(0, 100);
}

/**
 * Extrai o atalho digitado no fim do texto do composer.
 *   "ola /hor"  -> "/hor"
 *   "/bomdia"   -> "/bomdia"
 *   "a/b"       -> null  (precisa de espaco ou inicio antes da "/")
 */
export function trailingToken(text) {
  if (!text) return null;
  const m = /(?:^|\s)(\/[\p{L}\p{N}_-]{1,30})$/u.exec(String(text));
  return m ? m[1] : null;
}

/**
 * Respostas cujo atalho casa com o token.
 * Prioriza match exato; senao, match por prefixo.
 */
export function matchShortcut(replies, token) {
  if (!token) return [];
  const t = token.toLowerCase();
  const exact = replies.filter((r) => (r.shortcut || "").toLowerCase() === t);
  if (exact.length) return exact;
  return replies
    .filter((r) => {
      const s = (r.shortcut || "").toLowerCase();
      return s.length > 1 && s.startsWith(t);
    })
    .slice(0, 6);
}

// Rotulo (e sentinela) das respostas sem categoria.
export const NO_CATEGORY = "Sem categoria";

/** Nome de categoria normalizado ("" -> NO_CATEGORY). */
export function categoryLabel(cat) {
  const c = String(cat || "").trim();
  return c || NO_CATEGORY;
}

/** Categorias existentes, em ordem: nomeadas (alfabetica) e, por fim, "Sem categoria". */
export function categoriesOf(replies) {
  const set = new Set();
  let hasEmpty = false;
  for (const r of replies || []) {
    const c = String(r.category || "").trim();
    if (c) set.add(c);
    else hasEmpty = true;
  }
  const named = Array.from(set).sort((a, b) => a.localeCompare(b, "pt-BR"));
  return hasEmpty ? named.concat(NO_CATEGORY) : named;
}

/** Filtra por categoria. `cat` = nome, ou NO_CATEGORY, ou "all"/vazio para tudo. */
export function filterByCategory(replies, cat) {
  if (!cat || cat === "all") return replies.slice();
  if (cat === NO_CATEGORY) return (replies || []).filter((r) => !String(r.category || "").trim());
  return (replies || []).filter((r) => String(r.category || "").trim() === cat);
}

/** Ordena a lista de respostas: "manual" (como está), "used" (mais usadas) ou "alpha". */
export function sortReplies(replies, mode) {
  const arr = Array.isArray(replies) ? replies.slice() : [];
  const label = (r) => (r.title || r.shortcut || r.text || "").toLowerCase();
  if (mode === "used") {
    return arr.sort((a, b) => (b.uses || 0) - (a.uses || 0) || label(a).localeCompare(label(b), "pt-BR"));
  }
  if (mode === "alpha") {
    return arr.sort((a, b) => label(a).localeCompare(label(b), "pt-BR"));
  }
  return arr;
}

/** Agrupa as respostas por categoria, na ordem de categoriesOf. */
export function groupByCategory(replies) {
  const order = categoriesOf(replies);
  return order.map((category) => ({
    category,
    items: filterByCategory(replies, category),
  }));
}

/** Valida os campos de uma resposta antes de salvar. Retorna array de erros. */
export function validateReply(draft) {
  const errors = [];
  const text = String(draft && draft.text || "").trim();
  const shortcut = String(draft && draft.shortcut || "").trim();
  if (!text) errors.push("O texto da resposta é obrigatório.");
  if (text.length > 4000) errors.push("O texto é muito longo (máx. 4000).");
  if (shortcut && !/^\/[\p{L}\p{N}_-]{1,30}$/u.test(shortcut)) {
    errors.push("Atalho inválido. Use algo como /horario (sem espaços).");
  }
  const category = String(draft && draft.category || "").trim();
  if (category.length > 40) errors.push("Nome da categoria muito longo (máx. 40).");
  return errors;
}
