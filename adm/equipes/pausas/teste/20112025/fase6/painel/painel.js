    // ============================================================
    // painel.js - FASE 6 FINAL do Controle de Pausas
    // ============================================================
    // - Status: ativo / espera / pausa
    // - Fila horizontal em tempo real (1s)
    // - Layout limpo e moderno
    // - Botões principais corrigidos
    // - Integra operador.js
    // ============================================================

    document.addEventListener("DOMContentLoaded", () => {

        // ============================================================
        // 1) Carregar operador logado
        // ============================================================
        const dadosOperador = JSON.parse(localStorage.getItem("tga_operador"));
        if (!dadosOperador) {
            window.location.href = "../login/login.php";
            return;
        }

        const equipeAtual        = dadosOperador.equipe;
        const operadorLogadoId   = Number(dadosOperador.id);
        const operadorLogadoNome = dadosOperador.operador;

        // Cabeçalho
        document.getElementById("subtituloEquipe").textContent     = "Equipe: " + equipeAtual;
        document.getElementById("nomeEquipeTitulo").textContent     = equipeAtual;
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

        // Containers
        const elPausa          = document.getElementById("listaPausa");
        const elFila           = document.getElementById("listaFila");
        const elEquipeCompleta = document.getElementById("listaEquipeCompleta");
        const elAreaBotoes     = document.getElementById("areaBotoesOperador");

        // ============================================================
        // 2) Formatador de tempo (global)
        // ============================================================
        function formatarTempo(seg) {
            if (!seg || seg < 0) seg = 0;
            const m = Math.floor(seg / 60);
            const s = seg % 60;
            return `${m.toString().padStart(2,"0")}:${s.toString().padStart(2,"0")}`;
        }

        // ============================================================
        // 3) Buscar dados
        // ============================================================
        async function carregarPainel() {
            try {
                const resp = await fetch(`../backend/obter_status_equipe.php?equipe=${encodeURIComponent(equipeAtual)}`);
                const dados = await resp.json();

                if (!dados.success) {
                    preencherListasVazias("Erro ao carregar equipe.");
                    return;
                }

                atualizarPainel(dados.operadores || []);

            } catch (e) {
                console.error("Erro carregar painel:", e);
                preencherListasVazias("Falha ao comunicar com servidor.");
            }
        }

        function preencherListasVazias(msg) {
            elPausa.innerHTML = elFila.innerHTML = elEquipeCompleta.innerHTML =
                `<p class="lista-vazia">${msg}</p>`;
        }

        // ============================================================
        // 4) Cronômetro global 1s
        // ============================================================
        let contadorGlobalAtivo = false;

        function iniciarContadorGlobal() {
            if (contadorGlobalAtivo) return;
            contadorGlobalAtivo = true;

            setInterval(() => {
                document.querySelectorAll(".tempo-fila-global").forEach(el => {
                    let t = parseInt(el.dataset.inicio);
                    t++;
                    el.dataset.inicio = t;
                    el.textContent = formatarTempo(t);
                });
            }, 1000);
        }

        // ============================================================
        // 5) Atualizar Painel
        // ============================================================
        function atualizarPainel(operadores) {

            const emPausa = operadores.filter(o => o.status === "pausa");
            const emFila  = operadores.filter(o => o.status === "espera");
            const todos   = operadores.slice().sort((a,b)=>a.nome.localeCompare(b.nome));

            // ----- PAUSA -----
            elPausa.innerHTML = emPausa.length ? "" : `<p class="lista-vazia">Nenhum operador em pausa.</p>`;
            emPausa.forEach(op => elPausa.appendChild(criarLinhaParticipante(op,"pausa")));

            // ----- FILA -----
            elFila.innerHTML = emFila.length ? "" : `<p class="lista-vazia">Nenhum operador na fila.</p>`;
            emFila
                .sort((a,b)=>(a.posicao_fila||999)-(b.posicao_fila||999))
                .forEach(op => elFila.appendChild(criarLinhaParticipante(op,"fila")));

            // ----- EQUIPE COMPLETA -----
            elEquipeCompleta.innerHTML = "";
            todos.forEach(op => {
                const linha = criarLinhaParticipante(op,"equipe");
                if (op.id === operadorLogadoId) linha.classList.add("atual");
                elEquipeCompleta.appendChild(linha);
            });

            iniciarContadorGlobal();
            atualizarBotoesGlobais(operadores);

            if (typeof inserirBotoesIndividuais === "function") {
                inserirBotoesIndividuais(operadores);
            }
        }

        // ============================================================
        // 6) Botões principais do operador
        // ============================================================
        function atualizarBotoesGlobais(operadores) {

            const me = operadores.find(o => o.id === operadorLogadoId);

            if (!me) {
                elAreaBotoes.innerHTML = `<p class="acoes-operador">Erro ao localizar operador.</p>`;
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
        // 7) Criar cartão visual
        // ============================================================
        function criarLinhaParticipante(op, contexto) {

        const div = document.createElement("div");
        div.className = "linha-participante";

        div.dataset.id   = op.id;
        div.dataset.nome = op.nome;

        const statusClasse = op.status;
        const tempoFila    = op.tempo_espera_seg || 0;
        const tempoPausa   = op.tempo_pausa_seg  || 0;

        let topo = "";
        let info = "";

        // ============================================================
        // FILA — SOMENTE LAYOUT HORIZONTAL (CORRIGIDO)
        // ============================================================
        if (contexto === "fila") {

            const pos = op.posicao_fila ?? "-";
            const tempoInicial = op.tempo_espera_seg ?? 0;

            topo = `
                <div class="linha-participante-topo"
                    style="display:flex;align-items:center;gap:8px;">
                    
                    <span class="bolinha-estado ${statusClasse}"></span>

                    <span class="nome-op" style="font-weight:600;">
                        ${pos}° • ${op.nome} • 
                        <span class="tempo-fila-global"
                            data-id="${op.id}"
                            data-inicio="${tempoInicial}">
                            ${formatarTempo(tempoInicial)}
                        </span>
                    </span>
                </div>
            `;

            info = ""; // ← REMOVE TOTALMENTE QUALQUER LINHA "TEMPO:"
        }

        // ============================================================
        // PAUSA
        // ============================================================
        else if (contexto === "pausa") {

            topo = `
                <div class="linha-participante-topo">
                    <span class="bolinha-estado pausa"></span>
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

        // ============================================================
        // EQUIPE COMPLETA (visão geral)
        // ============================================================
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

            info = `<div class="linha-participante-info">${extra}</div>`;
        }

        div.innerHTML = topo + info;
        return div;
    }


        // ============================================================
        // 8) Ações backend
        // ============================================================
        window.entrarFila = async id => {
            await fetch("../backend/entrar_fila.php", {
                method:"POST",
                body:new URLSearchParams({id})
            });
            setTimeout(carregarPainel,200);
        };

        window.sairFila = async id => {
            await fetch("../backend/sair_fila.php", {
                method:"POST",
                body:new URLSearchParams({id})
            });
            setTimeout(carregarPainel,200);
        };

        window.sairPausa = async id => {
            await fetch("../backend/sair_pausa.php", {
                method:"POST",
                body:new URLSearchParams({id})
            });
            setTimeout(carregarPainel,200);
        };

        // ============================================================
        // 9) Inicializar
        // ============================================================
        carregarPainel();
        setInterval(carregarPainel,8000);
    });
