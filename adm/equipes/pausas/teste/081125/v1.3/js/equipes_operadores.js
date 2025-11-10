// ==============================================
// equipes_operadores.js (v1.4)
// Renderiza equipes com status de pausa em tempo real
// ==============================================
console.log("%c[Equipes] Módulo de equipes iniciado...", "color:#00c6ff;font-weight:bold;");

async function carregarEquipes() {
  const container = document.getElementById("listaEquipes");
  if (!container) return console.warn("[Equipes] Contêiner 'listaEquipes' não encontrado.");

  container.innerHTML = `<div class="loading"></div>`;

  try {
    const resp = await fetch("php/listar_operadores.php", { cache: "no-store" });
    if (!resp.ok) throw new Error(`Erro HTTP ${resp.status}`);

    const data = await resp.json();
    if (!data.success) throw new Error(data.error || "Falha ao buscar equipes.");

    container.innerHTML = "";

    data.equipes.forEach(equipe => {
      const bloco = document.createElement("div");
      bloco.className = "equipe-bloco";
      bloco.dataset.lider = equipe.lider;

      const nomeFila = equipe.fila && equipe.fila !== "—" ? equipe.fila : "Fila não informada";

      // Cabeçalho da equipe
      bloco.innerHTML = `
        <h3 style="margin-bottom:10px;">
          <i class="fas fa-users"></i> Equipe <b>${equipe.lider}</b> 
          <span style="color:#9cd;">(${nomeFila})</span>
        </h3>
        <div class="equipe-operadores"></div>
      `;

      const equipeDiv = bloco.querySelector(".equipe-operadores");

      if (equipe.operadores.length === 0) {
        equipeDiv.innerHTML = `<div class="lista-vazia">Nenhum operador encontrado.</div>`;
      } else {
        equipe.operadores.forEach(op => {
          const status = (op.status || "").toLowerCase();
          let cor = "#00ff88";
          let texto = "🟢 Disponível";
          let tempo = "";
          let motivo = op.motivo_pausa ? `(${op.motivo_pausa})` : "";

          switch (status) {
            case "pausa":
            case "em pausa":
              cor = "#007ced";
              texto = "☕ Em Pausa";
              tempo = formatarTempo(op.tempo_pausa);
              break;
            case "espera":
            case "em espera":
              cor = "#ffaa00";
              texto = "⏳ Em Espera";
              tempo = formatarTempo(op.tempo_espera);
              break;
            case "disponivel":
            case "disponível":
              cor = "#00ff88";
              texto = "🟢 Disponível";
              break;
            case "inativo":
            case "expirado":
              cor = "#ff4444";
              texto = "🔴 Inativo";
              break;
          }

          const opDiv = document.createElement("div");
          opDiv.className = "op-item";
          opDiv.style.borderLeft = `5px solid ${cor}`;
          opDiv.style.padding = "8px 10px";
          opDiv.style.margin = "4px 0";
          opDiv.style.background = "rgba(255,255,255,0.03)";
          opDiv.innerHTML = `
            <strong style="color:#fff;">${op.nome}</strong><br>
            <small style="color:#aaa;">${op.fila || "—"}</small><br>
            <span style="color:${cor};font-weight:600;">${texto}</span>
            ${tempo ? `<span style="display:block;color:#9cd;">⏱ ${tempo} ${motivo}</span>` : ""}
          `;

          equipeDiv.appendChild(opDiv);
        });
      }

      container.appendChild(bloco);
    });

    console.log("%c[Equipes] Dados atualizados com sucesso.", "color:#00ff88;");
  } catch (e) {
    console.error("[Equipes] Erro ao carregar:", e);
    container.innerHTML = `<div class="lista-vazia">❌ Erro ao carregar equipes: ${e.message}</div>`;
  }
}

// ==================================================
// 🕒 Função para formatar tempo (minutos → h:mm)
// ==================================================
function formatarTempo(minutos) {
  if (!minutos || isNaN(minutos)) return "";
  const h = Math.floor(minutos / 60);
  const m = minutos % 60;
  return `${h > 0 ? h + "h " : ""}${m}min`;
}

// ==================================================
// Filtro por equipe + atualização automática
// ==================================================
document.addEventListener("DOMContentLoaded", () => {
  carregarEquipes();
  setInterval(carregarEquipes, 60000); // Atualiza a cada 60s

  document.querySelectorAll(".chk-equipe").forEach(chk => {
    chk.addEventListener("change", () => {
      const lider = chk.value;
      const bloco = document.querySelector(`.equipe-bloco[data-lider="${lider}"]`);
      if (bloco) bloco.style.display = chk.checked ? "block" : "none";
    });
  });
});
