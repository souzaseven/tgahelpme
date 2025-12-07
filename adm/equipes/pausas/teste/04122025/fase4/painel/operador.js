/* ============================================================
   operador.js — FASE 4 (com permissão por operador / admin)
   - Insere botões individuais apenas:
     • no operador logado
     • ou em todos, se for admin (Anderson de Souza ou is_admin = 1)
============================================================ */

function inserirBotoesIndividuais() {
    // Pega dados do operador logado
    let dadosOperador = null;
    try {
        dadosOperador = JSON.parse(localStorage.getItem("tga_operador"));
    } catch (e) {
        console.warn("tga_operador inválido no localStorage");
    }

    if (!dadosOperador) return;

    const nomeLogado = (dadosOperador.operador || "").trim();

    // Regra de admin:
    // - se no futuro você gravar is_admin = 1 no localStorage, já funciona
    // - por enquanto, também considera o nome "Anderson de Souza" como admin
    const isAdmin =
        dadosOperador.is_admin === 1 ||
        dadosOperador.is_admin === "1" ||
        nomeLogado === "Anderson de Souza";

    // Todos os cards de operadores renderizados pelo painel.js
    const operadores = document.querySelectorAll(".linha-participante");

    operadores.forEach(op => {
        const nomeSpan = op.querySelector(".nome-op");
        if (!nomeSpan) return;

        const nomeOp = nomeSpan.textContent.trim();

        // Se NÃO for admin e esse card NÃO for do operador logado → não mostra botões
        if (!isAdmin && nomeOp !== nomeLogado) {
            // Se por algum motivo já existir bloco de botões, remove
            const existente = op.querySelector(".op-botoes");
            if (existente) existente.remove();
            return;
        }

        // Se já tem botões nesse operador, não recria
        if (op.querySelector(".op-botoes")) return;

        // Cria container dos botões
        const container = document.createElement("div");
        container.className = "op-botoes";

        container.innerHTML = `
            <button class="op-btn op-online" type="button">
                <i class="fas fa-plug"></i> Online
            </button>

            <button class="op-btn op-espera" type="button">
                <i class="fas fa-clock"></i> Fila
            </button>

            <button class="op-btn op-pausa" type="button">
                <i class="fas fa-pause"></i> Pausa
            </button>

            <button class="op-btn op-expira" type="button">
                <i class="fas fa-skull-crossbones"></i> Expirar
            </button>
        `;

        op.appendChild(container);
    });
}
