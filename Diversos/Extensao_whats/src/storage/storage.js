/*
 * storage.js — unica porta de acesso ao chrome.storage.local.
 * ------------------------------------------------------------------
 * Todo o estado da extensao vive sob UMA chave. Sempre normalizado
 * antes de ler ou gravar, entao dados corrompidos/parciais nunca
 * quebram a UI.
 *
 * Privacidade: nada sai do navegador. Nenhum servidor externo.
 */
import { uid } from "../core/util.js";
import { seedTagDefs, PALETTE } from "../features/tags.js";

const KEY = "tgaw_state_v1";
const SCHEMA_VERSION = 1;

export const DEFAULT_SETTINGS = {
  theme: "auto",                 // "auto" | "light" | "dark"
  buttonPosition: "sidebar",      // "sidebar" | "bottom-right" | "top-right" | "right-center"
  buttonCustomPos: null,          // {x,y} se o usuario arrastou o botao; senao null
  panelSize: null,                // {w,h} se o usuario redimensionou o painel; senao null
  suggestionsEnabled: true,       // sugerir resposta ao digitar "/atalho"
  lastTab: "replies",             // aba lembrada do painel
  newChatDdi: "55",               // DDI padrao para "nova conversa por numero"
  privacy: {                      // "modo privacidade de tela" (so CSS, so a sua tela)
    enabled: false,
    blurNames: true,
    blurPhotos: true,
    blurPreviews: true,
    blurConversation: false,
    blurComposer: false,
  },
  signature: { enabled: false, text: "" }, // assinatura ao usar uma resposta
  replySort: "manual",            // "manual" | "used" | "alpha"
  compact: false,                 // painel em modo compacto
  chatBg: { mode: "none", color: "#0b141a", opacity: 100, imageUrl: "" }, // fundo da conversa
};

const REPLY_SORTS = ["manual", "used", "alpha"];
const BG_MODES = ["none", "color", "image"];

const BUTTON_POSITIONS = ["sidebar", "bottom-right", "top-right", "right-center"];

function seedReplies() {
  return [
    { id: uid(), shortcut: "/ola", title: "Saudação com nome", category: "Atendimento", text: "{{saudacao}}, {{nome}}! Tudo bem? Como posso ajudar?" },
    { id: uid(), shortcut: "/bomdia", title: "Bom dia", category: "Atendimento", text: "Olá! Bom dia, tudo bem? Como posso ajudar?" },
    { id: uid(), shortcut: "/boatarde", title: "Boa tarde", category: "Atendimento", text: "Olá! Boa tarde, tudo bem? Como posso ajudar?" },
    { id: uid(), shortcut: "/horario", title: "Horário de atendimento", category: "Atendimento", text: "Nosso horário de atendimento é das 07h30 às 18h." },
    { id: uid(), shortcut: "/obg", title: "Agradecimento", category: "Atendimento", text: "Obrigado pelo contato! Posso ajudar em algo mais?" },
    { id: uid(), shortcut: "/aguarde", title: "Pedir para aguardar", category: "Suporte", text: "Só um momento, por favor. Já verifico e retorno." },
    { id: uid(), shortcut: "/print", title: "Solicitar print", category: "Suporte", text: "Você consegue me enviar um print da tela onde aparece o erro?" },
    { id: uid(), shortcut: "/boleto", title: "Segunda via de boleto", category: "Financeiro", text: "Vou gerar a segunda via do seu boleto e te envio aqui em instantes." },
  ];
}

export const DEFAULT_STATE = {
  version: SCHEMA_VERSION,
  settings: { ...DEFAULT_SETTINGS },
  quickReplies: seedReplies(),
  notes: {}, // { "<conversationKey>": { text, updatedAt } }
  favorites: [], // [{ key, name, addedAt }]
  tagDefs: seedTagDefs(), // [{ id, label, color }]
  convTags: {}, // { "<conversationKey>": [tagId] }
  reminders: [], // [{ id, text, dueAt, convKey, convName, done, notified, createdAt }]
};

const MAX_NOTES = 500;
const MAX_NOTE_LEN = 5000;
const MAX_FAVORITES = 200;
const MAX_TAG_DEFS = 40;
const MAX_TAGS_PER_CONV = 20;
const MAX_REMINDERS = 200;

function isValidReply(r) {
  return (
    r && typeof r === "object" &&
    typeof r.id === "string" &&
    typeof r.text === "string" && r.text.length > 0
  );
}

