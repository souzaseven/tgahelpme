/* =============================
  botoes_operador.js
============================= */

console.log("%c[BOTOES] módulo carregado", "color:#38bdf8;font-weight:bold;");

/* =============================
   Variáveis globais
============================= */
let OPERADOR_ID = null;
let EQUIPE = null;
let filaCache = [];
let pausasCache = [];

/* =============================
   Inicialização
============================= */
function iniciarBotoesOperador(dadosOperador) {
    OPERADOR_ID = dadosOperador.id;
    EQUIPE = dadosOperador.equipe;

    atualizarEstadoBotoes();
}

/* =============================
   Atualizar botões conforme estado
============================= */
function atualizarEstadoBotoes() {

    const btnEntrarFila      = document.getElementById("btnEntrarFila");
    const btnSairFila        = document.getElementById("btnSairFila");
    const btnEntrarPausa     = document.getElementById("btnEntrarPausa");
    const btnOpcoesPrimeiro  = document.getElementById("btnOpcoesPrimeiro");

    if (!btnEntrarFila || !btnSairFila || !btnEntrarPausa || !btnOpcoesPrimeiro) {
        console.warn("[BOTOES] Botões ainda não existem no DOM.");
        return;
    }

    // Oculta tudo antes
    btnEntrarFila.classList.add("hidden");
    btnSairFila.classList.add("hidden");
    btnEntrarPausa.classList.add("hidden");
    btnOpcoesPrimeiro.classList.add("hidden");

    fetch(`backend/fila/buscar_fila.php?equipe=${encodeURIComponent(EQUIPE)}`)
        .then(r => r.json())
        .then(resp => {

            if (!resp.success) return;

            filaCache   = resp.fila   || [];
            pausasCache = resp.pausas || [];

            const estouNaFila = filaCache.some(f => f.operador_id == OPERADOR_ID);
            const souPrimeiro = filaCache.length > 0 && filaCache[0].operador_id == OPERADOR_ID;
            const vagas       = resp.vagas_pausa;

            // ---- 1. Fora da fila
            if (!estouNaFila) {
                btnEntrarFila.classList.remove("hidden");
                return;
            }

            // ---- 2. Está na fila
            btnSairFila.classList.remove("hidden");

            // ---- 3. Sou o primeiro da fila
            if (souPrimeiro) {
                btnOpcoesPrimeiro.classList.remove("hidden");

                if (vagas > 0) {
                    btnEntrarPausa.classList.remove("hidden");
                }
            }
        });
}

/* =============================
   Ações básicas
============================= */

function entrarFila() {
    const dados = new FormData();
    dados.append("operador_id", OPERADOR_ID);
    dados.append("equipe", EQUIPE);

    fetch("backend/fila/entrar_fila.php", { method:"POST", body:dados })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) atualizarEstadoBotoes();
            else alert(resp.erro || "Falha ao entrar na fila");
        });
}

function sairFila() {
    const dados = new FormData();
    dados.append("operador_id", OPERADOR_ID);
    dados.append("equipe", EQUIPE);

    fetch("backend/fila/sair_fila.php", { method:"POST", body:dados })
        .then(r => r.json())
        .then(() => atualizarEstadoBotoes());
}

function entrarPausa() {
    const dados = new FormData();
    dados.append("operador_id", OPERADOR_ID);
    dados.append("equipe", EQUIPE);

    fetch("backend/fila/entrar_pausa.php", { method:"POST", body:dados })
        .then(r => r.json())
        .then(resp => {
            if (!resp.success) {
                alert(resp.erro || "Erro ao entrar em pausa.");
                return;
            }

            alert("☕ Você entrou em pausa.");
            atualizarEstadoBotoes();

            // Atualizar painel visual
            if (window.carregarParticipantesEquipe) carregarParticipantesEquipe(EQUIPE);
            if (window.atualizarListasPainel) atualizarListasPainel();
        });
}

/* =============================
   Eventos globais de clique
============================= */
document.addEventListener("click", (ev) => {
    if (ev.target.closest("#btnEntrarFila")) entrarFila();
    if (ev.target.closest("#btnSairFila")) sairFila();
    if (ev.target.closest("#btnEntrarPausa")) entrarPausa();

    if (ev.target.closest("#btnOpcoesPrimeiro")) {
        alert("⚙️ Opções do primeiro serão ativadas na ETAPA 4.");
    }
});

console.log("%c[BOTOES] módulo ativo", "color:#7dd3fc");
