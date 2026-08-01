/* ============================================================
   theme-toggle.js — alternância de tema claro/escuro
   Arquivo aditivo e independente: não interfere em nenhum
   script existente do sistema (login.js, painel etc).
   Persistência em localStorage sob a chave "tga_theme".
============================================================ */
(function () {
    const CHAVE = "tga_theme";
    const raiz = document.documentElement;

    function aplicarTema(tema) {
        if (tema === "light") {
            raiz.setAttribute("data-theme", "light");
        } else {
            raiz.removeAttribute("data-theme");
        }

        const btn = document.getElementById("btn-tema");
        if (btn) btn.textContent = tema === "light" ? "☀️" : "🌙";
    }

    const salvo = localStorage.getItem(CHAVE);
    aplicarTema(salvo === "light" ? "light" : "dark");

    document.addEventListener("DOMContentLoaded", () => {
        aplicarTema(salvo === "light" ? "light" : "dark");

        const btn = document.getElementById("btn-tema");
        if (!btn) return;

        btn.addEventListener("click", () => {
            const atual = raiz.getAttribute("data-theme") === "light" ? "light" : "dark";
            const proximo = atual === "light" ? "dark" : "light";
            aplicarTema(proximo);
            localStorage.setItem(CHAVE, proximo);
        });
    });
})();
