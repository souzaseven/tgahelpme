// ============================================================
// controle_pausa.js (v3.9 - CORRIGIDO) - Disponível → Fila → Pausa
// - CORREÇÃO: Inicialização automática funcionando
// - Preserva líder mesmo em FILA/PAUSA (normaliza nome)
// - Cores por status aplicadas em todos os blocos de equipe
// - Ordenação por status: disponivel(0) < espera(1) < pausa(2)
// - Emite window.controleReady + evento 'controle:ready'
// ============================================================

console.log("%c[Controle de Pausa] Sistema carregado...", "color:#00ff88;font-weight:bold;");

class ControlePausaSistema {
    constructor() {
      this.urlPHP        = "./php/controle_pausa.php";
      this.urlOperadores = "./php/listar_operadores.php";
      this.intervaloAtualizacao = 10000; // 10s
      this.maxPausas = 2;

      // Seletores principais (se existirem na página)
      this.listaPausa         = document.getElementById("pausa-lista");
      this.listaEspera        = document.getElementById("lista-espera");
      this.listaParticipantes = document.getElementById("listaParticipantes");
      this.contPausa          = document.getElementById("contador-pausa");
      this.contEspera         = document.getElementById("contador-espera");
      this.syncStatus         = document.getElementById("sync-status");

      // Bases
      this.todosOperadores = []; // [{nome, lider}]
      this.estado = [];          // [{nome, status, lider, motivo_pausa}]
      this.pronto = false;

      // Pesos de ordenação de status
      this.pesoStatus = { disponivel: 0, espera: 1, pausa: 2 };
    }

    // Utilitário: normaliza nomes (remove acentos, trim, lower)
    normalizarNome(s) {
      return (s || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/\s+/g, " ")
        .trim()
        .toLowerCase();
    }

   // 🚀 Inicialização
async iniciar() {
  try {
    console.log("🚀 [Controle de Pausa] Iniciando sistema...");
    
    // 1️⃣ Carrega base de operadores e mostra na tela imediatamente
    await this.carregarParticipantesFixos();
    this.renderizarParticipantes(this.listaParticipantes, this.todosOperadores);

    // 2️⃣ Em seguida, tenta buscar o estado atual do PHP (se existir)
    await this.atualizarEstado();

    this.pronto = true;

    // 🔔 Sinaliza globalmente que o controle está pronto
    window.controleReady = true;
    window.dispatchEvent(new CustomEvent("controle:ready", { detail: { pronto: true }}));
    console.log("%c📢 [Controle de Pausa] Evento 'controle:ready' disparado.", "color:#00c3ff;font-weight:bold;");
  } catch (error) {
    console.error("❌ [Controle de Pausa] Erro na inicialização:", error);
  } finally {
    // Atualiza a cada 10s
    setInterval(() => this.atualizarEstado(), this.intervaloAtualizacao);
  }
}

  // 📋 Carrega base de operadores com líder e status real
async carregarParticipantesFixos() {
  try {
    console.log("📥 [Controle de Pausa] Carregando operadores...");
    const resp = await fetch(this.urlOperadores, { cache: "no-store" });
    const dados = await resp.json();

    if (dados.success && Array.isArray(dados.equipes)) {
      const nomes = [];

      dados.equipes.forEach(eq => {
        const lider = eq.lider || eq.nome || "Sem líder definido";
        (eq.operadores || []).forEach(op => {
          const nome = (op.nome || "").trim();
          if (!nome) return;

          // Usa status real do JSON (pausa, espera, disponivel)
          nomes.push({
            nome,
            status: op.status || "disponivel",
            lider,
            motivo_pausa: op.motivo_pausa || ""
          });
        });
      });

      this.todosOperadores = nomes;
      console.log(`✅ [Controle de Pausa] ${nomes.length} operadores carregados (status real + líder).`);
    } else {
      console.warn("⚠️ [Controle de Pausa] Nenhum operador retornado do listar_operadores.php");
    }
  } catch (e) {
    console.error("❌ [Controle de Pausa] Erro ao carregar operadores:", e);
  }
}


