/*
 * suggestions.js — caixinha de sugestao acima do campo de mensagem.
 * ------------------------------------------------------------------
 * Quando o usuario digita "/atalho" no composer, mostra as respostas
 * que casam. Escolher uma substitui o "/atalho" pelo texto (SEM enviar).
 *
 * Cuidados de performance (master prompt):
 *  - 1 listener "input" delegado no document (debounced ~90ms).
 *  - 1 listener "keydown" de captura ligado diretamente ao composer
 *    (recriado so quando o composer muda).
 *  - Nenhum MutationObserver, nenhum polling.
 */
import { SUGGESTIONS_CSS } from "./styles.js";
import { el, clear } from "./dom.js";
import { debounce } from "../core/util.js";
import { log } from "../core/debug.js";

const HOST_ID = "tgaw-suggestions-host";

export function mountSuggestions({
  getComposer,
  getFooter,
  isEnabled,
  getMatches,
  tokenizer,
  onPick,
}) {
  const old = document.getElementById(HOST_ID);
  if (old) old.remove();

  const host = el("div", { id: HOST_ID });
  host.style.cssText = "position:fixed;top:0;left:0;width:0;height:0;z-index:2147483002;";
  (document.documentElement || document.body).appendChild(host);
  const shadow = host.attachShadow({ mode: "open" });

  const style = el("style", { text: SUGGESTIONS_CSS });
  const root = el("div", { class: "tgaw-root", "data-theme": "light" });

  const headHint = el("span", { text: "Enter para usar" });
  const head = el("div", { class: "tgaw-sug-head" }, [
    el("span", { text: "Respostas rápidas" }),
    headHint,
  ]);
  const listEl = el("div", { class: "tgaw-sug-list" });
  const box = el("div", { class: "tgaw-sug", role: "listbox", hidden: true }, [head, listEl]);

  root.appendChild(box);
  shadow.append(style, root);

  // ----- estado -----
  let matches = [];
  let token = null;
  let activeIndex = 0;
  let boundComposer = null;
  let blurTimer = 0;

  function visible() {
    return !box.hidden;
  }

  function hide() {
    if (box.hidden) return;
    box.hidden = true;
    matches = [];
    token = null;
    activeIndex = 0;
  }

  function position() {
    const footer = safe(getFooter);
    const anchor = footer || safe(getComposer);
    if (!anchor) return hide();
    const r = anchor.getBoundingClientRect();
    box.style.left = Math.max(12, r.left + 8) + "px";
    box.style.bottom = Math.max(12, window.innerHeight - r.top + 6) + "px";
  }

  function render() {
    clear(listEl);
    matches.forEach((reply, i) => {
      const top = el("div", { class: "tgaw-sug-item-top" }, [
        el("span", { class: "tgaw-sug-item-title", text: reply.title || reply.shortcut || "resposta" }),
        reply.shortcut ? el("span", { class: "tgaw-chip", text: reply.shortcut }) : null,
      ]);
      const text = el("div", { class: "tgaw-sug-item-text", text: reply.text });
      const item = el("div", {
        class: "tgaw-sug-item",
        role: "option",
        dataset: { active: String(i === activeIndex), index: String(i) },
      }, [top, text]);

      // mousedown (nao click) para agir antes do blur do composer.
      item.addEventListener("mousedown", (e) => {
        e.preventDefault();
        pick(i);
      });
      listEl.appendChild(item);
    });
  }

  function refreshActive() {
    listEl.querySelectorAll(".tgaw-sug-item").forEach((n) => {
      n.dataset.active = String(Number(n.dataset.index) === activeIndex);
    });
  }

  function pick(i) {
    const reply = matches[i];
    const tk = token;
    hide();
    if (reply) {
      try {
        onPick(reply, tk);
      } catch (e) {
        log("onPick falhou", e);
      }
    }
  }

  // ----- entrada de texto -----
  const onInput = debounce((composer) => {
    if (!isEnabled || !isEnabled()) return hide();
    if (!composer) return hide();
    const text = composer.textContent || "";
    const tk = tokenizer(text);
    if (!tk) return hide();
    const found = getMatches(tk) || [];
    if (!found.length) return hide();
    matches = found.slice(0, 6);
    token = tk;
    activeIndex = 0;
    render();
    box.hidden = false;
    position();
  }, 90);

  function handleDocInput(e) {
    const composer = safe(getComposer);
    if (!composer) return;
    const t = e.target;
    if (t !== composer && !(t && composer.contains(t))) return;
    bindComposer(composer);
    onInput(composer);
  }

  // keydown de captura ligado ao proprio composer -> vence o handler do WhatsApp.
  function handleComposerKeydown(e) {
    if (!visible()) return;
    const n = matches.length;
    if (!n) return;

    if (e.key === "ArrowDown") {
      e.preventDefault();
      activeIndex = (activeIndex + 1) % n;
      refreshActive();
    } else if (e.key === "ArrowUp") {
      e.preventDefault();
      activeIndex = (activeIndex - 1 + n) % n;
      refreshActive();
    } else if (e.key === "Enter" && !e.shiftKey) {
      // impede o WhatsApp de enviar a mensagem.
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      pick(activeIndex);
    } else if (e.key === "Tab") {
      e.preventDefault();
      pick(activeIndex);
    } else if (e.key === "Escape") {
      e.preventDefault();
      e.stopPropagation();
      hide();
    }
  }

  function handleComposerBlur() {
    clearTimeout(blurTimer);
    blurTimer = setTimeout(hide, 120);
  }

  function bindComposer(composer) {
    if (composer === boundComposer) return;
    if (boundComposer) {
      boundComposer.removeEventListener("keydown", handleComposerKeydown, true);
      boundComposer.removeEventListener("blur", handleComposerBlur, true);
    }
    boundComposer = composer;
    composer.addEventListener("keydown", handleComposerKeydown, true);
    composer.addEventListener("blur", handleComposerBlur, true);
  }

  function onScrollResize() {
    if (visible()) position();
  }

  document.addEventListener("input", handleDocInput, true);
  window.addEventListener("resize", onScrollResize);
  window.addEventListener("scroll", onScrollResize, true);

  function safe(fn) {
    try {
      return fn();
    } catch {
      return null;
    }
  }

  function setTheme(theme) {
    root.setAttribute("data-theme", theme === "dark" ? "dark" : "light");
  }

  function destroy() {
    hide();
    document.removeEventListener("input", handleDocInput, true);
    window.removeEventListener("resize", onScrollResize);
    window.removeEventListener("scroll", onScrollResize, true);
    if (boundComposer) {
      boundComposer.removeEventListener("keydown", handleComposerKeydown, true);
      boundComposer.removeEventListener("blur", handleComposerBlur, true);
    }
    host.remove();
  }

  log("sugestoes montadas");
  return { setTheme, destroy };
}
