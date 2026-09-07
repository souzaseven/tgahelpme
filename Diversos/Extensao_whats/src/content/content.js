/*
 * content.js — modulo principal (ES module, importado por loader.js).
 * ------------------------------------------------------------------
 * Amarra as pecas:
 *   storage  ->  estado
 *   adapter  ->  WhatsApp
 *   panel    ->  botao + painel
 *   suggestions -> caixinha ao digitar "/atalho"
 *   theme    ->  claro/escuro
 *
 * Filosofia: se qualquer parte falhar, o WhatsApp continua normal.
 */
import {
  waitForApp,
  getComposer,
  getFooter,
  getConversationName,
} from "../whatsapp/adapter.js";
import {
  insertText,
  replaceTrailingToken,
  getComposerText,
  setComposerText,
} from "../whatsapp/composer.js";
import { getState, setState, onStateChange } from "../storage/storage.js";
import { trailingToken, matchShortcut } from "../features/quick-replies.js";
import { resolveVars } from "../features/variables.js";
import { currentGreeting } from "../features/greeting.js";
import { dueNow as remindersDue, markNotified as remindersMarkNotified } from "../features/reminders.js";
import { buildPrivacyCSS } from "../features/privacy.js";
import { buildChatBgCSS } from "../features/chat-bg.js";
import { withSignature } from "../features/signature.js";
import { resolveTheme, watchEnvTheme } from "../ui/theme.js";
import { mountPanel } from "../ui/panel.js";
import { mountSuggestions } from "../ui/suggestions.js";
import { log, error, safeAsync } from "../core/debug.js";

const VERSION = "1.0.1";

let state = null;
let panel = null;
let suggestions = null;
let stopThemeWatch = null;
let reminderTimer = 0;
let reminderInterval = 0;
let heartbeatInterval = 0;
const injectedStyles = {}; // id -> <style> element
let torndown = false;

/** Cria/atualiza um <style id> no <head>. So CSS, sem observer. */
function injectStyle(id, css) {
  try {
    let el = injectedStyles[id];
    if ((!css || !css.trim())) {
      if (el) el.textContent = "";
      return;
    }
    if (!el || !el.isConnected) {
      el = document.createElement("style");
      el.id = id;
      (document.head || document.documentElement).appendChild(el);
      injectedStyles[id] = el;
    }
    el.textContent = css;
  } catch {
    /* fail safe */
  }
}

function applyPageStyles(settings) {
  injectStyle("tgaw-privacy-style", buildPrivacyCSS(settings && settings.privacy));
  injectStyle("tgaw-chatbg-style", buildChatBgCSS(settings && settings.chatBg));
}

/** Incrementa o contador de uso de uma resposta (fire-and-forget). */
async function bumpReplyUse(id) {
  try {
    if (torndown || !state || !id) return;
    const list = state.quickReplies.map((r) => (r.id === id ? { ...r, uses: (r.uses || 0) + 1 } : r));
    state = await setState({ ...state, quickReplies: list });
  } catch {
    /* ignora */
  }
}

function currentTheme() {
  return resolveTheme(state ? state.settings.theme : "auto");
}

function applyTheme() {
  const t = currentTheme();
  if (panel) panel.setTheme(t);
  if (suggestions) suggestions.setTheme(t);
}

/**
 * Desliga TUDO da extensao nesta aba (observers, timers, painel).
 * O WhatsApp continua 100%. Chamado pelo watchdog ou pelo modo seguro.
 */
function teardown(reason) {
  if (torndown) return;
  torndown = true;
  try { console.warn("[TGAW] desativando a extensao nesta aba: " + (reason || "")); } catch {}
  try { clearTimeout(reminderTimer); } catch {}
  try { clearInterval(reminderInterval); } catch {}
  try { clearInterval(heartbeatInterval); } catch {}
  try { if (stopThemeWatch) stopThemeWatch(); } catch {}
  try {
    for (const k of Object.keys(injectedStyles)) {
      if (injectedStyles[k]) injectedStyles[k].remove();
      delete injectedStyles[k];
    }
  } catch {}
  try { if (suggestions) suggestions.destroy(); } catch {}
  try { if (panel) panel.destroy(); } catch {}
  panel = null;
  suggestions = null;
}

