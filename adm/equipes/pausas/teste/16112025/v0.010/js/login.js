// ============================================================
// login.js - Controle do Modal de Login (manual + equipe/nome)
// ------------------------------------------------------------
// 🔹 Cuida SOMENTE do login e logout
// 🔹 Opções:
//    - Nome manual (igual antes)
//    - Escolher equipe / nome (novo fluxo)
// 🔹 Usa login_operador.php e listar_operadores.php
// ============================================================

console.log(
  "%c[Login] 🧩 Inicializando lógica de login...",
  "color:#4f46e5;font-weight:bold;"
);

document.addEventListener("DOMContentLoaded", () => {
  const modal = document.getElementById("modalOperador");

  // Elementos modo manual
  const nomeInput = document.getElementById("inputNome");
  const senhaInput = document.getElementById("inputSenha");
  const btnEntrar = document.getElementById("btnEntrar");
  const msgErro = document.getElementById("msgErro");

  // Toast
  const toast = document.getElementById("toast");

  // Botões de modo
  const btnModoManual = document.getElementById("btnModoManual");
  const btnModoEquipe = document.getElementById("btnModoEquipe");

  const secaoModoManual = document.getElementById("loginModoManual");
  const secaoModoEquipe = document.getElementById("loginModoEquipe");

  // Fluxo equipe/nome
  const stepEquipes = document.getElementById("stepEquipes");
  const stepOperadores = document.getElementById("stepOperadores");
  const listaEquipes = document.getElementById("listaEquipes");
  const listaOperadores = document.getElementById("listaOperadores");
  const btnVoltarEquipes = document.getElementById("btnVoltarEquipes");
  const btnConfirmarOperador = document.getElementById("btnConfirmarOperador");
  const tituloEquipeSelecionada = document.getElementById(
    "tituloEquipeSelecionada"
  );
  const msgErroEquipe = document.getElementById("msgErroEquipe");

  // Botão de trocar usuário (se existir no layout)
  const btnTrocar = document.getElementById("btnTrocarUsuario");

  let equipeSelecionada = null;
  let operadorSelecionado = null;

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
  // 🎨 Troca de modo do modal (manual / equipe)
  // ============================================================
  function ativarModoLogin(modo) {
    if (!secaoModoManual || !secaoModoEquipe) return;

    if (modo === "manual") {
      secaoModoManual.classList.add("ativo");
      secaoModoManual.classList.remove("hidden");

      secaoModoEquipe.classList.remove("ativo");
      secaoModoEquipe.classList.add("hidden");

      btnModoManual && btnModoManual.classList.add("ativo");
      btnModoEquipe && btnModoEquipe.classList.remove("ativo");

      setTimeout(() => nomeInput && nomeInput.focus(), 50);
    } else {
      secaoModoEquipe.classList.add("ativo");
      secaoModoEquipe.classList.remove("hidden");

      secaoModoManual.classList.remove("ativo");
      secaoModoManual.classList.add("hidden");

      btnModoEquipe && btnModoEquipe.classList.add("ativo");
      btnModoManual && btnModoManual.classList.remove("ativo");

      mostrarPassoEquipes();
      carregarEquipes();
    }
  }

  function mostrarPassoEquipes() {
    if (!stepEquipes || !stepOperadores) return;
    stepEquipes.classList.remove("hidden");
    stepOperadores.classList.add("hidden");
    equipeSelecionada = null;
    operadorSelecionado = null;
    if (btnConfirmarOperador) btnConfirmarOperador.disabled = true;
    if (msgErroEquipe) msgErroEquipe.style.display = "none";
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
  // 🔐 Login (reaproveita backend atual)
  // ============================================================
  async function fazerLogin(nome, senha = "") {
    if (!nome) {
      if (msgErro) {
       /* msgErro.textContent = "Digite seu nome";*/
msgErro.textContent = "Seleciona sua equipe antes";
        msgErro.style.display = "block";
      }
      return;
    }

    try {
      if (btnEntrar) {
        btnEntrar.disabled = true;
        btnEntrar.textContent = "Entrando...";
      }

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

        if (modal) modal.classList.remove("ativo");
        mostrarToast(`✅ Login realizado: ${data.nome_canonico}`);

        // Recarrega tudo para inicializacao.js subir o controle
        setTimeout(() => location.reload(), 700);
      } else if (data.requer_senha) {
        if (senhaInput) {
          senhaInput.style.display = "block";
          senhaInput.focus();
        }
        if (msgErro) {
          msgErro.textContent = "Digite a senha de administrador";
          msgErro.style.display = "block";
        }
      } else {
        if (msgErro) {
          msgErro.textContent = data.error || "Erro no login";
          msgErro.style.display = "block";
        }
      }
    } catch (erro) {
      console.error("[Login] Erro no login:", erro);
      if (msgErro) {
        msgErro.textContent = "Erro de conexão com o servidor";
        msgErro.style.display = "block";
      }
    } finally {
      if (btnEntrar) {
        btnEntrar.disabled = false;
        btnEntrar.textContent = "Entrar";
      }
    }
  }
  // ============================================================
  // 🌐 Consumo de equipes e operadores (USANDO listar_equipes_login.php)
  // ============================================================
  let cacheEquipesLogin = null; // cache simples pra não ficar buscando toda hora

  async function carregarEquipes() {
    if (!listaEquipes) return;

    listaEquipes.innerHTML =
      '<div class="info-carregando">Carregando equipes...</div>';

    try {
      // Se já buscou uma vez, reaproveita o cache
      if (!cacheEquipesLogin) {
        const resp = await fetch("php/listar_equipes_login.php", {
          cache: "no-store",
        });
        const data = await resp.json();

        if (!data.success) {
          listaEquipes.innerHTML = `<div class="info-erro">Erro: ${
            data.error || "Não foi possível listar as equipes."
          }</div>`;
          return;
        }

        cacheEquipesLogin = data.equipes || [];
      }

      const equipes = cacheEquipesLogin;
      if (!equipes.length) {
        listaEquipes.innerHTML =
          '<div class="info-erro">Nenhuma equipe encontrada.</div>';
        return;
      }

      listaEquipes.innerHTML = "";

      // equipes = [ { lider: "Daniel Feix", operadores: [...] }, ... ]
      equipes.forEach((eq) => {
        const card = document.createElement("button");
        card.className = "card-equipe";
        card.dataset.lider = eq.lider;

        card.innerHTML = `
          <div><i class="fas fa-users"></i></div>
          <div>${eq.lider}</div>
        `;

        card.addEventListener("click", () => {
          equipeSelecionada = eq.lider;
          carregarOperadores(eq.lider);
        });

        listaEquipes.appendChild(card);
      });
    } catch (erro) {
      console.error("[Login] Erro ao carregar equipes:", erro);
      listaEquipes.innerHTML =
        '<div class="info-erro">Erro de conexão ao buscar equipes.</div>';
    }
  }

  async function carregarOperadores(lider) {
    if (!listaOperadores) return;

    listaOperadores.innerHTML =
      '<div class="info-carregando">Carregando operadores...</div>';
    operadorSelecionado = null;
    if (btnConfirmarOperador) btnConfirmarOperador.disabled = true;
    if (msgErroEquipe) msgErroEquipe.style.display = "none";

    try {
      // Garante que temos o cache
      if (!cacheEquipesLogin) {
        const resp = await fetch("php/listar_equipes_login.php", {
          cache: "no-store",
        });
        const data = await resp.json();
        if (!data.success) {
          listaOperadores.innerHTML = `<div class="info-erro">Erro: ${
            data.error || "Não foi possível listar os operadores."
          }</div>`;
          return;
        }
        cacheEquipesLogin = data.equipes || [];
      }

      const equipeObj = cacheEquipesLogin.find((eq) => eq.lider === lider);

      if (!equipeObj) {
        listaOperadores.innerHTML =
          '<div class="info-erro">Equipe não encontrada.</div>';
        return;
      }

      const operadores = equipeObj.operadores || [];
      if (!operadores.length) {
        listaOperadores.innerHTML =
          '<div class="info-erro">Nenhum operador cadastrado nessa equipe.</div>';
        return;
      }

      listaOperadores.innerHTML = "";
      mostrarPassoOperadores(lider);

      operadores.forEach((nome) => {
        const card = document.createElement("button");
        card.className = "card-operador";
        card.dataset.nome = nome;

        card.innerHTML = `
          <div><i class="fas fa-user-circle"></i></div>
          <div>${nome}</div>
        `;

        card.addEventListener("click", () => {
          operadorSelecionado = nome;
          document
            .querySelectorAll(".card-operador.selecionado")
            .forEach((el) => el.classList.remove("selecionado"));
          card.classList.add("selecionado");
          if (btnConfirmarOperador) btnConfirmarOperador.disabled = false;
          if (msgErroEquipe) msgErroEquipe.style.display = "none";
        });

        listaOperadores.appendChild(card);
      });
    } catch (erro) {
      console.error("[Login] Erro ao carregar operadores:", erro);
      listaOperadores.innerHTML =
        '<div class="info-erro">Erro de conexão ao buscar operadores.</div>';
    }
  }

  // ============================================================
  // 🧠 Eventos — modo manual
  // ============================================================
  if (btnEntrar) {
    btnEntrar.addEventListener("click", () => {
      const nome = nomeInput ? nomeInput.value.trim() : "";
      const senha = senhaInput ? senhaInput.value.trim() : "";
      fazerLogin(nome, senha);
    });
  }

  if (nomeInput) {
    nomeInput.addEventListener("input", (e) => {
      const valor = e.target.value.toLowerCase();
      if (valor === "anderson") {
        if (senhaInput) senhaInput.style.display = "block";
      } else {
        if (senhaInput) {
          senhaInput.style.display = "none";
          senhaInput.value = "";
        }
        if (msgErro) msgErro.style.display = "none";
      }
    });

    nomeInput.addEventListener("keypress", (e) => {
      if (e.key === "Enter" && btnEntrar) btnEntrar.click();
    });
  }

  if (senhaInput) {
    senhaInput.addEventListener("keypress", (e) => {
      if (e.key === "Enter" && btnEntrar) btnEntrar.click();
    });
  }

  // ============================================================
  // 🧭 Eventos — modo equipe/nome
  // ============================================================
  if (btnModoManual) {
    btnModoManual.addEventListener("click", () => ativarModoLogin("manual"));
  }

  if (btnModoEquipe) {
    btnModoEquipe.addEventListener("click", () => ativarModoLogin("equipe"));
  }

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
        }
        return;
      }
      // Faz login como se tivesse digitado o nome manualmente
      fazerLogin(operadorSelecionado, "");
    });
  }

  // ============================================================
  // 🔁 Trocar usuário (logout local)
  // ============================================================
  if (btnTrocar) {
    btnTrocar.addEventListener("click", async () => {
      if (!confirm("Deseja realmente trocar de usuário?")) return;

      try {
        // Se você quiser chamar logout.php, descomente:
        // await fetch("php/logout.php", { method: "POST" });

        localStorage.clear();
        sessionStorage.clear();
        if (window.caches) {
          caches.keys().then((keys) => keys.forEach((k) => caches.delete(k)));
        }
        mostrarToast("✅ Sessão encerrada com sucesso.");
      } catch (e) {
        console.error("[Login] Erro ao encerrar sessão:", e);
      } finally {
        setTimeout(() => {
          if (modal) modal.classList.add("ativo");
          if (nomeInput) nomeInput.value = "";
          if (senhaInput) {
            senhaInput.value = "";
            senhaInput.style.display = "none";
          }
          ativarModoLogin("manual");
        }, 400);
      }
    });
  }

  // Modo padrão ao abrir o modal
  ativarModoLogin("manual");
});
