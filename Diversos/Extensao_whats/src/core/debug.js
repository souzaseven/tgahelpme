/*
 * debug.js — logging e execucao segura.
 * Em producao DEBUG = false: nenhum ruido no console do WhatsApp.
 */
export const DEBUG = false;

export function log(...args) {
  if (DEBUG) console.log("[TGAW]", ...args);
}

export function warn(...args) {
  if (DEBUG) console.warn("[TGAW]", ...args);
}

export function error(...args) {
  // Erros sempre aparecem, mas prefixados e sem stack ruidoso por padrao.
  console.error("[TGAW]", ...args);
}

/**
 * Executa fn dentro de try/catch. Se falhar, retorna `fallback` e o
 * WhatsApp segue intacto. Use para envolver qualquer acesso ao DOM do
 * WhatsApp ou a APIs que podem mudar.
 */
export function safe(fn, label, fallback) {
  try {
    return fn();
  } catch (e) {
    if (DEBUG) console.error("[TGAW] safe:", label || "(sem rotulo)", e);
    return fallback;
  }
}

export async function safeAsync(fn, label, fallback) {
  try {
    return await fn();
  } catch (e) {
    if (DEBUG) console.error("[TGAW] safeAsync:", label || "(sem rotulo)", e);
    return fallback;
  }
}
