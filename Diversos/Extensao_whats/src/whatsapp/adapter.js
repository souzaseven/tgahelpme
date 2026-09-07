/*
 * adapter.js — camada de compatibilidade com o WhatsApp Web.
 * ------------------------------------------------------------------
 * O resto da extensao NUNCA fala direto com o DOM do WhatsApp.
 * Fala com estas funcoes. Se o WhatsApp mudar, corrige-se aqui +
 * selectors.js, e nada mais.
 */
import { SEL, pick } from "./selectors.js";
import { waitFor } from "../core/util.js";
import { log } from "../core/debug.js";

/** Uma barra vertical estreita e alta, encostada na esquerda? */
function looksLikeRail(elm) {
  try {
    const r = elm.getBoundingClientRect();
    return r.width >= 36 && r.width <= 140 && r.height >= 200 && r.left <= 24;
  } catch {
    return false;
  }
}

/**
 * Barra lateral de icones do WhatsApp (UI nova), ou null.
 * 1) hook data-testid; 2) deriva do marcador data-navbar-item (mais resiliente,
 * independe de classe/testid); 3) seletores classicos; 4) heuristica.
 */
export function getNavRail() {
  // 1) hook explicito
  const byTestId = document.querySelector("[data-testid='navbar-primary-section']");
  if (byTestId) return byTestId;

  // 2) sobe a partir de um item de navegacao ate o ancestral que agrupa varios
  const anyItem = document.querySelector("[data-navbar-item='true'], [data-navbar-item]");
  if (anyItem) {
    let p = anyItem.parentElement;
    while (p && p !== document.body && p !== document.documentElement) {
      try {
        if (p.querySelectorAll("[data-navbar-item]").length >= 3) return p;
      } catch {
        /* ignora */
      }
      p = p.parentElement;
    }
    return anyItem.parentElement || anyItem;
  }

  // 3) seletores classicos + validacao de formato
  const direct = pick(SEL.navRail);
  if (direct && looksLikeRail(direct)) return direct;

  // 4) heuristica: nav estreito e alto encostado a esquerda
  const app = document.querySelector("#app");
  if (app) {
    for (const c of app.querySelectorAll("header, nav, [role='navigation']")) {
      if (looksLikeRail(c)) return c;
    }
  }
  return null;
}

/** Ultimo botao de navegacao da barra (ancora para posicionar sem sobrepor). */
export function getNavLastItem(rail) {
  const r = rail || getNavRail();
  if (!r) return null;
  let items = [];
  for (const sel of SEL.navItem) {
    try {
      const found = r.querySelectorAll(sel);
      if (found.length) { items = found; break; }
    } catch {
      /* ignora */
    }
  }
  return items.length ? items[items.length - 1] : null;
}

/** Elemento contenteditable da caixa de mensagem, ou null. */
export function getComposer() {
  return pick(SEL.composer);
}

/** Rodape da conversa (ancora para a caixa de sugestao), ou null. */
export function getFooter() {
  return pick(SEL.footer);
}

/** Container #main da conversa aberta, ou null. */
export function getMain() {
  return pick(SEL.main);
}

/** Nome do contato/grupo da conversa aberta (best effort), ou null. */
export function getConversationName() {
  const el = pick(SEL.conversationTitle);
  if (!el) return null;
  const raw = el.getAttribute("title") || el.textContent || "";
  const name = raw.trim();
  return name || null;
}

/** Ha uma conversa aberta e pronta para digitar? */
export function isChatOpen() {
  return Boolean(getMain() && getComposer());
}

/**
 * Escreve `text` no campo de busca de conversas do WhatsApp (filtra a lista).
 * Nao clica em nada — o usuario escolhe a conversa. @returns {boolean}
 */
export function searchChats(text) {
  const el = pick(SEL.chatSearch);
  if (!el) return false;
  try {
    el.focus();
    if (el.tagName === "INPUT" && "value" in el) {
      el.value = String(text);
      el.dispatchEvent(new InputEvent("input", { bubbles: true }));
      return true;
    }
    document.execCommand("selectAll", false);
    document.execCommand("delete", false);
    return document.execCommand("insertText", false, String(text));
  } catch {
    return false;
  }
}

/**
 * Identificador estavel da conversa aberta (para amarrar notas locais).
 *
 * Estrategia: as mensagens carregam data-id no formato
 *   "false_<numero>@c.us_<msgid>"  (individual)
 *   "true_<id>@g.us_<msgid>"       (grupo)
 *   "..._<x>@lid_<msgid>"          (formato novo)
 * O trecho "<...>@c.us|@g.us|@lid" e o JID: unico e estavel.
 * Fallback: "name:<nome do contato>" quando nao ha mensagens legiveis.
 *
 * @returns {string|null}  "jid:<...>" | "name:<...>" | null (sem conversa)
 */
export function getConversationKey() {
  const main = getMain();
  if (!main) return null;

  let nodes = [];
  for (const sel of SEL.messageWithId) {
    try {
      const found = main.querySelectorAll(sel);
      if (found.length) { nodes = found; break; }
    } catch {
      /* ignora */
    }
  }
  for (let i = nodes.length - 1; i >= 0; i--) {
    const raw = nodes[i].getAttribute("data-id") || "";
    const parts = raw.split("_");
    if (parts.length >= 3 && /@(c\.us|g\.us|lid|broadcast)$/.test(parts[1])) {
      return "jid:" + parts[1];
    }
  }

  const name = getConversationName();
  return name ? "name:" + name.toLowerCase().replace(/\s+/g, " ").trim() : null;
}

/**
 * Observa troca de conversa. `cb(newKey)` roda quando a conversa muda.
 *
 * Observer ESCOPADO ao #main e SEM subtree (a troca de conversa substitui
 * o container filho de #main; mensagens novas nao disparam). Se nao houver
 * #main, cai para um polling leve e limitado — nunca um observer no #app,
 * que storma no WhatsApp e pode travar a aba.
 * Retorna funcao de limpeza.
 */
export function onConversationChange(cb, { root, debounceMs = 300 } = {}) {
  let last = getConversationKey();
  let timer = 0;
  let poll = 0;
  let alive = true;

  const fire = () => {
    const k = getConversationKey();
    if (k !== last) {
      last = k;
      try { cb(k); } catch { /* fail safe */ }
    }
  };
  const check = () => {
    clearTimeout(timer);
    timer = setTimeout(() => { if (alive) fire(); }, debounceMs);
  };

  const target = root || getMain();
  let mo = null;
  if (target) {
    try {
      mo = new MutationObserver(check);
      mo.observe(target, { childList: true });
    } catch {
      mo = null;
    }
  } else {
    // #main ainda nao existe: checa a cada 1s ate aparecer (ou ~30s).
    let tries = 0;
    const tick = () => {
      if (!alive) return;
      fire();
      if (getMain() || ++tries > 30) return;
      poll = setTimeout(tick, 1000);
    };
    poll = setTimeout(tick, 1000);
  }

  return () => {
    alive = false;
    clearTimeout(timer);
    clearTimeout(poll);
    if (mo) {
      try { mo.disconnect(); } catch {}
    }
  };
}

/**
 * Resolve quando a interface essencial do WhatsApp esta montada.
 * Nao observa o DOM inteiro — apenas checa a raiz periodicamente.
 */
export async function waitForApp() {
  await waitFor(() => pick(SEL.appRoot), { timeout: 60000, interval: 400 });
  log("WhatsApp Web pronto");
  return true;
}
