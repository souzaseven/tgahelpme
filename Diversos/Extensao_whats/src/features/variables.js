/*
 * variables.js — troca de {{variaveis}} no texto das respostas rapidas.
 * Puro (sem DOM): recebe o texto + um contexto e devolve o texto final.
 *
 * Variaveis suportadas (nao diferencia maiuscula/minuscula, tolera espacos):
 *   {{nome}}       primeiro nome do contato da conversa aberta
 *   {{contato}}    nome completo do contato
 *   {{saudacao}}   "Bom dia" / "Boa tarde" / "Boa noite" (vem no contexto)
 *   {{data}}       data de hoje  (DD/MM/AAAA)
 *   {{hora}}       hora atual    (HH:MM)
 *   {{diasemana}}  dia da semana por extenso
 *
 * Tags desconhecidas ficam como estao. Tags que resolvem para vazio
 * (ex.: {{nome}} sem conversa aberta) sao reportadas em `missing`.
 */

const TAG_RE = /\{\{\s*([\p{L}\p{N}_-]+)\s*\}\}/gu;

function buildMap(ctx) {
  const now = ctx && ctx.now instanceof Date ? ctx.now : new Date();
  const full = String((ctx && ctx.name) || "").trim();
  const first = full ? full.split(/\s+/)[0] : "";
  let data = "";
  let hora = "";
  let diasemana = "";
  try {
    data = now.toLocaleDateString("pt-BR");
    hora = now.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" });
    diasemana = now.toLocaleDateString("pt-BR", { weekday: "long" });
  } catch {
    /* ambientes sem Intl completo — deixa vazio */
  }
  return {
    nome: first,
    contato: full,
    saudacao: String((ctx && ctx.greeting) || "").trim(),
    data,
    hora,
    diasemana,
  };
}

/** Ha alguma {{tag}} no texto? */
export function hasVars(text) {
  TAG_RE.lastIndex = 0;
  return TAG_RE.test(String(text));
}

/**
 * @returns {{ text: string, missing: string[] }}
 */
export function resolveVars(text, ctx = {}) {
  const map = buildMap(ctx);
  const missing = new Set();
  const out = String(text).replace(TAG_RE, (whole, rawKey) => {
    const key = rawKey.toLowerCase();
    if (!(key in map)) return whole; // tag desconhecida: mantem
    const value = map[key];
    if (value === "") {
      missing.add(key);
      return "";
    }
    return value;
  });
  return { text: out, missing: Array.from(missing) };
}

/** Lista das variaveis oferecidas na UI (ordem de exibicao). */
export const VARS = ["nome", "saudacao", "data", "hora", "contato", "diasemana"];
