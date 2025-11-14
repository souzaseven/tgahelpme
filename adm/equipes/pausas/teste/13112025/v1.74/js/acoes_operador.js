// ============================================================
// acoes_operador.js (v3.0)
// Ações do operador + compatibilidade com versões anteriores
// Totalmente sincronizado com interface_botoes.js e controle_pausa.js
// ============================================================

console.log("%c[acoes_operador.js] v3.0 carregado", "color:#00ff88;font-weight:bold;");

// ============================================================
// Utilidades
// ============================================================

function aoNormalizar(s) {
  return (s || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim().toLowerCase();
}

function aoToast(msg, erro = false) {
  if (!window.controle) return alert(msg);
  window.controle.toast(msg, erro);
}

function aoConfirmar(msg) {
  return new Promise((resolve) => {
    const ok = confirm(msg);
    resolve(ok);
  });
}

// ============================================================
// Ações diretas
// ============================================================

async function entrarFila(nome, equipe) {
  return window.controle?.enviarAcao("entrar_fila", { nome, equipe });
}

async function entrarPausa(nome, equipe) {
  return window.controle?.enviarAcao("forcar_pausa", { nome, equipe });
}

async function sairStatus(nome, equipe) {
  return window.controle?.enviarAcao("voltar_disponivel", { nome, equipe });
}

async function decidirTroca(nome, equipe, decisao) {
  return window.controle?.enviarAcao("decidir_troca", {
    nome,
    equipe,
    decisor: nome,
    decisao
  });
}

// ============================================================
// CLICK HANDLER UNIVERSAL (caso queira ativar futuramente)
// ============================================================

document.addEventListener("click", async (ev) => {
  const el = ev.target;
  if (!el.classList.contains("btn-acao")) return;

  const item = el.closest(".op-item");
  if (!item) return;

  const nome = item.querySelector("strong")?.textContent.trim();
  const status = (item.className.match(
    /\b(ativo|disponivel|espera|pausa|aguardando|expirada)\b/
  ) || [,""])[1] || "ativo";

  const equipe = window.controle?.buscarEquipePorOperador(nome);
  if (!nome || !equipe) return;

  // Apenas para debug futuro
  console.log("🔘 Botão pressionado:", nome, status, el.textContent.trim());

  // Lógica extra opcional se um dia precisar  
  // (Deixado preparado para features avançadas)
});

// ============================================================
// Compatibilidade com sistema legado (v1.x e v2.x)
// ============================================================

function aplicarBotoesOperador() {
  // Agora 100% delegada ao interface_botoes.js
  try {
    if (typeof window.aplicarBotoesOperador === "function") {
      window.aplicarBotoesOperador();
    }
  } catch (e) {
    console.warn("⚠️ erro compat:", e);
  }
}

// Mantém global para sistemas antigos
window.aplicarBotoesOperador = aplicarBotoesOperador;

// ============================================================
// Funções utilitárias públicas (caso precise em HTML ou outro JS)
// ============================================================

window.AcoesOperador = {
  entrarFila,
  entrarPausa,
  sairStatus,
  decidirTroca
};

console.log("%c[acoes_operador.js] pronto", "color:#00f7ff;");
