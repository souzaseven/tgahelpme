// ============================================================
// main.js (v3.0 Integrado com Evolux)
// ============================================================
// 🔹 Função: centraliza o carregamento de módulos do sistema
// 🔹 Integração: inicializa controle local, scraping e sincronização automática
// ============================================================

console.log("%c[Main] Iniciando sistema de pausas (v3.0)...", "color:#00ff88;font-weight:bold;");

// ============================================================
// ⚙️ Configurações principais
// ============================================================
(() => {
  const operador = localStorage.getItem("operador_nome");
  const modoAdmin = localStorage.getItem("modo_admin") === "true";

  if (operador) {
    console.log(`👤 Logado como: ${operador}`);
    const header = document.querySelector("header");
    if (header && !header.querySelector(".usuario-logado")) {
      const userInfo = document.createElement("div");
      userInfo.className = "usuario-logado";
      userInfo.innerHTML = `<i class="fas fa-user-circle"></i> ${operador}`;
      header.appendChild(userInfo);
    }
  }

  if (modoAdmin) {
    document.body.classList.add("modo-admin");
    console.log("🧑‍💼 [Main] Modo ADMIN ativado.");
  }
})();

// ============================================================
// 🚀 Inicialização dos módulos
// ============================================================
(async function iniciarSistema() {
  console.log("🧩 [Main] Carregando módulos principais...");

  // Detecta versão automaticamente
  const versao =
    window.SISTEMA_CONFIG?.versao ||
    window.SISTEMA_VERSAO ||
    (() => {
      const path = window.location.pathname.split("/");
      return path[path.indexOf("pausas") + 1] || "v1.0";
    })();

  const baseJS =
    window.SISTEMA_CONFIG?.caminhos?.js ||
    `/adm/equipes/pausas/${versao}/js/`;

  // ============================================================
  // 📦 Lista de módulos obrigatórios
  // ============================================================
  const modulos = [
    "controle_pausa.js",      // Painel local
    "diagnostico_sistema.js", // Diagnóstico e debug
    "integracao_ext.js",      // Scraping do Evolux (pausas)
    "integracao_fila.js"      // Sincronização com fila local
  ];

  // ============================================================
  // 📥 Carregamento dinâmico sequencial
  // ============================================================
  async function carregarScript(nome) {
    return new Promise((resolve, reject) => {
      const s = document.createElement("script");
      s.src = baseJS + nome + `?v=${versao}&t=${Date.now()}`;
      s.defer = true;
      s.onload = () => {
        console.log(`✅ [Main] Módulo carregado: ${nome}`);
        resolve(true);
      };
      s.onerror = () => {
        console.error(`❌ Falha ao carregar: ${nome}`);
        reject(false);
      };
      document.body.appendChild(s);
    });
  }

  // Carrega todos os scripts em sequência
  for (const arquivo of modulos) {
    try {
      await carregarScript(arquivo);
    } catch (e) {
      console.warn(`[Main] Erro ao carregar ${arquivo}:`, e);
    }
  }

  // ============================================================
  // 🧠 Diagnóstico final após inicialização
  // ============================================================
  setTimeout(() => {
    console.groupCollapsed("%c[Diagnóstico Final do Sistema]", "color:#00c3ff;font-weight:bold;");
    console.log("📦 Versão:", versao);
    console.log("📁 JS Base:", baseJS);
    console.log("👤 Operador:", localStorage.getItem("operador_nome") || "N/A");
    console.log("🧩 Controle:", typeof window.controle !== "undefined" ? "✅ OK" : "❌ Falhou");
    console.log("🌐 Integração Evolux:", typeof window.sincronizarFilaComEvolux !== "undefined" ? "✅ OK" : "⚠️ Pendente");
    console.groupEnd();
  }, 3000);

  console.log("%c[Main] Sistema de Pausas totalmente inicializado!", "color:#00ff88;font-weight:bold;");
})();

// ============================================================
// 🔌 Botão de Logout com fallback
// ============================================================
document.addEventListener("DOMContentLoaded", () => {
  const logout = document.getElementById("btnLogout");
  if (!logout) return;

  if (!localStorage.getItem("operador_nome")) {
    logout.style.display = "none";
  } else {
    logout.style.display = "flex";
  }

  logout.addEventListener("click", async () => {
    if (!confirm("Deseja desconectar e trocar de usuário?")) return;
    try {
      await fetch("./php/logout.php", { cache: "no-store" });
    } catch (e) {
      console.warn("[Main] Erro ao chamar logout.php:", e);
    }

    localStorage.removeItem("operador_nome");
    localStorage.removeItem("modo_admin");
    location.reload();
  });
});
