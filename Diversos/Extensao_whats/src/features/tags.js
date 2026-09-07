/*
 * tags.js — tags internas por conversa (rotulos proprios da extensao).
 * Puro (sem DOM, sem chrome.*).
 *
 *   tagDefs  = [{ id, label, color }]        catalogo de tags disponiveis
 *   convTags = { "<conversationKey>": [id] }  quais tags cada conversa tem
 */
import { uid } from "../core/util.js";

// Cores legiveis com texto branco, em claro e escuro.
export const PALETTE = [
  "#1e88e5", "#e8890c", "#2e7d32", "#6a1b9a",
  "#546e7a", "#c2185b", "#00838f", "#5d4037",
];

const MAX_LABEL = 24;

/** Catalogo inicial de tags (master prompt). */
export function seedTagDefs() {
  return [
    { id: uid(), label: "Novo cliente", color: PALETTE[0] },
    { id: uid(), label: "Aguardando retorno", color: PALETTE[1] },
    { id: uid(), label: "Financeiro", color: PALETTE[2] },
    { id: uid(), label: "Suporte", color: PALETTE[3] },
    { id: uid(), label: "Resolvido", color: PALETTE[4] },
  ];
}

/** Proxima cor do palette (a menos usada; empate -> ordem do palette). */
export function nextColor(defs) {
  const count = new Map(PALETTE.map((c) => [c, 0]));
  for (const d of defs || []) {
    if (count.has(d.color)) count.set(d.color, count.get(d.color) + 1);
  }
  let best = PALETTE[0];
  let min = Infinity;
  for (const c of PALETTE) {
    const n = count.get(c);
    if (n < min) {
      min = n;
      best = c;
    }
  }
  return best;
}

/** Ids das tags de uma conversa. */
export function tagsOfConv(convTags, key) {
  if (!key || !convTags) return [];
  const v = convTags[key];
  return Array.isArray(v) ? v.slice() : [];
}

/** A conversa tem alguma tag? */
export function hasTags(convTags, key) {
  return tagsOfConv(convTags, key).length > 0;
}

/** Liga/desliga uma tag na conversa. Retorna NOVO mapa convTags. */
export function toggleTag(convTags, key, tagId) {
  const next = { ...(convTags || {}) };
  if (!key || !tagId) return next;
  const cur = Array.isArray(next[key]) ? next[key].slice() : [];
  const i = cur.indexOf(tagId);
  if (i >= 0) cur.splice(i, 1);
  else cur.push(tagId);
  if (cur.length) next[key] = cur;
  else delete next[key];
  return next;
}

/**
 * Cria uma tag no catalogo. Dedupe por label (sem diferenciar caixa).
 * @returns {{ defs: object[], def: object|null }}
 */
export function addTagDef(defs, label) {
  const list = Array.isArray(defs) ? defs.slice() : [];
  const clean = String(label || "").trim().slice(0, MAX_LABEL);
  if (!clean) return { defs: list, def: null };
  const existing = list.find((d) => d.label.toLowerCase() === clean.toLowerCase());
  if (existing) return { defs: list, def: existing };
  const def = { id: uid(), label: clean, color: nextColor(list) };
  list.push(def);
  return { defs: list, def };
}

/** Remove uma tag do catalogo e de todas as conversas. */
export function removeTagDef(defs, convTags, tagId) {
  const nextDefs = (Array.isArray(defs) ? defs : []).filter((d) => d.id !== tagId);
  const nextConv = {};
  for (const [k, ids] of Object.entries(convTags || {})) {
    const kept = (Array.isArray(ids) ? ids : []).filter((id) => id !== tagId);
    if (kept.length) nextConv[k] = kept;
  }
  return { defs: nextDefs, convTags: nextConv };
}

/** Resolve ids -> [{id,label,color}], na ordem do catalogo. */
export function resolveTags(defs, ids) {
  const set = new Set(ids || []);
  return (Array.isArray(defs) ? defs : []).filter((d) => set.has(d.id));
}

export { MAX_LABEL };
