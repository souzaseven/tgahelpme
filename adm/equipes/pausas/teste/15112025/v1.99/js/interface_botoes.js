// ============================================================
// interface_botoes.js (v4.0)
// ÚNICO gerenciador oficial de botões do sistema
// Suporte aos status: ativo, disponivel, espera, pausa,
// aguardando e expirada
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

  // payload básico
  const payloadBase = { nome, equipe };

  // ------------------------------------------------------------
  // STATUS: AGUARDANDO (vaga liberada, operador precisa decidir)
  // - Aqui ele já é o próximo a poder entrar em pausa.
  // - Opções:
  //   ☕ Entrar em pausa agora
  //   ❌ Sair e voltar a ficar disponível
  // ------------------------------------------------------------
  if (status === "aguardando") {
    addBtn("☕ Entrar agora em pausa", "forcar_pausa", payloadBase);
    addBtn("❌ Sair da fila", "voltar_disponivel", payloadBase);
    box.classList.add("ib-fade-in");
    setTimeout(() => box.classList.remove("ib-fade-in"), 260);
    return;
  }

  // ------------------------------------------------------------
  // STATUS: PAUSA
  // ------------------------------------------------------------
  if (status === "pausa") {
    addBtn("✅ Sair da pausa", "voltar_disponivel", payloadBase);
    box.classList.add("ib-fade-in");
    setTimeout(() => box.classList.remove("ib-fade-in"), 260);
    return;
  }

  // ------------------------------------------------------------
  // STATUS: ESPERA
  // - Em espera normal (posição na fila)
  // - Pode:
  //   🔁 Solicitar troca com o 1º (backend valida se é o 2º)
  //   ❌ Sair da fila
  // ------------------------------------------------------------
  if (status === "espera") {
    addBtn("🔁 Solicitar troca com 1º", "solicitar_troca", {
      equipe,
      solicitante: nome
    });

    addBtn("❌ Sair da fila", "voltar_disponivel", payloadBase);

    box.classList.add("ib-fade-in");
    setTimeout(() => box.classList.remove("ib-fade-in"), 260);
    return;
  }

  // ------------------------------------------------------------
  // STATUS: EXPIRADA
  // ------------------------------------------------------------
  if (status === "expirada") {
    addBtn("🔄 Voltar disponível", "voltar_disponivel", payloadBase);
    box.classList.add("ib-fade-in");
    setTimeout(() => box.classList.remove("ib-fade-in"), 260);
    return;
  }

  // ------------------------------------------------------------
  // STATUS: ATIVO / DISPONÍVEL (padrão)
  // ------------------------------------------------------------
  addBtn("🕓 Entrar na fila", "entrar_fila", payloadBase);
  addBtn("☕ Entrar em pausa", "forcar_pausa", payloadBase);

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
  const isAdmin = nomeLogado === ctrl.normalizar("Anderson de Souza");

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

const operadorLogado = {
    nome: localStorage.getItem("operador_nome"),
    equipe: window.controle?.buscarEquipePorOperador(localStorage.getItem("operador_nome"))
};

fetch("./php/trocar_posicao.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({
        solicitante: operadorLogado.nome,
        equipe: operadorLogado.equipe,
        alvo: primeiroDaFila.nome
    })
});
