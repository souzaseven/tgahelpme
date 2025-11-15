// ==============================================
// equipes_operadores.js (v2.2)
// Renderiza equipes com status de pausa em tempo real
// ==============================================
console.log("%c[Equipes] Módulo de equipes iniciado...", "color:#00c6ff;font-weight:bold;");

async function carregarEquipes() {
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
        "Cache-Control": "no-cache"
      }
    });
    
    if (!resp.ok) throw new Error(`Erro HTTP ${resp.status}`);

    const data = await resp.json();
    if (!data.success) throw new Error(data.error || "Falha ao buscar equipes.");

    console.log("📊 [Equipes] Dados recebidos:", data);
    container.innerHTML = "";

    const textoOperadoresEl = document.getElementById("textoOperadores");
    const statusOperadoresEl = document.getElementById("statusOperadores");

    // Se não há equipes, mostra mensagem e atualiza topo
    if (!data.equipes || data.equipes.length === 0) {
      container.innerHTML = '<div class="lista-vazia">Nenhuma equipe encontrada</div>';

      if (textoOperadoresEl) {
        textoOperadoresEl.textContent = "Nenhuma equipe carregada";
      }
      if (statusOperadoresEl) {
        statusOperadoresEl.classList.remove("status-online");
        statusOperadoresEl.classList.add("status-offline");
      }
      return;
    }

    // 🔢 Contagem total de equipes e operadores
    const totalEquipes = data.equipes.length;
    let totalOperadores = 0;

    data.equipes.forEach(eq => {
      if (Array.isArray(eq.operadores)) {
        totalOperadores += eq.operadores.length;
      }
    });

    // Atualiza o texto no topo: "3 equipes / 37 operadores"
    if (textoOperadoresEl) {
      textoOperadoresEl.textContent =
        `${totalEquipes} equipe${totalEquipes === 1 ? "" : "s"} / ` +
        `${totalOperadores} operador${totalOperadores === 1 ? "" : "es"}`;
    }
    if (statusOperadoresEl) {
      statusOperadoresEl.classList.remove("status-offline");
      statusOperadoresEl.classList.add("status-online");
    }

    // Renderiza cada equipe
    data.equipes.forEach(equipe => {
      const bloco = document.createElement("div");
      bloco.className = "equipe-bloco";
      bloco.dataset.lider = equipe.lider;

      const nomeFila =
        equipe.fila && equipe.fila !== "—"
          ? equipe.fila
          : "Fila não informada";

      // Quantidade de operadores na equipe
      const qtdOperadores =
        equipe.operadores && Array.isArray(equipe.operadores)
          ? equipe.operadores.length
          : 0;

      const textoQtd = `${qtdOperadores} op${qtdOperadores === 1 ? "" : "s"}`;

      // Contagem por status dentro da equipe
      let disponiveis = 0;
      let pausas = 0;
      let esperas = 0;

      if (Array.isArray(equipe.operadores)) {
        equipe.operadores.forEach(op => {
          const s = (op.status || "disponivel").toLowerCase();
          if (s === "pausa" || s === "em pausa") {
            pausas++;
          } else if (s === "espera" || s === "em espera") {
            esperas++;
          } else {
            disponiveis++;
          }
        });
      }

      // Cabeçalho da equipe + resumo de status + container de operadores
      bloco.innerHTML = `
        <h3>
          <span>
            <i class="fas fa-users"></i>
            Equipe <b>${equipe.lider}</b> - ${textoQtd}
          </span>
          <span class="resumo-equipe">
            <span class="badge-status badge-disponivel">🟢 ${disponiveis}</span>
            <span class="badge-status badge-espera">⏳ ${esperas}</span>
            <span class="badge-status badge-pausa">☕ ${pausas}</span>
          </span>
        </h3>
        <div class="info-fila" style="color:#9cd;font-size:0.85rem;margin-bottom:6px;">
          ${nomeFila}
        </div>
        <div class="equipe-operadores"></div>
      `;
// Garantir exibição dos botões
const botoes = opDiv.querySelector('.user-botoes');
if (botoes) {
  botoes.style.display = "flex";
  botoes.style.justifyContent = "center";
  botoes.style.gap = "6px";
}


      const equipeDiv = bloco.querySelector(".equipe-operadores");

      if (!equipe.operadores || equipe.operadores.length === 0) {
        equipeDiv.innerHTML = `<div class="lista-vazia">Nenhum operador encontrado.</div>`;
      } else {
        equipe.operadores.forEach(op => {
          const statusRaw = (op.status || "disponivel").toLowerCase();

          // 🔹 Mapa de status → classe + cor (combina com CSS: success/warning/danger)
          let statusClasse = "disponivel";
          let corStatus = "#22c55e";          // --success
          let textoStatus = "🟢 Disponível";
          let tempo = "";
          const motivo = op.motivo_pausa ? `(${op.motivo_pausa})` : "";

          switch (statusRaw) {
            case "pausa":
            case "em pausa":
              statusClasse = "pausa";
              corStatus = "#ef4444";          // --danger
              textoStatus = "☕ Em Pausa";
              tempo = formatarTempo(op.tempo_pausa);
              break;
            case "espera":
            case "em espera":
              statusClasse = "espera";
              corStatus = "#facc15";          // --warning
              textoStatus = "⏳ Em Espera";
              tempo = formatarTempo(op.tempo_espera);
              break;
            case "disponivel":
            case "disponível":
              statusClasse = "disponivel";
              corStatus = "#22c55e";          // --success
              textoStatus = "🟢 Disponível";
              break;
            case "inativo":
            case "expirado":
              statusClasse = "expirado";
              corStatus = "#ef4444";
              textoStatus = "🔴 Inativo";
              break;
          }

          const opDiv = document.createElement("div");
          // Classe base + classe de status (para o CSS pintar o bloco todo)
          opDiv.className = `op-item ${statusClasse}`;
          
          opDiv.innerHTML = `
            <strong>${op.nome}</strong>
            <small>${op.fila || "—"}</small>
            <div class="status-texto" style="color:${corStatus}; font-weight:600; margin-top:5px;">
              ${textoStatus}
            </div>
            ${tempo ? `<div class="tempo">${tempo} ${motivo}</div>` : ""}
            <div class="user-botoes">
              <button class="btn-acao entrar-fila">🕓 Fila</button>
              <button class="btn-acao entrar-pausa">☕ Pausa</button>
              <button class="btn-acao disponivel">✅ Disponível</button>
            </div>
          `;

          equipeDiv.appendChild(opDiv);
        });
      }

      container.appendChild(bloco);
    });

    // 👇 Se o login já preencheu o nome e o perfil em variáveis globais,
    // reaplica as permissões depois de redesenhar a lista
    if (window.NOME_OPERADOR_LOGADO) {
      aplicarPermissoesOperador(
        window.NOME_OPERADOR_LOGADO,
        !!window.IS_ADMIN_LOGADO
      );
    }

    console.log(`🎉 [Equipes] ${data.equipes.length} equipes carregadas com sucesso!`);
    
  } catch (e) {
    console.error("❌ [Equipes] Erro ao carregar:", e);
    container.innerHTML = `
      <div class="lista-vazia">
        <i class="fas fa-exclamation-triangle"></i>
        <div>❌ Erro ao carregar participantes: ${e.message}</div>
        <small>Tentando novamente em 30 segundos...</small>
      </div>`;
    
    const textoOperadoresEl = document.getElementById("textoOperadores");
    const statusOperadoresEl = document.getElementById("statusOperadores");
    if (textoOperadoresEl) {
      textoOperadoresEl.textContent = "Erro ao carregar equipes";
    }
    if (statusOperadoresEl) {
      statusOperadoresEl.classList.remove("status-online");
      statusOperadoresEl.classList.add("status-offline");
    }
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
// Filtro por equipe (caso ainda use checkboxes .chk-equipe)
// ==================================================
function configurarFiltros() {
  const checkboxes = document.querySelectorAll(".chk-equipe");
  
  if (checkboxes.length === 0) {
    console.warn("⚠️ [Equipes] Checkboxes de filtro não encontrados (filtro desativado).");
    return;
  }

  checkboxes.forEach(chk => {
    chk.addEventListener("change", () => {
      const lider = chk.value;
      const bloco = document.querySelector(`.equipe-bloco[data-lider="${lider}"]`);
      if (bloco) {
        bloco.style.display = chk.checked ? "block" : "none";
        console.log(`🔧 [Equipes] ${chk.checked ? "Mostrando" : "Ocultando"} equipe ${lider}`);
      }
    });
  });
}

// ==================================================
// Permissões por operador / admin
// ==================================================
function aplicarPermissoesOperador(nomeLogado, isAdmin) {
  const itens = document.querySelectorAll(".op-item");

  itens.forEach(item => {
    const nomeEl = item.querySelector("strong");
    if (!nomeEl) return;

    const nomeOperador = nomeEl.textContent.trim();
    const botoes = item.querySelectorAll(".user-botoes .btn-acao");

    botoes.forEach(btn => {
      const isFilaOuPausa =
        btn.classList.contains("entrar-fila") ||
        btn.classList.contains("entrar-pausa");

      // Admin pode tudo
      if (isAdmin) {
        btn.disabled = false;
        btn.classList.remove("btn-desabilitado");
        return;
      }

      // Operador comum: só pode mexer nos próprios botões de fila/pausa
      if (isFilaOuPausa && nomeOperador !== nomeLogado) {
        btn.disabled = true;
        btn.classList.add("btn-desabilitado");
      } else {
        btn.disabled = false;
        btn.classList.remove("btn-desabilitado");
      }
    });
  });
}

// expõe a função para outros scripts (login, etc.)
window.aplicarPermissoesOperador = aplicarPermissoesOperador;
