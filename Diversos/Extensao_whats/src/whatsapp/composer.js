/*
 * composer.js — inserir texto na caixa de mensagem SEM ENVIAR.
 * ------------------------------------------------------------------
 * Principio do master prompt: preencher != enviar.
 * Estas funcoes apenas colocam texto no campo. O usuario revisa e
 * envia manualmente.
 */
import { getComposer } from "./adapter.js";
import { log } from "../core/debug.js";

/**
 * Insere `text` na posicao atual do cursor do composer.
 * Tenta 3 estrategias, da mais compativel para a mais generica.
 * @returns {boolean} true se alguma estrategia funcionou.
 */
export function insertText(text) {
  if (typeof text !== "string" || text.length === 0) return false;
  const el = getComposer();
  if (!el) return false;

  el.focus();

  // Estrategia 1: execCommand("insertText").
  // Ainda funciona no Chrome/Edge para contenteditable e dispara os
  // eventos (beforeinput/input) que o editor do WhatsApp escuta.
  try {
    if (document.execCommand("insertText", false, text)) {
      log("insertText: execCommand ok");
      return true;
    }
  } catch {
    /* segue */
  }

  // Estrategia 2: beforeinput sintetico + execCommand.
  try {
    el.dispatchEvent(
      new InputEvent("beforeinput", {
        inputType: "insertText",
        data: text,
        bubbles: true,
        cancelable: true,
      })
    );
    document.execCommand("insertText", false, text);
    log("insertText: beforeinput + execCommand");
    return true;
  } catch {
    /* segue */
  }

  // Estrategia 3: evento paste sintetico.
  try {
    const dt = new DataTransfer();
    dt.setData("text/plain", text);
    el.dispatchEvent(
      new ClipboardEvent("paste", { clipboardData: dt, bubbles: true, cancelable: true })
    );
    log("insertText: paste sintetico");
    return true;
  } catch (e) {
    log("insertText: todas as estrategias falharam", e);
    return false;
  }
}

/**
 * Substitui o `token` que o usuario acabou de digitar (ex.: "/horario")
 * pelo `text` final. Seleciona os ultimos token.length caracteres antes
 * do cursor e escreve por cima. Se a selecao falhar, apaga caractere a
 * caractere como fallback.
 * @returns {boolean}
 */
export function replaceTrailingToken(token, text) {
  const el = getComposer();
  if (!el || typeof token !== "string" || token.length === 0) return false;
  el.focus();

  let selectionAdjusted = false;
  try {
    const sel = window.getSelection();
    if (sel && sel.rangeCount) {
      const range = sel.getRangeAt(0).cloneRange();
      const node = range.startContainer;
      const offset = range.startOffset;
      if (node.nodeType === Node.TEXT_NODE && offset >= token.length) {
        range.setStart(node, offset - token.length);
        sel.removeAllRanges();
        sel.addRange(range);
        selectionAdjusted = true;
      }
    }
  } catch {
    selectionAdjusted = false;
  }

  if (!selectionAdjusted) {
    // Fallback: apaga o token caractere a caractere.
    try {
      for (let i = 0; i < token.length; i++) {
        document.execCommand("delete", false);
      }
    } catch {
      /* ultimo caso: apenas insere, deixando o token visivel */
    }
  }

  return insertText(text);
}

/** Texto atual do campo de mensagem (para a aba Ferramentas). "" se nao houver. */
export function getComposerText() {
  const el = getComposer();
  if (!el) return "";
  return (el.innerText || el.textContent || "").replace(/ /g, " ");
}

/**
 * Substitui TODO o conteudo do campo por `text` (sem enviar).
 * Seleciona tudo e escreve por cima.
 * @returns {boolean}
 */
export function setComposerText(text) {
  const el = getComposer();
  if (!el) return false;
  el.focus();
  try {
    document.execCommand("selectAll", false);
    if (document.execCommand("insertText", false, String(text))) return true;
  } catch {
    /* segue para o fallback */
  }
  // Fallback: limpa via delete e insere.
  try {
    document.execCommand("selectAll", false);
    document.execCommand("delete", false);
  } catch {
    /* ignora */
  }
  return insertText(String(text));
}
