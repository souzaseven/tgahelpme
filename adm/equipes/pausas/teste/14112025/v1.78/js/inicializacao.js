// ============================================================
// inicializacao.js - Sistema de Pausas (v2.0 - focado em status/controle)
// ------------------------------------------------------------
// 🔹 NÃO cuida mais do login (isso é do login.js)
// 🔹 Verifica status (banco + operadores)
// 🔹 Abre modal se não tiver operador logado
// 🔹 Inicializa controle de pausas se já houver login
// ============================================================

console.log(
  "%c[Init] 🚀 Inicializando sistema de pausas (core)...",
  "color:#10b981;font-weight:bold;"
);

function inicializarSistema() {
  const modal = document.getElementById("modalOperador");

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
        statusBanco && (statusBanco.className = "status-icon status-online");
        textoBanco && (textoBanco.textContent = "Banco de Dados Conectado");
      } else {
        statusBanco && (statusBanco.className = "status-icon status-offline");
        textoBanco && (textoBanco.textContent = "Erro no banco");
      }

      const respOperadores = await fetch("php/listar_operadores.php", {
        cache: "no-store",
      });
      const data = await respOperadores.json();

      if (data.success) {
        statusOperadores &&
          (statusOperadores.className = "status-icon status-online");
        const totalEquipes = (data.equipes && data.equipes.length) || 0;
        textoOperadores &&
          (textoOperadores.textContent = `${totalEquipes} equipes carregadas`);
      } else {
        statusOperadores &&
          (statusOperadores.className = "status-icon status-warning");
        textoOperadores &&
          (textoOperadores.textContent = `Erro: ${data.error}`);
      }
    } catch (err) {
      console.error("[Init] Erro ao verificar status:", err);
      statusOperadores &&
        (statusOperadores.className = "status-icon status-offline");
      textoOperadores &&
        (textoOperadores.textContent = "Erro de conexão");
    }
  }

  setTimeout(verificarStatus, 1000);
  setInterval(verificarStatus, 30000);

  // ============================================================
  // 👤 Verificar se já há usuário logado e iniciar controle
  // ============================================================
  const operadorLogado = localStorage.getItem("operador_nome");

  if (!operadorLogado) {
    console.log(
      "%c[Init] Nenhum operador logado — exibindo modal de login.",
      "color:#f59e0b;font-weight:bold;"
    );
    setTimeout(() => {
      if (modal) modal.classList.add("ativo");
    }, 500);
  } else {
    console.log(
      `%c[Init] 👤 Usuário logado: ${operadorLogado}`,
      "color:#10b981;font-weight:bold;"
    );

    // Exibe o nome no header, se aplicável
    const header = document.querySelector("header");
    if (header && !header.querySelector(".usuario-logado")) {
      const userInfo = document.createElement("div");
      userInfo.className = "usuario-logado";
      userInfo.innerHTML = `<i class="fas fa-user-circle"></i> ${operadorLogado}`;
      header.appendChild(userInfo);
    }

    // Inicializa o controle de pausas quando disponível
    const iniciarControle = () => {
      if (window.controle && typeof window.controle.iniciar === "function") {
        console.log(
          "%c[Init] Controle de pausa detectado — inicializando...",
          "color:#10b981;font-weight:bold;"
        );
        window.controle.iniciar();
      } else {
        console.warn(
          "[Init] Controle ainda não disponível, tentando em 500ms..."
        );
        setTimeout(iniciarControle, 500);
      }
    };

    if (window.controleReady || (window.controle && window.controle.pronto)) {
      iniciarControle();
    } else {
      window.addEventListener(
        "controle:ready",
        () => {
          console.log(
            "%c📢 [Init] Evento 'controle:ready' recebido — iniciando controle.",
            "color:#0ea5e9;font-weight:bold;"
          );
          iniciarControle();
        },
        { once: true }
      );
    }
  }
}

document.addEventListener("DOMContentLoaded", inicializarSistema);