function safeMode() {
  try {
    return localStorage.getItem("tgaw_off") === "1";
  } catch {
    return false;
  }
}

async function init() {
  if (window.__tgawInit) return;
  window.__tgawInit = true;

  if (safeMode()) {
    console.info("[TGAW] v" + VERSION + " — MODO SEGURO ligado (localStorage.tgaw_off=1). Extensao inativa. Para religar: delete localStorage.tgaw_off e recarregue.");
    return;
  }
  console.info("[TGAW] v" + VERSION + " inicializando. (Para desligar nesta aba: localStorage.tgaw_off='1' e recarregue.)");

  await waitForApp();

  state = await getState();
  applyPageStyles(state.settings);

  // ----- Painel + FAB -----
  panel = mountPanel({
    getState: () => state,
    applyState: async (next) => {
      state = await setState(next);
      panel.refresh(state);
      applyTheme();
    },
    insertText,
    getComposerText,
    setComposerText,
  });
  panel.refresh(state);

  // ----- Sugestoes ao digitar "/" -----
  suggestions = mountSuggestions({
    getComposer,
    getFooter,
    isEnabled: () => Boolean(state && state.settings.suggestionsEnabled),
    getMatches: (token) => (state ? matchShortcut(state.quickReplies, token) : []),
    tokenizer: trailingToken,
    onPick: (reply, token) => {
      let out = resolveVars(reply.text, {
        name: getConversationName(),
        greeting: currentGreeting(),
      }).text;
      out = withSignature(out, state && state.settings.signature);
      if (token) replaceTrailingToken(token, out);
      else insertText(out);
      bumpReplyUse(reply.id);
    },
  });

  applyTheme();

  // ----- Reagir a mudancas de estado (outras abas / painel) -----
  onStateChange((next) => {
    state = next;
    if (panel) panel.refresh(state);
    applyTheme();
    applyPageStyles(state.settings);
  });

  // ----- Seguir tema do WhatsApp quando em "auto" -----
  stopThemeWatch = watchEnvTheme(() => applyTheme());

  // ----- Watchdog: se a thread congelar (>6s entre batidas de 2s),
  // algo entrou em laco -> desliga a extensao para nao derrubar a aba. -----
  let lastBeat = Date.now();
  let frozenStrikes = 0;
  heartbeatInterval = setInterval(() => {
    const gap = Date.now() - lastBeat;
    lastBeat = Date.now();
    if (gap > 6000) {
      frozenStrikes++;
      console.error("[TGAW] thread travou por ~" + gap + "ms (strike " + frozenStrikes + "/2).");
      if (frozenStrikes >= 2) teardown("travamento repetido detectado pelo watchdog");
    } else if (frozenStrikes > 0 && gap < 3000) {
      frozenStrikes = 0;
    }
  }, 2000);

  // ----- Atalho de teclado (via service worker) e clique no icone -----
  chrome.runtime.onMessage.addListener((msg) => {
    if (msg && msg.type === "TGAW_TOGGLE_PANEL" && panel) panel.toggle();
  });

  // ----- Lembretes: checagem leve dos vencidos (sem permissao extra) -----
  const checkReminders = async () => {
    try {
      if (torndown || !state || !panel) return;
      const due = remindersDue(state.reminders, Date.now());
      if (!due.length) return;
      state = await setState({
        ...state,
        reminders: remindersMarkNotified(state.reminders, due.map((r) => r.id)),
      });
      if (panel) panel.showReminderBanner(due[0], due.length);
    } catch {
      /* fail safe */
    }
  };
  reminderTimer = setTimeout(checkReminders, 5000);
  reminderInterval = setInterval(checkReminders, 45000);

  log("extensao inicializada");
}

safeAsync(init, "init").catch((e) => error("init falhou de forma inesperada", e));
