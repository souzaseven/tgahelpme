/* ============================================================
   operador.js — FASE 5 (VERSÃO FINAL SEM VALIDAÇÃO DE STATUS)
   - Botões individuais SEM depender de status
   - Admin vê botões de todos
   - Operador comum vê apenas os dele
============================================================ */

console.log("%c[OPERADOR.JS] Iniciado com sucesso!", "color:#4ade80;font-weight:bold;");

let contadorFilaAtivo = false;

/* ====================================================================
   INSERIR BOTÕES INDIVIDUAIS
==================================================================== */
function inserirBotoesIndividuais(operadoresPainel = []) {

    console.log("%c[OPERADOR.JS] inserirBotoesIndividuais() chamado", "color:#38bdf8;");

    const dadosOperador = JSON.parse(localStorage.getItem("tga_operador"));
    if (!dadosOperador) return;

    console.log("[OPERADOR.JS] Logado:", dadosOperador.operador, " | Admin:", dadosOperador.is_admin == 1);

    const isAdmin = dadosOperador.is_admin == 1;

    const cards = document.querySelectorAll(".linha-participante");
    cards.forEach(card => {

        const nome = card.querySelector(".nome-op")?.textContent.trim();
        const operador = operadoresPainel.find(o => o.nome === nome);

        if (!operador) return;

        // remover botões anteriores
        const existe = card.querySelector(".op-botoes");
        if (existe) existe.remove();

        // Regras de exibição:
        // Admin -> vê todos
        // Normal -> só vê o próprio card
        const podeVer = isAdmin || operador.nome === dadosOperador.operador;
        if (!podeVer) return;

        const container = document.createElement("div");
        container.className = "op-botoes";

        /* ===== Exibir botão de fila para TODOS ===== */
        container.innerHTML = `
            <button class="op-btn op-espera" onclick="entrarFilaIndividual(${operador.id})">
                <i class="fas fa-clock"></i> Entrar na fila
            </button>
        `;

        /* ===== Se estiver em espera, mostra posição e tempo ===== */
        if (operador.status === "espera") {

            const pos = operador.posicao_fila ?? "-";
            const tempoInicial = operador.tempo_espera_seg ?? 0;

            container.innerHTML = `
                <div class="op-info-fila">
                    <div class="linha-info">
                        <strong>Posição:</strong> ${pos}
                        <span class="sep">•</span>
                        <strong>${operador.nome}</strong>
                    </div>
                    <div class="linha-tempo">
                        <strong>Tempo:</strong>
                        <span class="tempo-fila" data-id="${operador.id}" data-inicio="${tempoInicial}">
                            ${formatarTempoFila(tempoInicial)}
                        </span>
                    </div>
                </div>
            `;
        }

        card.appendChild(container);
    });

    iniciarContadorFila();
}

/* ====================================================================
   FORMATADOR DE TEMPO
==================================================================== */
function formatarTempoFila(seg) {
    const m = Math.floor(seg / 60);
    const s = seg % 60;
    return `${m.toString().padStart(2,"0")}:${s.toString().padStart(2,"0")}`;
}

/* ====================================================================
   CONTADOR
==================================================================== */
function iniciarContadorFila() {

    if (contadorFilaAtivo) return; // evita duplicação

    contadorFilaAtivo = true;
    console.log("%c[OPERADOR.JS] Contador iniciado!", "color:#facc15;font-weight:bold;");

    setInterval(() => {
        document.querySelectorAll(".tempo-fila").forEach(el => {
            let t = parseInt(el.dataset.inicio);
            el.dataset.inicio = t + 1;
            el.textContent = formatarTempoFila(t + 1);
        });
    }, 1000);
}

/* ====================================================================
   AÇÃO DO BOTÃO (entrar na fila)
==================================================================== */
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
