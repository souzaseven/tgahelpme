// ============================================================
// interface_botoes.js (v5.2)
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
    const equipe = p.equipe || "";

    // ============================================================
    // PERMISSÕES DE BOTÕES — ADMIN + OPERADOR PRÓPRIO
    // ============================================================
    // Nome do operador logado (pegando de vários lugares, pra garantir)
    const nomeLogadoRaw =
        ctrl.operador ||
        localStorage.getItem("operador_nome") ||
        localStorage.getItem("operador_logado") ||
        "";

    const nomeLogado = ctrl.normalizar(nomeLogadoRaw);
    const nomeOperador = ctrl.normalizar(nome);

    // Lista fixa de admins
    const ADM_LIST = [
        "anderson",
        "anderson souza",
        "anderson de souza",
        "admin"
    ];

    const ehAdmin = ADM_LIST.includes(nomeLogado);

    // Verifica se é o próprio operador (ajuda quando tem nome abreviado)
    const ehProprio =
        nomeOperador === nomeLogado ||
        nomeOperador.includes(nomeLogado) ||
        nomeLogado.includes(nomeOperador);

    // 🔍 DEBUG VISUAL
    console.log(
        "[BOTÕES] checando operador",
        {
            card: nome,
            status: p.status,
            equipe,
            nomeLogadoRaw,
            nomeLogado,
            ehAdmin,
            ehProprio
        }
    );

    // Se não é admin e não é o próprio -> NUNCA mostra botões
    if (!ehAdmin && !ehProprio) {
        area.innerHTML = "";
        return;
    }

    // ---------------------------------------------
    // CALCULAR FILA (ordem e posição)
    // ---------------------------------------------
    const filaEquipe = (ctrl.estado || [])
        .filter(
            x =>
                x.equipe === equipe &&
                ["espera", "aguardando"].includes(x.status)
        )
        .sort(
            (a, b) =>
                (a.posicao_fila || 999) - (b.posicao_fila || 999)
        );

    const idx = filaEquipe.findIndex(
        x => ctrl.normalizar(x.nome) === ctrl.normalizar(nome)
    );
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
        if (!operador) {
            console.warn("[BOTÕES] operador não encontrado no estado:", nome);
            return;
        }

        aplicarBotoesOperador(item, operador);
    });
});
