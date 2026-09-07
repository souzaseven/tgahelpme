/*
 * service-worker.js — background minimo (MV3).
 * ------------------------------------------------------------------
 * So faz o que PRECISA de contexto de extensao:
 *  - abrir o WhatsApp na primeira instalacao;
 *  - repassar o atalho de teclado (Alt+R) para o content script;
 *  - repassar o clique no icone da extensao.
 *
 * Toda a logica do WhatsApp mora no content script, nao aqui.
 */

const WA_URL = "https://web.whatsapp.com/";
const WA_MATCH = /^https:\/\/web\.whatsapp\.com\//;

chrome.runtime.onInstalled.addListener((details) => {
  if (details.reason === "install") {
    chrome.tabs.create({ url: WA_URL }).catch(() => {});
  }
});

function togglePanelOnTab(tab) {
  if (!tab || !tab.id || !WA_MATCH.test(tab.url || "")) return;
  chrome.tabs.sendMessage(tab.id, { type: "TGAW_TOGGLE_PANEL" }).catch(() => {
    /* aba ainda sem content script — ignora */
  });
}

chrome.commands.onCommand.addListener(async (command) => {
  if (command !== "toggle-panel") return;
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  togglePanelOnTab(tab);
});

chrome.action.onClicked.addListener((tab) => {
  togglePanelOnTab(tab);
});
