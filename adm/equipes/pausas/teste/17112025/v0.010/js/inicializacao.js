// ============================================================
// inicializacao.js - Sistema de Pausas (v1.8) [LIMPO]
// ------------------------------------------------------------
// 🔹 Totalmente compatível com login por equipe
// 🔹 SEM login manual (inputs removidos)
// 🔹 SEM erros de elementos inexistentes
// 🔹 Apenas controla abertura do modal e inicialização do sistema
// ============================================================

console.log("%c[Init] 🚀 Inicializando interface do sistema de pausas (v1.8)...", "color:#00ff88;font-weight:bold;");

function inicializarSistema() {

  const modal = document.getElementById("modalOperador");
  const btnEntrar = document.getElementById("btnEntrar");
  const msgErro = document.getElementById("msgErro");
  const toast = document.getElementById("toast");
  const btnTrocar = document.getElementById("btnTrocarUsuario");

  // ============================================================
  // 🔔 Toast
  // ============================================================
  function mostrarToast(msg) {
    if (!toast) return;
    toast.textContent = msg;
    toast.classList.add("show");
    setTimeout(() => toast.classList.remove("show"), 3000);
  }

  // ============================================================
  // 🔐 Login via Equipe
  // ============================================================

  async function fazerLoginEquipe() {
    try {
      btnEntrar.disabled = true;
      btnEntrar.textContent = "Entrando...";

      const operadorNome = localStorage.getItem("operador_nome_tmp");
      if (!operadorNome) {
        msgErro.textContent = "Selecione uma equipe e um operador.";
        msgErro.style.display = "block";
        return;
      }

      // login simples, sem senha
      const response = await fetch("php/login_operador.php?acao=validar_operador", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ nome: operadorNome })
      });

      const data = await response.json();

      if (data.success) {
        localStorage.setItem("operador_nome", data.nome_canonico);
        localStorage.removeItem("operador_nome_tmp");

        modal.classList.remove("ativo");
        mostrarToast(`✅ Login realizado: ${data.nome_canonico}`);

        setTimeout(() => location.reload(), 800);

      } else {
        msgErro.textContent = data.error || "Erro ao autenticar operador";
        msgErro.style.display = "block";
      }

    } catch (erro) {
      console.error("[Init] Erro no login:", erro);
      msgErro.textContent = "Falha ao conectar no servidor";
      msgErro.style.display = "block";
    } finally {
      btnEntrar.disabled = false;
      btnEntrar.textContent = "Entrar";
    }
  }

  // Evento do botão "Entrar"
  if (btnEntrar) {
    btnEntrar.addEventListener("click", fazerLoginEquipe);
  }

  // ============================================================
  // 🔁 Trocar usuário (logout)
  // ============================================================
  if (btnTrocar) {
    btnTrocar.addEventListener("click", () => {
      if (confirm("Deseja realmente trocar de usuário?")) {
        localStorage.clear();
        sessionStorage.clear();
        caches.keys().then(keys => keys.forEach(k => caches.delete(k)));

        mostrarToast("✅ Sessão encerrada.");
        setTimeout(() => modal.classList.add("ativo"), 500);
      }
    });
  }

  // ============================================================
  // 🔍 Verificação de status (banco/equipes)
  // ============================================================
  async function verificarStatus() {
    const statusBanco = document.getElementById("statusBanco");
    const textoBanco = document.getElementById("textoBanco");
    const statusOperadores = document.getElementById("statusOperadores");
    const textoOperadores = document.getElementById("textoOperadores");

    try {
      const respBanco = await fetch("php/conexao.php");
      if (respBanco.ok) {
        statusBanco.className = "status-icon status-online";
        textoBanco.textContent = "Banco de Dados Conectado";
      } else {
        statusBanco.className = "status-icon status-offline";
        textoBanco.textContent = "Erro no banco";
      }

      const respOps = await fetch("php/listar_operadores.php");
      const data = await respOps.json();

      if (data.success) {
        statusOperadores.className = "status-icon status-online";
        textoOperadores.textContent = `${data.equipes.length} equipes carregadas`;
      } else {
        statusOperadores.className = "status-icon status-warning";
        textoOperadores.textContent = data.error;
      }

    } catch (err) {
      console.error("[Init] Erro ao verificar status:", err);
      textoOperadores.textContent = "Erro de conexão";
      statusOperadores.className = "status-icon status-offline";
    }
  }

  setTimeout(verificarStatus, 1000);
  setInterval(verificarStatus, 30000);

  // ============================================================
  // 👤 Se já logado → iniciar sistema
  // ============================================================
  const operadorLogado = localStorage.getItem("operador_nome");

  if (!operadorLogado) {
    console.log("%c[Init] Nenhum operador logado — exibindo modal de login.", "color:#ffaa00;font-weight:bold;");
    setTimeout(() => modal.classList.add("ativo"), 500);
  } else {
    const header = document.querySelector("header");
    if (header && !header.querySelector(".usuario-logado")) {
      const userInfo = document.createElement("div");
      userInfo.className = "usuario-logado";
      userInfo.innerHTML = `<i class="fas fa-user-circle"></i> ${operadorLogado}`;
      header.appendChild(userInfo);
    }

    const iniciarControle = () => {
      if (window.controle && typeof window.controle.iniciar === "function") {
        console.log("%c[Init] Controle de Pausa iniciado.", "color:#00ff88;font-weight:bold;");
        window.controle.iniciar();
      } else {
        setTimeout(iniciarControle, 300);
      }
    };

    iniciarControle();
  }
}

// ============================================================
// 🚀 Execução automática
// ============================================================
document.addEventListener("DOMContentLoaded", inicializarSistema);
