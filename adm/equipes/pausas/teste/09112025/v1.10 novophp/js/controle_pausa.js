// ============================================================
// controle_pausa.js (v4.2 - ATUALIZADO)
// ------------------------------------------------------------
// ✅ Inicialização automática garantida
// ✅ Preserva líder mesmo em FILA/PAUSA (normaliza nome)
// ✅ Atualização visual suave (anti-flicker)
// ✅ Cores aplicadas em todos os blocos de equipe
// ✅ Ordenação por status: disponivel(0) < espera(1) < pausa(2)
// ✅ Exibe tempo e motivo da pausa
// ✅ Exibe status de sincronização visual
// ✅ Emite window.controleReady + evento 'controle:ready'
// ✅ Re-render imediato após ação (sem delay)
// ============================================================

console.log("%c[Controle de Pausa] Sistema v4.2 carregado...", "color:#00ff88;font-weight:bold;");

class ControlePausaSistema {
  constructor() {
    this.urlPHP        = "./php/controle_pausa.php";
    this.urlOperadores = "./php/listar_operadores.php";
    this.intervaloAtualizacao = 10000; // 10s

    // Seletores principais
    this.listaPausa         = document.getElementById("pausa-lista");
    this.listaEspera        = document.getElementById("lista-espera");
    this.listaParticipantes = document.getElementById("listaParticipantes");
    this.contPausa          = document.getElementById("contador-pausa");
    this.contEspera         = document.getElementById("contador-espera");
    this.syncStatus         = document.getElementById("sync-status");

    this.todosOperadores = [];
    this.estado = [];
    this.pronto = false;
    this.ultimoJSON = "";

    this.pesoStatus = { disponivel: 0, espera: 1, pausa: 2 };
  }

  normalizarNome(s) {
    return (s || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/\s+/g, " ")
      .trim()
      .toLowerCase();
  }

  async iniciar() {
    try {
      console.log("🚀 [Controle de Pausa] Iniciando sistema...");
      await this.carregarParticipantesFixos();
      this.renderizarParticipantes(this.listaParticipantes, this.todosOperadores);
      await this.atualizarEstado();

      this.pronto = true;
      window.controleReady = true;
      window.dispatchEvent(new CustomEvent("controle:ready", { detail: { pronto: true } }));
      console.log("%c📢 [Controle de Pausa] Evento 'controle:ready' disparado.", "color:#00c3ff;font-weight:bold;");
    } catch (error) {
      console.error("❌ [Controle de Pausa] Erro na inicialização:", error);
    } finally {
      setInterval(() => this.atualizarEstado(), this.intervaloAtualizacao);
    }
  }

  async carregarParticipantesFixos() {
    try {
      console.log("📥 [Controle de Pausa] Carregando operadores...");
      const resp = await fetch(this.urlOperadores, { cache: "no-store" });
      const dados = await resp.json();

      if (dados.success && Array.isArray(dados.equipes)) {
        const nomes = [];
        dados.equipes.forEach(eq => {
          const lider = eq.verlider || eq.lider || eq.nome || "Sem líder definido";
          (eq.operadores || []).forEach(op => {
            const nome = (op.nome || "").trim();
            if (!nome) return;
            nomes.push({
              nome,
              status: op.status || "disponivel",
              lider,
              motivo_pausa: op.motivo_pausa || ""
            });
          });
        });
        this.todosOperadores = nomes;
        console.log(`✅ ${nomes.length} operadores carregados.`);
      } else {
        console.warn("⚠️ Nenhum operador retornado do listar_operadores.php");
      }
    } catch (e) {
      console.error("❌ [Controle de Pausa] Erro ao carregar operadores:", e);
    }
  }

  // 🔄 Atualiza estado (aceita forçar re-render)
  async atualizarEstado(force = false) {
    if (this.syncStatus) this.syncStatus.textContent = "Sincronizando...";

    try {
      const resp = await fetch(`${this.urlPHP}?acao=get_estado`, { cache: "no-store" });
      const dados = await resp.json();
      if (!dados.success) throw new Error("Resposta inválida");

      const estadoServidor = Array.isArray(dados.estado) ? dados.estado : [];
      const mapaLider = new Map(
        this.todosOperadores.map(o => [this.normalizarNome(o.nome), o.lider])
      );

      const atualizados = this.todosOperadores.map(base => {
        const key = this.normalizarNome(base.nome);
        const match = estadoServidor.find(p => this.normalizarNome(p?.nome) === key);
        const status = match ? match.status : "disponivel";
        return {
          nome: base.nome,
          status,
          lider: mapaLider.get(key) || base.lider,
          motivo_pausa: match?.motivo_pausa || ""
        };
      });

      const novoJSON = JSON.stringify(atualizados);
      if (force || novoJSON !== this.ultimoJSON) {
        this.estado = atualizados;
        this.ultimoJSON = novoJSON;
        this.renderizarListas(this.estado);
      }

      if (this.syncStatus) {
        const hora = new Date().toLocaleTimeString("pt-BR", { hour12: false });
        this.syncStatus.textContent = `Atualizado às ${hora} ✅`;
      }
    } catch (e) {
      console.error("❌ [Controle de Pausa] Erro ao atualizar:", e);
      if (this.syncStatus) this.syncStatus.textContent = "Erro de conexão ❌";
    }
  }

