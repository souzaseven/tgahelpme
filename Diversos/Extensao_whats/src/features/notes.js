/*
 * notes.js — logica pura das notas por conversa.
 * Sem DOM, sem chrome.*. `notes` e um mapa { [conversationKey]: {text, updatedAt} }.
 */

const MAX_NOTE_LEN = 5000;

/** Texto da nota de uma conversa (ou "" se nao houver). */
export function getNoteText(notes, key) {
  if (!key || !notes) return "";
  const n = notes[key];
  return n && typeof n.text === "string" ? n.text : "";
}

/** Ha uma nota nao-vazia para esta conversa? */
export function hasNote(notes, key) {
  return Boolean(key && notes && notes[key] && String(notes[key].text || "").trim());
}

/** Metadados da nota (ou null). */
export function getNoteMeta(notes, key) {
  if (!key || !notes || !notes[key]) return null;
  return { updatedAt: notes[key].updatedAt || 0 };
}

/**
 * Retorna um NOVO mapa de notas com a nota da conversa atualizada.
 * Texto vazio remove a entrada (mantem o storage limpo).
 */
export function withNote(notes, key, text) {
  const next = { ...(notes || {}) };
  if (!key) return next;
  const t = String(text == null ? "" : text).trim();
  if (!t) {
    delete next[key];
  } else {
    next[key] = { text: t.slice(0, MAX_NOTE_LEN), updatedAt: Date.now() };
  }
  return next;
}

/** Tempo relativo curto em pt-BR: "agora mesmo", "há 3 min", "há 2 h", "há 5 d". */
export function relativeTime(ts, now = Date.now()) {
  if (!ts) return "";
  const s = Math.max(0, Math.floor((now - ts) / 1000));
  if (s < 45) return "agora mesmo";
  const m = Math.floor(s / 60);
  if (m < 60) return `há ${m} min`;
  const h = Math.floor(m / 60);
  if (h < 24) return `há ${h} h`;
  const d = Math.floor(h / 24);
  if (d < 30) return `há ${d} d`;
  const mo = Math.floor(d / 30);
  return `há ${mo} ${mo === 1 ? "mês" : "meses"}`;
}
