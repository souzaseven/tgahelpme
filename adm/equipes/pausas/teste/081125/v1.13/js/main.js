// ============================================================
// main.js (v1.6 final revisado) - Inicialização central automática
// ============================================================

// ============================================================
// Identificação do operador (salva no navegador)
// ============================================================
(() => {
  const operador = localStorage.getItem("operador_nome");

  if (operador) {
    console.log(`👤 [Sistema] Logado como: ${operador}`);

    // Mostra nome no topo
    const header = document.querySelector("header");
    if (header && !header.querySelector(".usuario-logado")) {
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

// ============================================================
// Inicialização Principal
// ============================================================
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
})(); // ✅ <-- Fechando corretamente a função principal

// ============================================================
// Botão de desconectar (com fallback dinâmico)
// ============================================================
function inicializarLogout() {
  const logout = document.getElementById("btnLogout");
  if (!logout) {
    setTimeout(inicializarLogout, 500);
    return;
  }

  // Esconde botão se não houver operador logado
  if (!localStorage.getItem("operador_nome")) {
    logout.style.display = "none";
  } else {
    logout.style.display = "block";
  }

logout.addEventListener("click", async () => {
  if (confirm("Deseja desconectar e trocar de usuário?")) {
    await fetch("php/logout.php");
    localStorage.removeItem("operador_nome");
    localStorage.removeItem("modo_admin");
    location.reload();
  }
});

}

document.addEventListener("DOMContentLoaded", inicializarLogout);


// ==========================
// login_operador.js
// ==========================
document.addEventListener("DOMContentLoaded", () => {
  const modal = document.getElementById("modalOperador");
  const nomeInput = document.getElementById("nomeOperador");
  const senhaContainer = document.getElementById("senhaContainer");
  const senhaInput = document.getElementById("senhaOperador");
  const btnLogin = document.getElementById("btnLogin");
  const btnLogout = document.getElementById("btnLogout");

  // Mostrar campo de senha só se digitar "anderson"
  nomeInput.addEventListener("input", () => {
    const nome = nomeInput.value.trim().toLowerCase();
    senhaContainer.style.display = (nome === "anderson") ? "block" : "none";
  });

  // Login
  btnLogin.addEventListener("click", async () => {
    const nome = nomeInput.value.trim();
    const senha = senhaInput.value.trim();

    if (!nome) {
      alert("Digite seu nome para continuar.");
      return;
    }

    try {
      const resp = await fetch("php/login_operador.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ nome, senha })
      });
      const data = await resp.json();

      if (data.success) {
        localStorage.setItem("operador_nome", nome);
        if (data.admin) localStorage.setItem("modo_admin", "true");
        modal.style.display = "none";
        location.reload();
      } else {
        alert(data.error || "Erro ao autenticar.");
      }
    } catch (err) {
      console.error("Erro de login:", err);
      alert("Falha na comunicação com o servidor.");
    }
  });

  // Logout
  btnLogout.addEventListener("click", async () => {
    if (confirm("Deseja desconectar e trocar de usuário?")) {
      await fetch("php/logout.php");
      localStorage.removeItem("operador_nome");
      localStorage.removeItem("modo_admin");
      location.reload();
    }
  });
});
