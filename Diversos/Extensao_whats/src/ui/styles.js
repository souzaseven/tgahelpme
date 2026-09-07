/*
 * styles.js — CSS da extensao como string, injetado DENTRO do Shadow DOM.
 * ------------------------------------------------------------------
 * Isolamento total:
 *  - Todo CSS vive no shadow root -> nao vaza para o WhatsApp.
 *  - O shadow root protege nossa UI do CSS do WhatsApp.
 *  - Ainda assim, todas as classes usam o prefixo "tgaw-".
 *
 * Tema: as cores sao custom properties trocadas por
 * [data-theme="light"|"dark"] no elemento .tgaw-root.
 */

const TOKENS = `
  .tgaw-root {
    --tgaw-font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    --tgaw-radius: 12px;
    --tgaw-accent: #00a884;
    --tgaw-accent-fg: #ffffff;
    all: initial;
    font-family: var(--tgaw-font);
    line-height: 1.4;
  }
  .tgaw-root[data-theme="light"] {
    --tgaw-bg: #ffffff;
    --tgaw-fg: #111b21;
    --tgaw-muted: #667781;
    --tgaw-border: #e9edef;
    --tgaw-hover: #f5f6f6;
    --tgaw-chip: #eef4f2;
    --tgaw-shadow: 0 8px 28px rgba(11, 20, 26, 0.18);
  }
  .tgaw-root[data-theme="dark"] {
    --tgaw-bg: #233138;
    --tgaw-fg: #e9edef;
    --tgaw-muted: #8696a0;
    --tgaw-border: #2f3b43;
    --tgaw-hover: #2a3942;
    --tgaw-chip: #1f2c33;
    --tgaw-shadow: 0 8px 28px rgba(0, 0, 0, 0.5);
  }
`;

