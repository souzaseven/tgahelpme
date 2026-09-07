/*
 * wa-contact.js — abrir conversa com um numero (mesmo nao salvo). Puro.
 * O WhatsApp Web entende https://web.whatsapp.com/send?phone=<E164>.
 */

/**
 * Junta DDI + numero em E.164 (só dígitos, sem "+").
 * @returns {{ e164: string, ok: boolean }}
 */
export function toE164(ddi, number) {
  const d = String(ddi || "").replace(/\D+/g, "");
  // tira o "0" que muita gente poe antes do DDD (ex.: 065 -> 65)
  const n = String(number || "").replace(/\D+/g, "").replace(/^0+/, "");
  let all;
  if (n.length >= 12) {
    all = n; // usuario ja colou o numero completo com DDI
  } else {
    all = d + n;
  }
  all = all.replace(/^0+/, "");
  if (all.length < 10 || all.length > 15) return { e164: "", ok: false };
  return { e164: all, ok: true };
}

/** URL que o WhatsApp Web abre direto na conversa (com texto opcional no campo). */
export function waSendUrl(e164, text) {
  const base = "https://web.whatsapp.com/send?phone=" + encodeURIComponent(e164);
  const t = String(text || "").trim();
  return t ? base + "&text=" + encodeURIComponent(t) : base;
}

/** Link curto wa.me (para copiar/compartilhar). */
export function waMeUrl(e164) {
  return "https://wa.me/" + encodeURIComponent(e164);
}
