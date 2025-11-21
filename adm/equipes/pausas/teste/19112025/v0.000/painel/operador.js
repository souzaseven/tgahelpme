/* ============================================================
   operador.js — FASE 7 FINAL
   - Botões dentro do card do operador
   - Sem textos duplicados
   - Suporte ao botão “Decidir na Fila”
   - Tempo no card é controlado pelo painel.js (global)
   - Operador comum vê só seus botões
   - Admin vê botões de todos
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

    const cards = document.querySelectorAll(".linha-participante");

    cards.forEach(card => {

        const nome = card.dataset.nome;
        const operador = operadoresPainel.find(o => o.nome === nome);
        if (!operador) return;

        // remover botões antigos
        const antigo = card.querySelector(".op-botoes");
        if (antigo) antigo.remove();

        // permitir apenas admin ou operador logado
        const podeVer = isAdmin || operador.id == dados.id;
        if (!podeVer) return;

        const box = document.createElement("div");
        box.className = "op-botoes";


        /* ============================================================
   BOTÕES INDIVIDUAIS POR STATUS DO OPERADOR
============================================================ */

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

/* ===== ATIVO → ENTRAR NA FILA ===== */
else if (operador.status === "ativo") {

    box.innerHTML = `
        <button class="op-btn op-espera"
                onclick="entrarFilaIndividual(${operador.id})">
            <i class="fas fa-clock"></i> Entrar na fila
        </button>
    `;
}

/* ===== PAUSA → VOLTAR ATIVO ===== */
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

    iniciarContadorFila(); // apenas para compatibilidade
}

/* ============================================================
   FORMATADOR DE TEMPO (apenas legado, painel.js controla tempo)
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
    setTimeout(carregarPainel, 250);
};

window.sairFilaIndividual = async id => {
    await fetch("../backend/sair_fila.php", {
        method: "POST",
        body: new URLSearchParams({ id })
    });
    setTimeout(carregarPainel, 250);
};

window.sairPausaIndividual = async id => {
    await fetch("../backend/sair_pausa.php", {
        method: "POST",
        body: new URLSearchParams({ id })
    });
    setTimeout(carregarPainel, 250);
};