export const PANEL_CSS = `
  ${TOKENS}

  .tgaw-root *,
  .tgaw-root *::before,
  .tgaw-root *::after { box-sizing: border-box; }
  .tgaw-root [hidden] { display: none !important; }

  /* ---------- Botao flutuante (FAB) ---------- */
  .tgaw-fab {
    position: fixed;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: var(--tgaw-accent);
    color: var(--tgaw-accent-fg);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: var(--tgaw-shadow);
    z-index: 2147483000;
    padding: 0;
    opacity: 0.85;
    touch-action: none;
    transition: opacity .15s ease, transform .15s ease;
  }
  .tgaw-fab:hover { opacity: 1; }
  .tgaw-fab:active { transform: scale(0.94); }
  .tgaw-fab:focus-visible { outline: 2px solid var(--tgaw-accent); outline-offset: 2px; }
  .tgaw-fab svg { width: 18px; height: 18px; display: block; }

  /* Posicao livre (arrastada pelo usuario): circulo visivel em qualquer fundo. */
  .tgaw-pos-custom { right: auto; bottom: auto; opacity: 1; cursor: grab; }
  .tgaw-pos-custom:active { cursor: grabbing; }
  .tgaw-dragging { opacity: 1; transition: none; cursor: grabbing; }

  .tgaw-pos-bottom-right { right: 16px; bottom: 96px; }
  .tgaw-pos-top-right    { right: 16px; top: 72px; }
  .tgaw-pos-right-center { right: 12px; top: 50%; transform: translateY(-50%); }
  .tgaw-pos-right-center:active { transform: translateY(-50%) scale(0.94); }

  /* Ancorado a coluna de icones do WhatsApp — left/top vem do JS.
     Combina com os itens nativos: sem circulo, so o raio na cor de destaque. */
  .tgaw-pos-sidebar {
    right: auto;
    bottom: auto;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: transparent;
    color: var(--tgaw-accent);
    box-shadow: none;
    opacity: 1;
  }
  .tgaw-pos-sidebar:hover { background: var(--tgaw-hover); opacity: 1; }
  .tgaw-pos-sidebar:active { transform: scale(0.92); }
  .tgaw-pos-sidebar svg { width: 22px; height: 22px; }

  /* ---------- Painel ---------- */
  .tgaw-panel {
    position: fixed;
    width: 328px;
    height: 520px;
    min-width: 280px;
    min-height: 220px;
    max-width: calc(100vw - 16px);
    max-height: calc(100vh - 16px);
    background: var(--tgaw-bg);
    color: var(--tgaw-fg);
    border: 1px solid var(--tgaw-border);
    border-radius: var(--tgaw-radius);
    box-shadow: var(--tgaw-shadow);
    z-index: 2147483001;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    font-size: 13px;
    resize: both;
  }
  .tgaw-panel[hidden] { display: none; }
  /* dica visual do canto redimensionavel (nao intercepta o arraste nativo) */
  .tgaw-panel::after {
    content: "";
    position: absolute;
    right: 3px;
    bottom: 3px;
    width: 11px;
    height: 11px;
    pointer-events: none;
    opacity: .55;
    background: linear-gradient(135deg,
      transparent 0 44%, var(--tgaw-muted) 44% 52%, transparent 52% 66%,
      var(--tgaw-muted) 66% 74%, transparent 74% 100%);
  }

  .tgaw-panel-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border-bottom: 1px solid var(--tgaw-border);
  }
  .tgaw-title { font-weight: 600; font-size: 13px; flex: 1; }
  .tgaw-iconbtn {
    border: none;
    background: transparent;
    color: var(--tgaw-muted);
    cursor: pointer;
    width: 26px;
    height: 26px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    line-height: 1;
  }
  .tgaw-iconbtn:hover { background: var(--tgaw-hover); color: var(--tgaw-fg); }
  .tgaw-iconbtn:focus-visible { outline: 2px solid var(--tgaw-accent); outline-offset: 1px; }

  .tgaw-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    padding: 8px 10px;
    border-bottom: 1px solid var(--tgaw-border);
  }
  .tgaw-tab {
    border: none;
    background: transparent;
    color: var(--tgaw-muted);
    cursor: pointer;
    padding: 5px 9px;
    border-radius: 7px;
    font-size: 11.5px;
    font-weight: 600;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
  }
  .tgaw-tab:hover { background: var(--tgaw-hover); }
  .tgaw-tab[aria-selected="true"] {
    background: var(--tgaw-accent);
    color: var(--tgaw-accent-fg);
  }
  .tgaw-tab:focus-visible { outline: 2px solid var(--tgaw-accent); outline-offset: 1px; }
  .tgaw-tab-dot {
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--tgaw-accent);
    margin-left: 5px;
    flex: 0 0 auto;
  }
  .tgaw-tab[aria-selected="true"] .tgaw-tab-dot { background: #fff; }
  .tgaw-tab-badge {
    display: inline-block;
    min-width: 15px;
    height: 15px;
    line-height: 15px;
    text-align: center;
    background: #ea4335;
    color: #fff;
    border-radius: 999px;
    font-size: 9.5px;
    font-weight: 700;
    padding: 0 3px;
    margin-left: 4px;
    vertical-align: middle;
  }

  /* Aviso de lembrete */
  .tgaw-rembanner {
    position: fixed;
    top: 14px;
    left: 50%;
    transform: translateX(-50%);
    width: 300px;
    max-width: calc(100vw - 24px);
    background: var(--tgaw-bg);
    color: var(--tgaw-fg);
    border: 1px solid var(--tgaw-border);
    border-left: 3px solid var(--tgaw-accent);
    border-radius: 10px;
    box-shadow: var(--tgaw-shadow);
    z-index: 2147483003;
    padding: 10px 12px;
    font-size: 13px;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .tgaw-rembanner[hidden] { display: none; }
  .tgaw-rembanner-title { font-weight: 700; font-size: 12px; }
  .tgaw-rembanner .tgaw-row .tgaw-btn { flex: 1; }

  /* Lista de lembretes */
  .tgaw-rem-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    color: var(--tgaw-muted);
    margin-top: 2px;
  }
  .tgaw-rem-overdue { color: #ea4335; font-weight: 600; }
  .tgaw-rem-done .tgaw-item-title { text-decoration: line-through; opacity: .6; }

  .tgaw-body {
    padding: 10px 12px 12px;
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-gutter: stable;
    flex: 1;
  }

  .tgaw-field {
    width: 100%;
    padding: 7px 9px;
    border: 1px solid var(--tgaw-border);
    border-radius: 8px;
    background: var(--tgaw-bg);
    color: var(--tgaw-fg);
    font: inherit;
    font-size: 13px;
  }
  .tgaw-field:focus-visible { outline: 2px solid var(--tgaw-accent); outline-offset: 0; border-color: transparent; }
  textarea.tgaw-field { resize: vertical; min-height: 56px; }
  textarea.tgaw-note { min-height: 130px; line-height: 1.45; }

  .tgaw-search { margin-bottom: 8px; }

  /* Filtro por categoria (chips) */
  .tgaw-cat-row { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 10px; }
  .tgaw-cat-chip {
    border: 1px solid var(--tgaw-border);
    background: var(--tgaw-bg);
    color: var(--tgaw-muted);
    border-radius: 999px;
    padding: 3px 10px;
    font: inherit;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
  }
  .tgaw-cat-chip:hover { background: var(--tgaw-hover); }
  .tgaw-cat-chip[aria-pressed="true"] {
    background: var(--tgaw-accent);
    border-color: var(--tgaw-accent);
    color: var(--tgaw-accent-fg);
  }
  .tgaw-cat-chip:focus-visible { outline: 2px solid var(--tgaw-accent); outline-offset: 1px; }

  /* Cabecalho de grupo na lista */
  .tgaw-group-head {
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .03em;
    text-transform: uppercase;
    color: var(--tgaw-muted);
    margin: 8px 0 2px;
    padding-top: 4px;
  }
  .tgaw-group-head:first-child { margin-top: 0; padding-top: 0; }
  .tgaw-chip-cat { background: transparent; border: 1px solid var(--tgaw-border); }

  /* Tira de saudacao no topo da aba Respostas */
  .tgaw-greet-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
  }
  .tgaw-greet-label { font-size: 11.5px; color: var(--tgaw-muted); }
  .tgaw-greet-btn { padding: 5px 12px; }

  /* ---------- Tags da conversa ---------- */
  .tgaw-tag-block { margin-bottom: 10px; }
  .tgaw-tag-row { display: flex; flex-wrap: wrap; gap: 5px; }
  .tgaw-tag {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    border: 1px solid var(--tgaw-border);
    background: transparent;
    border-radius: 999px;
    padding: 3px 9px;
    font: inherit;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    line-height: 1.3;
  }
  .tgaw-tag[data-active="true"] { color: #fff; }
  .tgaw-tag:focus-visible { outline: 2px solid var(--tgaw-accent); outline-offset: 1px; }
  .tgaw-tag-x { font-weight: 800; opacity: .9; margin-left: 1px; }
  .tgaw-tag-add { color: var(--tgaw-muted); border-style: dashed; }
  .tgaw-tag-editlink { padding: 2px 4px; font-size: 10.5px; margin-top: 5px; }
  .tgaw-newtag { margin-top: 6px; }

  /* ---------- Favoritos / Nova conversa ---------- */
  .tgaw-newchat { align-items: center; }
  .tgaw-row > .tgaw-nc-ddi { flex: 0 0 56px; max-width: 56px; text-align: center; }
  .tgaw-fav-current { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
  .tgaw-fav-current .tgaw-hint { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

  /* ---------- Notas ---------- */
  .tgaw-note-conv { font-size: 12.5px; color: var(--tgaw-muted); margin-bottom: 8px; }
  .tgaw-note-conv .tgaw-strong { color: var(--tgaw-fg); font-weight: 600; }
  .tgaw-note-meta { color: var(--tgaw-muted); font-size: 11px; margin-top: 6px; }

  /* ---------- Texto / Ferramentas ---------- */
  textarea.tgaw-tools-input { min-height: 92px; }
  .tgaw-tool-label {
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .03em;
    text-transform: uppercase;
    color: var(--tgaw-muted);
    margin: 12px 0 5px;
  }
  .tgaw-tool-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
  .tgaw-tool-fmt { grid-template-columns: repeat(4, 1fr); }
  .tgaw-tool-doc { grid-template-columns: repeat(3, 1fr); }
  .tgaw-tool-btn {
    font-size: 11.5px;
    padding: 6px 4px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .tgaw-tool-actions { margin-top: 8px; }
  .tgaw-tool-actions .tgaw-btn { flex: 1; }
  .tgaw-calc-out { font-size: 12.5px; font-weight: 600; color: var(--tgaw-fg); margin-top: 5px; min-height: 16px; }
  .tgaw-calc-pct { margin-top: 8px; align-items: center; }
  .tgaw-calc-mini { max-width: 72px; flex: 0 0 auto; }
  .tgaw-calc-x { color: var(--tgaw-muted); font-size: 12px; white-space: nowrap; }

  /* ---------- Lista de respostas ---------- */
  .tgaw-list { display: flex; flex-direction: column; gap: 6px; }
  .tgaw-item {
    border: 1px solid var(--tgaw-border);
    border-radius: 8px;
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .tgaw-item-top { display: flex; align-items: center; gap: 6px; }
  .tgaw-item-title { font-weight: 600; font-size: 12.5px; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .tgaw-chip {
    background: var(--tgaw-chip);
    color: var(--tgaw-muted);
    border-radius: 5px;
    padding: 1px 6px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
  }
  .tgaw-item-text {
    color: var(--tgaw-muted);
    font-size: 12px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
  .tgaw-item-actions { display: flex; gap: 6px; margin-top: 2px; }

  .tgaw-btn {
    border: 1px solid var(--tgaw-border);
    background: var(--tgaw-bg);
    color: var(--tgaw-fg);
    border-radius: 7px;
    padding: 5px 10px;
    font: inherit;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
  }
  .tgaw-btn:hover { background: var(--tgaw-hover); }
  .tgaw-btn:focus-visible { outline: 2px solid var(--tgaw-accent); outline-offset: 1px; }
  .tgaw-btn-primary {
    background: var(--tgaw-accent);
    border-color: var(--tgaw-accent);
    color: var(--tgaw-accent-fg);
  }
  .tgaw-btn-primary:hover { filter: brightness(1.05); background: var(--tgaw-accent); }
  .tgaw-btn-link {
    border: none;
    background: transparent;
    color: var(--tgaw-muted);
    padding: 5px 6px;
  }
  .tgaw-btn-link:hover { background: var(--tgaw-hover); color: var(--tgaw-fg); }
  .tgaw-btn-danger:hover { color: #ea4335; }

  .tgaw-row { display: flex; gap: 8px; align-items: center; }
  .tgaw-row > .tgaw-field { flex: 1; }

  .tgaw-empty {
    color: var(--tgaw-muted);
    text-align: center;
    padding: 20px 8px;
    font-size: 12.5px;
  }

  .tgaw-form { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--tgaw-border); }
  .tgaw-var-row { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; }
  .tgaw-var-row .tgaw-hint { margin-right: 2px; }
  .tgaw-var-chip { font-size: 11px; padding: 3px 6px; border: 1px solid var(--tgaw-border); border-radius: 6px; }
  .tgaw-var-chip:hover { background: var(--tgaw-hover); color: var(--tgaw-fg); }
  .tgaw-form[hidden] { display: none; }
  .tgaw-form-errors { color: #ea4335; font-size: 12px; }
  .tgaw-form-errors[hidden] { display: none; }

  /* ---------- Configuracoes ---------- */
  .tgaw-setting { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
  .tgaw-setting label { font-weight: 600; font-size: 12.5px; }
  .tgaw-setting .tgaw-hint { color: var(--tgaw-muted); font-size: 11.5px; font-weight: 400; }
  .tgaw-check { flex-direction: row; align-items: center; gap: 8px; }
  .tgaw-pv-sub { margin-left: 22px; margin-bottom: 4px; }
  .tgaw-pv-sub label { font-weight: 400; font-size: 12px; }
  .tgaw-pv-sub input:disabled + label { opacity: .5; }

  .tgaw-sort-sel { flex: 0 0 auto; max-width: 150px; font-size: 11.5px; padding: 4px 6px; }
  .tgaw-color { max-width: 90px; flex: 0 0 auto; }

  .tgaw-stat-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; }
  .tgaw-stat {
    border: 1px solid var(--tgaw-border);
    border-radius: 8px;
    padding: 7px 6px;
    text-align: center;
  }
  .tgaw-stat-v { font-size: 16px; font-weight: 700; color: var(--tgaw-fg); }
  .tgaw-stat-l { font-size: 10px; color: var(--tgaw-muted); line-height: 1.2; margin-top: 2px; }

  .tgaw-fmt-preview {
    border: 1px dashed var(--tgaw-border);
    border-radius: 8px;
    padding: 7px 9px;
    font-size: 13px;
    min-height: 20px;
    white-space: pre-wrap;
    word-break: break-word;
    color: var(--tgaw-fg);
  }
  .tgaw-fmt-preview code {
    background: var(--tgaw-chip);
    border-radius: 4px;
    padding: 0 3px;
    font-family: ui-monospace, Menlo, Consolas, monospace;
    font-size: 12px;
  }

  /* ---------- Modo compacto ---------- */
  .tgaw-root[data-compact="1"] .tgaw-body { padding: 7px 9px; }
  .tgaw-root[data-compact="1"] .tgaw-list { gap: 4px; }
  .tgaw-root[data-compact="1"] .tgaw-item { padding: 6px; gap: 3px; }
  .tgaw-root[data-compact="1"] .tgaw-btn { padding: 4px 8px; font-size: 11.5px; }
  .tgaw-root[data-compact="1"] .tgaw-item-text { -webkit-line-clamp: 1; }
  .tgaw-root[data-compact="1"] .tgaw-setting { margin-bottom: 8px; }
  .tgaw-root[data-compact="1"] .tgaw-greet-row,
  .tgaw-root[data-compact="1"] .tgaw-cat-row { margin-bottom: 7px; }
  .tgaw-root[data-compact="1"] .tgaw-panel-header { padding: 7px 10px; }
  .tgaw-root[data-compact="1"] .tgaw-tabs { padding: 6px 8px; }
  .tgaw-check input { width: 15px; height: 15px; accent-color: var(--tgaw-accent); }
  .tgaw-sep { border: none; border-top: 1px solid var(--tgaw-border); margin: 12px 0; }
  select.tgaw-field { cursor: pointer; }

  .tgaw-footnote { color: var(--tgaw-muted); font-size: 11px; margin-top: 10px; }
`;

