// ============================================================
// inicializacao.js - Sistema de Pausas (v1.8)
// ------------------------------------------------------------
// 🔹 Responsável pela inicialização da interface e login
// 🔹 Aguarda controle_pausa.js via evento "controle:ready"
// 🔹 Compatível com setup_sistema.php e main.js v1.7+
// ============================================================

console.log("%c[Init] 🚀 Inicializando interface do sistema de pausas (v1.8)...", "color:#00ff88;font-weight:bold;");

function inicializarSistema() {
  const modal = document.getElementById("modalOperador");
  const nomeInput = document.getElementById("inputNome");
  const senhaInput = document.getElementById("inputSenha");
  const msgErro = document.getElementById("msgErro");
  const toast = document.getElementById("toast");
  const btnEntrar = document.getElementById("btnEntrar");
  const btnTrocar = document.getElementById("btnTrocarUsuario");

  // ============================================================
  // 🔔 Toast de notificação
  // ============================================================
  function mostrarToast(msg) {
    if (!toast) return;
    toast.textContent = msg;
    toast.classList.add("show");
    setTimeout(() => toast.classList.remove("show"), 3000);
  }

  // ============================================================
  // 🔐 Login e autenticação
  // ============================================================
  async function fazerLogin(nome, senha = "") {
    try {
      btnEntrar.disabled = true;
      btnEntrar.textContent = "Entrando...";

      const endpoint = senha
        ? "php/login_operador.php?acao=login_admin"
        : "php/login_operador.php?acao=validar_operador";

      const response = await fetch(endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ nome, senha }),
      });

      const data = await response.json();

      if (data.success) {
        localStorage.setItem("operador_nome", data.nome_canonico);
        localStorage.setItem("modo_admin", data.role === "admin");
        modal.classList.remove("ativo");
        mostrarToast(`✅ Login realizado: ${data.nome_canonico}`);
        setTimeout(() => location.reload(), 800);
      } else if (data.requer_senha) {
        senhaInput.style.display = "block";
        senhaInput.focus();
        msgErro.textContent = "Digite a senha de administrador";
        msgErro.style.display = "block";
      } else {
        msgErro.textContent = data.error || "Erro no login";
        msgErro.style.display = "block";
      }
    } catch (error) {
      console.error("[Init] Erro no login:", error);
      msgErro.textContent = "Erro de conexão com o servidor";
      msgErro.style.display = "block";
    } finally {
      btnEntrar.disabled = false;
      btnEntrar.textContent = "Entrar";
    }
  }

  // ============================================================
  // 🧠 Eventos do modal e entrada de login
  // ============================================================
  btnEntrar.addEventListener("click", () => {
    const nome = nomeInput.value.trim();
    const senha = senhaInput.value.trim();
    if (!nome) {
      msgErro.textContent = "Digite seu nome";
      msgErro.style.display = "block";
      return;
    }
    fazerLogin(nome, senha);
  });

  nomeInput.addEventListener("input", (e) => {
    const valor = e.target.value.toLowerCase();
    if (valor === "anderson") {
      senhaInput.style.display = "block";
    } else {
      senhaInput.style.display = "none";
      senhaInput.value = "";
      msgErro.style.display = "none";
    }
  });

  senhaInput.addEventListener("keypress", (e) => {
    if (e.key === "Enter") btnEntrar.click();
  });

  nomeInput.addEventListener("keypress", (e) => {
    if (e.key === "Enter") btnEntrar.click();
  });

  // ============================================================
  // 🔁 Trocar usuário (logout)
  // ============================================================
  if (btnTrocar) {
    btnTrocar.addEventListener("click", () => {
      if (confirm("Deseja realmente trocar de usuário?")) {
        localStorage.clear();
        sessionStorage.clear();
        caches.keys().then(keys => keys.forEach(k => caches.delete(k)));
        mostrarToast("✅ Sessão encerrada com sucesso.");
        setTimeout(() => {
          modal.classList.add("ativo");
          nomeInput.focus();
        }, 500);
      }
    });
  }

  // ============================================================
  // 🔍 Verificação de status do sistema (Banco e Operadores)
  // ============================================================
  async function verificarStatus() {
    const statusBanco = document.getElementById("statusBanco");
    const textoBanco = document.getElementById("textoBanco");
    const statusOperadores = document.getElementById("statusOperadores");
    const textoOperadores = document.getElementById("textoOperadores");

    try {
      const respBanco = await fetch("php/conexao.php", { cache: "no-store" });
      if (respBanco.ok) {
        statusBanco.className = "status-icon status-online";
        textoBanco.textContent = "Banco de Dados Conectado";
      } else {
        statusBanco.className = "status-icon status-offline";
        textoBanco.textContent = "Erro no banco";
      }

      const respOperadores = await fetch("php/listar_operadores.php", { cache: "no-store" });
      const data = await respOperadores.json();

      if (data.success) {
        statusOperadores.className = "status-icon status-online";
        textoOperadores.textContent = `${data.equipes.length} equipes carregadas`;
      } else {
        statusOperadores.className = "status-icon status-warning";
        textoOperadores.textContent = `Erro: ${data.error}`;
      }
    } catch (err) {
      console.error("[Init] Erro ao verificar status:", err);
      statusOperadores.className = "status-icon status-offline";
      textoOperadores.textContent = "Erro de conexão";
    }
  }

  setTimeout(verificarStatus, 1000);
  setInterval(verificarStatus, 30000);

  // ============================================================
  // 👤 Verificar se já há usuário logado e iniciar controle
  // ============================================================
  const operadorLogado = localStorage.getItem("operador_nome");

  if (!operadorLogado) {
    console.log("%c[Init] Nenhum operador logado — exibindo modal de login.", "color:#ffaa00;font-weight:bold;");
    setTimeout(() => modal.classList.add("ativo"), 500);
  } else {
    console.log(`%c[Init] 👤 Usuário logado: ${operadorLogado}`, "color:#00ff88;font-weight:bold;");
    const header = document.querySelector("header");
    if (header && !header.querySelector(".usuario-logado")) {
      const userInfo = document.createElement("div");
      userInfo.className = "usuario-logado";
      userInfo.innerHTML = `<i class="fas fa-user-circle"></i> ${operadorLogado}`;
      header.appendChild(userInfo);
    }

    // 🧩 Inicializa controle de pausa quando disponível
    const iniciarControle = () => {
      if (window.controle && typeof window.controle.iniciar === "function") {
        console.log("%c[Init] Controle de pausa detectado — inicializando renderização...", "color:#00ff88;font-weight:bold;");
        window.controle.iniciar();
      } else {
        console.warn("[Init] Controle ainda não disponível, tentando novamente em 500ms...");
        setTimeout(iniciarControle, 500);
      }
    };

    // Se já estiver pronto
    if (window.controleReady || (window.controle && window.controle.pronto)) {
      iniciarControle();
    } else {
      window.addEventListener("controle:ready", () => {
        console.log("%c📢 [Init] Evento 'controle:ready' recebido — iniciando controle.", "color:#00c3ff;font-weight:bold;");
        iniciarControle();
      }, { once: true });
    }
  }
}

// ============================================================
// 🚀 Execução automática
// ============================================================
document.addEventListener("DOMContentLoaded", inicializarSistema);
