/*
 * chat-bg.js — fundo personalizado da conversa. SÓ gera CSS (best-effort).
 * Nada de JS de runtime nem observer.
 */
import { SEL } from "../whatsapp/selectors.js";

function safeUrl(u) {
  return String(u || "").replace(/["'()\\\s]/g, "");
}

/** CSS (string) para o fundo da conversa. "" se desligado. */
export function buildChatBgCSS(bg) {
  if (!bg || bg.mode === "none") return "";
  const targets = (SEL.chatBgTarget || ["#main"]).join(",\n");
  const op = (Number.isFinite(bg.opacity) ? bg.opacity : 100) / 100;

  if (bg.mode === "color" && bg.color) {
    return (
      targets +
      " {\n  background-color: " + bg.color + " !important;\n  background-image: none !important;\n}"
    );
  }
  if (bg.mode === "image" && /^https:\/\//.test(bg.imageUrl || "")) {
    return (
      targets +
      " {\n  background-image: linear-gradient(rgba(0,0,0," + (1 - op).toFixed(2) +
      "), rgba(0,0,0," + (1 - op).toFixed(2) + ")), url('" + safeUrl(bg.imageUrl) +
      "') !important;\n  background-size: cover !important;\n  background-position: center !important;\n}"
    );
  }
  return "";
}
