// =====================================================================
// diagnostico_sistema.js (v1.1)
// Diagnóstico técnico opcional - executado com ?debug=1
// =====================================================================

(() => {
  const modoDebug = window.MODO_DIAGNOSTICO || location.search.includes("debug=1");
  if (!modoDebug) return;

  document.addEventListener("DOMContentLoaded", () => {
    setTimeout(executarDiagnosticoCompleto, 2000);
  });

  function executarDiagnosticoCompleto() {
    const versao = window.SISTEMA_CONFIG?.versao || "v1.1";
    console.debug(`[Diagnóstico] Iniciando diagnóstico completo (versão ${versao})...`);

    const modulos = [
      "sistemaAutenticacao",
      "controle",
      "contadorPausa",
      "contadorEspera",
      "sistemaVoz",
      "sonsNotificacoes"
    ];
    modulos.forEach(mod =>
      console.debug(`[Diagnóstico] ${mod}:`, typeof window[mod] !== "undefined" ? "OK" : "❌ NÃO CARREGADO")
    );

    if (window.sistemaVoz) {
      try {
        const status = sistemaVoz.getStatus?.() || {};
        console.debug("[Voz] Ativa:", status.audioAtivo, "| Gênero:", status.vozGenero);
      } catch (e) {
        console.warn("[Diagnóstico] Falha ao inspecionar sistema de voz:", e);
      }
    }

    if ("Notification" in window) {
      console.debug("[Diagnóstico] Permissão de notificações:", Notification.permission);
    }

    criarBotaoTeste();
  }

  function criarBotaoTeste() {
    if (document.getElementById("btn-teste-sistema")) return;
    const btn = document.createElement("button");
    btn.id = "btn-teste-sistema";
    btn.textContent = "🧪 Testar Sistema";
    btn.style.cssText = `
      position: fixed;
      bottom: 20px;
      right: 20px;
      background: #007ced;
      color: #fff;
      border: none;
      padding: 10px 15px;
      border-radius: 8px;
      cursor: pointer;
      z-index: 9999;
      font-size: 14px;
      font-weight: bold;
      box-shadow: 0 3px 10px rgba(0,0,0,0.3);
    `;
    btn.onclick = () => {
      console.debug("[Diagnóstico] Teste manual iniciado.");
      try {
        if (window.sistemaVoz) {
          sistemaVoz.falarNotificacao("Teste de voz do sistema de pausas");
        }
        if (window.sonsNotificacoes) {
          sonsNotificacoes.tocarSomSucesso();
        }
        alert("✅ Teste de diagnóstico executado com sucesso!");
      } catch (e) {
        console.warn("[Diagnóstico] Erro no teste manual:", e);
      }
    };
    document.body.appendChild(btn);
  }
})();
