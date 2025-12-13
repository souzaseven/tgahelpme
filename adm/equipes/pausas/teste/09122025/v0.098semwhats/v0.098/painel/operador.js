/* ============================================================
   operador.js — FASE 9 (Atualizado)
   - Botões essenciais por status
   - Exibe botão DECIDIR quando operador está EM ESPERA
   - Integra com painel.js (carregarPainel no window)
   - Mantém compatibilidade total com FASE 6 / FASE 7 / FASE 8 / FASE 9
============================================================ */

console.log("%c[OPERADOR.JS] FASE 9 carregado.", "color:#38bdf8;font-weight:bold;");

/* ============================================================
   INSERIR BOTÕES EM CADA CARD
============================================================ */
function inserirBotoesIndividuais(operadoresPainel = []) {

    const dados = JSON.parse(localStorage.getItem("tga_operador"));
    if (!dados) return;

    //const isAdmin  = dados.is_admin == 1;
    const isAdmin  = dados.is_admin == 1;
const isLider  = dados.elider == 1;
const idLogado = Number(dados.id);

    const cards = document.querySelectorAll(".linha-participante");

    cards.forEach(card => {

        const nome     = card.dataset.nome;
        const operador = operadoresPainel.find(o => o.nome === nome);
        if (!operador) return;

        // Remove botões anteriores
        const antigo = card.querySelector(".op-botoes");
        if (antigo) antigo.remove();

        // Apenas admin ou operador logado vê os botões
       // const podeVer = isAdmin || operador.id === idLogado;
const podeVer = isAdmin || isLider || operador.id === idLogado;
        if (!podeVer) return;

        const box = document.createElement("div");
        box.className = "op-botoes";

        // ============================================================
        // OPERADOR EM ESPERA (fila) — FASE 7 + FASE 8 + FASE 9
        // ============================================================
     /*   if (operador.status === "espera") {

            // OPERADOR LOGADO
            if (operador.id === idLogado) {

                box.innerHTML = `
                    <button class="op-btn op-decidir"
                            onclick="abrirModalDecidir(${operador.id})">
                        <i class="fas fa-list-check"></i> Decidir na fila
                    </button>
                `;

            }
            // ADMIN PODE TIRAR OPERADOR DA FILA
            else if (isAdmin) {

                box.innerHTML = `
                    <button class="op-btn op-sair-fila"
                            onclick="sairFilaIndividual(${operador.id})">
                        <i class="fas fa-circle-xmark"></i> Sair da fila
                    </button>
                `;
            }
        }
*/
/*
if (operador.status === "espera") {

    // Líder OU Admin OU próprio operador → pode DECIDIR
    if (operador.id === idLogado || isAdmin || isLider) {

        box.innerHTML = `
            <button class="op-btn op-decidir"
                    onclick="abrirModalDecidir(${operador.id})">
                <i class="fas fa-list-check"></i> Decidir na fila
            </button>
        `;

    } else {
        // Apenas operadores comuns vendo outros operadores
        box.innerHTML = `
            <button class="op-btn op-sair-fila"
                    onclick="sairFilaIndividual(${operador.id})">
                <i class="fas fa-circle-xmark"></i> Sair da fila
            </button>
        `;
    }
}*/
if (operador.status === "espera") {

    // LÍDER E ADMIN SEMPRE TÊM ACESSO TOTAL AO DECIDIR
    if (isAdmin || isLider) {

        box.innerHTML = `
            <button class="op-btn op-decidir"
                    onclick="abrirModalDecidir(${operador.id}, true)">
                <i class="fas fa-list-check"></i> Decidir (Supervisão)
            </button>
        `;

    }
    // OPERADOR LOGADO (regras normais)
    else if (operador.id === idLogado) {

        box.innerHTML = `
            <button class="op-btn op-decidir"
                    onclick="abrirModalDecidir(${operador.id}, false)">
                <i class="fas fa-list-check"></i> Decidir na fila
            </button>
        `;

    }
    // OPERADOR NORMAL vendo outro operador
    else {
        box.innerHTML = `
            <button class="op-btn op-sair-fila"
                    onclick="sairFilaIndividual(${operador.id})">
                <i class="fas fa-circle-xmark"></i> Sair da fila
            </button>
        `;
    }
}


        // ============================================================
        // OPERADOR ATIVO
        // ============================================================
/*
        else if (operador.status === "ativo") {

            box.innerHTML = `
                <button class="op-btn op-espera"
                        onclick="entrarFilaIndividual(${operador.id})">
                    <i class="fas fa-clock"></i> Entrar na fila
                </button>
            `;
        }*/
else if (operador.status === "ativo") {

    box.innerHTML = `
        <button class="op-btn op-espera"
                onclick="entrarFilaIndividual(${operador.id})">
            <i class="fas fa-clock"></i> Entrar na fila
        </button>

        <button class="op-btn op-chat"
                onclick="abrirChat(${operador.id})">
            <i class="fas fa-comments"></i> Chat
        </button>
    `;
}



    // ============================================================
// OPERADOR EM PAUSA
// ============================================================
else if (operador.status === "pausa") {

    box.innerHTML = `
        <button class="op-btn op-voltar"
                onclick="sairPausaIndividual(${operador.id})">
            <i class="fas fa-play"></i> Sair da Pausa
        </button>
    `;
}


        card.appendChild(box);
    });
}

/* ============================================================
   AÇÕES INDIVIDUAIS — FASE 6 (mantidas e compatíveis)
============================================================ */

window.entrarFilaIndividual = async id => {
    await fetch("../backend/entrar_fila.php", {
        method: "POST",
        body: new URLSearchParams({ id })
    });
    setTimeout(() => window.carregarPainel?.(), 250);
};

window.sairFilaIndividual = async id => {
    await fetch("../backend/sair_fila.php", {
        method: "POST",
        body: new URLSearchParams({ id })
    });
    setTimeout(() => window.carregarPainel?.(), 250);
};

window.sairPausaIndividual = async id => {
    await fetch("../backend/sair_pausa.php", {
        method: "POST",
        body: new URLSearchParams({ id })
    });
    setTimeout(() => window.carregarPainel?.(), 250);
};

/* ============================================================
   NOVO: Função placeholder de abrir modal
   (Será substituída pelo arquivo decidir.js)
============================================================ */
/*
window.abrirModalDecidir = function(id) {
    // Placeholder temporário para não dar erro
    console.log("abrirModalDecidir() chamado para ID:", id);
    alert("Modal de decisão será carregado aqui. (Arquivo decidir.js)");
}
*/
