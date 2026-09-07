/*
 * panel.js — botao flutuante + painel, tudo dentro de um Shadow DOM.
 * ------------------------------------------------------------------
 * API:
 *   const panel = mountPanel({ getState, applyState, insertText });
 *   panel.toggle() / open() / close()
 *   panel.refresh(state)   -> re-renderiza com novo estado
 *   panel.setTheme("light"|"dark")
 *   panel.destroy()
 *
 * Nada aqui envia mensagem. "Usar" apenas preenche o composer.
 */
import { PANEL_CSS } from "./styles.js";
import { el, clear, boltIcon, waMarkupNodes } from "./dom.js";
import {
  findByQuery,
  validateReply,
  NO_CATEGORY,
  categoriesOf,
  filterByCategory,
  groupByCategory,
  sortReplies,
} from "../features/quick-replies.js";
import { withSignature } from "../features/signature.js";
import { computeStats, topReply } from "../features/stats.js";
import { getNoteText, hasNote, getNoteMeta, withNote, relativeTime } from "../features/notes.js";
import * as TT from "../features/text-tools.js";
import * as FMT from "../features/formatters.js";
import * as CALC from "../features/calc.js";
import { currentGreeting } from "../features/greeting.js";
import { resolveVars, VARS } from "../features/variables.js";
import { isFavorite, toggleFavorite, removeFavorite, sortFavorites } from "../features/favorites.js";
import { toE164, waSendUrl, waMeUrl } from "../features/wa-contact.js";
import { PRIVACY_ITEMS } from "../features/privacy.js";
import { tagsOfConv, hasTags, toggleTag, addTagDef, removeTagDef } from "../features/tags.js";
import {
  addReminder,
  removeReminder,
  setDone,
  snooze,
  sortReminders,
  overdueCount,
  presets as reminderPresets,
  formatDue,
} from "../features/reminders.js";
import { uid, debounce } from "../core/util.js";
import { DEFAULT_STATE } from "../storage/storage.js";
import {
  getNavRail,
  getNavLastItem,
  getConversationKey,
  getConversationName,
  getMain,
  onConversationChange,
  searchChats,
} from "../whatsapp/adapter.js";
import { log } from "../core/debug.js";

const HOST_ID = "tgaw-panel-host";

