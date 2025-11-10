// ============================================================
// controle_pausa.js (v3.3 Integrado e Ordenado)
// ============================================================
// 🔹 Exibe operadores: disponíveis → fila → pausa
// 🔹 Cruza dados de listar_operadores.php + controle_pausa.php
// 🔹 Protegido contra múltiplos carregamentos
// ============================================================

if (typeof window.ControlePausaSistema === "undefined") {
  console.log("%c[Controle de Pausa] Sistema iniciado...", "color:#00ff88;font-weight:bold;");

  class ControlePausaSistema {
    constructor() {
      this.urlPHP = "./php/controle_pausa.php";
      this.urlOperadores = "./php/listar_operadores.php";
      this.intervaloAtualizacao = 10000; // 10s
      this.maxPausas = 2;

      // Seletores principais
      this.listaPausa = document.getElementById("pausa-lista");
      this.listaEspera = document.getElementById("lista-espera");
      this.listaParticipantes = document.getElementById("listaParticipantes");
      this.contPausa = document.getElementById("contador-pausa");
      this.contEspera = document.getElementById("contador-espera");
      this.syncStatus = document.getElementById("sync-status");

      this.todosOperadores = []; // nomes fixos
      this.estado = []; // status atuais
    }

    // ============================================================
    // 🚀 Inicialização
    // ============================================================
    async iniciar() {
      console.log("[Controle de Pausa] Iniciando monitoramento local...");
      await this.carregarParticipantesFixos();
      await this.atualizarEstado();
      setInterval(() => this.atualizarEstado(), this.intervaloAtualizacao);
    }

    // ============================================================
    // 📋 Carrega todos os operadores fixos (base)
    // ============================================================
    async carregarParticipantesFixos() {
      try {
        const resp = await fetch(this.urlOperadores, { cache: "no-store" });
        const dados = await resp.json();

        if (dados.success && Array.isArray(dados.equipes)) {
          const nomes = [];
          dados.equipes.forEach(eq =>
            eq.operadores.forEach(op =>
              nomes.push({ nome: op.nome.trim(), status: "disponivel" })
            )
          );
          this.todosOperadores = nomes;
          console.log(`[Controle de Pausa] ${nomes.length} operadores carregados.`);
        } else {
          console.warn("[Controle de Pausa] Nenhum operador retornado do listar_operadores.php");
        }
      } catch (e) {
        console.error("[Controle de Pausa] Erro ao carregar operadores:", e);
      }
    }

    // ============================================================
    // 🔄 Atualiza estado local do PHP
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

        const estadoServidor = dados.estado || [];

        // 🧠 Cruza com operadores fixos
        const atualizados = this.todosOperadores.map(op => {
          const match = estadoServidor.find(p => p.nome.toLowerCase() === op.nome.toLowerCase());
          return match ? { nome: op.nome, status: match.status } : { ...op };
        });

        this.estado = atualizados;

        this.renderizarListas(this.estado);
        this.syncStatus.textContent = "Sincronizado ✅";
      } catch (e) {
        console.error("[Controle de Pausa] Erro ao atualizar:", e);
        this.syncStatus.textContent = "Erro de conexão ❌";
      }
    }

    // ============================================================
    // 🧩 Renderiza todas as listas e painel principal
    // ============================================================
    renderizarListas(lista) {
      if (!Array.isArray(lista) || !lista.length) return;

      const pausas = lista.filter(p => p.status === "pausa");
      const esperas = lista.filter(p => p.status === "espera");
      const disponiveis = lista.filter(p => p.status === "disponivel");

      if (this.contPausa) this.contPausa.textContent = pausas.length;
      if (this.contEspera) this.contEspera.textContent = esperas.length;

      this.renderizar(this.listaPausa, pausas, "Nenhuma pessoa em pausa ☕");
      this.renderizar(this.listaEspera, esperas, "Ninguém na fila de espera 📋");

      // 🔹 Junta todos em ordem: disponíveis → fila → pausa
      const ordenado = [...disponiveis, ...esperas, ...pausas];
      this.renderizarParticipantes(this.listaParticipantes, ordenado);
    }

    // ============================================================
    // 🔸 Renderiza seções genéricas
    // ============================================================
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
    // 👥 Renderiza lista completa de participantes
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
        // 👤 OPERADOR
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
    // 📡 Envia ações
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
    // 🎨 Utilitários de ícones e texto
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
  // 🧩 Inicialização única
  // ============================================================
  window.ControlePausaSistema = ControlePausaSistema;

  document.addEventListener("DOMContentLoaded", () => {
    if (!window.controle) {
      window.controle = new ControlePausaSistema();
      window.controle.iniciar();
    }
  });
}