    // 🔄 Atualiza estado do servidor preservando líder
    async atualizarEstado() {
      if (this.syncStatus) this.syncStatus.textContent = "Sincronizando...";

      try {
        const resp = await fetch(`${this.urlPHP}?acao=get_estado`, { cache: "no-store" });
        const dados = await resp.json();
        if (!dados.success) throw new Error("Resposta inválida do servidor");

        const estadoServidor = Array.isArray(dados.estado) ? dados.estado : [];

        // Mapa de líder original normalizado
        const mapaLider = new Map(
          this.todosOperadores.map(o => [this.normalizarNome(o.nome), o.lider])
        );

        // Mescla status atual preservando líder (casamento normalizado)
        const atualizados = this.todosOperadores.map(base => {
          const key = this.normalizarNome(base.nome);
          const match = estadoServidor.find(p => this.normalizarNome(p?.nome) === key);
          const status = match ? match.status : "disponivel";
          return {
            nome: base.nome,
            status,
            lider: mapaLider.get(key) || base.lider || "Sem líder definido",
            motivo_pausa: match?.motivo_pausa || ""
          };
        });

        this.estado = atualizados;
        this.renderizarListas(this.estado);

        if (this.syncStatus) this.syncStatus.textContent = "Sincronizado ✅";
      } catch (e) {
        console.error("❌ [Controle de Pausa] Erro ao atualizar:", e);
        if (this.syncStatus) this.syncStatus.textContent = "Erro de conexão ❌";
      }
    }

    // 🧩 Renderização principal (painéis superiores + equipes)
    renderizarListas(lista) {
      if (!Array.isArray(lista)) return;

      const pausas      = lista.filter(p => p.status === "pausa");
      const esperas     = lista.filter(p => p.status === "espera");
      const disponiveis = lista.filter(p => p.status === "disponivel");

      if (this.contPausa)  this.contPausa.textContent  = pausas.length;
      if (this.contEspera) this.contEspera.textContent = esperas.length;

      // Listas superiores
      this.renderizar(this.listaPausa,  pausas,  "Nenhuma pessoa em pausa ☕");
      this.renderizar(this.listaEspera, esperas, "Ninguém na fila de espera 📋");

      // Equipes (todos aparecem no bloco do líder, com cor por status)
      this.renderizarParticipantes(this.listaParticipantes, lista);
    }

    // Render simples de uma lista (pausas/fila)
    renderizar(container, dados, vazioMsg) {
      if (!container) return;
      container.innerHTML = "";

      if (!dados || !dados.length) {
        container.innerHTML = `
          <div class="lista-vazia">
            <i class="fas fa-info-circle" style="font-size:2rem;opacity:0.5;"></i>
            <div>${vazioMsg}</div>
          </div>`;
        return;
      }

      for (const p of dados) {
        const div = document.createElement("div");
        div.className = `item ${p.status}`;
        div.innerHTML = `
          <div class="item-info">
            <span class="item-nome">${p.nome}</span>
            <span class="item-status">
              <i class="fas ${this.getIcone(p.status)}"></i> ${this.formatarStatus(p.status)}
            </span>
          </div>`;
        container.appendChild(div);
      }
    }

