/* ============================================================
   operador.js — FASE 6 OFICIAL
   - Apenas botões essenciais
   - Sem Fase 7
   - Sem “Decidir na fila”
   - Sem troca
   - Sem posição
   - Sem contador local especial
============================================================ */

console.log("%c[OPERADOR.JS] FASE 6 carregado.", "color:#38bdf8;font-weight:bold;");

/* ============================================================
   INSERIR BOTÕES EM CADA CARD
============================================================ */
function inserirBotoesIndividuais(operadoresPainel = []) {

    const dados = JSON.parse(localStorage.getItem("tga_operador"));
    if (!dados) return;

    const isAdmin = dados.is_admin == 1;
    const idLogado = Number(dados.id);

    const cards = document.querySelectorAll(".linha-participante");

    cards.forEach(card => {

        const nome = card.dataset.nome;
        const operador = operadoresPainel.find(o => o.nome === nome);
        if (!operador) return;

        // remover botões antigos
        const antigo = card.querySelector(".op-botoes");
        if (antigo) antigo.remove();

        // permitir apenas admin OU operador logado
        const podeVer = isAdmin || operador.id === idLogado;
        if (!podeVer) return;

        const box = document.createElement("div");
        box.className = "op-botoes";

        /* ============================================================
           BOTÕES POR STATUS — FASE 6
        ============================================================ */

        // OPERADOR EM ESPERA (fila)
        if (operador.status === "espera") {

            box.innerHTML = `
                <button class="op-btn op-sair-fila"
                        onclick="sairFilaIndividual(${operador.id})">
                    <i class="fas fa-circle-xmark"></i> Sair da fila
                </button>
            `;
        }

        // OPERADOR ATIVO
        else if (operador.status === "ativo") {

            box.innerHTML = `
                <button class="op-btn op-espera"
                        onclick="entrarFilaIndividual(${operador.id})">
                    <i class="fas fa-clock"></i> Entrar na fila
                </button>
            `;
        }

        // OPERADOR EM PAUSA
        else if (operador.status === "pausa") {

            box.innerHTML = `
                <button class="op-btn op-voltar"
                        onclick="sairPausaIndividual(${operador.id})">
                    <i class="fas fa-play"></i> Voltar ativo
                </button>
            `;
        }

        card.appendChild(box);
    });
}

/* ============================================================
   AÇÕES INDIVIDUAIS — FASE 6
============================================================ */

window.entrarFilaIndividual = async id => {
    await fetch("../backend/entrar_fila.php", {
        method: "POST",
        body: new URLSearchParams({ id })
    });
    setTimeout(() => carregarPainel(), 250);
};

window.sairFilaIndividual = async id => {
    await fetch("../backend/sair_fila.php", {
        method: "POST",
        body: new URLSearchParams({ id })
    });
    setTimeout(() => carregarPainel(), 250);
};

window.sairPausaIndividual = async id => {
    await fetch("../backend/sair_pausa.php", {
        method: "POST",
        body: new URLSearchParams({ id })
    });
    setTimeout(() => carregarPainel(), 250);
};
window.entrarFilaIndividual = async id => {
    await fetch("../backend/entrar_fila.php", {
        method: "POST",
        body: new URLSearchParams({ id })
    });
    setTimeout(() => carregarPainel(), 250); // agora carregarPainel existe no window
};