function normalizeReply(r) {
  return {
    id: typeof r.id === "string" ? r.id : uid(),
    shortcut: typeof r.shortcut === "string" ? r.shortcut.trim() : "",
    title: typeof r.title === "string" ? r.title.trim() : "",
    text: String(r.text),
    category: typeof r.category === "string" ? r.category.trim().slice(0, 40) : "",
    uses: Number.isFinite(r.uses) && r.uses > 0 ? Math.floor(r.uses) : 0,
  };
}

function normalizeReminders(raw) {
  if (!Array.isArray(raw)) return [];
  const out = [];
  for (const r of raw) {
    if (!r || typeof r !== "object") continue;
    const text = typeof r.text === "string" ? r.text.slice(0, 300) : "";
    const dueAt = Number(r.dueAt);
    if (!text.trim() || !Number.isFinite(dueAt)) continue;
    out.push({
      id: typeof r.id === "string" && r.id ? r.id : uid(),
      text,
      dueAt,
      convKey: typeof r.convKey === "string" ? r.convKey.slice(0, 300) : "",
      convName: typeof r.convName === "string" ? r.convName.slice(0, 120) : "",
      done: r.done === true,
      notified: r.notified === true,
      createdAt: Number.isFinite(r.createdAt) ? r.createdAt : Date.now(),
    });
    if (out.length >= MAX_REMINDERS) break;
  }
  return out;
}

function normalizeTagDefs(raw) {
  if (!Array.isArray(raw)) return seedTagDefs();
  const seen = new Set();
  const out = [];
  for (const d of raw) {
    if (!d || typeof d !== "object") continue;
    const id = typeof d.id === "string" && d.id ? d.id : uid();
    const label = typeof d.label === "string" ? d.label.trim().slice(0, 24) : "";
    if (!label || seen.has(id)) continue;
    seen.add(id);
    const color = typeof d.color === "string" && PALETTE.includes(d.color) ? d.color : PALETTE[0];
    out.push({ id, label, color });
    if (out.length >= MAX_TAG_DEFS) break;
  }
  return out.length ? out : seedTagDefs();
}

function normalizeConvTags(raw, defs) {
  if (!raw || typeof raw !== "object") return {};
  const valid = new Set(defs.map((d) => d.id));
  const out = {};
  for (const [k, ids] of Object.entries(raw)) {
    if (typeof k !== "string" || !k || k.length > 300 || !Array.isArray(ids)) continue;
    const kept = [];
    for (const id of ids) {
      if (typeof id === "string" && valid.has(id) && !kept.includes(id)) kept.push(id);
      if (kept.length >= MAX_TAGS_PER_CONV) break;
    }
    if (kept.length) out[k] = kept;
  }
  return out;
}

function normalizeFavorites(raw) {
  if (!Array.isArray(raw)) return [];
  const seen = new Set();
  const out = [];
  for (const f of raw) {
    if (!f || typeof f !== "object") continue;
    const key = typeof f.key === "string" ? f.key.slice(0, 300) : "";
    if (!key || seen.has(key)) continue;
    seen.add(key);
    out.push({
      key,
      name: typeof f.name === "string" ? f.name.slice(0, 200) : "",
      addedAt: Number.isFinite(f.addedAt) ? f.addedAt : Date.now(),
    });
    if (out.length >= MAX_FAVORITES) break;
  }
  return out;
}

function normalizeNotes(raw) {
  if (!raw || typeof raw !== "object") return {};
  const entries = [];
  for (const [k, v] of Object.entries(raw)) {
    if (typeof k !== "string" || k.length === 0 || k.length > 300) continue;
    if (!v || typeof v !== "object" || typeof v.text !== "string") continue;
    const text = v.text.slice(0, MAX_NOTE_LEN);
    if (!text.trim()) continue;
    const updatedAt = Number.isFinite(v.updatedAt) ? v.updatedAt : Date.now();
    entries.push([k, { text, updatedAt }]);
  }
  // mantem apenas as mais recentes se passar do teto
  if (entries.length > MAX_NOTES) {
    entries.sort((a, b) => b[1].updatedAt - a[1].updatedAt);
    entries.length = MAX_NOTES;
  }
  const out = {};
  for (const [k, v] of entries) out[k] = v;
  return out;
}

