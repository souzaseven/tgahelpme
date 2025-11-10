// ============================================================
// controle_pausa.js (v2.4) - Fila inteligente + admin seguro + nome parcial
// ============================================================

// ============================================================
// Identificação automática do operador
// ============================================================
window.OPERADOR_ATUAL = (localStorage.getItem("operador_nome") || "").trim();
window.MODO_ADMIN = localStorage.getItem("modo_admin") === "true";

// Se não existir operador salvo, tenta modal ou prompt
if (!window.OPERADOR_ATUAL) {
  const modal = document.getElementById("modalOperador");
  if (modal) {
    modal.classList.add("ativo");
  } else {
    const nome = prompt("Digite seu nome para continuar:");
    if (nome) {
      localStorage.setItem("operador_nome", nome.trim());
      window.OPERADOR_ATUAL = nome.trim();
    }
  }
}

// Normaliza para comparar
const opLower = window.OPERADOR_ATUAL.toLowerCase();

// ============================================================
// Login de admin (usuário "anderson")
// ============================================================
(async () => {
  // Já autenticado em sessão local?
  if (window.MODO_ADMIN) return;

  if (opLower === "anderson") {
    const senha = prompt("🔐 Digite sua senha de administrador:");
    if (!senha) return;

    try {
      const resp = await fetch("php/verifica_admin.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ senha })
      });
      const data = await resp.json();

      if (data.sucesso) {
        window.MODO_ADMIN = true;
        localStorage.setItem("modo_admin", "true");
        console.log("✅ [Admin] Acesso concedido (anderson).");
      } else {
        alert(data.erro || "Senha incorreta.");
        window.MODO_ADMIN = false;
        localStorage.removeItem("modo_admin");
      }
    } catch (e) {
      console.error("Erro ao validar admin:", e);
      alert("Falha na validação do administrador.");
    }
  } else {
    window.MODO_ADMIN = false;
    localStorage.removeItem("modo_admin");
  }
})();

