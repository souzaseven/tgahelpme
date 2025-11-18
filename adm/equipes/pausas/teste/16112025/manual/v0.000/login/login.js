// ============================================================
// login.js - Controle do Modal de Login (somente seleção de equipe)
// ============================================================

console.log(
  "%c[Login] 🧩 Inicializando lógica de login...",
  "color:#4f46e5;font-weight:bold;"
);

// Controle de visibilidade do modal baseado no login
function verificarEstadoLogin() {
    const operadorLogado = localStorage.getItem("operador_nome");
    const modal = document.getElementById("modalOperador");
    
    if (!operadorLogado) {
        // Mostrar modal se não estiver logado
        if (modal) {
            modal.classList.add("ativo");
            document.body.style.overflow = "hidden"; // Previne scroll
        }
    } else {
        // Esconder modal se estiver logado
        if (modal) {
            modal.classList.remove("ativo");
            document.body.style.overflow = ""; // Restaura scroll
        }
        // Atualizar informações do usuário no header
        atualizarInfoUsuario(operadorLogado);
    }
}

function atualizarInfoUsuario(nome) {
    const userName = document.getElementById("userName");
    const userAvatar = document.getElementById("userAvatar");
    const userRole = document.getElementById("userRole");
    const equipeInfo = document.getElementById("equipeInfo");
    
    if (userName) userName.textContent = nome;
    if (userAvatar) userAvatar.textContent = nome.charAt(0).toUpperCase();
    if (userRole) {
        const isAdmin = localStorage.getItem("modo_admin") === "true";
        userRole.textContent = isAdmin ? "Administrador" : "Operador";
    }
    if (equipeInfo) equipeInfo.textContent = `Equipe: ${nome}`;
}