    // 👥 Render por equipe (sempre no grupo do líder)
    renderizarParticipantes(container, lista) {
      if (!container) {
        console.error("❌ [Controle de Pausa] Container não encontrado para renderização!");
        return;
      }
      
      console.log("🎨 [Controle de Pausa] Renderizando participantes...", lista?.length);
      container.innerHTML = "";

      // Agrupa por líder
      const grupos = {};
      for (const p of (lista || [])) {
        const lider = p.lider || "Sem líder definido";
        (grupos[lider] ||= []).push(p);
      }

      // Para cada equipe
      Object.keys(grupos).forEach(lider => {
        const equipeBox = document.createElement("div");
        equipeBox.className = "equipe-bloco";
        equipeBox.innerHTML = `<h3>Equipe ${lider}</h3><div class="equipe-operadores"></div>`;

        const equipeContainer = equipeBox.querySelector(".equipe-operadores");

        // Ordena: disponivel → espera → pausa
        grupos[lider]
          .slice()
          .sort((a, b) => (this.pesoStatus[a.status] ?? 99) - (this.pesoStatus[b.status] ?? 99))
          .forEach(p => {
            const item = document.createElement("div");
            item.className = `op-item ${p.status}`; // CSS aplica cores por status
            item.innerHTML = `
              <strong>${p.nome}</strong>
              <small>${this.formatarStatus(p.status)}</small>
              <div class="tempo">${p.motivo_pausa || ""}</div>
            `;

            // Botões
            const botoes = document.createElement("div");
            botoes.className = "user-botoes";
            const operadorAtual = (localStorage.getItem("operador_nome") || "").toLowerCase();
            const modoAdmin = localStorage.getItem("modo_admin") === "true";

            if (modoAdmin) {
              botoes.innerHTML = `
                <button class="btn-acao entrar-fila">🕓 Fila</button>
                <button class="btn-acao entrar-pausa">☕ Pausa</button>
                <button class="btn-acao disponivel">✅ Disponível</button>
              `;
              botoes.querySelector(".entrar-fila").onclick  = () => this.enviarAcao("entrar_fila", p.nome);
              botoes.querySelector(".entrar-pausa").onclick = () => this.enviarAcao("forcar_pausa", p.nome);
              botoes.querySelector(".disponivel").onclick   = () => this.enviarAcao("voltar_disponivel", p.nome);
            } else if (p.nome.toLowerCase() === operadorAtual) {
              if (p.status === "disponivel") {
                botoes.innerHTML = `<button class="btn-acao entrar-fila">🕓 Entrar na Fila</button>`;
                botoes.querySelector(".entrar-fila").onclick = () => this.enviarAcao("entrar_fila", p.nome);
              } else if (p.status === "espera") {
                botoes.innerHTML = `<button class="btn-acao" disabled>⏳ Aguardando vaga...</button>`;
              } else if (p.status === "pausa") {
                botoes.innerHTML = `<button class="btn-acao disponivel">✅ Voltar</button>`;
                botoes.querySelector(".disponivel").onclick = () => this.enviarAcao("voltar_disponivel", p.nome);
              }
            }

            item.appendChild(botoes);
            equipeContainer.appendChild(item);
          });

        container.appendChild(equipeBox);
      });
      
      console.log("✅ [Controle de Pausa] Renderização concluída!");
    }

    // 📡 Ações
    async enviarAcao(acao, nome) {
      try {
        const payload = { acao, nome, solicitante: localStorage.getItem("operador_nome") || "" };
        const resp = await fetch(`${this.urlPHP}?acao=${acao}`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload)
        });
        const dados = await resp.json();

        if (dados.success) {
          console.log(`✅ Ação '${acao}' executada para ${nome}`);
          this.atualizarEstado();
        } else {
          alert(`❌ ${dados.error || "Ação não permitida"}`);
        }
      } catch (err) {
        console.error("[Controle de Pausa] Erro ao enviar ação:", err);
        alert("Erro de comunicação com o servidor.");
      }
    }

    // 🎨 Utilitários
    getIcone(status) {
      switch (status) {
        case "pausa": return "fa-coffee";
        case "espera": return "fa-clock";
        case "disponivel": return "fa-user-check";
        default: return "fa-user";
      }
    }
    formatarStatus(status) {
      switch (status) {
        case "pausa": return "Em pausa";
        case "espera": return "Na fila";
        case "disponivel": return "Disponível";
        default: return status;
      }
    }
}

// 🧩 Registro global da classe
window.ControlePausaSistema = ControlePausaSistema;

// 🔄 Inicialização automática quando DOM estiver pronto
document.addEventListener("DOMContentLoaded", () => {
  console.log("📅 [Controle de Pausa] DOM carregado, inicializando...");
  
  if (!window.controle) {
    window.controle = new ControlePausaSistema();
    window.controle.iniciar();
    console.log("✅ [Controle de Pausa] Sistema inicializado automaticamente!");
  } else {
    console.log("ℹ️ [Controle de Pausa] Sistema já inicializado");
  }
});

// 🚀 Inicialização imediata se DOM já estiver pronto
if (document.readyState === 'complete' || document.readyState === 'interactive') {
  setTimeout(() => {
    if (!window.controle && window.ControlePausaSistema) {
      console.log("🔄 [Controle de Pausa] Inicializando após DOM pronto...");
      window.controle = new ControlePausaSistema();
      window.controle.iniciar();
    }
  }, 100);
}