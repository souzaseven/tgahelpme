// ============================================================
// integracao_ext.js
// Integração com Evolux (scraping via api_integracao.php)
// ============================================================

if (typeof window.INTEGRACAO_EVOLUX_CARREGADA === "undefined") {
  console.log("%c[Integração Evolux] Iniciando módulo de monitoramento...", "color:#00c3ff; font-weight:bold;");
  window.INTEGRACAO_EVOLUX_CARREGADA = true;

  // ============================================================
  // 🔧 Configurações
  // ============================================================
  if (typeof window.INTERVALO_ATUALIZACAO === "undefined") {
    window.INTERVALO_ATUALIZACAO = 15000; // 15 segundos
  }
  const ENDPOINT_INTEGRACAO = "./php/api_integracao.php?acao=listar";

  // ============================================================
  // 🧩 Inicialização da Seção Visual
  // ============================================================
  function criarPainelEvolux() {
    if (document.getElementById("painel-evolux")) return;
    const dashboard = document.querySelector(".dashboard");
    if (!dashboard) return console.error("❌ Dashboard não encontrado para inserir painel Evolux.");

    const card = document.createElement("div");
    card.className = "card evolux-container full-width";
    card.id = "painel-evolux";
    card.innerHTML = `
      <h2><i class="fas fa-headset"></i> Operadores Pausados (Evolux)</h2>
      <div class="contador">
        <i class="fas fa-circle-nodes"></i>
        <span id="contador-evolux">0</span> operadores pausados no Evolux
      </div>
      <div id="lista-evolux" class="lista-evolux">
        <div class="lista-vazia">
          <i class="fas fa-spinner fa-spin" style="font-size:2rem;margin-bottom:10px;"></i>
          <div>Carregando dados do Evolux...</div>
        </div>
      </div>
    `;
    dashboard.appendChild(card);
  }

  // ============================================================
  // 🔄 Atualiza a lista de operadores pausados
  // ============================================================
  async function atualizarPainelEvolux() {
    const lista = document.getElementById("lista-evolux");
    const contador = document.getElementById("contador-evolux");
    if (!lista || !contador) return;

    try {
      const resposta = await fetch(ENDPOINT_INTEGRACAO, { cache: "no-store" });
      const dados = await resposta.json();

      if (!dados.success) {
        lista.innerHTML = `
          <div class="lista-vazia">
            <i class="fas fa-triangle-exclamation" style="font-size:2rem;margin-bottom:10px;color:#ffaa00;"></i>
            <div>Falha ao carregar dados do Evolux.</div>
            <div style="font-size:0.8rem;opacity:0.7;">${dados.error || "Erro desconhecido"}</div>
          </div>`;
        contador.textContent = "0";
        return;
      }

      const pausados = dados.dados || [];
      contador.textContent = pausados.length;

      if (pausados.length === 0) {
        lista.innerHTML = `
          <div class="lista-vazia">
            <i class="fas fa-circle-check" style="font-size:2.2rem;margin-bottom:10px;color:#00ff88;"></i>
            <div>Nenhum operador pausado no momento 🎯</div>
          </div>`;
        return;
      }

      lista.innerHTML = pausados.map(op => `
        <div class="item evolux-item">
          <div class="item-info">
            <span class="item-nome"><i class="fas fa-user"></i> ${op.operador}</span>
            <span class="item-status">
              <i class="fas fa-coffee"></i> ${op.motivo || "Sem motivo"} 
              <span style="color:#00c8ff;font-weight:600;margin-left:10px;">
                ⏱ ${op.duracao || "00:00:00"}
              </span>
            </span>
          </div>
        </div>
      `).join("");

      console.log(`[Integração Evolux] ${pausados.length} operadores em pausa carregados.`);
    } catch (erro) {
      console.error("[Integração Evolux] Erro ao atualizar:", erro);
      if (lista) {
        lista.innerHTML = `
          <div class="lista-vazia">
            <i class="fas fa-wifi" style="font-size:2rem;margin-bottom:10px;color:#ff4444;"></i>
            <div>Falha na conexão com o servidor.</div>
          </div>`;
      }
    }
  }

  // ============================================================
  // 🔁 Atualização Automática
  // ============================================================
  function iniciarMonitoramentoEvolux() {
    criarPainelEvolux();
    atualizarPainelEvolux();
    setInterval(atualizarPainelEvolux, window.INTERVALO_ATUALIZACAO);
  }

  // ============================================================
  // 🚀 Inicialização automática
  // ============================================================
  document.addEventListener("DOMContentLoaded", iniciarMonitoramentoEvolux);
}
