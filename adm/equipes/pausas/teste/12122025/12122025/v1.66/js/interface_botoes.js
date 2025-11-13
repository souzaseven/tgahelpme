// ============================================================
// interface_botoes.js (v3.0)
// ÚNICO gerenciador oficial de botões do sistema
// Suporte total aos status: ativo, disponivel, espera,
// pausa, expirada e AGUARDANDO
// ============================================================

console.log("%c[interface_botoes.js] carregado", "color:#00ff88;font-weight:bold;");

let __ib_bloqueio = false;
const __IB_DELAY = 120;

// ============================================================
// CRIAÇÃO DOS BOTÕES PARA CADA OPERADOR
// ============================================================
function __ib_criarBotoesParaItem(opItem, nome, equipe, status) {
  const box = opItem.querySelector(".botoes-operador");
  if (!box) return;
  box.innerHTML = "";

  const addBtn = (label, acao, payload) => {
    const b = document.createElement("button");
    b.className = "btn-acao";
    b.textContent = label;
    b.onclick = () => window.controle.enviarAcao(acao, payload);
    box.appendChild(b);
  };

  const payload = { nome, equipe };

  // ------------------------------------------------------------
  // STATUS: AGUARDANDO (vaga liberada, operador precisa decidir)
  // ------------------------------------------------------------
  if (status === "aguardando") {
    addBtn("☕ Entrar agora", "forcar_pausa", payload);

    addBtn("🔁 Ficar como segundo", "decidir_troca", {
      ...payload,
      decisor: nome,
      decisao: "segundo"
    });

    addBtn("➡️ Ir para o fim da fila", "decidir_troca", {
      ...payload,
      decisor: nome,
      decisao: "fim"
    });

    addBtn("❌ Sair da fila", "voltar_disponivel", payload);
    return;
  }

  // ------------------------------------------------------------
  // STATUS: PAUSA
  // ------------------------------------------------------------
  if (status === "pausa") {
    addBtn("✅ Sair da pausa", "voltar_disponivel", payload);
    return;
  }

  // ------------------------------------------------------------
  // STATUS: ESPERA
  // ------------------------------------------------------------
  if (status === "espera") {
    addBtn("❌ Sair da fila", "voltar_disponivel", payload);
    return;
  }

  // ------------------------------------------------------------
  // STATUS: EXPIRADA
  // ------------------------------------------------------------
  if (status === "expirada") {
    addBtn("🔄 Voltar disponível", "voltar_disponivel", payload);
    return;
  }

  // ------------------------------------------------------------
  // STATUS: ATIVO / DISPONÍVEL
  // ------------------------------------------------------------
  addBtn("🕓 Fila de espera", "entrar_fila", payload);
  addBtn("☕ Entrar em pausa", "forcar_pausa", payload);

  box.classList.add("ib-fade-in");
  setTimeout(() => box.classList.remove("ib-fade-in"), 260);
}

// ============================================================
// APLICAÇÃO DOS BOTÕES AO DOM
// ============================================================
function __ib_aplicarParaDOM() {
  if (__ib_bloqueio) return;
  __ib_bloqueio = true;
  setTimeout(() => (__ib_bloqueio = false), __IB_DELAY);

  const ctrl = window.controle;
  if (!ctrl?.estado?.length) return;

  const nomeLogado = ctrl.normalizar(ctrl.operador);
  const isAdmin =
    nomeLogado === ctrl.normalizar("Anderson de Souza");

  document.querySelectorAll(".op-item").forEach((opItem) => {
    const nome = (opItem.querySelector("strong")?.textContent || "").trim();
    const nomeNorm = ctrl.normalizar(nome);
    const equipe = ctrl.buscarEquipePorOperador(nome);

    if (!nome || !equipe) return;

    // Identifica status real via classe CSS
    const status =
      (opItem.className.match(
        /\b(ativo|disponivel|espera|pausa|aguardando|expirada)\b/
      ) || [,""])[1] || "ativo";

    // Só exibe botões para: ADMIN ou USUÁRIO LOGADO
    if (isAdmin || nomeNorm === nomeLogado) {
      __ib_criarBotoesParaItem(opItem, nome, equipe, status);
    } else {
      const box = opItem.querySelector(".botoes-operador");
      if (box) box.innerHTML = "";
    }
  });
}

// ============================================================
// EVENTOS DO SISTEMA
// ============================================================
document.addEventListener("ui:operadores-renderizados", __ib_aplicarParaDOM);
document.addEventListener("estado:atualizado", __ib_aplicarParaDOM);

document.addEventListener("status:alterado", (e) => {
  const { nome } = e.detail || {};
  const ctrl = window.controle;
  if (!ctrl) return;

  const alvo = [...document.querySelectorAll(".op-item")].find(
    (el) =>
      ctrl.normalizar(el.querySelector("strong")?.textContent) ===
      ctrl.normalizar(nome)
  );
  if (!alvo) return;

  const status =
    (alvo.className.match(
      /\b(ativo|disponivel|espera|pausa|aguardando|expirada)\b/
    ) || [,""])[1] || "ativo";

  const equipe = ctrl.buscarEquipePorOperador(nome);
  __ib_criarBotoesParaItem(alvo, nome, equipe, status);
});

// ============================================================
// Compatibilidade com código legado
// ============================================================
window.aplicarBotoesOperador = () => __ib_aplicarParaDOM();
