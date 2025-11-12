// ============================================================
// diagnostico_sistema.js (v3.2 Integrado com ControlePausa v3.3)
// ============================================================
// 🔹 Função: monitora comunicação com PHP, integradores e cache local
// 🔹 Exibe logs coloridos e resumo de operadores carregados
// ============================================================

if (typeof window.SistemaDiagnostico === "undefined") {
  console.log("%c[Diagnóstico] Módulo de diagnóstico carregado (v3.2)", "color:#00c3ff;font-weight:bold;");

  class SistemaDiagnostico {
    constructor() {
      this.endpointPausa = "./php/controle_pausa.php?acao=get_estado";
      this.endpointOperadores = "./php/listar_operadores.php";
      this.status = {
        controlePausa: false,
        integracaoExt: false,
        integracaoFila: false,
        evoluxAPI: false,
        operadores: 0
      };
      this.iniciarAutoCheck();
      this.criarBotaoTeste();
    }

    // ============================================================
    // ⏱ Inicialização automática
    // ============================================================
    iniciarAutoCheck() {
      setTimeout(() => this.verificarModulos(), 2000);
    }

    // ============================================================
    // 🔍 Verifica módulos e estado de integração
    // ============================================================
    async verificarModulos() {
      console.groupCollapsed("%c[Diagnóstico do Sistema]", "color:#00c3ff;font-weight:bold;");

      // JS Locais
      this.status.controlePausa = typeof window.controle !== "undefined";
      this.status.integracaoExt = typeof window.atualizarPainelEvolux !== "undefined";
      this.status.integracaoFila = typeof window.sincronizarFilaComEvolux !== "undefined";

      console.log(`🧩 controle_pausa.js → ${this.status.controlePausa ? "✅ OK" : "❌ Falhou"}`);
      console.log(`🌐 integracao_ext.js → ${this.status.integracaoExt ? "✅ OK" : "⚠️ Ausente"}`);
      console.log(`🔁 integracao_fila.js → ${this.status.integracaoFila ? "✅ OK" : "⚠️ Ausente"}`);

      await this.verificarPHP();
      console.groupEnd();
    }

    // ============================================================
    // 🧠 Verifica comunicação com PHPs
    // ============================================================
    async verificarPHP() {
      console.group("%c[Testes de Comunicação com o Servidor]", "color:#00ff88;font-weight:bold;");
      try {
        // 🔹 Estado de pausas
        const respPausa = await fetch(this.endpointPausa, { cache: "no-store" });
        const dadosPausa = await respPausa.json();
        if (dadosPausa.success) {
          const qtd = (dadosPausa.estado || []).length;
          this.status.evoluxAPI = true;
          console.log(`☕ controle_pausa.php → ✅ OK (${qtd} operadores ativos)`);
        } else {
          console.warn("☕ controle_pausa.php → ⚠️ Sem dados válidos");
        }

        // 🔹 Lista de operadores
        const respOps = await fetch(this.endpointOperadores, { cache: "no-store" });
        const dadosOps = await respOps.json();
        if (dadosOps.success && Array.isArray(dadosOps.equipes)) {
          let total = 0;
          dadosOps.equipes.forEach(eq => total += (eq.operadores?.length || 0));
          this.status.operadores = total;
          console.log(`👥 listar_operadores.php → ✅ OK (${total} operadores detectados)`);
        } else {
          console.warn("👥 listar_operadores.php → ⚠️ Nenhuma equipe retornada");
        }

        // 🔹 Resultado geral
        console.table(this.status);
      } catch (e) {
        console.error("❌ Falha na comunicação com o servidor:", e);
      }
      console.groupEnd();
    }

    // ============================================================
    // 🧠 Botão de diagnóstico rápido
    // ============================================================
    criarBotaoTeste() {
      const btn = document.createElement("button");
      btn.textContent = "🧠 Testar Sistema";
      Object.assign(btn.style, {
        position: "fixed",
        bottom: "15px",
        right: "15px",
        background: "#007ced",
        color: "#fff",
        border: "none",
        padding: "10px 14px",
        borderRadius: "8px",
        cursor: "pointer",
        fontWeight: "600",
        zIndex: "9999",
        boxShadow: "0 0 12px rgba(0,0,0,0.4)",
        transition: "all 0.3s ease"
      });
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

  window.SistemaDiagnostico = SistemaDiagnostico;

  document.addEventListener("DOMContentLoaded", () => {
    if (!window.diagnostico) {
      window.diagnostico = new SistemaDiagnostico();
    }
  });
}
