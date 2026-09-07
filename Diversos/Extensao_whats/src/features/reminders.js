/*
 * reminders.js — lembretes / follow-up. Puro (sem DOM, sem chrome.*).
 * Um lembrete: { id, text, dueAt, convKey, convName, done, notified, createdAt }
 */
import { uid } from "../core/util.js";

const MAX_REMINDERS = 200;
const MAX_TEXT = 300;

export function addReminder(list, { text, dueAt, convKey, convName } = {}) {
  const arr = Array.isArray(list) ? list.slice() : [];
  const t = String(text || "").trim().slice(0, MAX_TEXT);
  const due = Number(dueAt);
  if (!t || !Number.isFinite(due)) return { reminders: arr, added: null };
  const r = {
    id: uid(),
    text: t,
    dueAt: due,
    convKey: String(convKey || ""),
    convName: String(convName || "").slice(0, 120),
    done: false,
    notified: false,
    createdAt: Date.now(),
  };
  arr.push(r);
  if (arr.length > MAX_REMINDERS) arr.splice(0, arr.length - MAX_REMINDERS);
  return { reminders: arr, added: r };
}

export function removeReminder(list, id) {
  return (Array.isArray(list) ? list : []).filter((r) => r.id !== id);
}

export function setDone(list, id, done) {
  return (Array.isArray(list) ? list : []).map((r) =>
    r.id === id ? { ...r, done: Boolean(done) } : r
  );
}

/** Reagenda para daqui a `ms` e rearma o aviso. */
export function snooze(list, id, ms, now = Date.now()) {
  return (Array.isArray(list) ? list : []).map((r) =>
    r.id === id ? { ...r, dueAt: now + ms, notified: false, done: false } : r
  );
}

/** Marca os ids como já avisados (para não repetir o aviso). */
export function markNotified(list, ids) {
  const set = new Set(ids || []);
  return (Array.isArray(list) ? list : []).map((r) =>
    set.has(r.id) ? { ...r, notified: true } : r
  );
}

/** Ordena: pendentes antes de concluídos; dentro, por vencimento. */
export function sortReminders(list) {
  return (Array.isArray(list) ? list.slice() : []).sort(
    (a, b) => (a.done ? 1 : 0) - (b.done ? 1 : 0) || a.dueAt - b.dueAt
  );
}

/** Lembretes vencidos que ainda não foram avisados. */
export function dueNow(list, now = Date.now()) {
  return (Array.isArray(list) ? list : []).filter(
    (r) => !r.done && !r.notified && r.dueAt <= now
  );
}

/** Quantos lembretes estão vencidos e pendentes. */
export function overdueCount(list, now = Date.now()) {
  return (Array.isArray(list) ? list : []).filter((r) => !r.done && r.dueAt <= now).length;
}

/** Botões de atalho de horário (só os que ainda estão no futuro). */
export function presets(now = new Date()) {
  const at = (base, h, m) => {
    const x = new Date(base);
    x.setHours(h, m || 0, 0, 0);
    return x.getTime();
  };
  const tomorrow = new Date(now);
  tomorrow.setDate(tomorrow.getDate() + 1);
  const in2days = new Date(now);
  in2days.setDate(in2days.getDate() + 2);

  return [
    { label: "Em 1 h", ts: now.getTime() + 3600e3 },
    { label: "Em 3 h", ts: now.getTime() + 3 * 3600e3 },
    { label: "Hoje 18h", ts: at(now, 18) },
    { label: "Amanhã 9h", ts: at(tomorrow, 9) },
    { label: "Em 2 dias", ts: at(in2days, 9) },
  ].filter((p) => p.ts > now.getTime());
}

/** Vencimento em texto curto pt-BR: "hoje 14:30", "amanhã 09:00", "06/09 14:30". */
export function formatDue(ts, now = Date.now()) {
  const d = new Date(ts);
  const day = (x) => new Date(x).toDateString();
  const time = d.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" });
  if (day(now) === day(ts)) return "hoje " + time;
  const tmr = new Date(now);
  tmr.setDate(tmr.getDate() + 1);
  if (day(tmr) === day(ts)) return "amanhã " + time;
  return d.toLocaleDateString("pt-BR", { day: "2-digit", month: "2-digit" }) + " " + time;
}

export { MAX_REMINDERS };
