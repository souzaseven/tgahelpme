/*
 * calc.js — calculadora simples e SEGURA (sem eval). Puro.
 * Suporta + - * / ( ) e decimais com "." ou "," (pt-BR).
 */

function normalize(input) {
  let s = String(input == null ? "" : input).trim().replace(/\s+/g, "");
  if (!s) return "";
  const hasC = s.includes(",");
  const hasD = s.includes(".");
  if (hasC && hasD) s = s.replace(/\./g, "").replace(/,/g, "."); // 1.234,56 -> 1234.56
  else if (hasC) s = s.replace(/,/g, ".");
  return s;
}

function applyOp(stack, op) {
  if (op === "u-") {
    stack.push(-stack.pop());
    return;
  }
  const b = stack.pop();
  const a = stack.pop();
  if (a === undefined || b === undefined) {
    stack.push(NaN);
    return;
  }
  if (op === "+") stack.push(a + b);
  else if (op === "-") stack.push(a - b);
  else if (op === "*") stack.push(a * b);
  else if (op === "/") stack.push(b === 0 ? NaN : a / b);
}

/**
 * Avalia uma expressao aritmetica. @returns {{ value:number, ok:boolean }}
 */
export function evalExpr(input) {
  const s = normalize(input);
  if (!s) return { value: 0, ok: false };
  if (!/^[0-9.+\-*/()]+$/.test(s)) return { value: 0, ok: false };

  const tokens = s.match(/\d*\.\d+|\d+\.?|[+\-*/()]/g) || [];
  const out = [];
  const ops = [];
  const prec = { "u-": 4, "*": 3, "/": 3, "+": 2, "-": 2 };
  let prev = null; // "num" | "op" | "(" | ")"

  for (const tk of tokens) {
    if (/^[\d.]/.test(tk)) {
      const n = Number(tk);
      if (!Number.isFinite(n)) return { value: 0, ok: false };
      out.push(n);
      prev = "num";
    } else if (tk === "(") {
      ops.push(tk);
      prev = "(";
    } else if (tk === ")") {
      while (ops.length && ops[ops.length - 1] !== "(") applyOp(out, ops.pop());
      if (!ops.length) return { value: 0, ok: false };
      ops.pop();
      prev = ")";
    } else {
      let op = tk;
      if (tk === "-" && (prev === null || prev === "op" || prev === "(")) op = "u-";
      while (
        ops.length &&
        ops[ops.length - 1] !== "(" &&
        op !== "u-" &&
        prec[ops[ops.length - 1]] >= prec[op]
      ) {
        applyOp(out, ops.pop());
      }
      ops.push(op);
      prev = "op";
    }
  }
  while (ops.length) {
    const op = ops.pop();
    if (op === "(") return { value: 0, ok: false };
    applyOp(out, op);
  }
  if (out.length !== 1 || !Number.isFinite(out[0])) return { value: 0, ok: false };
  return { value: out[0], ok: true };
}

const round6 = (n) => Math.round(n * 1e6) / 1e6;

/** X% de Y -> { of, plus, minus }. */
export function percentOf(pct, base) {
  const p = Number(normalize(pct));
  const b = Number(normalize(base));
  if (!Number.isFinite(p) || !Number.isFinite(b)) return { ok: false };
  return {
    ok: true,
    of: round6((p / 100) * b),
    plus: round6(b * (1 + p / 100)),
    minus: round6(b * (1 - p / 100)),
  };
}

/** Numero em pt-BR, sem casas decimais inuteis. */
export function fmtNum(n) {
  if (!Number.isFinite(n)) return "—";
  const r = Math.round(n * 1e6) / 1e6;
  return r.toLocaleString("pt-BR", { maximumFractionDigits: 6 });
}

/** Numero como moeda R$. */
export function fmtMoney(n) {
  if (!Number.isFinite(n)) return "—";
  try {
    return n.toLocaleString("pt-BR", { style: "currency", currency: "BRL" }).replace(/ /g, " ");
  } catch {
    return "R$ " + fmtNum(Math.round(n * 100) / 100);
  }
}
