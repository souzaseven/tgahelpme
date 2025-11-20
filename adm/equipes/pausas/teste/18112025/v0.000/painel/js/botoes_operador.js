/* =============================
  botoes_operador.js (versão final refinada)
============================= */

console.log("%c[BOTOES] módulo carregado", "color:#38bdf8;font-weight:bold;");

/* =============================
   Variáveis globais
============================= */
let OPERADOR_ID = null;
let EQUIPE = null;

let filaCache = [];
let pausasCache = [];
let vagasCache = 0;

/* =============================
   Inicialização
============================= */
function iniciarBotoesOperador(dadosOperador) {
    if (!dadosOperador || !dadosOperador.id) {
        console.warn("[BOTOES] Dados do operador ausentes.");
        return;
    }

    OPERADOR_ID = dadosOperador.id;
    EQUIPE      = dadosOperador.equipe;

    atualizarEstadoBotoes();
}

/* =============================
   Atualizar visibilidade dos botões (TOPO)
============================= */
function atualizarEstadoBotoes() {

    const btnEntrarFila  = document.getElementById("btnEntrarFila");
    const btnEntrarPausa = document.getElementById("btnEntrarPausa");
    const btnDecidir     = document.getElementById("btnDecidir");
    const btnSairPausa   = document.getElementById("btnSairPausa");

    if (!btnEntrarFila || !btnEntrarPausa || !btnDecidir || !btnSairPausa) {
        console.warn("[BOTOES] Botões não estão no DOM.");
        return;
    }

    // Reset visual (topo)
    [btnEntrarFila, btnEntrarPausa, btnDecidir, btnSairPausa]
        .forEach(btn => btn.classList.add("hidden"));

    // Buscar estado atual
    fetch(`backend/fila/buscar_fila.php?equipe=${encodeURIComponent(EQUIPE)}`)
        .then(r => r.json())
        .then(resp => {

            if (!resp || !resp.success) {
                console.warn("[BOTOES] Falha ao carregar estado da equipe.");
                return;
            }

            filaCache   = resp.fila   || [];
            pausasCache = resp.pausas || [];
            vagasCache  = resp.vagas_pausa || 0;

            const estouNaFila   = filaCache.some(f => String(f.operador_id) === String(OPERADOR_ID));
            const souPrimeiro   = filaCache.length > 0 && String(filaCache[0].operador_id) === String(OPERADOR_ID);
            const estouEmPausa  = pausasCache.some(p => String(p.operador_id) === String(OPERADOR_ID));

            /* 1) DISPONÍVEL */
            if (!estouNaFila && !estouEmPausa) {
                btnEntrarFila.classList.remove("hidden");
            }

            /* 2) PAUSADO */
            if (estouEmPausa) {
                btnSairPausa.classList.remove("hidden");
            }

            /* 3) NA FILA */
            if (estouNaFila) {
                btnDecidir.classList.remove("hidden");
            }

            /* 4) PRIMEIRO COM VAGA → pode entrar pausa */
            if (souPrimeiro && vagasCache > 0) {
                btnEntrarPausa.classList.remove("hidden");
            }

            /* 🔥 ***NOVO*** — Atualiza botões INLINE dentro da linha do operador */
            renderizarBotoesInline();
        })
        .catch(() => {
            console.warn("[BOTOES] Erro ao conectar com backend.");
        });
}

/* =============================
   BOTÕES INLINE NA LISTA (NOVO)
============================= */
function renderizarBotoesInline() {

    const linha = document.querySelector(".linha-participante.atual");
    if (!linha) {
        console.warn("[INLINE] Linha do operador atual não encontrada.");
        return;
    }

    const box = linha.querySelector(".acoes-operador-inline");
    if (!box) {
        console.warn("[INLINE] .acoes-operador-inline não existe.");
        return;
    }

    // Limpa botões antigos
    box.innerHTML = "";

    const estouNaFila   = filaCache.some(f => f.operador_id == OPERADOR_ID);
    const souPrimeiro   = filaCache.length > 0 && filaCache[0].operador_id == OPERADOR_ID;
    const estouEmPausa  = pausasCache.some(p => p.operador_id == OPERADOR_ID);

    /* ============================= */
    /* 1) Disponível → botão entrar fila */
    if (!estouNaFila && !estouEmPausa) {
box.innerHTML += `
    <button class="btn-mini btn-primario" onclick="entrarFila()">
        <i class="fas fa-sign-in-alt"></i> Fila
    </button>
`;

    }

    /* 2) Em pausa → só sair */
    if (estouEmPausa) {
box.innerHTML += `
    <button class="btn-mini btn-perigo" onclick="sairPausa()">
        <i class="fas fa-times"></i> Sair
    </button>
`;

        return;
    }

    /* 3) Está na fila → botão decidir */
    if (estouNaFila) {
box.innerHTML += `
    <button class="btn-mini btn-secundario" onclick="abrirModalDecidir()">
        <i class="fas fa-user-cog"></i> Decidir
    </button>
`;

    }

    /* 4) Primeiro com vaga → botão entrar pausa */
    if (souPrimeiro && vagasCache > 0) {
 box.innerHTML += `
    <button class="btn-mini btn-primario" onclick="entrarPausa()">
        <i class="fas fa-coffee"></i> Pausa
    </button>
`;

    }
}

/* =============================
   Ações
============================= */
function entrarFila() {
    const dados = new FormData();
    dados.append("operador_id", OPERADOR_ID);
    dados.append("equipe", EQUIPE);

    fetch("backend/fila/entrar_fila.php", { method: "POST", body: dados })
        .then(r => r.json())
        .then(resp => {
            if (!resp.success) return alert(resp.erro || "Erro ao entrar na fila.");

            atualizarEstadoBotoes();

            if (typeof atualizarListasPainel === "function") {
                atualizarListasPainel();
            }
        });
}

function entrarPausa() {
    const dados = new FormData();
    dados.append("operador_id", OPERADOR_ID);
    dados.append("equipe", EQUIPE);

    fetch("backend/fila/entrar_pausa.php", { method: "POST", body: dados })
        .then(r => r.json())
        .then(resp => {
            if (!resp.success) {
                alert(resp.erro || "Erro ao entrar na pausa.");
                return;
            }

            alert("☕ Você entrou em pausa.");
            atualizarEstadoBotoes();

            if (typeof atualizarListasPainel === "function") {
                atualizarListasPainel();   // força atualizar card de pausas
            }
        });
}

function sairPausa() {
    const dados = new FormData();
    dados.append("operador_id", OPERADOR_ID);
    dados.append("equipe", EQUIPE);

    fetch("backend/fila/sair_pausa.php", { method: "POST", body: dados })
        .then(r => r.json())
        .then(() => {
            atualizarEstadoBotoes();

            if (typeof atualizarListasPainel === "function") {
                atualizarListasPainel();   // limpa/atualiza card de pausas
            }
        });
}


/* =============================
   Eventos globais de clique
============================= */
document.addEventListener("click", (ev) => {
    if (ev.target.closest("#btnEntrarFila"))  entrarFila();
    if (ev.target.closest("#btnEntrarPausa")) entrarPausa();
    if (ev.target.closest("#btnSairPausa"))   sairPausa();
    if (ev.target.closest("#btnDecidir"))     abrirModalDecidir();
});

console.log("%c[BOTOES] módulo ativo", "color:#7dd3fc");
