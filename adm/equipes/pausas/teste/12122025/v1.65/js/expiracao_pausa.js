// ============================================================
// expiracao_pausa.js - Monitoramento Automático de Expiração
// v1.14 (Compatível com controle_pausa_novo.php)
// ============================================================
//
// 🔁 Executa a cada 60 segundos:
// - Chama acao=expirar_pausas (limite padrão: 15 minutos)
// - Atualiza interface via window.controle.atualizarEstado()
// - Exibe notificação e som se alguma pausa expirar
//
// ============================================================

console.log("%c[Expiração v1.14] Monitoramento de pausas iniciado...", "color:#ffaa00;font-weight:bold;");

class SistemaExpiracaoPausa {
  constructor() {
    this.url = "./php/controle_pausa_novo.php?acao=expirar_pausas";
    this.intervalo = 60000; // 1 min
    this.limiteMin = 15; // tempo padrão de expiração
    this.iniciar();
  }

  async verificar() {
    try {
      const resp = await fetch(`${this.url}&limite_min=${this.limiteMin}`, { cache: "no-store" });
      const dados = await resp.json();
      if (!dados.success) return;

      const qtd = dados.expiradas || 0;
      if (qtd > 0) {
        console.warn(`⚠️ ${qtd} pausa(s) expiraram automaticamente.`);
        if (window.somPausa) window.somPausa.aviso(`${qtd} pausa(s) expiraram automaticamente.`);
        if (window.controle) window.controle.toast(`⚠️ ${qtd} pausa(s) expiraram por tempo.`, true);
      }

      // atualiza o painel sempre
      if (window.controle && typeof window.controle.atualizarEstado === "function") {
        await window.controle.atualizarEstado();
      }
    } catch (e) {
      console.error("❌ Erro ao verificar expiração:", e);
    }
  }

  iniciar() {
    this.verificar(); // executa imediatamente
    setInterval(() => this.verificar(), this.intervalo);
  }
}

// ============================================================
// Inicialização automática
// ============================================================
document.addEventListener("DOMContentLoaded", () => {
  window.expiradorPausa = new SistemaExpiracaoPausa();
});
