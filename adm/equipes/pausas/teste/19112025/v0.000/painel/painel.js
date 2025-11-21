/* ============================================================
   operador.js — FASE 7 FINAL (CORRIGIDO)
   - Botões individuais dentro do card do operador
   - Sem textos duplicados
   - Compatível com painel.js FASE 7
   - Operador comum vê só seus botões
   - Admin vê botões de todos
   - Suporte ao botão “Decidir na Fila”
============================================================ */

console.log("%c[OPERADOR.JS] FASE 7 iniciado!", "color:#4ade80;font-weight:bold;");

let contadorFilaAtivo = false;

/* ============================================================
   INSERIR BOTÕES INDIVIDUAIS EM CADA CARD
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

        // permitir apenas admin ou operador logado
        const podeVer = isAdmin || operador.id === idLogado;
        if (!podeVer) return;

        const box = document.createElement("div");
        box.className = "op-botoes";

        /* ============================================================
           BOTÕES POR STATUS
        ============================================================= */

        // ------------------------------
        // 1) OPERADOR EM ESPERA
        // ------------------------------
        if (operador.status === "espera") {

            const pos = operador.posicao_fila ?? "-";
            const tempoInicial = operador.tempo_espera_seg ?? 0;

            box.innerHTML = `
                <div class="op-info-fila">
                    <div class="linha-info">
                        <strong>${pos}°</strong> • ${operador.nome}
                    </div>

                    <div class="linha-tempo">
                        <span class="tempo-fila"
                              data-id="${operador.id}"
                              data-inicio="${tempoInicial}">
                            ${formatarTempoFila(tempoInicial)}
                        </span>
                    </div>

                    <button class="op-btn op-decisao"
                            onclick="abrirModalDecisaoFila()">
                        <i class="fas fa-list-check"></i> Decidir na fila
                    </button>
                </div>
            `;
        }

        // ------------------------------
        // 2) OPERADOR ATIVO
        // ------------------------------
        else if (operador.status === "ativo") {

            box.innerHTML = `
                <button class="op-btn op-espera"
                        onclick="entrarFilaIndividual(${operador.id})">
                    <i class="fas fa-clock"></i> Entrar na fila
                </button>
            `;
        }

        // ------------------------------
        // 3) OPERADOR EM PAUSA
        // ------------------------------
        else if (operador.status === "pausa") {

            box.innerHTML = `
                <button class="op-btn op-voltar"
                        onclick="sairPausaIndividual(${operador.id})">
                    <i class="fas fa-play"></i> Voltar ativo
                </button>
            `;
        }

        // insere no card
        card.appendChild(box);
    });

    iniciarContadorFila();
}

/* ============================================================
   FORMATADOR DE TEMPO (mm:ss)
============================================================ */
function formatarTempoFila(seg) {
    const m = Math.floor(seg / 60);
    const s = seg % 60;
    return `${m.toString().padStart(2, "0")}:${s.toString().padStart(2, "0")}`;
}

/* ============================================================
   CONTADOR LOCAL (compatível, não interfere no painel)
============================================================ */
function iniciarContadorFila() {

    if (contadorFilaAtivo) return;
    contadorFilaAtivo = true;

    console.log("%c[OPERADOR.JS] Contador individual iniciado", "color:#facc15");

    setInterval(() => {
        document.querySelectorAll(".tempo-fila").forEach(el => {
            let t = parseInt(el.dataset.inicio);
            el.dataset.inicio = t + 1;
            el.textContent = formatarTempoFila(t + 1);
        });
    }, 1000);
}

/* ============================================================
   AÇÕES INDIVIDUAIS
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
