/*
 * stats.js — estatisticas dos DADOS DA EXTENSAO (nao lê o WhatsApp). Puro.
 */

export function computeStats(state) {
  const s = state || {};
  const replies = Array.isArray(s.quickReplies) ? s.quickReplies : [];
  const cats = new Set(replies.map((r) => String(r.category || "").trim()).filter(Boolean));
  const reminders = Array.isArray(s.reminders) ? s.reminders : [];
  const now = Date.now();
  return [
    { label: "Respostas rápidas", value: replies.length },
    { label: "Categorias", value: cats.size },
    { label: "Usos de respostas", value: replies.reduce((a, r) => a + (r.uses || 0), 0) },
    { label: "Notas de conversa", value: Object.keys(s.notes || {}).length },
    { label: "Favoritos", value: (s.favorites || []).length },
    { label: "Tags no catálogo", value: (s.tagDefs || []).length },
    { label: "Conversas com tag", value: Object.keys(s.convTags || {}).length },
    { label: "Lembretes pendentes", value: reminders.filter((r) => !r.done).length },
    { label: "Lembretes vencidos", value: reminders.filter((r) => !r.done && r.dueAt <= now).length },
  ];
}

/** Resposta mais usada (ou null). */
export function topReply(state) {
  const replies = (state && state.quickReplies) || [];
  let best = null;
  for (const r of replies) {
    if ((r.uses || 0) > 0 && (!best || r.uses > best.uses)) best = r;
  }
  return best;
}
