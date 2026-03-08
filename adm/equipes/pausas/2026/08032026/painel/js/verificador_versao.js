// ============================================================
// verificador_versao.js
// Força reload quando a versão do sistema muda
// ============================================================

(function () {
    const STORAGE_KEY = "tga_versao_sistema";

    function obterVersaoAtual() {
        const footer = document.querySelector("footer");
        if (!footer) return null;

        // Ex: "TGA – Versão 2025.12.16.02"
        const texto = footer.textContent || "";
        const match = texto.match(/Versão\s+([\d.]+)/i);

        return match ? match[1] : null;
    }

    const versaoAtual = obterVersaoAtual();
    if (!versaoAtual) return;

    const versaoSalva = localStorage.getItem(STORAGE_KEY);

    // Primeira vez
    if (!versaoSalva) {
        localStorage.setItem(STORAGE_KEY, versaoAtual);
        return;
    }

    // Mudou a versão → limpa cache e recarrega
    if (versaoSalva !== versaoAtual) {
        console.warn(
            `[VERSÃO] Alterada de ${versaoSalva} para ${versaoAtual}. Recarregando…`
        );

        // limpa caches locais
        localStorage.clear();
        sessionStorage.clear();

        // salva nova versão
        localStorage.setItem(STORAGE_KEY, versaoAtual);

        // força reload ignorando cache
        location.reload(true);
    }
})();