export const SUGGESTIONS_CSS = `
  ${TOKENS}

  .tgaw-root *,
  .tgaw-root *::before,
  .tgaw-root *::after { box-sizing: border-box; }
  .tgaw-root [hidden] { display: none !important; }

  .tgaw-sug {
    position: fixed;
    z-index: 2147483002;
    width: 340px;
    max-width: calc(100vw - 24px);
    background: var(--tgaw-bg);
    color: var(--tgaw-fg);
    border: 1px solid var(--tgaw-border);
    border-radius: 10px;
    box-shadow: var(--tgaw-shadow);
    overflow: hidden;
    font-size: 13px;
  }
  .tgaw-sug[hidden] { display: none; }
  .tgaw-sug-head {
    font-size: 11px;
    font-weight: 600;
    color: var(--tgaw-muted);
    padding: 6px 10px;
    border-bottom: 1px solid var(--tgaw-border);
    display: flex;
    justify-content: space-between;
  }
  .tgaw-sug-list { max-height: 240px; overflow-y: auto; }
  .tgaw-sug-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 7px 10px;
    cursor: pointer;
    border-left: 3px solid transparent;
  }
  .tgaw-sug-item:hover,
  .tgaw-sug-item[data-active="true"] {
    background: var(--tgaw-hover);
    border-left-color: var(--tgaw-accent);
  }
  .tgaw-sug-item-top { display: flex; gap: 6px; align-items: center; }
  .tgaw-sug-item-title { font-weight: 600; font-size: 12.5px; }
  .tgaw-sug-item-text {
    color: var(--tgaw-muted);
    font-size: 12px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
`;
