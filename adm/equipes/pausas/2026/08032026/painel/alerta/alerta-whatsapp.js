// ============================================================
// alerta-whatsapp.js
// Helper único de envio WhatsApp (manual/auto) + log completo
// ============================================================

(function () {

  const cfg = window.ALERTA_PAUSA_CONFIG || {};

  function log(...args) {
    if (cfg.debug) console.log("[ALERTA-PAUSA]", ...args);
  }

  async function safeReadJson(resp) {
    const text = await resp.text();

    try {
      return JSON.parse(text);
    } catch (e) {
      return {
        success: false,
        erro: "Resposta não-JSON do servidor",
        http_status: resp.status,
        raw: text?.slice(0, 300)
      };
    }
  }

  // ============================================================
  // ENVIO MANUAL / AUTO
  // - PHP espera: id, origem, enviado_por
  // ============================================================
  window.enviarAlertaWhatsApp = async function ({ id, origem = "manual", enviado_por = "sistema" } = {}) {

    if (!id) {
      console.warn("[WHATSAPP] ID ausente");
      return { success: false, erro: "ID ausente" };
    }

    log("[WHATSAPP] Enviando...", { id, origem, enviado_por });

    try {
      const resp = await fetch("../backend/whatsapp/enviar_aviso_manual.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
        body: new URLSearchParams({
          id: String(id),               // ✅ NOME CERTO DO CAMPO
          origem: String(origem),       // manual | auto
          enviado_por: String(enviado_por)
        })
      });

      const r = await safeReadJson(resp);

      console.log("[WHATSAPP] HTTP:", resp.status);
      console.log("[WHATSAPP] RETORNO COMPLETO:", r);

      return r;

    } catch (e) {
      console.error("[WHATSAPP] ERRO FETCH:", e);
      return { success: false, erro: "Falha de rede/servidor", detalhe: String(e) };
    }
  };

})();
