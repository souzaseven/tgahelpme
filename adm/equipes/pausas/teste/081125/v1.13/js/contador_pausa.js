// ============================================================
// controle_pausa.js (v1.6 seguro) - Auto versão + fallback local
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

      console.log(`✅ [Controle] Versão detectada: ${versao}`);
      console.log(`📡 [Controle] Endpoint PHP: ${this.url}`);
    }

    async iniciarMonitoramento() {
      console.log("🟢 [Controle] Monitoramento iniciado...");
      await this.atualizarEstado();
      clearInterval(this.intervalo);
      this.intervalo = setInterval(() => this.atualizarEstado(), 10000);
    }

    async atualizarEstado() {
      try {
        // ✅ Fallback automático (se o caminho dinâmico falhar)
        const caminhoPHP = this.url || "php/controle_pausa.php";
        console.log(`🔄 [Controle] Buscando dados em: ${caminhoPHP}`);

        const resp = await fetch(`${caminhoPHP}?acao=get_estado`);
        const dados = await resp.json();

        if (!dados.success) {
          console.warn("[Controle] Erro ao buscar estado:", dados.error);
          this.syncStatus.textContent = "Erro ao carregar ❌";
          return;
        }

        const lista = dados.estado || [];
        console.log(`👥 [Controle] ${lista.length} participantes carregados`);
        this.renderizarListas(lista);
        this.syncStatus.textContent = "Sincronizado ✅";

        if (window.contadorPausa?.atualizarEstado)
          window.contadorPausa.atualizarEstado(lista);
        if (window.contadorEspera?.atualizarEstado)
          window.contadorEspera.atualizarEstado(lista);
      } catch (e) {
        console.error("[Controle] Falha de conexão:", e);
        this.syncStatus.textContent = "Erro de conexão ❌";
      }
    }

    renderizarListas(lista) {
      const pausas = lista.filter(p => p.status === "pausa");
      const esperas = lista.filter(p => p.status === "espera");
      const todos = lista;

      this.contPausa.textContent = pausas.length;
      this.contEspera.textContent = esperas.length;

      this.renderizar(this.listaPausa, pausas, "Nenhuma pessoa em pausa ☕");
      this.renderizar(this.listaEspera, esperas, "Nenhuma pessoa na fila 📋");
      this.renderizarParticipantes(this.participantes, todos);
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

    renderizarParticipantes(container, lista) {
      if (!container) return;
      container.innerHTML = "";

      if (!lista.length) {
        container.innerHTML = `
          <div class="lista-vazia">
            <div class="loading"></div>
            <div style="margin-top:15px;">Nenhum participante encontrado 👤</div>
          </div>`;
        return;
      }

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
        container.appendChild(div);
      });
    }

    getIcone(status) {
      switch (status) {
        case "pausa": return "fa-coffee";
        case "espera": return "fa-clock";
        case "disponivel": return "fa-user-check";
        case "offline": return "fa-user-slash";
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

  // Inicialização com fallback automático
  window.ControlePausaSistema = ControlePausaSistema;
  window.controle = new ControlePausaSistema();

  if (!window.__controleIniciado) {
    window.__controleIniciado = true;
    const iniciar = () => {
      if (window.controle?.iniciarMonitoramento) {
        console.log("🚀 [Controle] Iniciando monitoramento...");
        window.controle.iniciarMonitoramento();
      } else {
        console.warn("⚠️ [Controle] Tentando novamente...");
        setTimeout(iniciar, 1000);
      }
    };
    document.readyState === "complete"
      ? iniciar()
      : window.addEventListener("DOMContentLoaded", iniciar);
  }
}
