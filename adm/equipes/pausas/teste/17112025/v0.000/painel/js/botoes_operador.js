console.log("%c[BOTOES] módulo carregado", "color:#38bdf8;font-weight:bold;");

/* =============================
   Variáveis globais
============================= */
let OPERADOR_ID = null;
let EQUIPE = null;
let filaCache = [];   // fila atual
let pausasCache = []; // pausas atuais

/* =============================
   Inicialização
============================= */
function iniciarBotoesOperador(dadosOperador) {
    OPERADOR_ID = dadosOperador.id;
    EQUIPE = dadosOperador.equipe;

    atualizarEstadoBotoes(); // exibe/oculta botões
}

/* =============================
   Atualizar botões conforme estado
============================= */
function atualizarEstadoBotoes() {

    const btnEntrarFila = document.getElementById("btnEntrarFila");
    const btnSairFila   = document.getElementById("btnSairFila");
    const btnEntrarPausa = document.getElementById("btnEntrarPausa");
    const btnOpcoesPrimeiro = document.getElementById("btnOpcoesPrimeiro");

    // Oculta tudo antes
    btnEntrarFila.classList.add("hidden");
    btnSairFila.classList.add("hidden");
    btnEntrarPausa.classList.add("hidden");
    btnOpcoesPrimeiro.classList.add("hidden");

    fetch(`backend/fila/buscar_fila.php?equipe=${encodeURIComponent(EQUIPE)}`)
        .then(r => r.json())
        .then(resp => {

            if (!resp.success) return;

            filaCache = resp.fila || [];
            pausasCache = resp.pausas || [];

            const estouNaFila = filaCache.some(f => f.operador_id == OPERADOR_ID);
            const souPrimeiro = filaCache.length > 0 && filaCache[0].operador_id == OPERADOR_ID;
            const vagas = resp.vagas_pausa;

            // --------- SITUAÇÃO 1: Fora da fila
            if (!estouNaFila) {
                btnEntrarFila.classList.remove("hidden");
                return;
            }

            // --------- SITUAÇÃO 2: Está na fila
            btnSairFila.classList.remove("hidden");

            // --------- SITUAÇÃO 3: Sou o primeiro da fila
            if (souPrimeiro) {
                btnOpcoesPrimeiro.classList.remove("hidden");

                if (vagas > 0) {
                    btnEntrarPausa.classList.remove("hidden");
                }
            }
        });
}

/* =============================
   Ações (entrar fila, sair, pausa)
============================= */

function entrarFila() {
    const dados = new FormData();
    dados.append("operador_id", OPERADOR_ID);
    dados.append("equipe", EQUIPE);

    fetch("backend/fila/entrar_fila.php", { method:"POST", body:dados })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                atualizarEstadoBotoes();
            } else {
                alert(resp.erro || "Falha ao entrar na fila");
            }
        });
}

function sairFila() {
    const dados = new FormData();
    dados.append("operador_id", OPERADOR_ID);
    dados.append("equipe", EQUIPE);

    fetch("backend/fila/sair_fila.php", { method:"POST", body:dados })
        .then(r => r.json())
        .then(resp => {
            atualizarEstadoBotoes();
        });
}

function entrarPausa() {
    const dados = new FormData();
    dados.append("operador_id", OPERADOR_ID);
    dados.append("equipe", EQUIPE);

    fetch("backend/fila/entrar_pausa.php", { method: "POST", body: dados })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                alert("☕ Você entrou em pausa.");

                // Atualizar botões
                atualizarEstadoBotoes();

                // Atualizar painel
                if (window.carregarParticipantesEquipe) {
                    carregarParticipantesEquipe(EQUIPE, OPERADOR_ID);
                }

                // Atualizar listas
                if (window.atualizarListasPainel) {
                    atualizarListasPainel();
                }
            } else {
                alert(resp.erro || "Falha ao entrar em pausa.");
            }
        });
}


/* =============================
   Eventos de clique
============================= */
document.addEventListener("click", (ev) => {
    if (ev.target.closest("#btnEntrarFila")) entrarFila();
    if (ev.target.closest("#btnSairFila")) sairFila();
    if (ev.target.closest("#btnEntrarPausa")) entrarPausa();

    if (ev.target.closest("#btnOpcoesPrimeiro")) {
        alert("⚙️ Opções do primeiro da fila serão adicionadas na ETAPA 4");
    }
});

console.log("%c[BOTOES] módulo carregado", "color:#7dd3fc");

let estadoPainel = {
    operadorId: null,
    nomeOperador: null,
    equipe: null,
    fila: [],
    pausas: [],
    vagas: 0
};

// ==============================
// FUNÇÃO PRINCIPAL
// ==============================
function atualizarBotoesOperador() {
    const area = document.getElementById("areaBotoesOperador");
    if (!area) return;

    const { operadorId, fila, pausas, vagas } = estadoPainel;

    const estouNaFila = fila.find(e => e.id == operadorId);
    const estouEmPausa = pausas.find(e => e.id == operadorId);
    const souPrimeiro = fila.length > 0 && fila[0].id == operadorId;

    area.innerHTML = "";

    // --- OPERADOR EM PAUSA ---
    if (estouEmPausa) {
        area.innerHTML = `
            <button class="btn-op btn-aviso" disabled>
                ☕ Você está em pausa
            </button>
        `;
        return;
    }

    // --- OPERADOR FORA DA FILA ---
    if (!estouNaFila) {
        area.innerHTML = `
            <button class="btn-op btn-primario" onclick="entrarFila()">
                ⏳ Entrar na Fila
            </button>
        `;
        return;
    }

    // --- ESTOU NA FILA ---
    if (estouNaFila) {
        // SE EU SOU O PRIMEIRO
        if (souPrimeiro) {
            // BOTÃO: ENTRAR EM PAUSA (SE HOUVER VAGA)
            if (vagas > 0) {
                area.innerHTML += `
                    <button class="btn-op btn-verde" onclick="entrarPausa()">
                        ☕ Entrar em Pausa
                    </button>
                `;
            }

            // BOTÃO OPÇÕES
            area.innerHTML += `
                <button class="btn-op btn-primario" onclick="abrirOpcoesPrimeiro()">
                    ⚙️ Opções do Primeiro
                </button>
            `;
        }

        // BOTÃO SAIR DA FILA
        area.innerHTML += `
            <button class="btn-op btn-perigo" onclick="sairFila()">
                ❌ Sair da Fila
            </button>
        `;
    }

}
