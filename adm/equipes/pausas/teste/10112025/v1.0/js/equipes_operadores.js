// ==============================================
// equipes_operadores.js (v1.9 - CORRIGIDO)
// Renderiza equipes com status de pausa em tempo real
// ==============================================
console.log("%c[Equipes] Módulo de equipes iniciado...", "color:#00c6ff;font-weight:bold;");

async function carregarEquipes() {
  // 🔧 CORREÇÃO: ID atualizado para "listaParticipantes"
  const container = document.getElementById("listaParticipantes");
  if (!container) {
    console.error("❌ [Equipes] ERRO: Elemento 'listaParticipantes' não encontrado!");
    return;
  }

  console.log("🚀 [Equipes] Iniciando carregamento de participantes...");
  container.innerHTML = `<div class="loading">Carregando participantes...</div>`;

  try {
    const resp = await fetch("php/listar_operadores.php", { 
      cache: "no-store",
      headers: {
        'Cache-Control': 'no-cache'
      }
    });
    
    if (!resp.ok) throw new Error(`Erro HTTP ${resp.status}`);

    const data = await resp.json();
    if (!data.success) throw new Error(data.error || "Falha ao buscar equipes.");

    console.log("📊 [Equipes] Dados recebidos:", data);
    container.innerHTML = "";

    // Se não há equipes, mostra mensagem
    if (!data.equipes || data.equipes.length === 0) {
      container.innerHTML = '<div class="lista-vazia">Nenhuma equipe encontrada</div>';
      return;
    }

    // Renderiza cada equipe
    data.equipes.forEach(equipe => {
      const bloco = document.createElement("div");
      bloco.className = "equipe-bloco";
      bloco.dataset.lider = equipe.lider;

      const nomeFila = equipe.fila && equipe.fila !== "—" ? equipe.fila : "Fila não informada";

      // Cabeçalho da equipe
      bloco.innerHTML = `
        <h3>
          <span><i class="fas fa-users"></i> Equipe <b>${equipe.lider}</b></span>
          <span style="color:#9cd;font-size:0.9rem;">${nomeFila}</span>
        </h3>
        <div class="equipe-operadores"></div>
      `;

      const equipeDiv = bloco.querySelector(".equipe-operadores");

      if (!equipe.operadores || equipe.operadores.length === 0) {
        equipeDiv.innerHTML = `<div class="lista-vazia">Nenhum operador encontrado.</div>`;
      } else {
        equipe.operadores.forEach(op => {
          const status = (op.status || "disponivel").toLowerCase();
          let classeCor = "#00ff88";
          let texto = "🟢 Disponível";
          let tempo = "";
          let motivo = op.motivo_pausa ? `(${op.motivo_pausa})` : "";

          switch (status) {
            case "pausa":
            case "em pausa":
              classeCor = "#007ced";
              texto = "☕ Em Pausa";
              tempo = formatarTempo(op.tempo_pausa);
              break;
            case "espera":
            case "em espera":
              classeCor = "#ffaa00";
              texto = "⏳ Em Espera";
              tempo = formatarTempo(op.tempo_espera);
              break;
            case "disponivel":
            case "disponível":
              classeCor = "#00ff88";
              texto = "🟢 Disponível";
              break;
            case "inativo":
            case "expirado":
              classeCor = "#ff4444";
              texto = "🔴 Inativo";
              break;
          }

          const opDiv = document.createElement("div");
          opDiv.className = "op-item";
          
          opDiv.innerHTML = `
            <strong>${op.nome}</strong>
            <span>${op.fila || "—"}</span>
            <div style="color:${classeCor}; font-weight:600; margin-top:5px;">${texto}</div>
            ${tempo ? `<div class="tempo">${tempo} ${motivo}</div>` : ""}
          `;

          equipeDiv.appendChild(opDiv);
        });
      }

      container.appendChild(bloco);
    });

    console.log(`🎉 [Equipes] ${data.equipes.length} equipes carregadas com sucesso!`);
    
  } catch (e) {
    console.error("❌ [Equipes] Erro ao carregar:", e);
    container.innerHTML = `
      <div class="lista-vazia">
        <i class="fas fa-exclamation-triangle"></i>
        <div>❌ Erro ao carregar participantes: ${e.message}</div>
        <small>Tentando novamente em 30 segundos...</small>
      </div>`;
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
function configurarFiltros() {
  const checkboxes = document.querySelectorAll(".chk-equipe");
  
  if (checkboxes.length === 0) {
    console.warn("⚠️ [Equipes] Checkboxes de filtro não encontrados");
    return;
  }

  checkboxes.forEach(chk => {
    chk.addEventListener("change", () => {
      const lider = chk.value;
      const bloco = document.querySelector(`.equipe-bloco[data-lider="${lider}"]`);
      if (bloco) {
        bloco.style.display = chk.checked ? "block" : "none";
        console.log(`🔧 [Equipes] ${chk.checked ? 'Mostrando' : 'Ocultando'} equipe ${lider}`);
      }
    });
  });
}