export function mountPanel({
  getState,
  applyState,
  insertText,
  getComposerText,
  setComposerText,
}) {
  // Evita hosts duplicados.
  const existing = document.getElementById(HOST_ID);
  if (existing) existing.remove();

  const host = el("div", { id: HOST_ID });
  host.style.cssText = "position:fixed;top:0;left:0;width:0;height:0;z-index:2147483000;";
  (document.documentElement || document.body).appendChild(host);
  const shadow = host.attachShadow({ mode: "open" });

  const style = el("style", { text: PANEL_CSS });
  const root = el("div", { class: "tgaw-root", "data-theme": "light" });
  shadow.append(style, root);

  // ----- estado local de UI -----
  let currentTab = safeState().settings.lastTab || "replies";
  let searchQuery = "";
  let replyCategory = "all";  // filtro de categoria da aba Respostas (nao persiste)
  let editingId = null;      // id da resposta em edicao, ou null
  let showForm = false;      // form "nova resposta" aberto?
  let flashTimer = 0;
  let toolsText = "";        // area de trabalho da aba Texto (efemera)
  let toolsUndo = null;      // um nivel de "desfazer" na aba Texto

  function safeState() {
    try {
      return getState() || DEFAULT_STATE;
    } catch {
      return DEFAULT_STATE;
    }
  }

  // ----- FAB -----
  let suppressNextClick = false;
  const fab = el(
    "button",
    {
      class: "tgaw-fab",
      type: "button",
      "aria-label": "Abrir ferramentas (Alt+R) — arraste para mover",
      title: "TGameAjuda Edita Whats (Alt+R) · arraste para mover",
      onClick: (e) => {
        if (suppressNextClick) {
          suppressNextClick = false;
          e.preventDefault();
          e.stopPropagation();
          return;
        }
        toggle();
      },
    },
    [boltIcon()]
  );
  fab.addEventListener("pointerdown", onFabPointerDown);

  // ----- Painel -----
  const titleEl = el("div", { class: "tgaw-title", text: "Ferramentas", id: "tgaw-panel-title" });
  const closeBtn = el("button", {
    class: "tgaw-iconbtn",
    type: "button",
    "aria-label": "Fechar",
    text: "✕",
    onClick: () => close(),
  });
  const header = el("div", { class: "tgaw-panel-header" }, [titleEl, closeBtn]);

  const tabsEl = el("div", { class: "tgaw-tabs", role: "tablist" });
  const bodyEl = el("div", { class: "tgaw-body", role: "tabpanel", "aria-labelledby": "tgaw-panel-title" });
  const statusEl = el("div", { class: "tgaw-footnote", "aria-live": "polite" });

  const panel = el(
    "div",
    {
      class: "tgaw-panel",
      role: "dialog",
      "aria-label": "Ferramentas do TGameAjuda Edita Whats",
      hidden: true,
    },
    [header, tabsEl, bodyEl, statusEl]
  );

  // Aviso de lembrete (independente do painel).
  let reminderBannerTimer = 0;
  const reminderBanner = el("div", { class: "tgaw-rembanner", role: "alert", hidden: true });

  root.append(fab, panel, reminderBanner);

  function hideReminderBanner() {
    clearTimeout(reminderBannerTimer);
    reminderBanner.hidden = true;
    clear(reminderBanner);
  }

  function showReminderBanner(rem, count) {
    if (!rem) return;
    clear(reminderBanner);
    clearTimeout(reminderBannerTimer);
    const extra = count > 1 ? "  (+" + (count - 1) + ")" : "";
    reminderBanner.append(
      el("div", { class: "tgaw-rembanner-title", text: "⏰ Lembrete" + extra }),
      el("div", {
        class: "tgaw-rembanner-body",
        text: rem.text + (rem.convName ? "  ·  " + rem.convName : ""),
      }),
      el("div", { class: "tgaw-row" }, [
        el("button", { class: "tgaw-btn tgaw-btn-primary", type: "button", text: "OK", onClick: hideReminderBanner }),
        el("button", {
          class: "tgaw-btn",
          type: "button",
          text: "+1 h",
          onClick: async () => {
            try {
              const st = safeState();
              await applyState({ ...st, reminders: snooze(st.reminders, rem.id, 3600e3) });
            } catch {}
            hideReminderBanner();
          },
        }),
        el("button", {
          class: "tgaw-btn",
          type: "button",
          text: "Ver",
          onClick: () => {
            currentTab = "reminders";
            hideReminderBanner();
            if (open_) {
              renderTabs();
              renderBody();
            } else {
              open();
            }
          },
        }),
      ])
    );
    reminderBanner.hidden = false;
    reminderBannerTimer = setTimeout(hideReminderBanner, 25000);
  }

  // ----- helpers -----
  function flash(msg) {
    statusEl.textContent = msg;
    clearTimeout(flashTimer);
    flashTimer = setTimeout(() => {
      statusEl.textContent = "";
    }, 2600);
  }

  function applyThemeClass(theme) {
    root.setAttribute("data-theme", theme === "dark" ? "dark" : "light");
  }

  // ----- posicionamento do botao -----
  let lastFabPos = null;
  let sidebarAnchored = false;
  let railEl = null;              // barra do WhatsApp em cache
  let railWaitTimer = 0;
  let railWaitTries = 0;
  let sidebarRetryTimers = [];
  let positioningFab = false;     // trava anti-reentrancia
  let fabPosThrottleAt = 0;

  function safeGetRail() {
    // Reaproveita a barra em cache enquanto ela estiver viva no DOM.
    if (railEl && railEl.isConnected) return railEl;
    try {
      railEl = getNavRail() || null;
    } catch {
      railEl = null;
    }
    return railEl;
  }

  function stopWaitForRail() {
    clearTimeout(railWaitTimer);
    railWaitTimer = 0;
    railWaitTries = 0;
  }

  // Espera a barra montar por checagem leve e LIMITADA (~15s). Nada de
  // MutationObserver no #app — isso storma no WhatsApp e pode travar a aba.
  function waitForRail() {
    if (railWaitTimer || safeGetRail()) return;
    const tick = () => {
      railWaitTimer = 0;
      if (safeGetRail()) {
        stopWaitForRail();
        positionFabSidebar();
        return;
      }
      if (++railWaitTries > 30) {
        stopWaitForRail();
        return;
      }
      railWaitTimer = setTimeout(tick, 500);
    };
    railWaitTimer = setTimeout(tick, 500);
  }
  const POS_CLASSES = [
    "tgaw-pos-sidebar",
    "tgaw-pos-bottom-right",
    "tgaw-pos-top-right",
    "tgaw-pos-right-center",
  ];
  const ALL_POS_CLASSES = POS_CLASSES.concat("tgaw-pos-custom");

  // ----- posicao personalizada (arrastada pelo usuario) -----
  let customAnchored = false;

  function fabSize() {
    return fab.offsetWidth || 36;
  }

  function clampToViewport(x, y) {
    const s = fabSize();
    return {
      x: Math.max(2, Math.min(x, window.innerWidth - s - 2)),
      y: Math.max(2, Math.min(y, window.innerHeight - s - 2)),
    };
  }

  function applyCustomPos(pos) {
    const { x, y } = clampToViewport(pos.x, pos.y);
    fab.style.left = x + "px";
    fab.style.top = y + "px";
    fab.style.right = "auto";
    fab.style.bottom = "auto";
    fab.style.transform = "";
    if (open_) positionPanel();
  }

  function onCustomResize() {
    const pos = safeState().settings.buttonCustomPos;
    if (pos) applyCustomPos(pos);
  }

  function startCustomAnchor() {
    if (!customAnchored) {
      window.addEventListener("resize", onCustomResize);
      customAnchored = true;
    }
  }

  function stopCustomAnchor() {
    if (customAnchored) {
      window.removeEventListener("resize", onCustomResize);
      customAnchored = false;
    }
  }

  // Decide onde o botao fica: posicao arrastada (se houver) ou um dos modos.
  function placeFab(settings) {
    const custom = settings && settings.buttonCustomPos;
    if (custom && Number.isFinite(custom.x) && Number.isFinite(custom.y)) {
      stopSidebarAnchor();
      startCustomAnchor();
      lastFabPos = "custom";
      for (const c of ALL_POS_CLASSES) fab.classList.remove(c);
      fab.classList.add("tgaw-pos-custom");
      applyCustomPos(custom);
      return;
    }
    stopCustomAnchor();
    fab.classList.remove("tgaw-pos-custom");
    applyFabPosition(settings ? settings.buttonPosition : "sidebar");
  }

  // ----- arrastar o botao -----
  let dragState = null;

  function onFabPointerDown(e) {
    if (e.button != null && e.button !== 0) return;
    const r = fab.getBoundingClientRect();
    dragState = {
      startX: e.clientX,
      startY: e.clientY,
      offX: e.clientX - r.left,
      offY: e.clientY - r.top,
      pointerId: e.pointerId,
      moved: false,
    };
    try {
      fab.setPointerCapture(e.pointerId);
    } catch {}
    window.addEventListener("pointermove", onFabPointerMove);
    window.addEventListener("pointerup", onFabPointerUp);
    window.addEventListener("pointercancel", onFabPointerUp);
  }

  function onFabPointerMove(e) {
    if (!dragState) return;
    const dx = e.clientX - dragState.startX;
    const dy = e.clientY - dragState.startY;
    if (!dragState.moved && Math.hypot(dx, dy) < 5) return;

    if (!dragState.moved) {
      dragState.moved = true;
      fab.classList.add("tgaw-dragging");
      stopSidebarAnchor();
      stopCustomAnchor();
      for (const c of ALL_POS_CLASSES) fab.classList.remove(c);
      fab.classList.add("tgaw-pos-custom");
      lastFabPos = "custom";
      try {
        document.documentElement.style.userSelect = "none";
      } catch {}
    }
    e.preventDefault();

    const p = clampToViewport(e.clientX - dragState.offX, e.clientY - dragState.offY);
    fab.style.left = p.x + "px";
    fab.style.top = p.y + "px";
    fab.style.right = "auto";
    fab.style.bottom = "auto";
    fab.style.transform = "";
    if (open_) positionPanel();
  }

  function onFabPointerUp() {
    window.removeEventListener("pointermove", onFabPointerMove);
    window.removeEventListener("pointerup", onFabPointerUp);
    window.removeEventListener("pointercancel", onFabPointerUp);
    const moved = dragState && dragState.moved;
    if (dragState) {
      try {
        fab.releasePointerCapture(dragState.pointerId);
      } catch {}
    }
    dragState = null;
    fab.classList.remove("tgaw-dragging");
    try {
      document.documentElement.style.userSelect = "";
    } catch {}
    if (moved) {
      suppressNextClick = true;
      setTimeout(() => {
        suppressNextClick = false;
      }, 350);
      const r = fab.getBoundingClientRect();
      persistCustomPos({ x: Math.round(r.left), y: Math.round(r.top) });
      startCustomAnchor();
    }
  }

  async function persistCustomPos(pos) {
    const next = safeState();
    next.settings = { ...next.settings, buttonCustomPos: pos };
    await persist(next);
  }

  function applyFabPosition(pos) {
    const p = POS_CLASSES.includes("tgaw-pos-" + pos) ? pos : "sidebar";
    if (p === lastFabPos) {
      if (p === "sidebar") positionFabSidebar();
      return;
    }
    lastFabPos = p;
    for (const c of POS_CLASSES) fab.classList.remove(c);
    fab.classList.add("tgaw-pos-" + p);

    if (p === "sidebar") {
      startSidebarAnchor();
    } else {
      stopSidebarAnchor();
      fab.style.left = "";
      fab.style.top = "";
      fab.style.right = "";
      fab.style.bottom = "";
      fab.style.transform = "";
    }
  }

  function clearSidebarRetries() {
    sidebarRetryTimers.forEach(clearTimeout);
    sidebarRetryTimers = [];
  }

  // Coloca o botao centralizado na coluna de icones do WhatsApp,
  // logo abaixo do ultimo item (Meta AI / Comunidades), sem sobrepor.
  // Anti-reentrancia + throttle: por mais que seja chamada em rajada,
  // nunca vira laco nem starva a aba.
  function positionFabSidebar() {
    if (positioningFab) return;
    const now = Date.now();
    if (now - fabPosThrottleAt < 120) return;
    fabPosThrottleAt = now;
    positioningFab = true;
    try {
      const rail = safeGetRail();
      if (!rail) {
        fab.style.left = "auto";
        fab.style.right = "12px";
        fab.style.top = "50%";
        fab.style.bottom = "auto";
        fab.style.transform = "translateY(-50%)";
        waitForRail();
        return;
      }

      stopWaitForRail();
      fab.style.transform = "";
      const size = fab.offsetWidth || 40;
      const rr = rail.getBoundingClientRect();

      let ar = null;
      try {
        const anchor = getNavLastItem(rail);
        if (anchor) ar = anchor.getBoundingClientRect();
      } catch {
        ar = null;
      }

      let top = ar ? ar.bottom + 6 : rr.top + 6;
      top = Math.max(8, Math.min(top, window.innerHeight - size - 56));
      const centerX = ar ? ar.left + ar.width / 2 : rr.left + rr.width / 2;
      const left = Math.max(4, Math.round(centerX - size / 2));

      fab.style.left = left + "px";
      fab.style.top = Math.round(top) + "px";
      fab.style.right = "auto";
      fab.style.bottom = "auto";

      if (open_) positionPanel();
    } finally {
      positioningFab = false;
    }
  }

  function startSidebarAnchor() {
    clearSidebarRetries();
    positionFabSidebar();
    // A barra pode montar depois do nosso init: tenta realinhar algumas vezes.
    [250, 800, 1800, 3500].forEach((ms) => {
      sidebarRetryTimers.push(setTimeout(positionFabSidebar, ms));
    });
    if (!sidebarAnchored) {
      window.addEventListener("resize", positionFabSidebar);
      sidebarAnchored = true;
    }
  }

  function stopSidebarAnchor() {
    clearSidebarRetries();
    stopWaitForRail();
    if (sidebarAnchored) {
      window.removeEventListener("resize", positionFabSidebar);
      sidebarAnchored = false;
    }
    railEl = null;
  }

  function positionPanel() {
    // Ancora o painel ao botao usando top/left (para o "resize" crescer natural).
    const r = fab.getBoundingClientRect();
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const pad = 8;
    const w = panel.offsetWidth || 328;
    const h = panel.offsetHeight || 400;

    let left = r.left < vw / 2 ? r.right + 8 : r.left - w - 8;
    let top = r.top < vh / 2 ? r.top : r.bottom - h;

    left = Math.max(pad, Math.min(left, vw - w - pad));
    top = Math.max(pad, Math.min(top, vh - h - pad));

    panel.style.right = "auto";
    panel.style.bottom = "auto";
    panel.style.left = Math.round(left) + "px";
    panel.style.top = Math.round(top) + "px";
  }

  // Mantem o painel dentro da tela apos um redimensionamento manual.
  function clampPanelIntoView() {
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const pad = 8;
    const cur = panel.getBoundingClientRect();
    const left = Math.max(pad, Math.min(cur.left, vw - cur.width - pad));
    const top = Math.max(pad, Math.min(cur.top, vh - cur.height - pad));
    panel.style.left = Math.round(left) + "px";
    panel.style.top = Math.round(top) + "px";
  }

  // ----- tamanho do painel (arrastavel pelo canto) -----
  let applyingPanelSize = false;
  let lastAppliedSize = null;

  function applyPanelSize(settings) {
    const ps = settings && settings.panelSize;
    applyingPanelSize = true;
    if (ps && Number.isFinite(ps.w) && Number.isFinite(ps.h)) {
      panel.style.width = ps.w + "px";
      panel.style.height = ps.h + "px";
    } else {
      panel.style.width = "";
      panel.style.height = "";
    }
    applyingPanelSize = false;
    if (open_) {
      lastAppliedSize = { w: panel.offsetWidth, h: panel.offsetHeight };
      positionPanel();
    }
  }

  // Salva o novo tamanho ao SOLTAR o mouse (fim do arraste do canto).
  // Sem ResizeObserver de proposito: nenhum observer pode virar laco.
  function onDocPointerUp() {
    if (applyingPanelSize || !open_) return;
    const w = panel.offsetWidth;
    const h = panel.offsetHeight;
    if (w >= window.innerWidth - 18 || h >= window.innerHeight - 18) return;
    if (lastAppliedSize && Math.abs(w - lastAppliedSize.w) < 3 && Math.abs(h - lastAppliedSize.h) < 3) {
      return;
    }
    const cur = safeState().settings.panelSize;
    if (cur && cur.w === w && cur.h === h) return;
    lastAppliedSize = { w, h };
    clampPanelIntoView();
    persistSettings({ panelSize: { w, h } });
  }

  // ----- render -----
  function renderTabs() {
    clear(tabsEl);
    const state = safeState();
    const convKey = safeConvKey();
    const defs = [
      { id: "replies", label: "Respostas" },
      {
        id: "notes",
        label: "Notas",
        dot: hasNote(state.notes, convKey) || hasTags(state.convTags, convKey),
      },
      { id: "favorites", label: "Favoritos", dot: isFavorite(state.favorites, convKey) },
      { id: "reminders", label: "Lembretes", badge: overdueCount(state.reminders) },
      { id: "tools", label: "Texto" },
      { id: "settings", label: "Config" },
    ];
    for (const d of defs) {
      const btn = el("button", {
        class: "tgaw-tab",
        type: "button",
        role: "tab",
        "aria-selected": String(currentTab === d.id),
        onClick: () => {
          if (currentTab === d.id) return;
          if (currentTab === "notes") flushNote();
          currentTab = d.id;
          editingId = null;
          showForm = false;
          renderTabs();
          renderBody();
          persistTab(d.id);
          updateChatWatch();
        },
      });
      btn.appendChild(document.createTextNode(d.label));
      if (d.dot) btn.appendChild(el("span", { class: "tgaw-tab-dot", "aria-label": "tem conteúdo" }));
      if (d.badge > 0) {
        btn.appendChild(el("span", { class: "tgaw-tab-badge", text: String(d.badge > 9 ? "9+" : d.badge) }));
      }
      tabsEl.appendChild(btn);
    }
  }

  function renderBody() {
    clear(bodyEl);
    bodyEl.scrollTop = 0;
    if (currentTab === "replies") renderReplies();
    else if (currentTab === "notes") renderNotes();
    else if (currentTab === "favorites") renderFavorites();
    else if (currentTab === "reminders") renderReminders();
    else if (currentTab === "tools") renderTools();
    else renderSettings();
  }

  // ---------- aba Respostas ----------
  function renderReplies() {
    const state = safeState();
    const replies = sortReplies(state.quickReplies, state.settings.replySort);

    // Ordenacao da lista.
    const sortSel = el("select", {
      class: "tgaw-field tgaw-sort-sel",
      "aria-label": "Ordenar respostas",
    });
    for (const [v, lbl] of [
      ["manual", "Ordem manual"],
      ["used", "Mais usadas primeiro"],
      ["alpha", "Alfabética"],
    ]) {
      const o = el("option", { value: v, text: lbl });
      if (state.settings.replySort === v) o.selected = true;
      sortSel.appendChild(o);
    }
    sortSel.addEventListener("change", () => persistSettings({ replySort: sortSel.value }));

    // Tira rapida: saudacao conforme o horario local.
    const greet = currentGreeting();
    const greetBtn = el("button", {
      class: "tgaw-btn tgaw-btn-primary tgaw-greet-btn",
      type: "button",
      text: greet,
      title: "Inserir “" + greet + "! ” no campo de mensagem",
      onClick: () => {
        if (tryInsert(greet + "! ")) {
          flash("Saudação inserida.");
          close();
        }
      },
    });
    bodyEl.appendChild(
      el("div", { class: "tgaw-greet-row" }, [
        el("span", { class: "tgaw-greet-label", text: "Saudação agora:" }),
        greetBtn,
        sortSel,
      ])
    );

    const search = el("input", {
      class: "tgaw-field tgaw-search",
      type: "search",
      placeholder: "Pesquisar resposta...",
      "aria-label": "Pesquisar resposta",
      value: searchQuery,
    });
    search.addEventListener("input", () => {
      searchQuery = search.value;
      renderList();
    });
    bodyEl.appendChild(search);

    // Filtro por categoria (chips). Nao aparece se so ha uma categoria.
    const catRow = el("div", { class: "tgaw-cat-row" });
    bodyEl.appendChild(catRow);

    function renderCatChips() {
      clear(catRow);
      const cats = categoriesOf(replies);
      // filtro apontando para categoria que nao existe mais -> volta pra "Todas"
      if (replyCategory !== "all" && !cats.includes(replyCategory)) replyCategory = "all";
      if (cats.length <= 1) {
        replyCategory = "all";
        catRow.hidden = true;
        return;
      }
      catRow.hidden = false;
      const chip = (label, value) =>
        el("button", {
          class: "tgaw-cat-chip",
          type: "button",
          text: label,
          "aria-pressed": String(replyCategory === value),
          onClick: () => {
            replyCategory = value;
            renderCatChips();
            renderList();
          },
        });
      catRow.appendChild(chip("Todas", "all"));
      for (const c of cats) catRow.appendChild(chip(c, c));
    }

    const listWrap = el("div", { class: "tgaw-list" });
    bodyEl.appendChild(listWrap);

    const addBtn = el("button", {
      class: "tgaw-btn tgaw-btn-link",
      type: "button",
      text: "＋  Nova resposta",
      onClick: () => {
        showForm = true;
        editingId = null;
        renderForm();
        formBox.scrollIntoView({ block: "nearest" });
      },
    });
    bodyEl.appendChild(addBtn);

    const formBox = el("div", { class: "tgaw-form", hidden: true });
    bodyEl.appendChild(formBox);

    function renderList() {
      clear(listWrap);

      // Busca ignora o filtro de categoria (procura em tudo), lista plana.
      if (searchQuery.trim()) {
        const items = findByQuery(replies, searchQuery);
        if (!items.length) {
          listWrap.appendChild(el("div", { class: "tgaw-empty", text: "Nenhuma resposta encontrada." }));
          return;
        }
        for (const r of items) listWrap.appendChild(renderItem(r, true));
        return;
      }

      if (!replies.length) {
        listWrap.appendChild(
          el("div", { class: "tgaw-empty", text: "Sem respostas ainda. Clique em “Nova resposta”." })
        );
        return;
      }

      if (replyCategory === "all") {
        // Agrupado por categoria, com cabecalho.
        const groups = groupByCategory(replies);
        for (const g of groups) {
          if (!g.items.length) continue;
          listWrap.appendChild(el("div", { class: "tgaw-group-head", text: g.category }));
          for (const r of g.items) listWrap.appendChild(renderItem(r, false));
        }
        return;
      }

      // Uma categoria selecionada: lista plana.
      const items = filterByCategory(replies, replyCategory);
      if (!items.length) {
        listWrap.appendChild(el("div", { class: "tgaw-empty", text: "Nenhuma resposta nesta categoria." }));
        return;
      }
      for (const r of items) listWrap.appendChild(renderItem(r, false));
    }

    function renderItem(r, showCat) {
      const cat = String(r.category || "").trim();
      const top = el("div", { class: "tgaw-item-top" }, [
        el("span", { class: "tgaw-item-title", text: r.title || r.shortcut || "(sem título)" }),
        r.shortcut ? el("span", { class: "tgaw-chip", text: r.shortcut }) : null,
        showCat ? el("span", { class: "tgaw-chip tgaw-chip-cat", text: cat || NO_CATEGORY }) : null,
      ]);
      const text = el("div", { class: "tgaw-item-text", text: r.text });

      const useBtn = el("button", {
        class: "tgaw-btn tgaw-btn-primary",
        type: "button",
        text: r.uses ? "Usar · " + r.uses : "Usar",
        title: r.uses ? "Usada " + r.uses + " vez(es)" : "",
        onClick: () => {
          const ok = tryInsert(applyVars(r.text));
          if (ok) {
            bumpUse(r.id);
            close();
          }
        },
      });
      const editBtn = el("button", {
        class: "tgaw-btn",
        type: "button",
        text: "Editar",
        onClick: () => {
          showForm = true;
          editingId = r.id;
          renderForm();
          formBox.scrollIntoView({ block: "nearest" });
        },
      });
      const dupBtn = el("button", {
        class: "tgaw-btn tgaw-btn-link",
        type: "button",
        text: "Duplicar",
        onClick: async () => {
          const next = safeState();
          const src = next.quickReplies.find((x) => x.id === r.id) || r;
          next.quickReplies = [
            { ...src, id: uid(), shortcut: "", title: (src.title || "") + " (cópia)", uses: 0 },
            ...next.quickReplies,
          ];
          await persist(next);
          flash("Resposta duplicada.");
        },
      });
      const delBtn = el("button", {
        class: "tgaw-btn tgaw-btn-link tgaw-btn-danger",
        type: "button",
        text: "Excluir",
        dataset: { armed: "0" },
      });
      delBtn.addEventListener("click", async () => {
        if (delBtn.dataset.armed !== "1") {
          delBtn.dataset.armed = "1";
          delBtn.textContent = "Confirmar?";
          setTimeout(() => {
            delBtn.dataset.armed = "0";
            delBtn.textContent = "Excluir";
          }, 3000);
          return;
        }
        const next = safeState();
        next.quickReplies = next.quickReplies.filter((x) => x.id !== r.id);
        await persist(next);
        flash("Resposta excluída.");
      });

      const actions = el("div", { class: "tgaw-item-actions" }, [useBtn, editBtn, dupBtn, delBtn]);
      return el("div", { class: "tgaw-item" }, [top, text, actions]);
    }

    function renderForm() {
      clear(formBox);
      formBox.hidden = !showForm;
      if (!showForm) return;

      const state2 = safeState();
      const editing = editingId ? state2.quickReplies.find((x) => x.id === editingId) : null;

      const titleInput = el("input", {
        class: "tgaw-field",
        type: "text",
        placeholder: "Título (ex.: Horário de atendimento)",
        "aria-label": "Título da resposta",
        value: editing ? editing.title : "",
      });
      const shortcutInput = el("input", {
        class: "tgaw-field",
        type: "text",
        placeholder: "Atalho opcional (ex.: /horario)",
        "aria-label": "Atalho da resposta",
        value: editing ? editing.shortcut : "",
      });

      const catListId = "tgaw-catlist-" + Math.random().toString(36).slice(2, 7);
      const catInput = el("input", {
        class: "tgaw-field",
        type: "text",
        placeholder: "Categoria (opcional — ex.: Suporte)",
        "aria-label": "Categoria da resposta",
        list: catListId,
        value: editing ? editing.category || "" : replyCategory !== "all" && replyCategory !== NO_CATEGORY ? replyCategory : "",
      });
      const catList = el("datalist", { id: catListId });
      for (const c of categoriesOf(state2.quickReplies)) {
        if (c !== NO_CATEGORY) catList.appendChild(el("option", { value: c }));
      }

      const textInput = el("textarea", {
        class: "tgaw-field",
        placeholder: "Texto da resposta...",
        "aria-label": "Texto da resposta",
        rows: "3",
      });
      textInput.value = editing ? editing.text : "";

      // Chips que inserem {{variavel}} na posicao do cursor.
      const varsRow = el("div", { class: "tgaw-var-row" }, [
        el("span", { class: "tgaw-hint", text: "Inserir variável:" }),
      ]);
      for (const v of VARS) {
        varsRow.appendChild(
          el("button", {
            class: "tgaw-btn tgaw-btn-link tgaw-var-chip",
            type: "button",
            text: "{{" + v + "}}",
            title: "Trocada pelo valor atual ao usar a resposta",
            onClick: () => insertAtCursor(textInput, "{{" + v + "}}"),
          })
        );
      }

      const errors = el("div", { class: "tgaw-form-errors", hidden: true });

      const saveBtn = el("button", {
        class: "tgaw-btn tgaw-btn-primary",
        type: "button",
        text: editing ? "Salvar" : "Adicionar",
        onClick: async () => {
          const draft = {
            title: titleInput.value.trim(),
            shortcut: shortcutInput.value.trim(),
            text: textInput.value.trim(),
            category: catInput.value.trim().slice(0, 40),
          };
          const errs = validateReply(draft);
          if (errs.length) {
            errors.textContent = errs.join(" ");
            errors.hidden = false;
            return;
          }
          const next = safeState();
          if (editing) {
            next.quickReplies = next.quickReplies.map((x) =>
              x.id === editing.id ? { ...x, ...draft } : x
            );
          } else {
            next.quickReplies = [{ id: uid(), ...draft }, ...next.quickReplies];
          }
          await persist(next);
          showForm = false;
          editingId = null;
          flash(editing ? "Resposta salva." : "Resposta adicionada.");
        },
      });
      const cancelBtn = el("button", {
        class: "tgaw-btn",
        type: "button",
        text: "Cancelar",
        onClick: () => {
          showForm = false;
          editingId = null;
          renderForm();
        },
      });

      formBox.append(
        titleInput,
        shortcutInput,
        catInput,
        catList,
        textInput,
        varsRow,
        errors,
        el("div", { class: "tgaw-row" }, [saveBtn, cancelBtn])
      );
    }

    renderCatChips();
    renderList();
    renderForm();
  }

  // ---------- aba Notas ----------
  let shownNoteKey = null;      // conversa atualmente carregada no textarea
  let noteDirty = false;        // ha alteracao nao salva?
  let noteMetaEl = null;        // linha "salvo ha ..."
  let noteTextarea = null;

  function safeConvKey() {
    try {
      return getConversationKey();
    } catch {
      return null;
    }
  }
  function safeConvName() {
    try {
      return getConversationName();
    } catch {
      return null;
    }
  }

  const scheduleNoteSave = debounce(() => flushNote(), 700);

  function flushNote() {
    scheduleNoteSave.cancel && scheduleNoteSave.cancel();
    if (!noteDirty || !noteTextarea || !shownNoteKey) {
      noteDirty = false;
      return;
    }
    noteDirty = false;
    const text = noteTextarea.value;
    const next = safeState();
    next.notes = withNote(next.notes, shownNoteKey, text);
    persist(next);
    refreshNoteMeta(next.notes, shownNoteKey, text);
  }

  function refreshNoteMeta(notes, key, currentText) {
    if (!noteMetaEl) return;
    const meta = getNoteMeta(notes, key);
    if (currentText && currentText.trim() && meta) {
      noteMetaEl.textContent = "Salvo automaticamente · " + relativeTime(meta.updatedAt);
    } else if (currentText && currentText.trim()) {
      noteMetaEl.textContent = "Salvo automaticamente.";
    } else {
      noteMetaEl.textContent = "Nota vazia (não é salva).";
    }
  }

  function renderNotes() {
    const state = safeState();
    const key = safeConvKey();
    const name = safeConvName();
    shownNoteKey = key;
    noteDirty = false;

    const convLine = el("div", { class: "tgaw-note-conv" }, [
      key ? "Nota de: " : "Nenhuma conversa aberta",
      key ? el("span", { class: "tgaw-strong", text: name || "conversa atual" }) : null,
    ]);

    noteTextarea = el("textarea", {
      class: "tgaw-field tgaw-note",
      placeholder: key
        ? "Anote algo sobre esta conversa (ex.: cliente aguardando retorno sobre a atualização)…"
        : "Abra uma conversa para anotar.",
      "aria-label": "Nota da conversa",
      rows: "5",
    });
    noteTextarea.value = key ? getNoteText(state.notes, key) : "";
    noteTextarea.disabled = !key;

    noteTextarea.addEventListener("input", () => {
      noteDirty = true;
      scheduleNoteSave();
    });
    noteTextarea.addEventListener("blur", () => flushNote());

    noteMetaEl = el("div", { class: "tgaw-note-meta" });
    refreshNoteMeta(state.notes, key, noteTextarea.value);

    // ----- Tags da conversa -----
    const tagBlock = el("div", { class: "tgaw-tag-block" });
    let tagEdit = false;
    let newTagOpen = false;

    function renderTagRow() {
      clear(tagBlock);
      const st = safeState();
      const active = new Set(tagsOfConv(st.convTags, key));
      const row = el("div", { class: "tgaw-tag-row" });

      for (const d of st.tagDefs) {
        const on = active.has(d.id);
        const chip = el("button", {
          class: "tgaw-tag",
          type: "button",
          "data-active": String(on),
          "aria-pressed": String(on),
          style: on
            ? "background:" + d.color + ";border-color:" + d.color
            : "color:" + d.color + ";border-color:" + d.color,
          title: !key ? "Abra uma conversa" : on ? "Remover tag" : "Adicionar tag",
        });
        chip.appendChild(document.createTextNode(d.label));

        if (tagEdit) {
          const x = el("span", { class: "tgaw-tag-x", text: "×", title: "Excluir tag do catálogo" });
          x.addEventListener("click", async (e) => {
            e.stopPropagation();
            const s2 = safeState();
            const res = removeTagDef(s2.tagDefs, s2.convTags, d.id);
            s2.tagDefs = res.defs;
            s2.convTags = res.convTags;
            await persist(s2);
            renderTagRow();
            renderTabs();
          });
          chip.appendChild(x);
        } else {
          chip.addEventListener("click", async () => {
            if (!key) {
              flash("Abra uma conversa para marcar tags.");
              return;
            }
            const s2 = safeState();
            s2.convTags = toggleTag(s2.convTags, key, d.id);
            await persist(s2);
            renderTagRow();
            renderTabs();
          });
        }
        row.appendChild(chip);
      }

      const addChip = el("button", {
        class: "tgaw-tag tgaw-tag-add",
        type: "button",
        text: newTagOpen ? "×" : "＋",
        title: "Nova tag",
        onClick: () => {
          newTagOpen = !newTagOpen;
          renderTagRow();
        },
      });
      row.appendChild(addChip);
      tagBlock.appendChild(row);

      if (st.tagDefs.length) {
        tagBlock.appendChild(
          el("button", {
            class: "tgaw-btn tgaw-btn-link tgaw-tag-editlink",
            type: "button",
            text: tagEdit ? "concluir edição" : "editar tags",
            onClick: () => {
              tagEdit = !tagEdit;
              newTagOpen = false;
              renderTagRow();
            },
          })
        );
      }

      if (newTagOpen) {
        const inp = el("input", {
          class: "tgaw-field",
          type: "text",
          placeholder: "Nome da nova tag",
          "aria-label": "Nome da nova tag",
          maxlength: "24",
        });
        const create = async () => {
          const s2 = safeState();
          const { defs, def } = addTagDef(s2.tagDefs, inp.value);
          if (!def) {
            flash("Digite um nome para a tag.");
            return;
          }
          s2.tagDefs = defs;
          await persist(s2);
          newTagOpen = false;
          renderTagRow();
        };
        inp.addEventListener("keydown", (e) => {
          if (e.key === "Enter") {
            e.preventDefault();
            create();
          }
        });
        tagBlock.appendChild(
          el("div", { class: "tgaw-row tgaw-newtag" }, [
            inp,
            el("button", { class: "tgaw-btn tgaw-btn-primary", type: "button", text: "Criar", onClick: create }),
          ])
        );
        setTimeout(() => inp.focus(), 0);
      }
    }
    renderTagRow();

    bodyEl.append(convLine, tagBlock, noteTextarea, noteMetaEl);

    if (key) {
      bodyEl.append(
        el("div", {
          class: "tgaw-footnote",
          text:
            "A nota fica salva só neste navegador e é vinculada a esta conversa. " +
            "Some se você limpar os dados do navegador — use o backup em Config.",
        })
      );
    }

    updateChatWatch();
  }

  // ----- observar troca de conversa (so enquanto Notas/Favoritos estao visiveis) -----
  let chatWatchStop = null;

  function updateChatWatch() {
    const shouldWatch = open_ && (currentTab === "notes" || currentTab === "favorites");
    if (shouldWatch && !chatWatchStop) {
      chatWatchStop = onConversationChange(() => onChatChanged(), {
        root: getMain() || undefined,
      });
    } else if (!shouldWatch && chatWatchStop) {
      chatWatchStop();
      chatWatchStop = null;
    }
  }

  function onChatChanged() {
    if (!open_) return;
    if (currentTab === "notes") {
      flushNote();               // salva a nota da conversa anterior
      renderTabs();
      renderBody();              // recarrega o textarea para a nova conversa
    } else if (currentTab === "favorites") {
      renderTabs();              // atualiza a estrela
      renderBody();              // atualiza o botao "favoritar esta conversa"
    }
  }

  // ---------- aba Favoritos ----------
  function renderFavorites() {
    const state = safeState();
    const key = safeConvKey();
    const name = safeConvName();
    const fav = isFavorite(state.favorites, key);

    // ----- Nova conversa por numero (mesmo nao salvo) -----
    const ddiInput = el("input", {
      class: "tgaw-field tgaw-nc-ddi",
      type: "text",
      inputmode: "numeric",
      "aria-label": "DDI",
      value: state.settings.newChatDdi || "55",
      maxlength: "4",
    });
    const numInput = el("input", {
      class: "tgaw-field",
      type: "text",
      inputmode: "tel",
      "aria-label": "Número",
      placeholder: "DDD + número (ex.: 65 99999-8888)",
    });
    const msgInput = el("input", {
      class: "tgaw-field",
      type: "text",
      "aria-label": "Mensagem inicial (opcional)",
      placeholder: "Mensagem inicial (opcional)",
    });

    ddiInput.addEventListener("change", () => {
      persistSettings({ newChatDdi: ddiInput.value });
    });

    const resolve = () => {
      const r = toE164(ddiInput.value, numInput.value);
      if (!r.ok) {
        flash("Número incompleto — informe DDD + número.");
        return null;
      }
      return r.e164;
    };

    const openBtn = el("button", {
      class: "tgaw-btn tgaw-btn-primary",
      type: "button",
      text: "Abrir conversa",
      title: "Abre a conversa com esse número (recarrega o WhatsApp Web)",
      onClick: () => {
        const e164 = resolve();
        if (!e164) return;
        try {
          window.location.assign(waSendUrl(e164, msgInput.value));
        } catch {
          flash("Não foi possível abrir.");
        }
      },
    });
    const linkBtn = el("button", {
      class: "tgaw-btn",
      type: "button",
      text: "Copiar link",
      title: "Copia um link wa.me para colar em outro lugar",
      onClick: async () => {
        const e164 = resolve();
        if (!e164) return;
        const ok = await copyText(waMeUrl(e164));
        flash(ok ? "Link copiado." : "Não foi possível copiar.");
      },
    });

    bodyEl.append(
      el("div", { class: "tgaw-tool-label", text: "Nova conversa por número" }),
      el("div", { class: "tgaw-row tgaw-newchat" }, [ddiInput, numInput]),
      msgInput,
      el("div", { class: "tgaw-row", style: "margin-top:6px" }, [openBtn, linkBtn]),
      el("hr", { class: "tgaw-sep" })
    );

    const toggleBtn = el("button", {
      class: fav ? "tgaw-btn" : "tgaw-btn tgaw-btn-primary",
      type: "button",
      disabled: !key,
      text: !key
        ? "Nenhuma conversa aberta"
        : fav
        ? "★ Remover dos favoritos"
        : "☆ Favoritar esta conversa",
      onClick: async () => {
        const { favorites, added } = toggleFavorite(state.favorites, key, name);
        const next = safeState();
        next.favorites = favorites;
        await persist(next);
        flash(added ? "Adicionada aos favoritos." : "Removida dos favoritos.");
      },
    });
    bodyEl.append(
      el("div", { class: "tgaw-fav-current" }, [
        toggleBtn,
        key ? el("span", { class: "tgaw-hint", text: name || "conversa atual" }) : null,
      ]),
      el("hr", { class: "tgaw-sep" })
    );

    const favs = sortFavorites(state.favorites);
    if (!favs.length) {
      bodyEl.append(
        el("div", {
          class: "tgaw-empty",
          text: "Nenhum favorito ainda. Abra uma conversa e toque em “Favoritar”.",
        })
      );
      return;
    }

    const list = el("div", { class: "tgaw-list" });
    for (const f of favs) {
      const openBtn = el("button", {
        class: "tgaw-btn tgaw-btn-primary",
        type: "button",
        text: "Abrir",
        title: "Filtra a lista de conversas do WhatsApp pelo nome",
        onClick: () => {
          if (!f.name) {
            flash("Este favorito não tem nome salvo.");
            return;
          }
          const ok = searchChats(f.name);
          flash(ok ? "Busca preenchida — clique na conversa na lista." : "Não achei a busca do WhatsApp.");
          if (ok) close();
        },
      });
      const rmBtn = el("button", {
        class: "tgaw-btn tgaw-btn-link tgaw-btn-danger",
        type: "button",
        text: "Remover",
        onClick: async () => {
          const next = safeState();
          next.favorites = removeFavorite(next.favorites, f.key);
          await persist(next);
          flash("Favorito removido.");
        },
      });
      list.append(
        el("div", { class: "tgaw-item" }, [
          el("div", { class: "tgaw-item-top" }, [
            el("span", { class: "tgaw-item-title", text: f.name || "(sem nome)" }),
            f.key === key ? el("span", { class: "tgaw-chip", text: "aberta" }) : null,
          ]),
          el("div", { class: "tgaw-item-actions" }, [openBtn, rmBtn]),
        ])
      );
    }
    bodyEl.append(
      list,
      el("div", {
        class: "tgaw-footnote",
        text: "Lista própria da extensão — não altera nada no WhatsApp. Salva só neste navegador.",
      })
    );
  }

  // ---------- aba Lembretes ----------
  let reminderCustomOpen = false;
  let reminderDraft = "";       // texto do lembrete em digitacao (sobrevive a re-render)
  let reminderLink = true;      // preferencia "vincular a conversa"

  function renderReminders() {
    const state = safeState();
    const key = safeConvKey();
    const name = safeConvName();

    const textInput = el("input", {
      class: "tgaw-field",
      type: "text",
      placeholder: "O que lembrar? (ex.: retornar sobre o orçamento)",
      "aria-label": "Texto do lembrete",
      maxlength: "300",
    });
    textInput.value = reminderDraft;
    textInput.addEventListener("input", () => {
      reminderDraft = textInput.value;
    });

    let linkConv = Boolean(key) && reminderLink;
    const linkWrap = el("label", { class: "tgaw-setting tgaw-check", style: key ? "" : "display:none" });
    const linkInput = el("input", { type: "checkbox" });
    linkInput.checked = linkConv;
    linkInput.addEventListener("change", () => {
      linkConv = linkInput.checked;
      reminderLink = linkInput.checked;
    });
    linkWrap.append(linkInput, el("span", { text: "Vincular à conversa: " + (name || "atual") }));

    const create = async (ts) => {
      const txt = textInput.value.trim();
      if (!txt) {
        flash("Escreva o texto do lembrete.");
        textInput.focus();
        return;
      }
      if (!Number.isFinite(ts) || ts <= Date.now()) {
        flash("Escolha um horário no futuro.");
        return;
      }
      const next = safeState();
      const { added, reminders } = addReminder(next.reminders, {
        text: txt,
        dueAt: ts,
        convKey: linkConv ? key : "",
        convName: linkConv ? name : "",
      });
      if (!added) {
        flash("Não foi possível criar o lembrete.");
        return;
      }
      next.reminders = reminders;
      await persist(next);
      reminderDraft = "";
      textInput.value = "";
      reminderCustomOpen = false;
      renderBody();
      renderTabs();
      flash("Lembrete criado para " + formatDue(ts) + ".");
    };

    const presetRow = el("div", { class: "tgaw-tag-row" });
    for (const p of reminderPresets()) {
      presetRow.appendChild(
        el("button", {
          class: "tgaw-btn tgaw-tool-btn",
          type: "button",
          text: p.label,
          onClick: () => create(p.ts),
        })
      );
    }
    presetRow.appendChild(
      el("button", {
        class: "tgaw-tag tgaw-tag-add",
        type: "button",
        text: reminderCustomOpen ? "×" : "outro horário",
        onClick: () => {
          reminderCustomOpen = !reminderCustomOpen;
          renderBody();
        },
      })
    );

    bodyEl.append(
      el("div", { class: "tgaw-tool-label", text: "Novo lembrete" }),
      textInput,
      linkWrap,
      presetRow
    );

    if (reminderCustomOpen) {
      const dt = el("input", { class: "tgaw-field", type: "datetime-local", "aria-label": "Data e hora" });
      const okBtn = el("button", {
        class: "tgaw-btn tgaw-btn-primary",
        type: "button",
        text: "Criar",
        onClick: () => {
          const ts = dt.value ? new Date(dt.value).getTime() : NaN;
          create(ts);
        },
      });
      bodyEl.append(el("div", { class: "tgaw-row tgaw-newtag" }, [dt, okBtn]));
    }

    bodyEl.append(el("hr", { class: "tgaw-sep" }));

    const items = sortReminders(state.reminders);
    if (!items.length) {
      bodyEl.append(el("div", { class: "tgaw-empty", text: "Nenhum lembrete." }));
    } else {
      const list = el("div", { class: "tgaw-list" });
      const now = Date.now();
      for (const r of items) {
        const overdue = !r.done && r.dueAt <= now;
        const doneBtn = el("button", {
          class: "tgaw-btn tgaw-btn-link",
          type: "button",
          text: r.done ? "reabrir" : "concluir",
          onClick: async () => {
            const next = safeState();
            next.reminders = setDone(next.reminders, r.id, !r.done);
            await persist(next);
            renderBody();
            renderTabs();
          },
        });
        const snoozeBtn = el("button", {
          class: "tgaw-btn tgaw-btn-link",
          type: "button",
          text: "+1 h",
          title: "Adiar 1 hora",
          onClick: async () => {
            const next = safeState();
            next.reminders = snooze(next.reminders, r.id, 3600e3);
            await persist(next);
            renderBody();
            renderTabs();
            flash("Adiado para " + formatDue(Date.now() + 3600e3) + ".");
          },
        });
        const rmBtn = el("button", {
          class: "tgaw-btn tgaw-btn-link tgaw-btn-danger",
          type: "button",
          text: "remover",
          onClick: async () => {
            const next = safeState();
            next.reminders = removeReminder(next.reminders, r.id);
            await persist(next);
            renderBody();
            renderTabs();
          },
        });
        list.append(
          el("div", { class: "tgaw-item" + (r.done ? " tgaw-rem-done" : "") }, [
            el("div", { class: "tgaw-item-top" }, [
              el("span", { class: "tgaw-item-title", text: r.text }),
            ]),
            el("div", { class: "tgaw-rem-meta" + (overdue ? " tgaw-rem-overdue" : "") }, [
              (overdue ? "⚠ " : "⏰ ") + formatDue(r.dueAt),
              r.convName ? el("span", { class: "tgaw-chip", text: r.convName }) : null,
            ]),
            el("div", { class: "tgaw-item-actions" }, [doneBtn, r.done ? null : snoozeBtn, rmBtn]),
          ])
        );
      }
      bodyEl.append(list);
    }

    bodyEl.append(
      el("div", {
        class: "tgaw-footnote",
        text: "Local. O aviso aparece na tela enquanto o WhatsApp Web estiver aberto.",
      })
    );
  }

  // ---------- aba Texto (ferramentas) ----------
  async function copyText(text) {
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(text);
        return true;
      }
    } catch {
      /* segue para o fallback */
    }
    try {
      const t = el("textarea", {});
      t.value = text;
      t.style.cssText = "position:fixed;top:0;left:0;opacity:0;pointer-events:none";
      root.appendChild(t);
      t.select();
      const ok = document.execCommand("copy");
      t.remove();
      return ok;
    } catch {
      return false;
    }
  }

  function renderTools() {
    const ta = el("textarea", {
      class: "tgaw-field tgaw-tools-input",
      rows: "5",
      placeholder: "Cole ou digite o texto aqui — ou toque em “Pegar do WhatsApp”.",
      "aria-label": "Texto para editar",
    });
    ta.value = toolsText;

    const stats = el("div", { class: "tgaw-note-meta", "aria-live": "polite" });
    const preview = el("div", { class: "tgaw-fmt-preview" });
    const updateStats = () => {
      const c = TT.countStats(ta.value);
      stats.textContent =
        `${c.chars} caractere${c.chars === 1 ? "" : "s"} · ` +
        `${c.words} palavra${c.words === 1 ? "" : "s"} · ` +
        `${c.lines} linha${c.lines === 1 ? "" : "s"}`;
      clear(preview);
      preview.appendChild(waMarkupNodes(ta.value));
    };
    ta.addEventListener("input", () => {
      toolsText = ta.value;
      toolsUndo = null;
      updateStats();
    });
    updateStats();

    const apply = (fn) => {
      toolsUndo = ta.value;
      const out = fn(ta.value);
      ta.value = out;
      toolsText = out;
      updateStats();
      ta.focus();
    };

    const toolBtn = (label, fn, title) =>
      el("button", {
        class: "tgaw-btn tgaw-tool-btn",
        type: "button",
        text: label,
        title: title || label,
        onClick: () => apply(fn),
      });

    // Formatadores BR: recebem { text, ok }; so aplicam se reconheceram.
    const applyFmt = (fn) => {
      const res = fn(ta.value);
      if (!res || !res.ok) {
        flash("Não reconheci um valor para esse formato.");
        return;
      }
      toolsUndo = ta.value;
      ta.value = res.text;
      toolsText = res.text;
      updateStats();
      ta.focus();
    };
    const fmtBtn = (label, fn) =>
      el("button", {
        class: "tgaw-btn tgaw-tool-btn",
        type: "button",
        text: label,
        onClick: () => applyFmt(fn),
      });

    const grid = el("div", { class: "tgaw-tool-grid" }, [
      toolBtn("MAIÚSCULAS", TT.toUpper),
      toolBtn("minúsculas", TT.toLower),
      toolBtn("Primeira De Cada", TT.titleCase, "Primeira letra de cada palavra"),
      toolBtn("Frases", TT.sentenceCase, "Primeira letra de cada frase"),
      toolBtn("Espaços duplos", TT.collapseSpaces, "Remove espaços repetidos e apara as linhas"),
      toolBtn("Linhas em branco", TT.collapseBlankLines, "Reduz linhas em branco em excesso"),
      toolBtn("Juntar linhas", TT.joinLines, "Junta tudo em um parágrafo"),
      toolBtn("Limpar invisíveis", TT.stripInvisible, "Remove caracteres invisíveis e NBSP"),
    ]);

    const fmt = el("div", { class: "tgaw-tool-grid tgaw-tool-fmt" }, [
      toolBtn("*negrito*", (t) => TT.wrapMark(t, "*")),
      toolBtn("_itálico_", (t) => TT.wrapMark(t, "_")),
      toolBtn("~tachado~", (t) => TT.wrapMark(t, "~")),
      toolBtn("`código`", (t) => TT.wrapMark(t, "`")),
    ]);

    const docs = el("div", { class: "tgaw-tool-grid tgaw-tool-doc" }, [
      fmtBtn("CPF", FMT.formatCPF),
      fmtBtn("CNPJ", FMT.formatCNPJ),
      fmtBtn("Telefone", FMT.formatPhoneBR),
      fmtBtn("CEP", FMT.formatCEP),
      fmtBtn("R$", FMT.formatCurrencyBR),
    ]);

    // ----- Calculadora -----
    const calcInput = el("input", {
      class: "tgaw-field",
      type: "text",
      placeholder: "Conta: (120 + 30) * 3",
      "aria-label": "Expressão para calcular",
    });
    const calcOut = el("div", { class: "tgaw-calc-out", "aria-live": "polite" });
    let calcValue = null;
    const runCalc = () => {
      const v = calcInput.value.trim();
      if (!v) {
        calcOut.textContent = "";
        calcValue = null;
        return;
      }
      const r = CALC.evalExpr(v);
      calcValue = r.ok ? r.value : null;
      calcOut.textContent = r.ok
        ? "= " + CALC.fmtNum(r.value) + "   (" + CALC.fmtMoney(r.value) + ")"
        : "= ?";
    };
    calcInput.addEventListener("input", runCalc);
    const calcCopy = el("button", {
      class: "tgaw-btn",
      type: "button",
      text: "Copiar resultado",
      onClick: async () => {
        if (calcValue == null) {
          flash("Sem resultado para copiar.");
          return;
        }
        const ok = await copyText(CALC.fmtNum(calcValue));
        flash(ok ? "Resultado copiado." : "Não foi possível copiar.");
      },
    });

    const pctA = el("input", { class: "tgaw-field tgaw-calc-mini", type: "text", inputmode: "decimal", "aria-label": "Porcentagem" });
    const pctB = el("input", { class: "tgaw-field", type: "text", inputmode: "decimal", "aria-label": "Valor base" });
    const pctOut = el("div", { class: "tgaw-calc-out", "aria-live": "polite" });
    const runPct = () => {
      const r = CALC.percentOf(pctA.value, pctB.value);
      if (!r.ok || (!pctA.value.trim() && !pctB.value.trim())) {
        pctOut.textContent = "";
        return;
      }
      pctOut.textContent =
        "= " + CALC.fmtNum(r.of) +
        "   (com acréscimo " + CALC.fmtNum(r.plus) +
        " · com desconto " + CALC.fmtNum(r.minus) + ")";
    };
    pctA.addEventListener("input", runPct);
    pctB.addEventListener("input", runPct);

    const calcSection = el("div", {}, [
      el("div", { class: "tgaw-tool-label", text: "Calculadora" }),
      calcInput,
      calcOut,
      el("div", { class: "tgaw-row", style: "margin-top:6px" }, [calcCopy]),
      el("div", { class: "tgaw-row tgaw-calc-pct" }, [
        pctA,
        el("span", { class: "tgaw-calc-x", text: "% de" }),
        pctB,
      ]),
      pctOut,
    ]);

    const grabBtn = el("button", {
      class: "tgaw-btn",
      type: "button",
      text: "Pegar do WhatsApp",
      onClick: () => {
        let v = "";
        try {
          v = getComposerText ? getComposerText() : "";
        } catch {
          v = "";
        }
        if (!v) {
          flash("Campo de mensagem vazio ou nenhuma conversa aberta.");
          return;
        }
        toolsUndo = ta.value;
        ta.value = v;
        toolsText = v;
        updateStats();
        ta.focus();
      },
    });

    const undoBtn = el("button", {
      class: "tgaw-btn",
      type: "button",
      text: "Desfazer",
      onClick: () => {
        if (toolsUndo == null) return;
        ta.value = toolsUndo;
        toolsText = toolsUndo;
        toolsUndo = null;
        updateStats();
        ta.focus();
      },
    });

    const copyBtn = el("button", {
      class: "tgaw-btn",
      type: "button",
      text: "Copiar",
      onClick: async () => {
        const ok = await copyText(ta.value);
        flash(ok ? "Texto copiado." : "Não foi possível copiar.");
      },
    });

    const useBtn = el("button", {
      class: "tgaw-btn tgaw-btn-primary",
      type: "button",
      text: "Usar no WhatsApp",
      onClick: () => {
        let ok = false;
        try {
          ok = setComposerText ? setComposerText(ta.value) : false;
        } catch {
          ok = false;
        }
        if (ok) {
          flash("Texto no campo de mensagem. Revise e envie.");
          close();
        } else {
          flash("Abra uma conversa primeiro.");
        }
      },
    });

    bodyEl.append(
      ta,
      stats,
      el("div", { class: "tgaw-tool-label", text: "Prévia da formatação" }),
      preview,
      el("div", { class: "tgaw-tool-label", text: "Transformar" }),
      grid,
      el("div", { class: "tgaw-tool-label", text: "Formatação do WhatsApp" }),
      fmt,
      el("div", { class: "tgaw-tool-label", text: "Documentos e valores" }),
      docs,
      calcSection,
      el("div", { class: "tgaw-row tgaw-tool-actions" }, [grabBtn, undoBtn]),
      el("div", { class: "tgaw-row tgaw-tool-actions" }, [copyBtn, useBtn]),
      el("div", {
        class: "tgaw-footnote",
        text: "Nada é enviado. “Usar” apenas preenche o campo de mensagem para você revisar.",
      })
    );
  }

  // ---------- aba Config ----------
  function renderSettings() {
    const state = safeState();
    const s = state.settings;

    // Tema
    const themeSel = selectField(
      "Tema do painel",
      "Em “Automático”, segue o tema do WhatsApp.",
      [
        ["auto", "Automático"],
        ["light", "Claro"],
        ["dark", "Escuro"],
      ],
      s.theme,
      async (v) => {
        await persistSettings({ theme: v });
        flash("Tema atualizado.");
      }
    );

    // Posicao do botao
    const posSel = selectField(
      "Posição do botão",
      "Você também pode arrastar o botão para qualquer lugar da tela.",
      [
        ["sidebar", "Na barra do WhatsApp (junto aos ícones)"],
        ["bottom-right", "Flutuante — inferior direita"],
        ["top-right", "Flutuante — superior direita"],
        ["right-center", "Flutuante — lateral direita"],
      ],
      s.buttonPosition,
      async (v) => {
        // Escolher um modo desfaz a posição arrastada.
        await persistSettings({ buttonPosition: v, buttonCustomPos: null });
        flash("Posição atualizada.");
      }
    );

    // Aviso + reset quando o botao foi arrastado para uma posicao livre.
    let dragNote = null;
    if (s.buttonCustomPos) {
      const resetPosBtn = el("button", {
        class: "tgaw-btn tgaw-btn-link",
        type: "button",
        text: "Voltar para a posição automática",
        onClick: async () => {
          await persistSettings({ buttonCustomPos: null });
          flash("Botão voltou para a posição automática.");
        },
      });
      dragNote = el("div", { class: "tgaw-setting" }, [
        el("span", { class: "tgaw-hint", text: "O botão está numa posição arrastada por você." }),
        resetPosBtn,
      ]);
    }

    // Tamanho do painel: dica + reset quando foi redimensionado.
    const sizeNote = el("div", { class: "tgaw-setting" }, [
      el("span", {
        class: "tgaw-hint",
        text: s.panelSize
          ? "Painel redimensionado por você (" + s.panelSize.w + "×" + s.panelSize.h + ")."
          : "Arraste o canto inferior do painel para redimensioná-lo.",
      }),
    ]);
    if (s.panelSize) {
      sizeNote.appendChild(
        el("button", {
          class: "tgaw-btn tgaw-btn-link",
          type: "button",
          text: "Restaurar tamanho padrão",
          onClick: async () => {
            await persistSettings({ panelSize: null });
            flash("Tamanho do painel restaurado.");
          },
        })
      );
    }

    // Sugestoes ao digitar "/"
    const sugWrap = el("div", { class: "tgaw-setting tgaw-check" });
    const sugInput = el("input", { type: "checkbox", id: "tgaw-sug-toggle" });
    sugInput.checked = s.suggestionsEnabled !== false;
    sugInput.addEventListener("change", async () => {
      await persistSettings({ suggestionsEnabled: sugInput.checked });
      flash(sugInput.checked ? "Sugestões ativadas." : "Sugestões desativadas.");
    });
    sugWrap.append(
      sugInput,
      el("label", {
        for: "tgaw-sug-toggle",
        text: "Sugerir resposta ao digitar /atalho no campo de mensagem",
      })
    );

    bodyEl.append(themeSel, posSel);
    if (dragNote) bodyEl.append(dragNote);
    bodyEl.append(sizeNote, sugWrap, el("hr", { class: "tgaw-sep" }));

    // ----- Privacidade de tela -----
    const pv = s.privacy || {};
    const pvMaster = el("input", { type: "checkbox", id: "tgaw-pv-master" });
    pvMaster.checked = pv.enabled === true;
    pvMaster.addEventListener("change", async () => {
      await persistSettings({ privacy: { ...pv, enabled: pvMaster.checked } });
    });
    bodyEl.append(
      el("div", { class: "tgaw-setting tgaw-check" }, [
        pvMaster,
        el("label", { for: "tgaw-pv-master", text: "Privacidade de tela (desfoca; passe o mouse p/ ver)" }),
      ]),
      el("div", { class: "tgaw-hint", style: "margin:-4px 0 6px" }, [
        "Só afeta a sua tela. Nada é enviado, ninguém é enganado.",
      ])
    );
    for (const it of PRIVACY_ITEMS) {
      const cb = el("input", { type: "checkbox", id: "tgaw-pv-" + it.key });
      cb.checked = Boolean(pv[it.key]);
      cb.disabled = !pvMaster.checked;
      cb.addEventListener("change", async () => {
        await persistSettings({ privacy: { ...pv, [it.key]: cb.checked } });
      });
      bodyEl.append(
        el("div", { class: "tgaw-setting tgaw-check tgaw-pv-sub" }, [
          cb,
          el("label", { for: "tgaw-pv-" + it.key, text: it.label }),
        ])
      );
    }
    bodyEl.append(el("hr", { class: "tgaw-sep" }));

    // ----- Assinatura automática -----
    const sig = s.signature || {};
    const sigChk = el("input", { type: "checkbox", id: "tgaw-sig-on" });
    sigChk.checked = sig.enabled === true;
    const sigTxt = el("textarea", {
      class: "tgaw-field",
      rows: "2",
      placeholder: "— Fulano, Suporte",
      "aria-label": "Texto da assinatura",
    });
    sigTxt.value = sig.text || "";
    sigTxt.disabled = !sigChk.checked;
    sigChk.addEventListener("change", () =>
      persistSettings({ signature: { enabled: sigChk.checked, text: sigTxt.value } })
    );
    let sigSaveT = 0;
    sigTxt.addEventListener("input", () => {
      clearTimeout(sigSaveT);
      sigSaveT = setTimeout(
        () => persistSettings({ signature: { enabled: sigChk.checked, text: sigTxt.value } }),
        600
      );
    });
    bodyEl.append(
      el("div", { class: "tgaw-setting tgaw-check" }, [
        sigChk,
        el("label", { for: "tgaw-sig-on", text: "Assinatura no fim das respostas rápidas" }),
      ]),
      sigTxt
    );

    // ----- Modo compacto -----
    const cmpChk = el("input", { type: "checkbox", id: "tgaw-compact" });
    cmpChk.checked = s.compact === true;
    cmpChk.addEventListener("change", () => persistSettings({ compact: cmpChk.checked }));
    bodyEl.append(
      el("div", { class: "tgaw-setting tgaw-check" }, [
        cmpChk,
        el("label", { for: "tgaw-compact", text: "Modo compacto (menos espaçamento)" }),
      ])
    );

    bodyEl.append(el("hr", { class: "tgaw-sep" }));

    // ----- Fundo da conversa -----
    const bg = s.chatBg || {};
    const bgSel = selectField(
      "Fundo da conversa",
      "Best-effort (só CSS). Se o WhatsApp mudar, pode não pegar.",
      [
        ["none", "Padrão do WhatsApp"],
        ["color", "Cor sólida"],
        ["image", "Imagem por URL (https)"],
      ],
      bg.mode || "none",
      (v) => persistSettings({ chatBg: { ...bg, mode: v } })
    );
    bodyEl.append(bgSel);
    if (bg.mode === "color") {
      const colInput = el("input", { type: "color", class: "tgaw-field tgaw-color", value: bg.color || "#0b141a" });
      colInput.addEventListener("change", () => persistSettings({ chatBg: { ...bg, color: colInput.value } }));
      bodyEl.append(el("div", { class: "tgaw-row", style: "margin-top:4px" }, [colInput]));
    }
    if (bg.mode === "image") {
      const urlInput = el("input", {
        class: "tgaw-field",
        type: "url",
        placeholder: "https://.../imagem.jpg",
        value: bg.imageUrl || "",
      });
      const opInput = el("input", {
        class: "tgaw-field tgaw-color",
        type: "number",
        min: "0",
        max: "100",
        value: String(bg.opacity == null ? 100 : bg.opacity),
        title: "Opacidade %",
      });
      const applyBg = () =>
        persistSettings({ chatBg: { ...bg, imageUrl: urlInput.value.trim(), opacity: Number(opInput.value) || 100 } });
      urlInput.addEventListener("change", applyBg);
      opInput.addEventListener("change", applyBg);
      bodyEl.append(urlInput, el("div", { class: "tgaw-row", style: "margin-top:4px" }, [
        el("span", { class: "tgaw-hint", text: "Opacidade" }),
        opInput,
      ]));
    }

    bodyEl.append(el("hr", { class: "tgaw-sep" }));

    // ----- Estatísticas -----
    const statGrid = el("div", { class: "tgaw-stat-grid" });
    for (const st of computeStats(state)) {
      statGrid.append(
        el("div", { class: "tgaw-stat" }, [
          el("div", { class: "tgaw-stat-v", text: String(st.value) }),
          el("div", { class: "tgaw-stat-l", text: st.label }),
        ])
      );
    }
    const tr = topReply(state);
    bodyEl.append(
      el("div", { class: "tgaw-setting" }, [el("label", { text: "Estatísticas (só os dados da extensão)" })]),
      statGrid,
      tr
        ? el("div", { class: "tgaw-hint", style: "margin-top:6px" }, [
            "Mais usada: " + (tr.title || tr.shortcut || "resposta") + " (" + tr.uses + "×)",
          ])
        : null
    );

    bodyEl.append(el("hr", { class: "tgaw-sep" }));

    // Backup
    const exportBtn = el("button", {
      class: "tgaw-btn",
      type: "button",
      text: "Exportar backup (.json)",
      onClick: () => doExport(),
    });

    const importInput = el("input", {
      type: "file",
      accept: "application/json,.json",
      style: "display:none",
    });
    importInput.addEventListener("change", () => doImport(importInput));
    const importBtn = el("button", {
      class: "tgaw-btn",
      type: "button",
      text: "Importar backup",
      onClick: () => importInput.click(),
    });

    bodyEl.append(
      el("div", { class: "tgaw-setting" }, [
        el("label", { text: "Backup" }),
        el("span", { class: "tgaw-hint", text: "Salve ou restaure suas respostas e preferências." }),
        el("div", { class: "tgaw-row", style: "margin-top:4px" }, [exportBtn, importBtn, importInput]),
      ])
    );

    // Restaurar padroes
    const resetBtn = el("button", {
      class: "tgaw-btn tgaw-btn-link tgaw-btn-danger",
      type: "button",
      text: "Restaurar respostas padrão",
      dataset: { armed: "0" },
    });
    resetBtn.addEventListener("click", async () => {
      if (resetBtn.dataset.armed !== "1") {
        resetBtn.dataset.armed = "1";
        resetBtn.textContent = "Confirmar restauração?";
        setTimeout(() => {
          resetBtn.dataset.armed = "0";
          resetBtn.textContent = "Restaurar respostas padrão";
        }, 3000);
        return;
      }
      const next = safeState();
      next.quickReplies = JSON.parse(JSON.stringify(DEFAULT_STATE.quickReplies));
      await persist(next);
      flash("Respostas padrão restauradas.");
    });
    const wipeBtn = el("button", {
      class: "tgaw-btn tgaw-btn-link tgaw-btn-danger",
      type: "button",
      text: "Apagar TUDO da extensão",
      dataset: { armed: "0" },
    });
    wipeBtn.addEventListener("click", async () => {
      if (wipeBtn.dataset.armed !== "1") {
        wipeBtn.dataset.armed = "1";
        wipeBtn.textContent = "Confirmar? apaga respostas, notas, tags, favoritos, lembretes";
        setTimeout(() => {
          wipeBtn.dataset.armed = "0";
          wipeBtn.textContent = "Apagar TUDO da extensão";
        }, 4000);
        return;
      }
      await persist(JSON.parse(JSON.stringify(DEFAULT_STATE)));
      flash("Dados da extensão apagados.");
    });
    bodyEl.append(el("hr", { class: "tgaw-sep" }), resetBtn, wipeBtn);

    bodyEl.append(
      el("div", {
        class: "tgaw-footnote",
        text:
          "Tudo fica salvo apenas neste navegador (chrome.storage.local). Nada é enviado para servidores. A extensão nunca envia mensagens sozinha.",
      })
    );
  }

  function selectField(labelText, hintText, options, value, onChange) {
    const wrap = el("div", { class: "tgaw-setting" });
    const id = "tgaw-sel-" + Math.random().toString(36).slice(2, 7);
    const label = el("label", { for: id, text: labelText });
    const hint = el("span", { class: "tgaw-hint", text: hintText });
    const sel = el("select", { class: "tgaw-field", id });
    for (const [val, txt] of options) {
      const opt = el("option", { value: val, text: txt });
      if (val === value) opt.selected = true;
      sel.appendChild(opt);
    }
    sel.addEventListener("change", () => onChange(sel.value));
    wrap.append(label, hint, sel);
    return wrap;
  }

  // ----- persistencia -----
  async function persist(nextState) {
    try {
      await applyState(nextState);
    } catch (e) {
      log("persist falhou", e);
      flash("Não foi possível salvar.");
    }
  }
  async function persistSettings(patch) {
    const next = safeState();
    next.settings = { ...next.settings, ...patch };
    await persist(next);
  }
  async function persistTab(tabId) {
    persistSettings({ lastTab: tabId });
  }

  // ----- inserir no composer -----

  /** Insere `str` na posicao do cursor de um textarea/input. */
  function insertAtCursor(field, str) {
    const s = typeof field.selectionStart === "number" ? field.selectionStart : field.value.length;
    const e = typeof field.selectionEnd === "number" ? field.selectionEnd : field.value.length;
    field.value = field.value.slice(0, s) + str + field.value.slice(e);
    const pos = s + str.length;
    try {
      field.selectionStart = field.selectionEnd = pos;
    } catch {}
    field.focus();
  }

  /** {{variaveis}} + assinatura opcional. */
  function applyVars(text) {
    const { text: out, missing } = resolveVars(text, {
      name: safeConvName(),
      greeting: currentGreeting(),
    });
    if (missing.length) {
      flash(
        "Abra a conversa: " +
        missing.map((k) => "{{" + k + "}}").join(", ") +
        (missing.length === 1 ? " ficou vazio." : " ficaram vazios.")
      );
    }
    return withSignature(out, safeState().settings.signature);
  }

  /** Conta +1 uso da resposta (para "mais usadas no topo"). */
  async function bumpUse(id) {
    try {
      const next = safeState();
      next.quickReplies = next.quickReplies.map((r) =>
        r.id === id ? { ...r, uses: (r.uses || 0) + 1 } : r
      );
      await persist(next);
    } catch {
      /* ignora */
    }
  }

  function tryInsert(text) {
    let ok = false;
    try {
      ok = insertText(text);
    } catch {
      ok = false;
    }
    if (!ok) flash("Abra uma conversa antes de usar a resposta.");
    // (o composer só existe quando há um chat aberto)
    return ok;
  }

  // ----- backup -----
  function doExport() {
    try {
      const state = safeState();
      const payload = {
        app: "tgameajuda-edita-whats",
        version: 1,
        exportedAt: new Date().toISOString(),
        settings: state.settings,
        quickReplies: state.quickReplies,
        notes: state.notes,
        favorites: state.favorites,
        tagDefs: state.tagDefs,
        convTags: state.convTags,
        reminders: state.reminders,
      };
      const blob = new Blob([JSON.stringify(payload, null, 2)], { type: "application/json" });
      const url = URL.createObjectURL(blob);
      const a = el("a", {
        href: url,
        download: "tgaw-backup-" + new Date().toISOString().slice(0, 10) + ".json",
      });
      root.appendChild(a);
      a.click();
      a.remove();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
      flash("Backup exportado.");
    } catch (e) {
      log("export falhou", e);
      flash("Falha ao exportar.");
    }
  }

  function doImport(input) {
    const file = input.files && input.files[0];
    input.value = "";
    if (!file) return;
    const reader = new FileReader();
    reader.onload = async () => {
      try {
        const data = JSON.parse(String(reader.result));
        if (!data || typeof data !== "object") throw new Error("formato");
        const next = safeState();
        if (Array.isArray(data.quickReplies)) next.quickReplies = data.quickReplies;
        if (data.settings && typeof data.settings === "object") {
          next.settings = { ...next.settings, ...data.settings };
        }
        if (data.notes && typeof data.notes === "object") {
          next.notes = { ...next.notes, ...data.notes };
        }
        if (Array.isArray(data.favorites)) {
          next.favorites = data.favorites.concat(next.favorites);
        }
        if (Array.isArray(data.tagDefs)) next.tagDefs = data.tagDefs;
        if (data.convTags && typeof data.convTags === "object") {
          next.convTags = { ...next.convTags, ...data.convTags };
        }
        if (Array.isArray(data.reminders)) {
          next.reminders = data.reminders.concat(next.reminders);
        }
        await persist(next); // storage.normalize valida tudo (inclui dedupe)
        flash("Backup importado.");
      } catch (e) {
        log("import falhou", e);
        flash("Arquivo inválido. Importação cancelada.");
      }
    };
    reader.onerror = () => flash("Não foi possível ler o arquivo.");
    reader.readAsText(file);
  }

  // ----- abrir / fechar -----
  let open_ = false;

  function open() {
    if (open_) return;
    open_ = true;
    editingId = null;
    showForm = false;
    renderTabs();
    renderBody();
    panel.hidden = false;
    applyPanelSize(safeState().settings);
    positionPanel();
    updateChatWatch();
    document.addEventListener("keydown", onKeydown, true);
    document.addEventListener("mousedown", onOutside, true);
    document.addEventListener("pointerup", onDocPointerUp, true);
    window.addEventListener("resize", positionPanel);
    // foco no primeiro campo util
    const first = panel.querySelector("input, button, select, textarea");
    if (first) first.focus();
  }

  function close() {
    if (!open_) return;
    flushNote();
    open_ = false;
    updateChatWatch();
    panel.hidden = true;
    document.removeEventListener("keydown", onKeydown, true);
    document.removeEventListener("mousedown", onOutside, true);
    document.removeEventListener("pointerup", onDocPointerUp, true);
    window.removeEventListener("resize", positionPanel);
    fab.focus();
  }

  function toggle() {
    open_ ? close() : open();
  }

  const TAB_ORDER = ["replies", "notes", "favorites", "reminders", "tools", "settings"];

  function onKeydown(e) {
    if (e.key === "Escape") {
      e.stopPropagation();
      close();
      return;
    }
    // Alt+1..6 troca de aba (Alt evita atrapalhar quem digita numeros).
    if (e.altKey && !e.ctrlKey && !e.metaKey && /^[1-6]$/.test(e.key)) {
      const id = TAB_ORDER[Number(e.key) - 1];
      if (id && id !== currentTab) {
        e.preventDefault();
        e.stopPropagation();
        if (currentTab === "notes") flushNote();
        currentTab = id;
        editingId = null;
        showForm = false;
        renderTabs();
        renderBody();
        persistTab(id);
        updateChatWatch();
      }
    }
  }

  function onOutside(e) {
    const path = e.composedPath ? e.composedPath() : [];
    if (!path.includes(panel) && !path.includes(fab)) close();
  }

  // ----- API publica -----
  function refresh(state) {
    const s = (state || safeState()).settings;
    root.dataset.compact = s.compact ? "1" : "";
    if (!dragState) placeFab(s);
    if (open_) {
      applyPanelSize(s);
      renderTabs();
      // Abas com campos de digitacao gerenciam o proprio corpo;
      // nao re-renderiza para nao interromper o usuario.
      if (!["notes", "tools", "reminders"].includes(currentTab)) renderBody();
      positionPanel();
    }
  }

  function setTheme(theme) {
    applyThemeClass(theme);
  }

  function destroy() {
    close();
    hideReminderBanner();
    if (chatWatchStop) {
      chatWatchStop();
      chatWatchStop = null;
    }
    stopSidebarAnchor();
    stopCustomAnchor();
    clearTimeout(flashTimer);
    host.remove();
  }

  // init
  root.dataset.compact = safeState().settings.compact ? "1" : "";
  placeFab(safeState().settings);
  log("painel montado");

  return { open, close, toggle, refresh, setTheme, showReminderBanner, destroy };
}
