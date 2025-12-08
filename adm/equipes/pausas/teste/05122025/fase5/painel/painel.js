// ============================================================
// painel.js - FASE 5 COMPLETA do Controle de Pausas
// ============================================================
// - Lê operador logado
// - Carrega status da equipe (ativo / espera / pausa)
// - Aplica tempo real
// - Preenche Pausa / Fila / Equipe Completa
// - Mostra botões dinâmicos do operador logado
// - Integra com operador.js (botões individuais)
// ============================================================

document.addEventListener("DOMContentLoaded", () => {

    // ============================================================
    // 1) Dados do operador logado
    // ============================================================
    const dadosOperador = JSON.parse(localStorage.getItem("tga_operador"));
    if (!dadosOperador) {
        window.location.href = "../login/login.php";
        return;
    }

    const equipeAtual        = dadosOperador.equipe;
    const operadorLogadoId   = Number(dadosOperador.id);
    const operadorLogadoNome = dadosOperador.operador;

    // Atualiza cabeçalho
    document.getElementById("subtituloEquipe").textContent = "Equipe: " + equipeAtual;
    document.getElementById("nomeEquipeTitulo").textContent = equipeAtual;

    document.getElementById("boxOperadorLogado").innerHTML = `
        <div class="linha-op">
            <i class="fas fa-user-circle"></i>
            <span><strong>${operadorLogadoNome}</strong></span>
        </div>
        <div class="linha-op">
            <i class="fas fa-users"></i>
            <span>${equipeAtual}</span>
        </div>
    `;

    // Elementos principais do painel
    const elPausa          = document.getElementById("listaPausa");
    const elFila           = document.getElementById("listaFila");
    const elEquipeCompleta = document.getElementById("listaEquipeCompleta");
    const elAreaBotoes     = document.getElementById("areaBotoesOperador");

    // ============================================================
    // 2) Formatador de tempo (pausa / espera)
    // ============================================================
    function formatarTempo(segundos) {
        if (!segundos || segundos < 0) segundos = 0;
        const m = Math.floor(segundos / 60);
        const s = segundos % 60;
        return `${m.toString().padStart(2,"0")}:${s.toString().padStart(2,"0")}`;
    }

    // ============================================================
    // 3) Buscar dados no backend
    // ============================================================
    async function carregarPainel() {
        try {
            const resp = await fetch(`../backend/obter_status_equipe.php?equipe=${encodeURIComponent(equipeAtual)}`);
            const dados = await resp.json();

            if (!dados.success) {
                preencherListasVazias("Erro ao carregar dados.");
                return;
            }

            atualizarPainel(dados.operadores || []);

        } catch (e) {
            console.error("Erro ao carregar painel:", e);
            preencherListasVazias("Falha ao comunicar com servidor.");
        }
    }

    function preencherListasVazias(msg) {
        elPausa.innerHTML          = `<p class="lista-vazia">${msg}</p>`;
        elFila.innerHTML           = `<p class="lista-vazia">${msg}</p>`;
        elEquipeCompleta.innerHTML = `<p class="lista-vazia">${msg}</p>`;
    }

    // ============================================================
    // 4) Atualizar Painel (principal)
    // ============================================================
    function atualizarPainel(operadores) {

        const emPausa = operadores.filter(op => op.status === "pausa");
        const emFila  = operadores.filter(op => op.status === "espera");
        const todos   = operadores.slice().sort((a,b) => a.nome.localeCompare(b.nome,"pt-BR"));

        // ---------------- PAUSA ----------------
        elPausa.innerHTML = emPausa.length ? "" : `<p class="lista-vazia">Nenhum operador em pausa.</p>`;
        emPausa.forEach(op => elPausa.appendChild(criarLinhaParticipante(op, "pausa")));

        // ---------------- FILA ----------------
        elFila.innerHTML = emFila.length ? "" : `<p class="lista-vazia">Nenhum operador na fila.</p>`;
        emFila
            .slice()
            .sort((a,b) => (a.posicao_fila||9999) - (b.posicao_fila||9999))
            .forEach(op => elFila.appendChild(criarLinhaParticipante(op, "fila")));

        // ------------- EQUIPE COMPLETA -------------
        elEquipeCompleta.innerHTML = "";

        todos.forEach(op => {
            const linha = criarLinhaParticipante(op, "equipe");

            if (op.id === operadorLogadoId) linha.classList.add("atual");

            elEquipeCompleta.appendChild(linha);
        });

        // Botões globais
        atualizarBotoesGlobais(operadores);

        // Botões individuais (operador.js)
        if (typeof inserirBotoesIndividuais === "function") {
            inserirBotoesIndividuais(operadores);
        }
    }

    // ============================================================
    // 5) Botões do operador logado (abaixo da equipe)
    // ============================================================
    function atualizarBotoesGlobais(operadores) {

        const me = operadores.find(op => op.id === operadorLogadoId);

        if (!me) {
            elAreaBotoes.innerHTML = `<p class="acoes-operador">Erro: operador não encontrado.</p>`;
            return;
        }

        const textoStatus =
            me.status === "pausa"  ? "Em pausa" :
            me.status === "espera" ? "Na fila"  :
                                     "Ativo";

        elAreaBotoes.innerHTML = `
            <div class="acoes-operador">
                <button class="acao-btn btn-secundario" disabled>
                    <i class="fas fa-user-circle"></i>
                    ${operadorLogadoNome} • ${textoStatus}
                </button>
            </div>
        `;

        if (me.status === "ativo") {
            elAreaBotoes.innerHTML += `
                <button class="acao-btn btn-primario" onclick="entrarFila(${me.id})">
                    <i class="fas fa-clock"></i> Entrar na fila
                </button>`;
        }

        if (me.status === "espera") {
            elAreaBotoes.innerHTML += `
                <button class="acao-btn btn-alerta" onclick="sairFila(${me.id})">
                    <i class="fas fa-xmark"></i> Sair da fila
                </button>`;
        }

        if (me.status === "pausa") {
            elAreaBotoes.innerHTML += `
                <button class="acao-btn btn-sucesso" onclick="sairPausa(${me.id})">
                    <i class="fas fa-play"></i> Voltar ativo
                </button>`;
        }
    }

    // ============================================================
    // 6) Criar card visual do operador
    // ============================================================
    function criarLinhaParticipante(op, contexto) {

        const div = document.createElement("div");
        div.className = "linha-participante";

        // Ajuda o operador.js identificar
        div.dataset.id   = op.id;
        div.dataset.nome = op.nome;

        const statusClasse = op.status;
        const tempoPausa   = op.tempo_pausa_seg || 0;
        const tempoFila    = op.tempo_espera_seg || 0;

        let topo = "";
        let info = "";

        // MODO FILA — Formato especial
        if (contexto === "fila") {

            topo = `
                <div class="linha-participante-topo">
                    <span class="bolinha-estado ${statusClasse}"></span>
                    <span class="nome-op">Posição: ${op.posicao_fila ?? "-"} • ${op.nome}</span>
                </div>
            `;

            info = `
                <div class="linha-participante-info">
                    Tempo: ${formatarTempo(tempoFila)}
                </div>
            `;
        }

        // MODO PAUSA
        else if (contexto === "pausa") {

            topo = `
                <div class="linha-participante-topo">
                    <span class="bolinha-estado ${statusClasse}"></span>
                    <span class="nome-op">${op.nome}</span>
                    <span class="status-label">Em pausa</span>
                </div>
            `;

            info = `
                <div class="linha-participante-info">
                    Tempo em pausa: ${formatarTempo(tempoPausa)}
                </div>
            `;
        }

        // MODO EQUIPE COMPLETA
        else {
            const label =
                op.status === "pausa"  ? "Em pausa" :
                op.status === "espera" ? "Fila" :
                                          "Ativo";

            let extra = "";
            if (op.status === "pausa")  extra = "Tempo em pausa: "  + formatarTempo(tempoPausa);
            if (op.status === "espera") extra = "Tempo em espera: " + formatarTempo(tempoFila);

            topo = `
                <div class="linha-participante-topo">
                    <span class="bolinha-estado ${statusClasse}"></span>
                    <span class="nome-op">${op.nome}</span>
                    <span class="status-label">${label}</span>
                </div>
            `;

            info = `
                <div class="linha-participante-info">
                    ${extra || ""}
                </div>
            `;
        }

        div.innerHTML = topo + info;
        return div;
    }

    // ============================================================
    // 7) Ações (backend)
    // ============================================================
    window.entrarFila = async id => {
        await fetch("../backend/entrar_fila.php", {
            method: "POST",
            body: new URLSearchParams({ id })
        });
        setTimeout(carregarPainel, 200);
    };

    window.sairFila = async id => {
        await fetch("../backend/sair_fila.php", {
            method: "POST",
            body: new URLSearchParams({ id })
        });
        setTimeout(carregarPainel, 200);
    };

    window.sairPausa = async id => {
        await fetch("../backend/sair_pausa.php", {
            method: "POST",
            body: new URLSearchParams({ id })
        });
        setTimeout(carregarPainel, 200);
    };

    // ============================================================
    // 8) Inicializar
    // ============================================================
    carregarPainel();
    setInterval(carregarPainel, 8000);
});