// ============================================================
// Classe principal
// ============================================================
if (!window.ControlePausaSistema) {
  class ControlePausaSistema {
    constructor() {
      const versao =
        window.SISTEMA_CONFIG?.versao ||
        window.SISTEMA_VERSAO ||
        (() => {
          const path = window.location.pathname.split("/");
          return path[path.indexOf("pausas") + 1] || "v1.0";
        })();

      const phpPath =
        window.SISTEMA_CONFIG?.caminhos?.php ||
        `/adm/equipes/pausas/${versao}/php/`;

      this.url = phpPath + "controle_pausa.php";
      this.intervalo = null;

      this.listaPausa = document.getElementById("pausa-lista");
      this.listaEspera = document.getElementById("lista-espera");
      this.participantes = document.getElementById("listaParticipantes");
      this.contPausa = document.getElementById("contador-pausa");
      this.contEspera = document.getElementById("contador-espera");
      this.syncStatus = document.getElementById("sync-status");
      this.maxPausas = 2;

      console.log(`✅ [Controle] Endpoint PHP: ${this.url}`);
    }

    // ============================================================
    async iniciarMonitoramento() {
      console.log("🟢 [Controle] Monitoramento iniciado...");
      await this.atualizarEstado();
      clearInterval(this.intervalo);
      this.intervalo = setInterval(() => this.atualizarEstado(), 10000);
    }

    // ============================================================
    async atualizarEstado() {
      try {
        const resp = await fetch(`${this.url}?acao=get_estado`);
        const dados = await resp.json();

        if (!dados.success) throw new Error(dados.error || "Erro desconhecido");
        this.estadoAtual = dados.estado || [];

        this.renderizarListas(this.estadoAtual);
        this.syncStatus.textContent = "Sincronizado ✅";

        if (window.contadorPausa?.atualizarEstado)
          window.contadorPausa.atualizarEstado(this.estadoAtual);
      } catch (e) {
        console.error("[Controle] Falha ao atualizar estado:", e);
        this.syncStatus.textContent = "Erro de conexão ❌";
      }
    }

    // ============================================================
    renderizarListas(lista) {
      const pausas = lista.filter(p => p.status === "pausa");
      const esperas = lista.filter(p => p.status === "espera");

      this.contPausa.textContent = pausas.length;
      this.contEspera.textContent = esperas.length;

      this.renderizar(this.listaPausa, pausas, "Nenhuma pessoa em pausa ☕");
      this.renderizar(this.listaEspera, esperas, "Nenhuma pessoa na fila 📋");
      this.renderizarParticipantes(this.participantes, lista);
    }

    renderizar(container, dados, vazioMsg) {
      if (!container) return;
      container.innerHTML = "";

      if (!dados.length) {
        container.innerHTML = `
          <div class="lista-vazia">
            <i class="fas fa-info-circle" style="font-size: 2rem; opacity: 0.5;"></i>
            <div>${vazioMsg}</div>
          </div>`;
        return;
      }

      dados.forEach(p => {
const div = document.createElement("div");
div.className = `item participante ${p.status}`;
div.innerHTML = `
  <div class="item-info">
    <span class="item-nome">${p.nome}</span>
    <span class="item-status">
      <i class="fas ${this.getIcone(p.status)}"></i>
    </span>
  </div>`;

        container.appendChild(div);
      });
    }

    // ============================================================
    renderizarParticipantes(container, lista) {
      if (!container) return;
      container.innerHTML = "";

      const operadorAtual = (window.OPERADOR_ATUAL || "").toLowerCase();
      const ehAdmin = window.MODO_ADMIN || operadorAtual === "anderson";

      const pausas = lista.filter(p => p.status === "pausa");
      const esperas = lista.filter(p => p.status === "espera").sort(
        (a, b) => new Date(a.inicio_espera) - new Date(b.inicio_espera)
      );

      lista.forEach(p => {
        const nomeLower = (p.nome || "").toLowerCase();
        const ehMesmoOperador =
          nomeLower.startsWith(operadorAtual) || nomeLower.includes(operadorAtual);

        const div = document.createElement("div");
        div.className = `item participante ${p.status}`;
        div.innerHTML = `
          <div class="item-info">
            <span class="item-nome">${p.nome}</span>
            <span class="item-status">
              <i class="fas ${this.getIcone(p.status)}"></i> ${this.formatarStatus(p.status)}
            </span>
          </div>`;

        const info = div.querySelector(".item-info");

        // ADMIN: todos os botões
        if (ehAdmin) {
          const botoes = document.createElement("div");
          botoes.className = "admin-botoes";
          botoes.innerHTML = `
            <button class="btn-acao entrar-fila">🕓 Fila</button>
            <button class="btn-acao entrar-pausa">☕ Pausa</button>
            <button class="btn-acao disponivel">✅ Disponível</button>`;
          botoes.querySelector(".entrar-fila").onclick = () => this.enviarAcao("entrar_fila", p.nome);
          botoes.querySelector(".entrar-pausa").onclick = () => this.enviarAcao("forcar_pausa", p.nome);
          botoes.querySelector(".disponivel").onclick = () => this.enviarAcao("voltar_disponivel", p.nome);
          info.appendChild(botoes);
        }

        // OPERADOR comum
        else if (ehMesmoOperador) {
          const botoes = document.createElement("div");
          botoes.className = "user-botoes";

          if (p.status === "disponivel") {
            botoes.innerHTML = `<button class="btn-acao entrar-fila">🕓 Entrar na fila</button>`;
            botoes.querySelector(".entrar-fila").onclick = () => this.enviarAcao("entrar_fila", p.nome);
          } else if (p.status === "espera") {
            const souPrimeiro = esperas.length && esperas[0].nome === p.nome;
            const vagaDisponivel = pausas.length < this.maxPausas;
            if (souPrimeiro && vagaDisponivel) {
              botoes.innerHTML = `<button class="btn-acao entrar-pausa">☕ Entrar em pausa</button>`;
              botoes.querySelector(".entrar-pausa").onclick = () => this.enviarAcao("entrar_pausa", p.nome);
            } else {
              botoes.innerHTML = `<button class="btn-acao" disabled>⏳ Aguardando vaga...</button>`;
            }
          } else if (p.status === "pausa") {
            botoes.innerHTML = `<button class="btn-acao disponivel">✅ Voltar</button>`;
            botoes.querySelector(".disponivel").onclick = () => this.enviarAcao("voltar_disponivel", p.nome);
          }

          info.appendChild(botoes);
        }

        container.appendChild(div);
      });
    }

    // ============================================================
    async enviarAcao(acao, nome) {
      try {
        const payload = {
          acao,
          nome,
          solicitante: window.OPERADOR_ATUAL || "",
          admin: !!window.MODO_ADMIN
        };

        const resp = await fetch(`${this.url}?acao=${acao}`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload)
        });
        const dados = await resp.json();

        if (dados.success) {
          console.log(`✅ [Controle] ${acao} executada para ${nome}`);
          this.atualizarEstado();
        } else {
          alert(`❌ ${dados.error || "Ação não permitida."}`);
        }
      } catch (err) {
        console.error("[Controle] Erro ao enviar ação:", err);
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
        case "disponivel": return "Disponível";
        case "pausa": return "Em pausa";
        case "espera": return "Na fila";
        default: return status;
      }
    }
  }

  // ============================================================
  // Inicialização
  // ============================================================
  window.ControlePausaSistema = ControlePausaSistema;
  window.controle = new ControlePausaSistema();

  const iniciar = () => {
    if (window.controle?.iniciarMonitoramento) {
      console.log("🚀 [Controle] Iniciando monitoramento (v2.4)...");
      window.controle.iniciarMonitoramento();
    } else setTimeout(iniciar, 1000);
  };

  if (document.readyState === "complete") iniciar();
  else window.addEventListener("DOMContentLoaded", iniciar);
}
