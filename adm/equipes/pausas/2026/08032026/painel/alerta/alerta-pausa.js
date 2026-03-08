// ============================================================
// alerta-pausa.js
// Monitor contínuo de pausa excessiva + WhatsApp (ESTÁVEL FINAL)
// ============================================================

console.log("%c[ALERTA-PAUSA] Monitor iniciado", "color:#dc2626;font-weight:bold;");

(function () {

  const cfg = window.ALERTA_PAUSA_CONFIG;
  if (!cfg) {
    console.warn("[ALERTA-PAUSA] Config não encontrada");
    return;
  }

  function log(...args) {
    if (cfg.debug) console.log("[ALERTA-PAUSA]", ...args);
  }

  // ==========================================================
  // ENVIO WHATSAPP (MANUAL / AUTO)
  // ==========================================================
  async function enviarWhats(id, origem) {

    if (!window.enviarAlertaWhatsApp) {
      console.warn("[ALERTA-PAUSA] Função enviarAlertaWhatsApp não carregada");
      return { success: false, erro: "Função WhatsApp não disponível" };
    }

    log(`Tentando envio ${origem.toUpperCase()} WhatsApp`, id);

    try {
      const r = await window.enviarAlertaWhatsApp({
        id,
        origem,
        enviado_por: origem === "auto" ? "sistema" : "supervisao"
      });

      if (r?.success) {
        console.warn(`[WHATSAPP] ${origem.toUpperCase()} OK`, r);
      } else {
        console.error(`[WHATSAPP] ${origem.toUpperCase()} FALHOU`, r);
      }

      return r;

    } catch (e) {
      console.error("[WHATSAPP] ERRO AO ENVIAR", e);
      return { success: false, erro: e };
    }
  }

  // ==========================================================
  // PROCESSAMENTO PRINCIPAL
  // ==========================================================
  function processarAlertas() {

    document.querySelectorAll(".linha-participante").forEach(card => {

      const tempoEl = card.querySelector(".tempo-pausa");
      const nomeEl  = card.querySelector(".nome");
      const id      = Number(card.dataset.id || 0);

      if (!tempoEl || !id) return;

      const segundos = Number(tempoEl.dataset.segundos || 0);

      // ------------------------------------------------------
      // Saiu da pausa → limpa tudo
      // ------------------------------------------------------
      if (segundos <= 0) {
        card.classList.remove(cfg.CLASSE_ALERTA, "alerta-pausa");
        nomeEl?.classList.remove("alerta-pausa-nome");
        card.querySelector(".btn-alerta-whatsapp")?.remove();
        card.__whatsapp_auto_enviado = false;
        return;
      }

      // ------------------------------------------------------
      // 🔴 ALERTA VISUAL — 20 minutos
      // ------------------------------------------------------
      if (segundos >= cfg.LIMITE_MINUTOS * 60) {
        card.classList.add(cfg.CLASSE_ALERTA, "alerta-pausa");
        nomeEl?.classList.add("alerta-pausa-nome");
      }

      // ------------------------------------------------------
      // 🟡 BOTÃO MANUAL — 18:55
      // ------------------------------------------------------
      if (segundos >= cfg.tempoExibirBotao) {

        let btn = card.querySelector(".btn-alerta-whatsapp");

        if (!btn) {
          btn = document.createElement("button");
          btn.className = "btn-alerta-whatsapp";
          btn.innerHTML = "📲 Avisar WhatsApp";

          btn.onclick = async () => {
            btn.disabled = true;
            btn.innerHTML = "⏳ Enviando...";

            const r = await enviarWhats(id, "manual");

            if (r?.success) {
              btn.innerHTML = "✅ Aviso enviado";
              card.__whatsapp_auto_enviado = true;
            } else {
              btn.disabled = false;
              btn.innerHTML = "📲 Avisar WhatsApp";
            }
          };

          card.appendChild(btn);
          log("Botão WhatsApp exibido", id);
        }
      }

      // ------------------------------------------------------
      // 🔥 AUTO-ENVIO — 19:00 (repete até sucesso)
      // ------------------------------------------------------
      if (
        segundos >= cfg.tempoAutoEnvio &&
        !card.__whatsapp_auto_enviado
      ) {
        enviarWhats(id, "auto").then(r => {

          if (r?.success) {
            card.__whatsapp_auto_enviado = true;

            const btn = card.querySelector(".btn-alerta-whatsapp");
            if (btn) {
              btn.disabled = true;
              btn.innerHTML = "✅ Aviso enviado";
            }
          }

        });
      }

    });
  }

  // ==========================================================
  // LOOP CONTÍNUO
  // ==========================================================
  setInterval(processarAlertas, cfg.intervaloCheck);
  processarAlertas();

})();
