// ============================================================
// diagnostico_sistema.js (v3.0 Integrado com Evolux)
// ============================================================
// 🔹 Função: Monitorar, testar e exibir status do sistema de pausas
// 🔹 Módulos monitorados: controle_pausa, integracao_ext, integracao_fila, api_integracao.php
// ============================================================

console.log("%c[Diagnóstico] Módulo de diagnóstico carregado (v3.0)", "color:#00c3ff;font-weight:bold;");

class SistemaDiagnostico {
  constructor() {
    this.endpointPHP = "./php/api_integracao.php?acao=listar";
    this.status = {
      controlePausa: false,
      integracaoExt: false,
      integracaoFila: false,
      evoluxAPI: false
    };

    this.iniciarAutoCheck();
    this.criarBotaoTeste();
  }

  // ============================================================
  // 🚀 Auto verificação inicial
  // ============================================================
  iniciarAutoCheck() {
    setTimeout(() => this.verificarModulos(), 1500);
  }

  // ============================================================
  // 🔍 Verifica presença dos módulos e API
  // ============================================================
  async verificarModulos() {
    console.groupCollapsed("%c[Diagnóstico do Sistema]", "color:#00c3ff;font-weight:bold;");

    // Controle principal
    this.status.controlePausa = typeof window.controle !== "undefined";
    console.log(`🧩 controle_pausa.js → ${this.status.controlePausa ? "✅ OK" : "❌ Falhou"}`);

    // Scraping Evolux
    this.status.integracaoExt = typeof window.atualizarPainelEvolux !== "undefined";
    console.log(`🌐 integracao_ext.js → ${this.status.integracaoExt ? "✅ OK" : "⚠️ Ausente"}`);

    // Sincronização Fila
    this.status.integracaoFila = typeof window.sincronizarFilaComEvolux !== "undefined";
    console.log(`🔁 integracao_fila.js → ${this.status.integracaoFila ? "✅ OK" : "⚠️ Ausente"}`);

    // Teste da API PHP (scraping)
    await this.testarEvoluxAPI();

    console.groupEnd();
  }

  // ============================================================
  // 🔄 Teste direto no PHP (api_integracao.php)
  // ============================================================
  async testarEvoluxAPI() {
    console.log("🧪 Testando comunicação com api_integracao.php ...");
    try {
      const resp = await fetch(this.endpointPHP, { cache: "no-store" });
      const dados = await resp.json();

      if (dados.success && Array.isArray(dados.dados)) {
        this.status.evoluxAPI = true;
        console.log(`✅ Evolux API online (${dados.dados.length} operadores detectados).`);
      } else {
        this.status.evoluxAPI = false;
        console.warn("⚠️ Evolux API respondeu, mas sem dados válidos:", dados);
      }
    } catch (e) {
      this.status.evoluxAPI = false;
      console.error("❌ Falha na comunicação com o PHP (api_integracao):", e);
    }

    // Mostra resultado final no console
    console.table(this.status);
  }

  // ============================================================
  // 🧪 Botão de teste visual no canto inferior direito
  // ============================================================
  criarBotaoTeste() {
    const btn = document.createElement("button");
    btn.textContent = "🧠 Testar Sistema";
    btn.title = "Executa testes rápidos de diagnóstico";
    btn.style.position = "fixed";
    btn.style.bottom = "15px";
    btn.style.right = "15px";
    btn.style.background = "#007ced";
    btn.style.color = "#fff";
    btn.style.border = "none";
    btn.style.padding = "10px 14px";
    btn.style.borderRadius = "8px";
    btn.style.cursor = "pointer";
    btn.style.fontWeight = "600";
    btn.style.zIndex = "9999";
    btn.style.boxShadow = "0 0 12px rgba(0,0,0,0.4)";
    btn.style.transition = "all 0.3s ease";

    btn.onmouseenter = () => (btn.style.transform = "scale(1.08)");
    btn.onmouseleave = () => (btn.style.transform = "scale(1)");
    btn.onclick = async () => {
      btn.textContent = "🔄 Testando...";
      btn.disabled = true;
      btn.style.opacity = "0.7";
      await this.verificarModulos();
      btn.textContent = "🧠 Testar Sistema";
      btn.disabled = false;
      btn.style.opacity = "1";
    };

    document.body.appendChild(btn);
  }
}

// ============================================================
// 🚀 Inicialização automática
// ============================================================
document.addEventListener("DOMContentLoaded", () => {
  window.diagnostico = new SistemaDiagnostico();
});
