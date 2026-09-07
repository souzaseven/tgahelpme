/*
 * formatters.js — formata documentos e valores brasileiros. Puro.
 * Cada funcao recebe uma string e devolve { text, ok }:
 *   ok=true  -> `text` e o valor formatado (ou o texto com o trecho formatado)
 *   ok=false -> nao deu para reconhecer; `text` volta igual
 */

function onlyDigits(s) {
  return String(s).replace(/\D+/g, "");
}

function apply(text, len, maskFn) {
  const s = String(text);
  // "runs" = digitos, possivelmente separados por . - / ( ) ou espaco
  const runs = s.match(/\d(?:[ .\-/()]*\d)*/g) || [];
  for (const run of runs) {
    if (onlyDigits(run).length !== len) continue;
    const masked = maskFn(onlyDigits(run));
    // se o texto e so esse valor, devolve so a mascara; senao troca no lugar
    return { text: s.trim() === run.trim() ? masked : s.replace(run, masked), ok: true };
  }
  return { text: s, ok: false };
}

export function formatCPF(text) {
  return apply(text, 11, (d) =>
    d.slice(0, 3) + "." + d.slice(3, 6) + "." + d.slice(6, 9) + "-" + d.slice(9, 11)
  );
}

export function formatCNPJ(text) {
  return apply(text, 14, (d) =>
    d.slice(0, 2) + "." + d.slice(2, 5) + "." + d.slice(5, 8) + "/" + d.slice(8, 12) + "-" + d.slice(12, 14)
  );
}

export function formatCEP(text) {
  return apply(text, 8, (d) => d.slice(0, 5) + "-" + d.slice(5, 8));
}

export function formatPhoneBR(text) {
  const s = String(text);
  let d = onlyDigits(s);
  if ((d.length === 12 || d.length === 13) && d.startsWith("55")) d = d.slice(2);
  let masked = null;
  if (d.length === 11) masked = "(" + d.slice(0, 2) + ") " + d.slice(2, 7) + "-" + d.slice(7);
  else if (d.length === 10) masked = "(" + d.slice(0, 2) + ") " + d.slice(2, 6) + "-" + d.slice(6);
  if (!masked) return { text: s, ok: false };

  // se o texto e so o numero, devolve so a mascara; senao troca no lugar
  if (onlyDigits(s) === onlyDigits(masked) || onlyDigits(s).replace(/^55/, "") === onlyDigits(masked)) {
    return { text: masked, ok: true };
  }
  const m = s.match(/(?<!\d)(?:\+?55\s?)?\(?\d{2}\)?[\s.-]?\d{4,5}[\s.-]?\d{4}(?!\d)/);
  if (m) return { text: s.replace(m[0], masked), ok: true };
  return { text: masked, ok: true };
}

export function formatCurrencyBR(text) {
  const s = String(text).trim();
  if (!s) return { text: s, ok: false };
  let norm = s.replace(/R\$/gi, "").replace(/\s+/g, "");
  if (norm.includes(",")) {
    norm = norm.replace(/\./g, "").replace(",", ".");
  }
  const n = Number(norm);
  if (!Number.isFinite(n)) return { text: s, ok: false };
  try {
    const out = n
      .toLocaleString("pt-BR", { style: "currency", currency: "BRL" })
      .replace(/ /g, " ");
    return { text: out, ok: true };
  } catch {
    return { text: "R$ " + n.toFixed(2).replace(".", ","), ok: true };
  }
}
