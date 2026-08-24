/**
 * tema.js
 * ------------------------------------------------------------------
 * Alternância de modo claro/escuro.
 *
 * A escolha do usuário fica em localStorage (chave abaixo) e sempre
 * tem prioridade sobre a preferência do sistema. Sem escolha salva,
 * o CSS já resolve sozinho via `prefers-color-scheme` (ver style.css)
 * — este arquivo só entra em ação quando o usuário clica no botão,
 * ou para manter o ícone do botão sincronizado com o tema em uso.
 *
 * O atributo data-theme já é aplicado antes da primeira pintura por
 * um script inline no <head> do index.html (evita "flash" de tema
 * errado) — aqui só cuidamos da interação depois que a página carrega.
 * ------------------------------------------------------------------
 */
window.CentralBoletos = window.CentralBoletos || {};

(function () {
  const CHAVE = "central-boletos-tema";

  function temaSalvo() {
    try {
      return localStorage.getItem(CHAVE);
    } catch (e) {
      return null;
    }
  }

  function temaAtualEfetivo() {
    const salvo = temaSalvo();
    if (salvo === "dark" || salvo === "light") return salvo;
    return window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
  }

  function aplicar(tema) {
    document.documentElement.setAttribute("data-theme", tema);
    try {
      localStorage.setItem(CHAVE, tema);
    } catch (e) {
      // localStorage indisponível (modo privado, política do navegador etc.)
      // — a troca ainda funciona nesta sessão, só não persiste.
    }
    atualizarBotao(tema);
  }

  // Ícones em SVG inline (não dependem de fonte de emoji do sistema,
  // que pode faltar em ambientes headless/corporativos e virar um
  // quadrado vazio no lugar do símbolo).
  const ICONE_SOL =
    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4.5"></circle><path d="M12 2.5v2.5M12 19v2.5M4.2 4.2l1.8 1.8M18 18l1.8 1.8M2.5 12H5M19 12h2.5M4.2 19.8L6 18M18 6l1.8-1.8"></path></svg>';
  const ICONE_LUA =
    '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20.4 14.7A8.5 8.5 0 1 1 9.3 3.6a7 7 0 0 0 11.1 11.1z"></path></svg>';

  function atualizarBotao(tema) {
    const botao = document.getElementById("botao-tema");
    if (!botao) return;
    const ehEscuro = tema === "dark";
    // Escuro → mostra o sol (ação: voltar pro claro). Claro → mostra a lua.
    botao.innerHTML = ehEscuro ? ICONE_SOL : ICONE_LUA;
    botao.setAttribute("aria-label", ehEscuro ? "Mudar para modo claro" : "Mudar para modo escuro");
    botao.title = ehEscuro ? "Mudar para modo claro" : "Mudar para modo escuro";
  }

  function alternar() {
    aplicar(temaAtualEfetivo() === "dark" ? "light" : "dark");
  }

  window.addEventListener("DOMContentLoaded", () => {
    atualizarBotao(temaAtualEfetivo());
    const botao = document.getElementById("botao-tema");
    if (botao) botao.addEventListener("click", alternar);

    // Se o usuário nunca escolheu manualmente, acompanha mudanças no
    // sistema operacional em tempo real (ex.: agendamento claro/escuro do Windows).
    if (window.matchMedia) {
      window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", () => {
        if (!temaSalvo()) atualizarBotao(temaAtualEfetivo());
      });
    }
  });

  window.CentralBoletos.tema = { alternar, atual: temaAtualEfetivo };
})();
