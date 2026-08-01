/* ============================================================
   preferencias-extra.js — fechar o painel de Preferências de Fonte
   Arquivo aditivo e independente: não interfere em ajustafonte.js,
   apenas fecha o painel (remove a classe "open" que ele já usa).
============================================================ */
document.addEventListener("DOMContentLoaded", () => {
    const menu = document.getElementById("menu-preferencias");
    const overlay = document.getElementById("overlay-preferencias");
    const btnFechar = document.getElementById("btnFecharPreferencias");

    function fecharPreferencias() {
        menu?.classList.remove("open");
    }

    btnFechar?.addEventListener("click", fecharPreferencias);
    overlay?.addEventListener("click", fecharPreferencias);

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") fecharPreferencias();
    });
});
