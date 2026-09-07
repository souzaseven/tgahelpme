/*
 * text-tools.js - transformacoes de texto puras (sem DOM, sem chrome.*).
 * Todas recebem string e devolvem string. Nada altera nada "sozinho":
 * a UI mostra o resultado e o usuario decide usar.
 */

const LOCALE = "pt-BR";
const NBSP = String.fromCharCode(0xa0);
// zero-width space / non-joiner / joiner, word joiner, BOM
const INVISIBLE = new RegExp("[\\u200B\\u200C\\u200D\\u2060\\uFEFF]", "g");
const MULTISPACE = new RegExp("[ \\t\\u00A0]+", "g");

/** TUDO EM MAIUSCULAS. */
export function toUpper(text) {
  return String(text).toLocaleUpperCase(LOCALE);
}

/** tudo em minusculas. */
export function toLower(text) {
  return String(text).toLocaleLowerCase(LOCALE);
}

/** Primeira Letra De Cada Palavra. */
export function titleCase(text) {
  return String(text).replace(/\S+/gu, (w) =>
    w.charAt(0).toLocaleUpperCase(LOCALE) + w.slice(1).toLocaleLowerCase(LOCALE)
  );
}

/** Primeira letra de cada frase em maiuscula; o resto minusculo. */
export function sentenceCase(text) {
  const lower = String(text).toLocaleLowerCase(LOCALE);
  return lower.replace(/(^\s*|[.!?]\s+|\n\s*)(\p{L})/gu, (m, sep, ch) =>
    sep + ch.toLocaleUpperCase(LOCALE)
  );
}

/** Remove espacos duplicados e apara as pontas de cada linha. Mantem quebras. */
export function collapseSpaces(text) {
  return String(text)
    .replace(MULTISPACE, " ")
    .split("\n")
    .map((line) => line.replace(/^ +| +$/g, ""))
    .join("\n");
}

/** 3+ linhas em branco viram no maximo 1 (uma linha vazia). */
export function collapseBlankLines(text) {
  return String(text).replace(/\n{3,}/g, "\n\n").trimEnd();
}

/** Junta tudo em um paragrafo so (remove as quebras de linha). */
export function joinLines(text) {
  return String(text)
    .replace(/\s*\n\s*/g, " ")
    .replace(/ {2,}/g, " ")
    .trim();
}

/** Remove caracteres invisiveis (zero-width) e troca NBSP por espaco normal. */
export function stripInvisible(text) {
  return String(text).replace(INVISIBLE, "").split(NBSP).join(" ");
}

/**
 * Envolve (ou desenvolve, se ja estiver envolvido) o texto com um marcador
 * de formatacao do WhatsApp: "*" negrito, "_" italico, "~" tachado, "`" codigo.
 */
export function wrapMark(text, mark) {
  const t = String(text);
  const m = String(mark);
  if (!t) return m + m;
  if (t.length >= m.length * 2 && t.startsWith(m) && t.endsWith(m)) {
    return t.slice(m.length, -m.length);
  }
  return m + t + m;
}

/** Contagem para o rodape: caracteres (code points), palavras e linhas. */
export function countStats(text) {
  const t = String(text);
  return {
    chars: Array.from(t).length,
    words: (t.match(/\S+/gu) || []).length,
    lines: t.length ? t.split("\n").length : 0,
  };
}
