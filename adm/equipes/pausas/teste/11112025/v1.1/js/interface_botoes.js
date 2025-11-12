// ============================================================
// interface_botoes.js (v1.0)
// ============================================================
// Controla os botões visuais do operador logado e
// atualiza dinamicamente quando o status muda.
// ============================================================

console.log("%c[interface_botoes.js] inicializado", "color:#00ff88;");

function atualizarInterfaceStatus(nome, acao) {
  if (!window.controle?.estado) return;

  const nomeNorm = window.controle.normalizar(nome);
  const operadorItem = [...document.querySelectorAll(".op-item")].find(
    op => window.controle.normalizar(op.querySelector("strong")?.textContent) === nomeNorm
  );
  if (!operadorItem) return;

  let novoStatus = "";
  if (acao.includes("pausa")) novoStatus = "pausa";
  else if (acao.includes("fila")) novoStatus = "espera";
  else if (acao.includes("voltar")) novoStatus = "disponivel";
  else if (acao.includes("expirada")) novoStatus = "expirada";

  operadorItem.className = `op-item ${novoStatus}`;

  const botoes = operadorItem.querySelector(".botoes-operador");
  if (!botoes) return;
  botoes.innerHTML = "";

  const equipe = window.controle.buscarEquipePorOperador(nome);

  if (novoStatus === "pausa") {
    botoes.innerHTML = `
      <button class="btn-acao" onclick="controle.enviarAcao('voltar_disponivel',{nome:'${nome}',equipe:'${equipe}'})">
        ✅ Sair da pausa
      </button>`;
  } else if (novoStatus === "espera") {
    botoes.innerHTML = `
      <button class="btn-acao" onclick="controle.enviarAcao('voltar_disponivel',{nome:'${nome}',equipe:'${equipe}'})">
        ❌ Sair da fila
      </button>`;
  } else if (novoStatus === "expirada") {
    botoes.innerHTML = `
      <button class="btn-acao" onclick="controle.enviarAcao('voltar_disponivel',{nome:'${nome}',equipe:'${equipe}'})">
        🔄 Reiniciar
      </button>`;
  } else {
    botoes.innerHTML = `
      <button class="btn-acao" onclick="controle.enviarAcao('entrar_fila',{nome:'${nome}',equipe:'${equipe}'})">
        🕓 Fila de espera
      </button>
      <button class="btn-acao" onclick="controle.enviarAcao('forcar_pausa',{nome:'${nome}',equipe:'${equipe}'})">
        ☕ Entrar em pausa
      </button>`;
  }
}

// ============================================================
// Sincroniza automaticamente com o controle principal
// ============================================================

setInterval(() => {
  if (!window.controle?.estado?.length) return;

  // Verifica se o operador logado precisa atualizar botões
  const operador = window.controle.operador;
  if (!operador) return;

  // Atualiza visual do operador logado conforme status real
  const operadorData = window.controle.estado.find(
    p => window.controle.normalizar(p.nome) === window.controle.normalizar(operador)
  );

  if (operadorData) {
    const status = operadorData.status;
    atualizarInterfaceStatus(operadorData.nome, status);
  }
}, 2000); // sincroniza a cada 2s
