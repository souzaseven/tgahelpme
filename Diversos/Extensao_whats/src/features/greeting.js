/*
 * greeting.js — saudacao conforme o horario local. Puro (sem DOM).
 *
 * Faixas:
 *   05:00–11:59  ->  Bom dia
 *   12:00–17:59  ->  Boa tarde
 *   18:00–04:59  ->  Boa noite
 */

/** @param {number} hour 0..23 */
export function greetingForHour(hour) {
  const h = Number(hour);
  if (!Number.isFinite(h)) return "Olá";
  if (h >= 5 && h < 12) return "Bom dia";
  if (h >= 12 && h < 18) return "Boa tarde";
  return "Boa noite";
}

/** Saudacao para o horario atual (ou de uma data dada). */
export function currentGreeting(date = new Date()) {
  return greetingForHour(date.getHours());
}
