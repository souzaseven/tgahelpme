// ===================================================
// painel.js — Painel principal de navegação
// ---------------------------------------------------
// RESPONSABILIDADE:
// - Inicializar o painel principal
// - Centralizar logs do painel
// - Preparar hooks para permissões e navegação
// ---------------------------------------------------
// OBS:
// - NÃO carrega módulos (isso é função do loader.js)
// - NÃO contém regras de negócio
// ===================================================

(function () {

  /**
   * Log padrão do painel
   * Facilita desligar logs no futuro
   */
  function log(...args) {
    console.log("[PAINEL]", ...args);
  }

  /**
   * Inicialização do painel
   */
  function init() {
    log("Painel carregado com sucesso");

    // Futuro:
    // - validar sessão
    // - carregar usuário logado
    // - aplicar permissões
    // - restaurar último módulo aberto
  }

  // ===================================================
  // DOM READY
  // ===================================================
  document.addEventListener("DOMContentLoaded", init);

})();