/** Garante um objeto de estado sempre completo e coerente. */
export function normalize(raw) {
  const s = raw && typeof raw === "object" ? raw : {};
  const settings = { ...DEFAULT_SETTINGS, ...(s.settings && typeof s.settings === "object" ? s.settings : {}) };

  // valida enums
  if (!["auto", "light", "dark"].includes(settings.theme)) settings.theme = "auto";
  if (!BUTTON_POSITIONS.includes(settings.buttonPosition)) {
    settings.buttonPosition = "sidebar";
  }

  const bcp = settings.buttonCustomPos;
  settings.buttonCustomPos =
    bcp && typeof bcp === "object" && Number.isFinite(bcp.x) && Number.isFinite(bcp.y)
      ? { x: Math.round(bcp.x), y: Math.round(bcp.y) }
      : null;

  const ps = settings.panelSize;
  const clampSz = (v, lo, hi) => Math.round(Math.min(hi, Math.max(lo, v)));
  settings.panelSize =
    ps && typeof ps === "object" && Number.isFinite(ps.w) && Number.isFinite(ps.h)
      ? { w: clampSz(ps.w, 280, 4000), h: clampSz(ps.h, 220, 4000) }
      : null;

  settings.suggestionsEnabled = settings.suggestionsEnabled !== false;
  if (!["replies", "notes", "favorites", "reminders", "tools", "settings"].includes(settings.lastTab)) {
    settings.lastTab = "replies";
  }
  settings.newChatDdi = String(settings.newChatDdi || "").replace(/\D+/g, "").slice(0, 4) || "55";

  const pv = settings.privacy && typeof settings.privacy === "object" ? settings.privacy : {};
  settings.privacy = {
    enabled: pv.enabled === true,
    blurNames: pv.blurNames !== false,
    blurPhotos: pv.blurPhotos !== false,
    blurPreviews: pv.blurPreviews !== false,
    blurConversation: pv.blurConversation === true,
    blurComposer: pv.blurComposer === true,
  };

  const sig = settings.signature && typeof settings.signature === "object" ? settings.signature : {};
  settings.signature = {
    enabled: sig.enabled === true,
    text: typeof sig.text === "string" ? sig.text.slice(0, 300) : "",
  };

  if (!REPLY_SORTS.includes(settings.replySort)) settings.replySort = "manual";
  settings.compact = settings.compact === true;

  const bg = settings.chatBg && typeof settings.chatBg === "object" ? settings.chatBg : {};
  const hex = typeof bg.color === "string" && /^#[0-9a-fA-F]{3,8}$/.test(bg.color) ? bg.color : "#0b141a";
  const op = Number.isFinite(bg.opacity) ? Math.min(100, Math.max(0, Math.round(bg.opacity))) : 100;
  const url = typeof bg.imageUrl === "string" && /^https:\/\//.test(bg.imageUrl) ? bg.imageUrl.slice(0, 1000) : "";
  settings.chatBg = {
    mode: BG_MODES.includes(bg.mode) ? bg.mode : "none",
    color: hex,
    opacity: op,
    imageUrl: url,
  };

  const list = Array.isArray(s.quickReplies) ? s.quickReplies.filter(isValidReply).map(normalizeReply) : null;

  const tagDefs = normalizeTagDefs(s.tagDefs);

  return {
    version: SCHEMA_VERSION,
    settings,
    quickReplies: list && list.length ? list : seedReplies(),
    notes: normalizeNotes(s.notes),
    favorites: normalizeFavorites(s.favorites),
    tagDefs,
    convTags: normalizeConvTags(s.convTags, tagDefs),
    reminders: normalizeReminders(s.reminders),
  };
}

/** Le o estado. Se ainda nao existe, grava o padrao (seed) e retorna. */
export async function getState() {
  const obj = await chrome.storage.local.get(KEY);
  if (!obj || !obj[KEY]) {
    const seeded = normalize(DEFAULT_STATE);
    await chrome.storage.local.set({ [KEY]: seeded });
    return seeded;
  }
  return normalize(obj[KEY]);
}

/** Grava o estado inteiro (normalizado). Retorna o estado efetivamente salvo. */
export async function setState(next) {
  const norm = normalize(next);
  await chrome.storage.local.set({ [KEY]: norm });
  return norm;
}

/** Atalho para atualizar apenas settings. */
export async function patchSettings(patch) {
  const st = await getState();
  return setState({ ...st, settings: { ...st.settings, ...patch } });
}

/**
 * Observa mudancas no estado (feitas por qualquer aba/contexto).
 * Retorna funcao para cancelar.
 */
export function onStateChange(cb) {
  const handler = (changes, area) => {
    if (area === "local" && changes[KEY]) {
      cb(normalize(changes[KEY].newValue));
    }
  };
  chrome.storage.onChanged.addListener(handler);
  return () => chrome.storage.onChanged.removeListener(handler);
}

export { KEY as STORAGE_KEY, SCHEMA_VERSION };
