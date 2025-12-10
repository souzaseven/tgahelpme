// ============================================================
// painel.js - FASE 2 COMPLETA do Controle de Pausas (ATUALIZADO)
// ============================================================
// - Lê operador logado (localStorage)
// - Carrega status da equipe (pausa / fila / online / offline)
// - Aplica status expirado
// - Preenche Pausa / Fila / Equipe Completa (6 por linha)
// - Mostra botões dinâmicos do operador logado
// - Atualiza automaticamente
// ============================================================

document.addEventListener("DOMContentLoaded", () => {

    // 1) Obter operador logado
    const dadosOperador = JSON.parse(localStorage.getItem("tga_operador"));
    if (!dadosOperador) {
        window.location.href = "../login/login.php";
        return;
    }

    const equipeAtual = dadosOperador.equipe;
    const operadorLogadoId = dadosOperador.id || null;
    const operadorLogadoNome = dadosOperador.operador;

    // Atualiza cabeçalho
    const subtituloEquipe = document.getElementById("subtituloEquipe");
    if (subtituloEquipe) subtituloEquipe.textContent = "Equipe: " + equipeAtual;

    const tituloEquipeBox = document.getElementById("nomeEquipeTitulo");
    if (tituloEquipeBox) tituloEquipeBox.textContent = equipeAtual;

    const boxOperadorLogado = document.getElementById("boxOperadorLogado");
    if (boxOperadorLogado) {
        boxOperadorLogado.innerHTML = `
            <div class="linha-op">
                <i class="fas fa-user-circle"></i>
                <span><strong>${operadorLogadoNome}</strong></span>
            </div>
            <div class="linha-op">
                <i class="fas fa-users"></i>
                <span>${equipeAtual}</span>
            </div>
        `;
    }

    // Elementos do painel
    const elPausa          = document.getElementById("listaPausa");
    const elFila           = document.getElementById("listaFila");
    const elEquipeCompleta = document.getElementById("listaEquipeCompleta");
    const elAreaBotoes     = document.getElementById("areaBotoesOperador");

    // -------------------------------
    // 2) Formatador de tempo
    // -------------------------------
    function formatarTempo(segundos) {
        if (!segundos || segundos < 0) segundos = 0;
        const h = Math.floor(segundos / 3600);
        const m = Math.floor((segundos % 3600) / 60);
        const s = segundos % 60;

        if (h > 0) {
            return `${h.toString().padStart(2,"0")}:${m.toString().padStart(2,"0")}:${s.toString().padStart(2,"0")}`;
        }
        return `${m.toString().padStart(2,"0")}:${s.toString().padStart(2,"0")}`;
    }

    // -------------------------------
    // 3) Buscar dados no backend
    // -------------------------------
    async function carregarPainel() {
        try {
            const url = `../backend/obter_status_equipe.php?equipe=${encodeURIComponent(equipeAtual)}`;
            const resp = await fetch(url);
            const dados = await resp.json();

            if (!dados.success) {
                preencherListasVazias("Erro ao carregar dados.");
                return;
            }

            atualizarPainel(dados.operadores || []);

        } catch (err) {
            preencherListasVazias("Falha ao comunicar com servidor.");
        }
    }

    function preencherListasVazias(msg) {
        elPausa.innerHTML          = `<p class="lista-vazia">${msg}</p>`;
        elFila.innerHTML           = `<p class="lista-vazia">${msg}</p>`;
        elEquipeCompleta.innerHTML = `<p class="lista-vazia">${msg}</p>`;
    }

    // -------------------------------
    // 4) Atualizar Painel
    // -------------------------------
    function atualizarPainel(operadores) {

        const emPausa = operadores.filter(op => op.status === "pausa");
        const emFila  = operadores.filter(op => op.status === "espera");
        const todos   = operadores.slice().sort((a,b)=>a.nome.localeCompare(b.nome,"pt-BR"));

        // PAUSA
        elPausa.innerHTML = emPausa.length
            ? ""
            : `<p class="lista-vazia">Nenhum operador em pausa.</p>`;
        emPausa.forEach(op => elPausa.appendChild(criarLinhaParticipante(op, "pausa")));

        // FILA
        elFila.innerHTML = emFila.length
            ? ""
            : `<p class="lista-vazia">Nenhum operador na fila.</p>`;
        emFila
            .slice()
            .sort((a,b)=>(a.posicao_fila||9999)-(b.posicao_fila||9999))
            .forEach(op => elFila.appendChild(criarLinhaParticipante(op, "fila")));

        // EQUIPE COMPLETA
        elEquipeCompleta.innerHTML = "";
        todos.forEach(op => {
            const linha = criarLinhaParticipante(op, "equipe");

            if (operadorLogadoId && op.id === operadorLogadoId ||
                (!operadorLogadoId && op.nome === operadorLogadoNome)) {
                linha.classList.add("atual");
            }

            elEquipeCompleta.appendChild(linha);
        });

    atualizarBotoes(operadores);

    // 🔥 INSERIR BOTÕES INDIVIDUAIS APÓS A LISTA SER RECRIADA
    if (typeof inserirBotoesIndividuais === "function") {
        inserirBotoesIndividuais();
    }
}

    // -------------------------------
    // 5) Botões do Operador
    // -------------------------------
    function atualizarBotoes(operadores) {

        const me = operadores.find(op =>
            (operadorLogadoId && op.id === operadorLogadoId) ||
            (!operadorLogadoId && op.nome === operadorLogadoNome)
        );

        if (!me) {
            elAreaBotoes.innerHTML =
                `<div class="acoes-operador"><span>Seu usuário não foi encontrado.</span></div>`;
            return;
        }

        const labelStatus =
            me.status === "pausa"   ? "Em Pausa" :
            me.status === "espera"  ? "Na Fila" :
            me.status === "offline" ? "Offline" :
                                      "Online";

        elAreaBotoes.innerHTML = `
            <div class="acoes-operador">
                <button class="acao-btn btn-secundario" disabled>
                    <i class="fas fa-user-circle"></i>
                    ${operadorLogadoNome} • ${labelStatus}
                </button>
            </div>
        `;

        if (me.status === "online") {
            elAreaBotoes.innerHTML += `
                <button class="acao-btn btn-primario" onclick="entrarFila(${me.id})">
                    <i class="fas fa-clock"></i> Entrar na fila de espera
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
                    <i class="fas fa-play"></i> Voltar online
                </button>`;
        }

        if (me.status === "offline") {
            elAreaBotoes.innerHTML += `
                <button class="acao-btn btn-primario" onclick="voltarOnline(${me.id})">
                    <i class="fas fa-plug"></i> Voltar online
                </button>`;
        }
    }

    // -------------------------------
    // 6) Criar Linha Visual
    // -------------------------------
    function criarLinhaParticipante(op, contexto) {
        const div = document.createElement("div");
        div.className = "linha-participante";

        // Determina a classe de status
        let statusClasse = op.status;

        // Se o operador está em PAUSA há muito tempo → EXPIRADO
        if (op.status === "pausa" && op.tempo_pausa_seg >= 3600) {
            statusClasse = "expirado";
        }

        const label =
            op.status === "pausa"   ? "Em pausa" :
            op.status === "espera"  ? "Fila" :
            op.status === "online"  ? "Online" :
                                      "Offline";

        let tempoStr = "";
        if (op.status === "pausa" && op.tempo_pausa_seg)
            tempoStr = "Tempo em pausa: " + formatarTempo(op.tempo_pausa_seg);
        else if (op.status === "espera" && op.tempo_espera_seg)
            tempoStr = "Tempo em espera: " + formatarTempo(op.tempo_espera_seg);

        const filaStr =
            contexto === "fila" && op.posicao_fila !== null
                ? ` • Posição: ${op.posicao_fila}`
                : "";

        div.innerHTML = `
            <div class="linha-participante-topo">
                <span class="bolinha-estado ${statusClasse}"></span>
                <span class="nome-op">${op.nome}</span>
                <span style="font-size:12px; opacity:0.8;">${label}</span>
            </div>
            <div style="font-size:12px; opacity:0.85; margin-top:2px;">
                ${tempoStr}${filaStr}
            </div>
        `;

        return div;
    }

    // -------------------------------
    // 7) Ações
    // -------------------------------
    window.entrarFila = async id => {
        await fetch("../backend/entrar_fila.php", {
            method: "POST",
            body: new URLSearchParams({ id })
        });
        setTimeout(carregarPainel, 300);
    };

    window.sairFila = async id => {
        await fetch("../backend/sair_fila.php", {
            method: "POST",
            body: new URLSearchParams({ id })
        });
        setTimeout(carregarPainel, 300);
    };

    window.sairPausa = async id => {
        await fetch("../backend/sair_pausa.php", {
            method: "POST",
            body: new URLSearchParams({ id })
        });
        setTimeout(carregarPainel, 300);
    };

    window.voltarOnline = async id => {
        await fetch("../backend/voltar_online.php", {
            method: "POST",
            body: new URLSearchParams({ id })
        });
        setTimeout(carregarPainel, 300);
    };

    // -------------------------------
    // 8) Inicializar
    // -------------------------------
    carregarPainel();
    setInterval(carregarPainel, 8000);
});
