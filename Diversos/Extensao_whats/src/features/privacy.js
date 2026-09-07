/*
 * privacy.js — "Modo privacidade de tela".
 * Gera SÓ um bloco de CSS que desfoca partes da tela do PROPRIO usuario
 * (proteção contra quem olha por cima do ombro). Passe o mouse por cima
 * para revelar. Nao mexe em dado nenhum, nao engana ninguem, sem JS de
 * runtime, sem observer.
 */
import { SEL } from "../whatsapp/selectors.js";

export const PRIVACY_ITEMS = [
  { key: "blurNames", label: "Nomes dos contatos", sel: "pvNames", px: 4 },
  { key: "blurPhotos", label: "Fotos de perfil", sel: "pvPhotos", px: 8 },
  { key: "blurPreviews", label: "Prévia das mensagens na lista", sel: "pvPreviews", px: 3.5 },
  { key: "blurConversation", label: "Mensagens da conversa aberta", sel: "pvConversation", px: 4.5 },
  { key: "blurComposer", label: "Campo de digitação", sel: "pvComposer", px: 4 },
];

/** Retorna o CSS (string) para o modo privacidade. "" se desligado. */
export function buildPrivacyCSS(privacy) {
  if (!privacy || !privacy.enabled) return "";
  const rules = [];
  for (const it of PRIVACY_ITEMS) {
    if (!privacy[it.key]) continue;
    const list = SEL[it.sel] || [];
    if (!list.length) continue;
    const base = list.join(", ");
    const hover = list.map((x) => x + ":hover").join(", ");
    rules.push(base + " { filter: blur(" + it.px + "px) !important; transition: filter .12s ease; }");
    rules.push(hover + " { filter: none !important; }");
  }
  return rules.length ? "/* tgaw privacidade de tela */\n" + rules.join("\n") : "";
}
