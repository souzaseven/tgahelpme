// ============================================================
// main.js (v1.6 final) - Inicialização central automática
// ============================================================
// ============================================================
// Identificação do operador (salva no navegador)
// ============================================================
(() => {
  let operador = localStorage.getItem("operador_nome");
  if (!operador) {
    operador = prompt("👋 Olá! Digite seu nome para entrar no sistema:")?.trim();
    if (operador) {
      operador = operador.charAt(0).toUpperCase() + operador.slice(1).toLowerCase();
      localStorage.setItem("operador_nome", operador);
    }
  }

  // Exibe no console e na tela
  if (operador) {
    console.log(`👤 [Sistema] Logado como: ${operador}`);
    const header = document.querySelector("header");
    if (header) {
      const userInfo = document.createElement("div");
      userInfo.className = "usuario-logado";
      userInfo.innerHTML = `<i class="fas fa-user-circle"></i> ${operador}`;
      header.appendChild(userInfo);
    }
  }

  // Define modo administrador (qualquer variação de 'anderson')
  if (operador && operador.toLowerCase() === "anderson") {
    window.MODO_ADMIN = true;
    console.log("🧑‍💼 [Sistema] Modo ADMIN ativado!");
    document.body.classList.add("modo-admin");
  } else {
    window.MODO_ADMIN = false;
  }
})();


(() => {
  console.log("🔧 [Main] Inicializando sistema de pausas...");

  // 🧠 Detecta a versão atual automaticamente
  const versao =
    window.SISTEMA_CONFIG?.versao ||
    window.SISTEMA_VERSAO ||
    (() => {
      const path = window.location.pathname.split("/");
      return path[path.indexOf("pausas") + 1] || "v1.0";
    })();

  // 🗂️ Caminhos principais
  const baseJS =
    window.SISTEMA_CONFIG?.caminhos?.js ||
    `/adm/equipes/pausas/${versao}/js/`;

  const basePHP =
    window.SISTEMA_CONFIG?.caminhos?.php ||
    `/adm/equipes/pausas/${versao}/php/`;

  console.log(`🧠 [Main] Versão detectada: ${versao}`);
  console.log(`📁 [Main] JS base: ${baseJS}`);
  console.log(`🗄️ [Main] PHP base: ${basePHP}`);

  // ============================================================
  // Função para iniciar o sistema
  // ============================================================
  const iniciarSistema = async () => {
    try {
      // 1️⃣ Autenticação (se existir)
      if (window.sistemaAutenticacao?.iniciarAutenticacao && !window.__authIniciado) {
        window.__authIniciado = true;
        console.log("🔐 [Main] Iniciando autenticação...");
        await sistemaAutenticacao.iniciarAutenticacao();
      }

      // 2️⃣ Controle de Pausas
      if (window.controle?.iniciarMonitoramento && !window.__controleIniciado) {
        window.__controleIniciado = true;
        console.log("🟢 [Main] Iniciando controle de pausas...");
        window.controle.iniciarMonitoramento();
      } else if (!window.controle) {
        console.warn("⚠️ [Main] Controle de pausas ainda não disponível. Tentando novamente...");
        setTimeout(iniciarSistema, 1000);
        return;
      }

      // 3️⃣ Diagnóstico opcional
      if (window.MODO_DIAGNOSTICO || location.search.includes("debug=1")) {
        if (typeof executarDiagnosticoCompleto === "function") {
          console.log("🧩 [Main] Executando diagnóstico completo...");
          setTimeout(executarDiagnosticoCompleto, 1500);
        }
      }

      // 4️⃣ Permissão de notificação (caso necessário)
      if ("Notification" in window && Notification.permission === "default") {
        Notification.requestPermission().catch(() => {});
      }

      console.log("✅ [Main] Sistema totalmente inicializado.");
    } catch (erro) {
      console.error("❌ [Main] Erro na inicialização:", erro);
    }
  };

  // ============================================================
  // Disparo Automático (com fallback)
  // ============================================================
  if (!window.__mainIniciado) {
    window.__mainIniciado = true;
    if (document.readyState === "complete") {
      iniciarSistema();
    } else {
      document.addEventListener("DOMContentLoaded", iniciarSistema);
      window.addEventListener("load", () => setTimeout(iniciarSistema, 500));
    }
  }
})();


// Botão de desconectar
document.addEventListener("DOMContentLoaded", () => {
  const logout = document.getElementById("btnLogout");
  if (logout) {
    logout.addEventListener("click", () => {
      if (confirm("Deseja desconectar e trocar de usuário?")) {
        localStorage.removeItem("operador_nome");
        localStorage.removeItem("modo_admin");
        location.reload();
      }
    });
  }
});

