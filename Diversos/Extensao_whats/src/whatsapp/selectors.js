/*
 * selectors.js — TODOS os seletores do WhatsApp Web ficam AQUI.
 * ------------------------------------------------------------------
 * O WhatsApp Web muda o DOM com frequencia. Se algo parar de
 * funcionar, ajuste SOMENTE este arquivo.
 *
 * Cada campo e uma lista de candidatos em ordem de preferencia:
 *   1) o mais semantico / estavel (role, aria, data-* estrutural)
 *   ...
 *   n) o mais fragil (ultimo recurso)
 *
 * NUNCA dependa de classes ofuscadas do tipo ".x1n2onr6 .x1iyjqo2".
 */

export const SEL = {
  // Raiz do app (indica que o WhatsApp montou).
  appRoot: [
    "#app .two",
    "#app .app-wrapper-web",
    "#app",
  ],

  // Container da conversa aberta a direita.
  main: [
    "#main",
    "div[id='main']",
  ],

  // Campo de digitacao (composer). E um contenteditable dentro do footer.
  composer: [
    "#main footer div[contenteditable='true'][data-tab]",
    "#main footer [role='textbox'][contenteditable='true']",
    "footer div[contenteditable='true'][data-tab]",
    "div[contenteditable='true'][data-tab='10']",
    "#main footer div[contenteditable='true']",
  ],

  // Rodape onde ancoramos a caixa de sugestao.
  footer: [
    "#main footer",
    "footer.copyable-area",
    "#main > footer",
    "footer",
  ],

  // Cabecalho da conversa (reservado para features futuras: notas/favoritos).
  conversationHeader: [
    "#main header",
    "#main > header",
  ],

  // Titulo/nome do contato ou grupo no cabecalho.
  conversationTitle: [
    "#main header span[title][dir='auto']",
    "#main header span[title]",
    "#main header [role='button'] span[dir='auto']",
  ],

  // Elementos de mensagem que carregam data-id (usado so para extrair o JID
  // da conversa: "false_<jid>_<msgid>"). Relativo a #main.
  messageWithId: [
    "div[role='row'] [data-id]",
    "[data-id][class*='message']",
    "[data-id]",
  ],

  // ---- Modo privacidade de tela (SO CSS: desfoca a propria tela) ----
  pvNames: [
    "#pane-side [role='listitem'] span[title]",
    "#pane-side [role='row'] span[title]",
    "#main header span[title][dir='auto']",
  ],
  pvPhotos: [
    "#pane-side [role='listitem'] img[draggable='false']",
    "#pane-side [role='row'] img[draggable='false']",
    "#main header img[draggable='false']",
  ],
  pvPreviews: [
    "#pane-side [role='listitem'] [role='gridcell']:last-child span[dir='auto']",
    "#pane-side [role='listitem'] span[dir='auto']:not([title])",
  ],
  pvConversation: [
    "#main [data-pre-plain-text]",
    "#main .copyable-text span.selectable-text",
    "#main div[class*='message-in'] span.selectable-text",
    "#main div[class*='message-out'] span.selectable-text",
  ],
  pvComposer: [
    "#main footer div[contenteditable='true']",
  ],

  // ---- Fundo da conversa (SO CSS) — a area de rolagem das mensagens ----
  chatBgTarget: [
    "#main .copyable-area",
    "#main div[role='application']",
    "#main [data-tab='8']",
  ],

  // Barra vertical de icones a esquerda (UI nova do WhatsApp):
  // conversas, chamadas, status, canais, comunidades, Meta AI...
  // Ancoramos nosso botao NESTA coluna. O data-testid e o hook mais estavel.
  navRail: [
    "[data-testid='navbar-primary-section']",
    "header[role='navigation']",
    "#app nav[role='navigation']",
    "#app [role='navigation']",
  ],

  // Cada botao de navegacao dentro da barra (relativo a navRail).
  // Usamos o ULTIMO para ancorar nosso botao logo abaixo, sem sobrepor.
  navItem: ["[data-navbar-item='true']"],

  // Campo de busca de conversas (painel esquerdo). Usado para "Abrir
  // favorito": filtramos a lista pelo nome e o usuario clica.
  chatSearch: [
    "#side [aria-label='Pesquisar']",
    "#side [aria-label='Caixa de texto de pesquisa']",
    "[aria-label='Search input textbox']",
    "#side div[contenteditable='true'][data-tab='3']",
    "#side div[contenteditable='true']",
  ],
};

/**
 * Retorna o primeiro elemento que casar com algum seletor da lista.
 * Ignora seletores invalidos em navegadores antigos.
 */
export function pick(list, root = document) {
  for (const selector of list) {
    try {
      const el = root.querySelector(selector);
      if (el) return el;
    } catch {
      /* seletor nao suportado — tenta o proximo */
    }
  }
  return null;
}

/** Versao que retorna todos os elementos do primeiro seletor que casar. */
export function pickAll(list, root = document) {
  for (const selector of list) {
    try {
      const nodes = root.querySelectorAll(selector);
      if (nodes && nodes.length) return Array.from(nodes);
    } catch {
      /* ignora */
    }
  }
  return [];
}
