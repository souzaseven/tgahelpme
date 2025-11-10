// ============================================================
// integracao_fila.js
// Sincronização inteligente: Fila local ↔ Pausas do Evolux
// ============================================================

if (typeof window.INTEGRACAO_FILA_CARREGADA === "undefined") {
  console.log("%c[Integração Fila] Sincronização de estados iniciada...", "color:#00ff88;font-weight:bold;");
  window.INTEGRACAO_FILA_CARREGADA = true;

  if (typeof window.INTERVALO_SINCRONIZACAO === "undefined") {
    window.INTERVALO_SINCRONIZACAO = 10000; // 10 segundos
  }
  const ENDPOINT_EVOLUX = "./php/api_integracao.php?acao=listar";

  let cachePausasEvolux = [];

  // ============================================================
  // 🔄 Buscar pausas ativas do Evolux
  // ============================================================
  async function obterPausasEvolux() {
    try {
      const resp = await fetch(ENDPOINT_EVOLUX, { cache: "no-store" });
      const dados = await resp.json();
      if (!dados.success) throw new Error(dados.error || "Falha no scraping");

      cachePausasEvolux = dados.dados.map(p => p.operador.trim().toLowerCase());
      console.log(`[Fila] Evolux retornou ${cachePausasEvolux.length} pausados.`);
      return cachePausasEvolux;
    } catch (e) {
      console.warn("[Fila] Erro ao obter pausas Evolux:", e);
      return [];
    }
  }

  // ============================================================
  // 🧩 Atualizar estados locais com base no Evolux
  // ============================================================
  async function sincronizarFilaComEvolux() {
    const pausasEvolux = await obterPausasEvolux();
    const participantes = document.querySelectorAll("#listaParticipantes .item.participante");

    participantes.forEach(div => {
      const nome = div.querySelector(".item-nome")?.textContent.trim().toLowerCase();

      if (pausasEvolux.includes(nome)) {
        if (!div.classList.contains("pausa")) {
          div.classList.remove("disponivel", "espera");
          div.classList.add("pausa");
          div.style.opacity = "1";
          div.querySelector(".item-status").innerHTML = `<i class="fas fa-coffee"></i> Em pausa (Evolux)`;
          console.log(`☕ ${nome} movido automaticamente para PAUSA.`);
        }
        return;
      }

      if (!pausasEvolux.includes(nome) && div.classList.contains("pausa")) {
        div.classList.remove("pausa", "espera");
        div.classList.add("disponivel");
        div.querySelector(".item-status").innerHTML = `<i class="fas fa-user-check"></i> Disponível`;
        console.log(`🟢 ${nome} voltou para disponível.`);
        return;
      }
    });
  }

  // ============================================================
  // 🚀 Inicialização automática
  // ============================================================
  document.addEventListener("DOMContentLoaded", () => {
    console.log("[Integração Fila] Monitoramento automático iniciado.");
    sincronizarFilaComEvolux();
    setInterval(sincronizarFilaComEvolux, window.INTERVALO_SINCRONIZACAO);
  });
}
