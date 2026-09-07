/*
 * signature.js — assinatura opcional no fim das respostas rapidas. Puro.
 */

/** Acrescenta a assinatura ao texto, se ligada e ainda nao presente. */
export function withSignature(text, signature) {
  const t = String(text == null ? "" : text);
  if (!signature || signature.enabled !== true) return t;
  const sig = String(signature.text || "").trim();
  if (!sig) return t;
  if (t.replace(/\s+$/, "").endsWith(sig)) return t; // ja assinado
  return t.replace(/\s+$/, "") + "\n\n" + sig;
}
