/* ============================================================
   operador.js — FASE 6 (COMPATÍVEL COM PAINEL FASE 6)
   - Botões individuais para cada operador
   - Admin vê todos, operador comum vê só o próprio
   - SEM validação de status (sempre mostra botão)
   - Se operador estiver em espera → aparece bloco com tempo
   - Compatível com tempo real (cronômetro global do painel)
============================================================ */

console.log("%c[OPERADOR.JS] Iniciado com sucesso!", "color:#4ade80;font-weight:bold;");

let contadorFilaAtivo = false;

/* ============================================================
   INSERIR BOTÕES INDIVIDUAIS EM CADA CARD
============================================================ */
function inserirBotoesIndividuais(operadoresPainel = []) {

    console.log("%c[OPERADOR.JS] inserirBotoesIndividuais() chamado", "color:#38bdf8;");

    const dadosOperador = JSON.parse(localStorage.getItem("tga_operador"));
    if (!dadosOperador) {
        console.warn("[OPERADOR.JS] Nenhum operador logado encontrado.");
        return;
    }

    console.log("[OPERADOR.JS] Logado:", dadosOperador.operador, " | Admin:", dadosOperador.is_admin == 1);

    const isAdmin = dadosOperador.is_admin == 1;
    const cards = document.querySelectorAll(".linha-participante");

    if (cards.length === 0) {
        console.warn("[OPERADOR.JS] Nenhum card encontrado no DOM.");
        return;
    }

    cards.forEach(card => {

        const nome = card.dataset.nome;
        if (!nome) return;

        const operador = operadoresPainel.find(o => o.nome === nome);
        if (!operador) return;

        // remover botões antigos
        const antigo = card.querySelector(".op-botoes");
        if (antigo) antigo.remove();

        // regras de permissão
        const podeVer = isAdmin || operador.nome === dadosOperador.operador;
        if (!podeVer) return;

        const container = document.createElement("div");
        container.className = "op-botoes";

        /* ============================================================
           SE OPERADOR ESTÁ EM ESPERA → MOSTRAR BLOCO COM TEMPO
        ============================================================ */
        if (operador.status === "espera") {

            const pos = operador.posicao_fila ?? "-";
            const tempoInicial = operador.tempo_espera_seg ?? 0;
        }

        /* ============================================================
           SE NÃO ESTIVER EM ESPERA → EXIBE "ENTRAR NA FILA"
        ============================================================ */
        else {
            container.innerHTML = `
                <button class="op-btn op-espera" onclick="entrarFilaIndividual(${operador.id})">
                    <i class="fas fa-clock"></i> Entrar na fila
                </button>
            `;
        }

        card.appendChild(container);
    });

    iniciarContadorFila();
}

/* ============================================================
   FORMATADOR DE TEMPO (mm:ss)
============================================================ */
function formatarTempoFila(seg) {
    const m = Math.floor(seg / 60);
    const s = seg % 60;
    return `${m.toString().padStart(2,"0")}:${s.toString().padStart(2,"0")}`;
}

/* ============================================================
   CONTADOR LOCAL PARA TEMPOS DOS BOTÕES INDIVIDUAIS
   (NÃO conflita com contador global do painel.js)
============================================================ */
function iniciarContadorFila() {

    if (contadorFilaAtivo) return;

    contadorFilaAtivo = true;
    console.log("%c[OPERADOR.JS] Contador individual iniciado!", "color:#facc15;font-weight:bold;");

    setInterval(() => {
        document.querySelectorAll(".tempo-fila").forEach(el => {
            let t = parseInt(el.dataset.inicio);
            t++;
            el.dataset.inicio = t;
            el.textContent = formatarTempoFila(t);
        });
    }, 1000);
}

/* ============================================================
   AÇÃO: ENTRAR NA FILA
============================================================ */
window.entrarFilaIndividual = async id => {

    console.log("%c[OPERADOR.JS] entrarFilaIndividual → ID: " + id, "color:#fb923c");

    await fetch("../backend/entrar_fila.php", {
        method: "POST",
        body: new URLSearchParams({ id })
    });

    if (typeof carregarPainel === "function") {
        setTimeout(carregarPainel, 300);
    }
};
