/*
 * loader.js  (content script classico, carregado pelo manifest)
 * ------------------------------------------------------------------
 * Unica responsabilidade: importar dinamicamente o modulo principal
 * (content.js) como ES module. Assim todo o resto do codigo pode usar
 * import/export normalmente, sem build.
 *
 * Regras do master prompt aplicadas aqui:
 *  - Fail safe: se o import falhar, o WhatsApp continua funcionando.
 *  - Roda apenas na aba principal (all_frames: false + guarda abaixo).
 */
(() => {
  "use strict";

  // Nunca roda dentro de iframes.
  if (window.top !== window.self) return;

  // Evita dupla execucao caso o content script seja injetado 2x.
  if (window.__tgawLoaderRan) return;
  window.__tgawLoaderRan = true;

  const url = chrome.runtime.getURL("src/content/content.js");
  import(url).catch((err) => {
    // Log minimo em producao; nunca lanca para nao afetar a pagina.
    console.error("[TGAW] Falha ao carregar o modulo principal:", err);
  });
})();
