// ============================================================
// expiracao_pausa.js - Monitoramento Visual de Limite de Pausa
// v2.0 — Seguro, NÃO derruba operador automaticamente
// ============================================================
//
// ✔ NÃO chama mais acao=expirar_pausas
// ✔ NÃO volta operador sozinho para disponível
// ✔ Apenas identifica quem ultrapassou limite e aplica classe CSS
// ✔ Trabalha junto com o novo cronometro.js (responsável por som/alerta)
// ============================================================

console.log(
  "%c[Expiração v2.0] Monitoramento visual ativo (sem auto-derrubar).",
  "color:#ffaa00;font-weight:bold;"
);

class SistemaExpiracaoPausa {
  constructor() {
    this.intervalo = 10000; // 10s → mais fluido
    this.limiteMin = 20;     // limite real: 20 minutos
    this.limiteSeg = this.limiteMin * 60;

    this.iniciar();
  }

  verificar() {
  // Se não existe estado ainda → sai
  if (!window.controle || !window.controle.estado) return;

  const agora = Date.now();

  window.controle.estado.forEach((p) => {
    if (!["pausa", "espera"].includes(p.status)) return;
    if (!p.tempo_entrada) return;

    const inicio = new Date(p.tempo_entrada).getTime();
    if (!inicio || Number.isNaN(inicio)) return;

    const diff = (agora - inicio) / 1000;

    // 🔍 Encontrar operador pelo nome na interface
    const nomeElement = Array.from(document.querySelectorAll(".op-item strong"))
      .find(el => el.textContent.trim().toLowerCase() === p.nome.trim().toLowerCase());

    if (!nomeElement) return;

    const item = nomeElement.closest(".op-item");
    if (!item) return;

    // Remove classe antiga
    item.classList.remove("expirado-limite");

    // Se estourou o limite, marca apenas visualmente
    if (diff >= this.limiteSeg) {
      item.classList.add("expirado-limite");

      // dispara som/aviso apenas uma vez por operador
      if (window.Cronometro) {
        Cronometro.marcarExcedido(p.nome);
      }
    }
  });
}


  iniciar() {
    setInterval(() => this.verificar(), this.intervalo);
  }
}

// ============================================================
// Inicialização automática
// ============================================================
document.addEventListener("DOMContentLoaded", () => {
  window.expiradorPausa = new SistemaExpiracaoPausa();
});
