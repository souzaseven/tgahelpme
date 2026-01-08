/**
 * ===================================================
 * RESUMO DE PAUSAS — EVOLUX
 * BADGE DE CONTAGEM DE ATUALIZAÇÃO
 * ===================================================
 */
(() => {

  console.log("[RESUMO-PAUSAS] script carregado");

  function aguardar() {
    if (
      typeof window.AUTO_REFRESH_MS === "undefined"
    ) {
      setTimeout(aguardar, 300);
      return;
    }

    criarBadge();
    iniciarContagem();
  }

  document.addEventListener("DOMContentLoaded", aguardar);

  /* ===============================
     CRIAR BADGE (COM RETRY)
  =============================== */
  function criarBadge() {
    if (document.getElementById("badgeAtualizacao")) return;

    const tentar = () => {
      const filtros = document.querySelector(".filtros");
      if (!filtros) {
        setTimeout(tentar, 300);
        return;
      }

      const badge = document.createElement("div");
      badge.id = "badgeAtualizacao";
      badge.className = "badge-atualizacao";
      badge.textContent = "⏳ Atualizando...";

      filtros.appendChild(badge);
      console.log("[RESUMO-PAUSAS] badge criado");
    };

    tentar();
  }

  /* ===============================
     CONTAGEM REGRESSIVA
  =============================== */
  function iniciarContagem() {
    const total = Math.floor(window.AUTO_REFRESH_MS / 1000);
    let restante = total;

    setInterval(() => {
      const badge = document.getElementById("badgeAtualizacao");
      if (!badge) return;

      restante--;
      if (restante <= 0) restante = total;

      badge.textContent = `⏳ Atualiza em ${restante}s`;
    }, 1000);
  }

})();
