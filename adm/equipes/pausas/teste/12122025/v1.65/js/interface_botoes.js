// ============================================================
// interface_botoes.js (v2.0) — ÚNICO gerenciador de botões
// ============================================================

console.log("%c[interface_botoes.js] ativo", "color:#00ff88;");

let __ib_bloqueio = false;
const __IB_DELAY = 120;

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

  const payloadBase = { nome, equipe };

  if (status === "pausa") {
    addBtn("✅ Sair da pausa", "voltar_disponivel", payloadBase);
  } else if (status === "espera") {
    addBtn("❌ Sair da fila", "voltar_disponivel", payloadBase);
  } else if (status === "expirada") {
    addBtn("✅ Voltar disponível", "voltar_disponivel", payloadBase);
  } else {
    addBtn("🕓 Fila de espera", "entrar_fila", payloadBase);
    addBtn("☕ Entrar em pausa", "forcar_pausa", payloadBase);
  }

  box.classList.add("ib-fade-in");
  setTimeout(() => box.classList.remove("ib-fade-in"), 260);
}

function __ib_aplicarParaDOM() {
  if (__ib_bloqueio) return;
  __ib_bloqueio = true;
  setTimeout(() => (__ib_bloqueio = false), __IB_DELAY);

  const ctrl = window.controle;
  if (!ctrl?.estado?.length) return;
  const opLogado = ctrl.operador && ctrl.normalizar(ctrl.operador);
  const isAdmin = ctrl.normalizar(ctrl.operador) === ctrl.normalizar("Anderson de Souza");

  document.querySelectorAll(".op-item").forEach(opItem => {
    const nome = (opItem.querySelector("strong")?.textContent || "").trim();
    const nomeNorm = ctrl.normalizar(nome);
    const status = (opItem.className.match(/\b(ativo|disponivel|espera|pausa|expirada)\b/) || [,""])[1] || "";
    const equipe = ctrl.buscarEquipePorOperador(nome) || "";
    if (!nome || !equipe) return;

    if (isAdmin || nomeNorm === opLogado) {
      __ib_criarBotoesParaItem(opItem, nome, equipe, status);
    } else {
      const box = opItem.querySelector(".botoes-operador");
      box && (box.innerHTML = "");
    }
  });
}

document.addEventListener("ui:operadores-renderizados", __ib_aplicarParaDOM);
document.addEventListener("estado:atualizado", __ib_aplicarParaDOM);
document.addEventListener("status:alterado", (e) => {
  const { nome } = e.detail || {};
  const ctrl = window.controle;
  if (!ctrl) return;

  const alvo = [...document.querySelectorAll(".op-item")].find(
    el => ctrl.normalizar(el.querySelector("strong")?.textContent) === ctrl.normalizar(nome)
  );
  if (!alvo) return;

  const status = (alvo.className.match(/\b(ativo|disponivel|espera|pausa|expirada)\b/) || [,""])[1] || "";
  const equipe = ctrl.buscarEquipePorOperador(nome) || "";
  __ib_criarBotoesParaItem(alvo, nome, equipe, status);
});

// Compat: se algum legado ainda chamar
window.aplicarBotoesOperador = () => __ib_aplicarParaDOM();
