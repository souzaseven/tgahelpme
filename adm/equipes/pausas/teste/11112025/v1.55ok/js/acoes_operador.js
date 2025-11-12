// ============================================================
// acoes_operador.js (v1.4 leve e otimizado)
// ============================================================
// Exibe os botões "Entrar na Fila" e "Pausa" apenas para o operador logado.
// Reaplica automaticamente quando o painel atualiza via controle_pausa.js
// ============================================================

console.log("%c[ações_operador.js] carregado com sucesso!", "color:#00ff88;");

// 🔒 Bloqueio para evitar múltiplas reaplicações seguidas (desempenho)
let bloqueioReaplicacao = false;

function aplicarBotoesOperador(operadorLogado) {
  if (!operadorLogado || bloqueioReaplicacao) return;
  bloqueioReaplicacao = true;

  // 🔓 Libera novamente após 300 ms (garante leveza em atualizações rápidas)
  setTimeout(() => (bloqueioReaplicacao = false), 300);

  // permitir que o administrador veja todos os botões
  if (operadorLogado === "anderson") {
    // 🔹 1. Antes de tudo, limpar botões antigos (evita duplicar)
    document.querySelectorAll(".botoes-operador").forEach(div => div.remove());

    // 🔹 2. Pequeno delay para garantir que o DOM da lista foi totalmente renderizado
    setTimeout(() => {
      document.querySelectorAll(".op-item").forEach(op => {
        const bloco = op.closest(".equipe-bloco");
        const equipe = obterEquipeDoBloco(bloco);
        const nome = (op.querySelector("strong")?.textContent || "").trim().toLowerCase();
        if (!nome) return;

        // 🔹 3. Criar container dos botões
        const botoesBox = document.createElement("div");
        botoesBox.className = "botoes-operador";

        // 🕓 Entrar na Fila
        const btnFila = document.createElement("button");
        btnFila.className = "btn-acao";
        btnFila.textContent = "🕓 Entrar na Fila";
        btnFila.onclick = () =>
          controle.enviarAcao("entrar_fila", { nome: nome, equipe: equipe });

        // ☕ Pausa
        const btnPausa = document.createElement("button");
        btnPausa.className = "btn-acao";
        btnPausa.textContent = "☕ Pausa";
        btnPausa.onclick = () =>
          controle.enviarAcao("forcar_pausa", { nome: nome, equipe: equipe });

        botoesBox.appendChild(btnFila);
        botoesBox.appendChild(btnPausa);
        op.appendChild(botoesBox);
      });
    }, 100); // ⏳ 100ms garante que o DOM foi atualizado antes da inserção

    return; // evita duplicar ao seguir pro bloco comum
  }

  // limpa anteriores
  document.querySelectorAll(".botoes-operador").forEach(div => div.remove());

  // aplica botões apenas ao operador logado
  document.querySelectorAll(".op-item").forEach(op => {
    const nome = (op.querySelector("strong")?.textContent || "").trim().toLowerCase();

    if (nome === operadorLogado) {
      const bloco = op.closest(".equipe-bloco");
      const equipe = obterEquipeDoBloco(bloco);

      const botoesBox = document.createElement("div");
      botoesBox.className = "botoes-operador";

      // 🕓 Entrar na Fila
      const btnFila = document.createElement("button");
      btnFila.className = "btn-acao";
      btnFila.textContent = "🕓 Entrar na Fila";
      btnFila.onclick = () =>
        controle.enviarAcao("entrar_fila", { nome: nome, equipe: equipe });

      // ☕ Pausa
      const btnPausa = document.createElement("button");
      btnPausa.className = "btn-acao";
      btnPausa.textContent = "☕ Pausa";
     btnPausa.onclick = () =>
  controle.enviarAcao("forcar_pausa", { nome: nome, equipe: equipe });


      botoesBox.appendChild(btnFila);
      botoesBox.appendChild(btnPausa);
      op.appendChild(botoesBox);
    }
  });
}

// ============================================================
// Função auxiliar: obtém o nome da equipe a partir do título
// ============================================================
function obterEquipeDoBloco(bloco) {
  const h3 = bloco?.querySelector("h3");
  if (!h3) return "";

  // Captura somente o nome da equipe (tudo antes do contador)
  let texto = h3.cloneNode(true);
  // remove os elementos internos (como <span> do contador)
  texto.querySelectorAll("span").forEach(span => span.remove());
  
  // pega o texto limpo
  let equipe = texto.textContent.trim();

  // remove ícones e caracteres extras
  equipe = equipe.replace(/[🟢⏳☕🔴👥🌎]/g, "").trim();

  // evita sobras como "12 operadores"
  equipe = equipe.replace(/\d+\s*operadores?/gi, "").trim();

  return equipe;
}



// ============================================================
// Aplica uma vez após o DOM estar pronto
// ============================================================
document.addEventListener("DOMContentLoaded", () => {
  setTimeout(() => {
    if (window.controle?.operador) {
      aplicarBotoesOperador(window.controle.operador.toLowerCase());
    }
  }, 1500);
});
