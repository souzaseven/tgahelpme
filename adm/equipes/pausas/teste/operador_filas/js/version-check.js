/* ==========================================================
   version-check.js — Controle de Versão do Frontend
   - Detecta alterações de versão
   - Força reload automático
========================================================== */

(function () {

  const VERSION_URL = "version.json";
  const STORAGE_KEY = "tga_frontend_version";
  const CHECK_INTERVAL = 60 * 1000; // 1 minuto

  async function checkVersion() {
    try {
      const resp = await fetch(`${VERSION_URL}?t=${Date.now()}`, {
        cache: "no-store"
      });

      if (!resp.ok) return;

      const data = await resp.json();
      if (!data.version) return;

      const currentVersion = localStorage.getItem(STORAGE_KEY);

      // Primeira execução
      if (!currentVersion) {
        localStorage.setItem(STORAGE_KEY, data.version);
        return;
      }

      // Versão mudou → força reload
      if (currentVersion !== data.version) {
        console.warn(
          "[VERSÃO] Atualização detectada:",
          currentVersion,
          "→",
          data.version
        );

        localStorage.setItem(STORAGE_KEY, data.version);

        // força reload limpando cache
        window.location.reload(true);
      }

    } catch (e) {
      console.error("[VERSÃO] Erro ao verificar versão", e);
    }
  }

  // Executa ao carregar
  checkVersion();

  // Executa periodicamente
  setInterval(checkVersion, CHECK_INTERVAL);

})();
