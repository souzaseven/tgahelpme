// ============================================================
// main.js - Bootstrap central do Sistema de Pausas (v1.8)
// ============================================================

(function () {
  const versao = window.SISTEMA_VERSAO || "v1.0";
  const baseJS = window.SISTEMA_CONFIG?.caminhos?.js || `/adm/equipes/pausas/${versao}/js/`;

  console.log(`%c[Main] Iniciando sistema de pausas (${versao})...`, "color:#00ff88;font-weight:bold;");

  const operador = localStorage.getItem("operador_nome") || "N/A";
  const modoAdmin = localStorage.getItem("modo_admin") === "true";

  console.log("👤 Operador:", operador);
  if (modoAdmin) console.log("🧑‍💼 [Main] Modo ADMIN ativado.");
  console.log("📁 Base JS:", baseJS);

  const modulos = [
    "inicializacao.js",
    "equipes_operadores.js",
    "controle_pausa.js",
    "diagnostico_sistema.js",
    "integracao_ext.js",
    "integracao_fila.js"
  ];

  async function carregarScript(nome) {
    if (document.querySelector(`script[src*='${nome}']`)) {
      console.warn(`[Main] Script duplicado ignorado: ${nome}`);
      return;
    }

    return new Promise((resolve, reject) => {
      const s = document.createElement("script");
      s.src = `${baseJS}${nome}?v=${versao}&t=${Date.now()}`;
      s.defer = true;
      s.onload = () => {
        console.log(`✅ ${nome} carregado`);
        resolve();
      };
      s.onerror = () => {
        console.error(`❌ Falha ao carregar: ${nome}`);
        reject();
      };
      document.head.appendChild(s);
    });
  }

  (async function iniciar() {
    console.groupCollapsed("%c[Main] Carregando módulos...", "color:#00c3ff;font-weight:bold;");
    for (const m of modulos) {
      await carregarScript(m);
    }
    console.groupEnd();

    console.log("%c[Main] Sistema de Pausas totalmente inicializado!", "color:#00ff88;font-weight:bold;");

    // Notifica que o sistema principal está pronto
    window.dispatchEvent(new CustomEvent("main:ready"));
  })();
})();
