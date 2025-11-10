// ============================================================
// main.js - Inicialização central do sistema (v1.6)
// ============================================================
// 🔹 Esta versão NÃO carrega scripts (setup_sistema.php já faz isso)
// 🔹 Apenas valida ambiente e executa diagnóstico visual
// ============================================================

console.log("%c[Main] Iniciando sistema de pausas (v1.6)...", "color:#00ff88;font-weight:bold;");

(() => {
  const operador = localStorage.getItem("operador_nome") || "N/A";
  const modoAdmin = localStorage.getItem("modo_admin") === "true";
  const versao = window.SISTEMA_VERSAO || "v1.0";
  const baseJS = window.SISTEMA_CONFIG?.caminhos?.js || "/adm/equipes/pausas/v1.6/js/";

  console.log(`👤 Operador: ${operador}`);
  if (modoAdmin) console.log("🧑‍💼 [Main] Modo ADMIN ativado.");
  console.log(`📦 Versão: ${versao}`);
  console.log(`📁 Caminho JS: ${baseJS}`);

  // ============================================================
  // 🧠 Diagnóstico automático após inicialização
  // ============================================================
  const verificar = () => {
    console.groupCollapsed("%c[Diagnóstico Final do Sistema]", "color:#00c3ff;font-weight:bold;");
    console.log("🧩 Controle de Pausa:", typeof window.controle !== "undefined" ? "✅ OK" : "❌ Falhou");
    console.log("🌐 Integração Evolux:", typeof window.sincronizarFilaComEvolux !== "undefined" ? "✅ OK" : "⚠️ Ausente");
    console.log("🧠 Diagnóstico:", typeof window.SistemaDiagnostico !== "undefined" ? "✅ OK" : "⚠️ Ausente");
    console.log("📡 API PHP:", window.SISTEMA_CONFIG?.caminhos?.php || "❓ Indefinido");
    console.groupEnd();
  };

  // Aguarda o carregamento completo dos módulos
  setTimeout(verificar, 2000);

  console.log("%c[Main] Sistema de Pausas totalmente inicializado!", "color:#00ff88;font-weight:bold;");
})();
