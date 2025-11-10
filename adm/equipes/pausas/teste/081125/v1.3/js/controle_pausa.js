// ============================================================
// controle_pausa.js (v3.1 Integrado com Evolux)
// ============================================================
// 🔹 Função: Gerencia pausas e fila local
// 🔹 Integração: Sincroniza automaticamente com o backend PHP
// ============================================================

console.log("%c[Controle de Pausa] Sistema iniciado...", "color:#00ff88;font-weight:bold;");

class ControlePausaSistema {
  constructor() {
    this.urlPHP = "./php/controle_pausa.php";
    this.intervaloAtualizacao = 10000; // Atualização local a cada 10s
    this.maxPausas = 2;

    // Elementos principais
    this.listaPausa = document.getElementById("pausa-lista");
    this.listaEspera = document.getElementById("lista-espera");
    this.listaParticipantes = document.getElementById("listaParticipantes");
    this.contPausa = document.getElementById("contador-pausa");
    this.contEspera = document.getElementById("contador-espera");
    this.syncStatus = document.getElementById("sync-status");

    // Estado interno
    this.estado = [];
  }

  // ============================================================
  // 🔁 Início do monitoramento local
  // ============================================================
  async iniciar() {
    console.log("[Controle de Pausa] Iniciando monitoramento local...");
    await this.atualizarEstado();
    setInterval(() => this.atualizarEstado(), this.intervaloAtualizacao);
  }

  // ============================================================
  // 🔄 Atualiza estado local do servidor PHP
  // ============================================================
  async atualizarEstado() {
    if (!this.syncStatus) return;

    this.syncStatus.textContent = "Sincronizando...";
    try {
      const resp = await fetch(`${this.urlPHP}?acao=get_estado`, { cache: "no-store" });
      const dados = await resp.json();

      if (!dados.success) {
        console.warn("[Controle de Pausa] Falha na resposta:", dados);
        this.syncStatus.textContent = "Erro de comunicação ❌";
        return;
      }

      this.estado = dados.estado || [];
      this.renderizarListas(this.estado);
      this.syncStatus.textContent = "Sincronizado ✅";
    } catch (e) {
      console.error("[Controle de Pausa] Erro ao atualizar:", e);
      this.syncStatus.textContent = "Erro de conexão ❌";
    }
  }

  // ============================================================
  // 🧩 Renderiza as listas no painel
  // ============================================================
  renderizarListas(lista) {
    if (!Array.isArray(lista)) return;

    const pausas = lista.filter(p => p.status === "pausa");
    const esperas = lista.filter(p => p.status === "espera");

    if (this.contPausa) this.contPausa.textContent = pausas.length;
    if (this.contEspera) this.contEspera.textContent = esperas.length;

    this.renderizar(this.listaPausa, pausas, "Nenhuma pessoa em pausa ☕");
    this.renderizar(this.listaEspera, esperas, "Ninguém na fila de espera 📋");
    this.renderizarParticipantes(this.listaParticipantes, lista);
  }

  renderizar(container, dados, vazioMsg) {
    if (!container) return;
    container.innerHTML = "";

    if (!dados.length) {
      container.innerHTML = `
        <div class="lista-vazia">
          <i class="fas fa-info-circle" style="font-size:2rem;opacity:0.5;"></i>
          <div>${vazioMsg}</div>
        </div>`;
      return;
    }

    dados.forEach(p => {
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
    });
  }

  // ============================================================
  // 👥 Participantes - renderização com botões
  // ============================================================
  renderizarParticipantes(container, lista) {
    if (!container) return;
    container.innerHTML = "";

    if (!lista.length) {
      container.innerHTML = `
        <div class="lista-vazia">
          <div class="loading"></div>
          <div style="margin-top:15px;">Carregando participantes...</div>
        </div>`;
      return;
    }

    const operadorAtual = (localStorage.getItem("operador_nome") || "").toLowerCase();
    const modoAdmin = localStorage.getItem("modo_admin") === "true";

    lista.forEach(p => {
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

      // 🔐 ADMIN
      if (modoAdmin) {
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

      // 👤 OPERADOR comum
      else if (p.nome.toLowerCase() === operadorAtual) {
        const botoes = document.createElement("div");
        botoes.className = "user-botoes";

        if (p.status === "disponivel") {
          botoes.innerHTML = `<button class="btn-acao entrar-fila">🕓 Entrar na Fila</button>`;
          botoes.querySelector(".entrar-fila").onclick = () => this.enviarAcao("entrar_fila", p.nome);
        } else if (p.status === "espera") {
          botoes.innerHTML = `<button class="btn-acao" disabled>⏳ Aguardando vaga...</button>`;
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
  // 📡 Envia ações para o backend PHP
  // ============================================================
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

  // ============================================================
  // 🎨 Ícones e status
  // ============================================================
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

// ============================================================
// 🚀 Inicialização global imediata
// ============================================================
window.controle = new ControlePausaSistema();
window.controle.iniciar();