document.addEventListener("DOMContentLoaded", () => {
  // Verificar estado do login ao carregar a página
  verificarEstadoLogin();

  const modal = document.getElementById("modalOperador");

  // Toast
  const toast = document.getElementById("toast");

  // Fluxo equipe/nome
  const stepEquipes = document.getElementById("stepEquipes");
  const stepOperadores = document.getElementById("stepOperadores");
  const listaEquipes = document.getElementById("listaEquipes");
  const listaOperadores = document.getElementById("listaOperadores");
  const btnVoltarEquipes = document.getElementById("btnVoltarEquipes");
  const btnConfirmarOperador = document.getElementById("btnConfirmarOperador");
  const tituloEquipeSelecionada = document.getElementById("tituloEquipeSelecionada");
  const msgErroEquipe = document.getElementById("msgErroEquipe");

  // Botão de trocar usuário
  const btnTrocar = document.getElementById("btnTrocarUsuario");
  const btnLogout = document.getElementById("btnLogout");

  let equipeSelecionada = null;
  let operadorSelecionado = null;

  // ============================================================
  // 🔔 Toast
  // ============================================================
  function mostrarToast(msg, tipo = "sucesso") {
    if (!toast) return;
    toast.textContent = msg;
    toast.className = ""; // Reset classes
    toast.classList.add(tipo === "erro" ? "erro" : "sucesso");
    toast.classList.add("mostrar");
    
    setTimeout(() => {
      toast.classList.remove("mostrar");
    }, 3000);
  }

  // ============================================================
  // 🎨 Navegação entre steps
  // ============================================================
  function mostrarPassoEquipes() {
    if (!stepEquipes || !stepOperadores) return;
    stepEquipes.classList.remove("hidden");
    stepOperadores.classList.add("hidden");
    equipeSelecionada = null;
    operadorSelecionado = null;
    if (btnConfirmarOperador) btnConfirmarOperador.disabled = true;
    if (msgErroEquipe) {
      msgErroEquipe.style.display = "none";
      msgErroEquipe.classList.remove("mostrar");
    }
  }

  function mostrarPassoOperadores(equipe) {
    if (!stepEquipes || !stepOperadores) return;
    stepEquipes.classList.add("hidden");
    stepOperadores.classList.remove("hidden");
    if (tituloEquipeSelecionada) {
      tituloEquipeSelecionada.textContent = `Equipe: ${equipe}`;
    }
  }

  // ============================================================
  // 🔐 Login
  // ============================================================
  async function fazerLogin(nome) {
    if (!nome) {
      mostrarToast("❌ Selecione um operador para continuar", "erro");
      return;
    }

    try {
      if (btnConfirmarOperador) {
        btnConfirmarOperador.disabled = true;
        btnConfirmarOperador.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Entrando...';
      }

      // Verifica se é admin para pedir senha
      if (nome.toLowerCase() === 'anderson') {
        const senha = prompt("Para acessar como admin, digite a senha:");
        if (!senha) {
          mostrarToast("❌ Senha necessária para acesso admin", "erro");
          return;
        }

        const response = await fetch("php/login_operador.php?acao=login_admin", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ nome, senha }),
        });

        const data = await response.json();

        if (data.success) {
          finalizarLogin(data.nome_canonico, data.role === "admin");
        } else {
          mostrarToast("❌ " + (data.error || "Senha inválida"), "erro");
        }
      } else {
        // Login normal de operador
        const response = await fetch("php/login_operador.php?acao=validar_operador", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ nome }),
        });

        const data = await response.json();

        if (data.success) {
          finalizarLogin(data.nome_canonico, data.role === "admin");
        } else if (data.requer_senha) {
          const senha = prompt("Digite a senha de administrador:");
          if (senha) {
            // Tenta login como admin com a senha fornecida
            const responseAdmin = await fetch("php/login_operador.php?acao=login_admin", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ nome, senha }),
            });
            
            const dataAdmin = await responseAdmin.json();
            if (dataAdmin.success) {
              finalizarLogin(dataAdmin.nome_canonico, dataAdmin.role === "admin");
            } else {
              mostrarToast("❌ " + (dataAdmin.error || "Senha inválida"), "erro");
            }
          }
        } else {
          mostrarToast("❌ " + (data.error || "Erro no login"), "erro");
        }
      }
    } catch (erro) {
      console.error("[Login] Erro no login:", erro);
      mostrarToast("❌ Erro de conexão com o servidor", "erro");
    } finally {
      if (btnConfirmarOperador) {
        btnConfirmarOperador.disabled = false;
        btnConfirmarOperador.innerHTML = '<i class="fas fa-check"></i> Confirmar seleção';
      }
    }
  }

  function finalizarLogin(nome, isAdmin) {
    localStorage.setItem("operador_nome", nome);
    localStorage.setItem("modo_admin", isAdmin);

    if (modal) {
      modal.classList.remove("ativo");
      document.body.style.overflow = ""; // Restaura scroll
    }
    
    mostrarToast(`✅ Login realizado: ${nome}`);
    atualizarInfoUsuario(nome);
    
    // Atualizar a interface do dashboard
    setTimeout(() => {
      if (typeof atualizarDashboard === 'function') {
        atualizarDashboard();
      }
    }, 500);
  }

  // ============================================================
  // 🌐 Consumo de equipes e operadores
  // ============================================================
  let cacheEquipesLogin = null;

  async function carregarEquipes() {
    if (!listaEquipes) return;

    listaEquipes.innerHTML = '<div class="info-carregando"><i class="fas fa-spinner fa-spin"></i> Carregando equipes...</div>';

    try {
      if (!cacheEquipesLogin) {
        const resp = await fetch("php/listar_equipes_login.php", { cache: "no-store" });
        const data = await resp.json();

        if (!data.success) {
          listaEquipes.innerHTML = `<div class="info-erro"><i class="fas fa-exclamation-triangle"></i> Erro: ${data.error || "Não foi possível listar as equipes."}</div>`;
          return;
        }

        cacheEquipesLogin = data.equipes || [];
      }

      const equipes = cacheEquipesLogin;
      if (!equipes.length) {
        listaEquipes.innerHTML = '<div class="info-erro"><i class="fas fa-users"></i> Nenhuma equipe encontrada.</div>';
        return;
      }

      listaEquipes.innerHTML = "";

      equipes.forEach((eq) => {
        const card = document.createElement("button");
        card.className = "card-equipe";
        card.dataset.lider = eq.lider;

        card.innerHTML = `
          <i class="fas fa-users"></i>
          <div>${eq.lider}</div>
        `;

        card.addEventListener("click", () => {
          document.querySelectorAll(".card-equipe").forEach(c => c.classList.remove("selecionado"));
          card.classList.add("selecionado");
          equipeSelecionada = eq.lider;
          setTimeout(() => carregarOperadores(eq.lider), 300);
        });

        listaEquipes.appendChild(card);
      });
    } catch (erro) {
      console.error("[Login] Erro ao carregar equipes:", erro);
      listaEquipes.innerHTML = '<div class="info-erro"><i class="fas fa-exclamation-triangle"></i> Erro de conexão ao buscar equipes.</div>';
    }
  }

  async function carregarOperadores(lider) {
    if (!listaOperadores) return;

    listaOperadores.innerHTML = '<div class="info-carregando"><i class="fas fa-spinner fa-spin"></i> Carregando operadores...</div>';
    operadorSelecionado = null;
    if (btnConfirmarOperador) btnConfirmarOperador.disabled = true;
    if (msgErroEquipe) {
      msgErroEquipe.style.display = "none";
      msgErroEquipe.classList.remove("mostrar");
    }

    try {
      if (!cacheEquipesLogin) {
        const resp = await fetch("php/listar_equipes_login.php", { cache: "no-store" });
        const data = await resp.json();
        if (!data.success) {
          listaOperadores.innerHTML = `<div class="info-erro"><i class="fas fa-exclamation-triangle"></i> Erro: ${data.error || "Não foi possível listar os operadores."}</div>`;
          return;
        }
        cacheEquipesLogin = data.equipes || [];
      }

      const equipeObj = cacheEquipesLogin.find((eq) => eq.lider === lider);

      if (!equipeObj) {
        listaOperadores.innerHTML = '<div class="info-erro"><i class="fas fa-users"></i> Equipe não encontrada.</div>';
        return;
      }

      const operadores = equipeObj.operadores || [];
      if (!operadores.length) {
        listaOperadores.innerHTML = '<div class="info-erro"><i class="fas fa-user-times"></i> Nenhum operador cadastrado nessa equipe.</div>';
        return;
      }

      listaOperadores.innerHTML = "";
      mostrarPassoOperadores(lider);

      operadores.forEach((nome) => {
        const card = document.createElement("button");
        card.className = "card-operador";
        card.dataset.nome = nome;

        card.innerHTML = `
          <i class="fas fa-user-circle"></i>
          <div>${nome}</div>
        `;

        card.addEventListener("click", () => {
          operadorSelecionado = nome;
          document.querySelectorAll(".card-operador.selecionado").forEach((el) => el.classList.remove("selecionado"));
          card.classList.add("selecionado");
          if (btnConfirmarOperador) btnConfirmarOperador.disabled = false;
          if (msgErroEquipe) {
            msgErroEquipe.style.display = "none";
            msgErroEquipe.classList.remove("mostrar");
          }
        });

        listaOperadores.appendChild(card);
      });
    } catch (erro) {
      console.error("[Login] Erro ao carregar operadores:", erro);
      listaOperadores.innerHTML = '<div class="info-erro"><i class="fas fa-exclamation-triangle"></i> Erro de conexão ao buscar operadores.</div>';
    }
  }

  // ============================================================
  // 🧭 Eventos — modo equipe/nome
  // ============================================================
  if (btnVoltarEquipes) {
    btnVoltarEquipes.addEventListener("click", () => {
      mostrarPassoEquipes();
    });
  }

  if (btnConfirmarOperador) {
    btnConfirmarOperador.addEventListener("click", () => {
      if (!operadorSelecionado) {
        if (msgErroEquipe) {
          msgErroEquipe.textContent = "Selecione um operador para continuar.";
          msgErroEquipe.style.display = "block";
          msgErroEquipe.classList.add("mostrar");
        }
        return;
      }
      fazerLogin(operadorSelecionado);
    });
  }

  // ============================================================
  // 🔁 Trocar usuário (logout local)
  // ============================================================
  async function fazerLogout() {
    if (!confirm("Deseja realmente trocar de usuário?")) return;

    try {
      await fetch("php/logout.php", { method: "POST" });
      localStorage.clear();
      sessionStorage.clear();
      
      // Mostrar modal de login
      if (modal) {
        modal.classList.add("ativo");
        document.body.style.overflow = "hidden";
      }
      
      // Resetar interface
      atualizarInfoUsuario("Operador não identificado");
      mostrarToast("✅ Sessão encerrada com sucesso.");
      
      // Resetar seleções
      mostrarPassoEquipes();
      carregarEquipes();
      
    } catch (e) {
      console.error("[Login] Erro ao encerrar sessão:", e);
      mostrarToast("❌ Erro ao encerrar sessão", "erro");
    }
  }

  if (btnTrocar) {
    btnTrocar.addEventListener("click", fazerLogout);
  }

  if (btnLogout) {
    btnLogout.addEventListener("click", fazerLogout);
  }

  // Inicializar carregamento das equipes
  carregarEquipes();
});

// Função global para verificar se está logado (pode ser usada por outros scripts)
function estaLogado() {
  return !!localStorage.getItem("operador_nome");
}

// Função para obter informações do usuário logado
function obterUsuarioLogado() {
  return {
    nome: localStorage.getItem("operador_nome"),
    isAdmin: localStorage.getItem("modo_admin") === "true"
  };
}