  renderizarListas(lista) {
    if (!Array.isArray(lista)) return;

    const pausas      = lista.filter(p => p.status === "pausa");
    const esperas     = lista.filter(p => p.status === "espera");
    const disponiveis = lista.filter(p => p.status === "disponivel");

    if (this.contPausa)  this.contPausa.textContent  = pausas.length;
    if (this.contEspera) this.contEspera.textContent = esperas.length;

    this.renderizar(this.listaPausa,  pausas,  "Nenhuma pessoa em pausa ☕");
    this.renderizar(this.listaEspera, esperas, "Ninguém na fila de espera 📋");
    this.renderizarParticipantes(this.listaParticipantes, lista);
  }

  renderizar(container, dados, vazioMsg) {
    if (!container) return;

    container.style.opacity = 0;
    setTimeout(() => {
      container.innerHTML = "";

      if (!dados || !dados.length) {
        container.innerHTML = `
          <div class="lista-vazia">
            <i class="fas fa-info-circle" style="font-size:2rem;opacity:0.5;"></i>
            <div>${vazioMsg}</div>
          </div>`;
      } else {
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

      container.style.transition = "opacity 0.25s ease";
      container.style.opacity = 1;
    }, 100);
  }

  renderizarParticipantes(container, lista) {
    if (!container) return;
    container.style.opacity = 0;

    setTimeout(() => {
      container.innerHTML = "";
      const grupos = {};

      (lista || []).forEach(p => {
        const lider = p.lider || "Sem líder definido";
        (grupos[lider] ||= []).push(p);
      });

      Object.keys(grupos).forEach(lider => {
        const equipeBox = document.createElement("div");
        equipeBox.className = "equipe-bloco";
        equipeBox.dataset.lider = lider;
        equipeBox.innerHTML = `<h3>Equipe ${lider}</h3><div class="equipe-operadores"></div>`;
        const equipeContainer = equipeBox.querySelector(".equipe-operadores");

        grupos[lider]
          .slice()
          .sort((a, b) => (this.pesoStatus[a.status] ?? 99) - (this.pesoStatus[b.status] ?? 99))
          .forEach(p => {
            const item = document.createElement("div");
            item.className = `op-item ${p.status}`;
            item.innerHTML = `
              <strong>${p.nome}</strong>
              <small>${this.formatarStatus(p.status)}</small>
              <div class="tempo">${p.motivo_pausa || ""}</div>
            `;

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

      if (typeof window.aplicarFiltroEquipes === "function") window.aplicarFiltroEquipes();
      container.style.transition = "opacity 0.25s ease";
      container.style.opacity = 1;
    }, 80);
  }

  // 📡 Envia ação e força re-render
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
        this.atualizarEstado(true); // 🔥 força atualização imediata
      } else {
        alert(`❌ ${dados.error || "Ação não permitida"}`);
      }
    } catch (err) {
      console.error("[Controle de Pausa] Erro ao enviar ação:", err);
      alert("Erro de comunicação com o servidor.");
    }
  }

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

window.ControlePausaSistema = ControlePausaSistema;

document.addEventListener("DOMContentLoaded", () => {
  console.log("📅 [Controle de Pausa] DOM carregado.");
  if (!window.controle) {
    window.controle = new ControlePausaSistema();
    window.controle.iniciar();
    console.log("✅ [Controle de Pausa] Sistema inicializado automaticamente!");
  }
});

if (document.readyState === "complete" || document.readyState === "interactive") {
  setTimeout(() => {
    if (!window.controle && window.ControlePausaSistema) {
      console.log("🔁 [Controle de Pausa] Inicializando após DOM pronto...");
      window.controle = new ControlePausaSistema();
      window.controle.iniciar();
    }
  }, 100);
}
