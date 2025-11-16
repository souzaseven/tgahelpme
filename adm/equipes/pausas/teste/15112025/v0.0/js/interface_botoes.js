// ============================================================
// interface_botoes.js (v5.1)
// Sistema ÚNICO e OFICIAL de botões do Controle de Pausas
// Compatível com controle_pausa.js v5.3 e troca_fila.js v2.0
// ============================================================

console.log("%c[interface_botoes.js] carregado", "color:#00ff88;font-weight:bold;");

// ============================================================
// FUNÇÃO PRINCIPAL — CRIA OS BOTÕES DO OPERADOR
// ============================================================
function aplicarBotoesOperador(item, p) {
    const area = item.querySelector(".botoes-operador");
    if (!area) return;
    area.innerHTML = "";

    const ctrl = window.controle;
    if (!ctrl) return;

    const nome = p.nome;
    const equipe = p.equipe;

    // Quem pode ver botões?
    const nomeLogado = ctrl.normalizar(ctrl.operador || "");

    // 🔐 Detecção mais robusta de admin (você)
    const ehAdmin =
        nomeLogado === ctrl.normalizar("Anderson de Souza") ||
        nomeLogado === ctrl.normalizar("Anderson Souza") ||
        nomeLogado === ctrl.normalizar("Anderson");

    const ehProprio = ctrl.normalizar(nome) === nomeLogado;

    if (!ehAdmin && !ehProprio) {
        area.innerHTML = "";
        return;
    }

    // ---------------------------------------------
    // CALCULAR FILA (ordem e posição)
    // ---------------------------------------------
    const filaEquipe = (ctrl.estado || [])
        .filter(x => x.equipe === equipe && ["espera", "aguardando"].includes(x.status))
        .sort((a, b) => (a.posicao_fila || 999) - (b.posicao_fila || 999));

    const idx = filaEquipe.findIndex(x => ctrl.normalizar(x.nome) === ctrl.normalizar(nome));
    const posicaoNaFila = idx >= 0 ? idx + 1 : null;

    const pausasEquipe = (ctrl.estado || []).filter(
        x => x.equipe === equipe && x.status === "pausa"
    ).length;

    const limitePausas = ctrl.maxPausas || 2;

    // ============================================================
    // BOTÕES PADRÃO — entrar na fila / voltar disponível
    // ============================================================
    if (p.status === "disponivel" || p.status === "ativo") {
        const btFila = document.createElement("button");
        btFila.textContent = "⏳ Entrar na fila";
        btFila.className = "btn-acao btn-fila";
        btFila.onclick = () => ctrl.enviarAcao("entrar_fila", { nome, equipe });
        area.appendChild(btFila);
    }

    if (["pausa", "espera", "aguardando"].includes(p.status)) {
        const btVoltar = document.createElement("button");
        btVoltar.textContent = "✅ Voltar disponível";
        btVoltar.className = "btn-acao btn-voltar";
        btVoltar.onclick = () => ctrl.enviarAcao("voltar_disponivel", { nome, equipe });
        area.appendChild(btVoltar);
    }

    // ============================================================
    // ☕ ENTRAR EM PAUSA (somente o 1º da fila + vaga disponível)
    // ============================================================
    if (
        posicaoNaFila === 1 &&
        ["espera", "aguardando"].includes(p.status) &&
        pausasEquipe < limitePausas
    ) {
        const btPausa = document.createElement("button");
        btPausa.textContent = "☕ Entrar em pausa";
        btPausa.className = "btn-acao btn-pausa-fila";

        btPausa.onclick = () => {
            if (window.trocaFila) {
                window.trocaFila.entrarPausaPrimeiro(nome, equipe);
            } else {
                ctrl.enviarAcao("fila_entrar_pausa", { nome, equipe });
            }
        };

        area.appendChild(btPausa);
    }

    // ============================================================
    // 🔄 SOLICITAR TROCA (apenas posição 2+)
    // ============================================================
    if (
        posicaoNaFila !== null &&
        posicaoNaFila >= 2 &&
        ["espera", "aguardando"].includes(p.status)
    ) {
        const btTroca = document.createElement("button");
        btTroca.textContent = "🔄 Solicitar troca";
        btTroca.className = "btn-acao btn-troca-fila";

        btTroca.onclick = () => {
            if (window.trocaFila) {
                window.trocaFila.abrirSolicitacaoTroca(nome, equipe);
            } else {
                ctrl.toast("Módulo de troca não carregado.", true);
            }
        };

        area.appendChild(btTroca);
    }
}


// ============================================================
// EVENTO PRINCIPAL — após renderizar operadores
// ============================================================
document.addEventListener("ui:operadores-renderizados", () => {
    const ctrl = window.controle;
    if (!ctrl || !Array.isArray(ctrl.estado)) return;

    document.querySelectorAll(".op-item").forEach(item => {
        const nome = item.querySelector("strong")?.textContent || "";
        const operador = ctrl.estado.find(
            p => ctrl.normalizar(p.nome) === ctrl.normalizar(nome)
        );
        if (!operador) return;

        aplicarBotoesOperador(item, operador);
    });
});
