    /* =========================================================
    STATE GLOBAL
    ========================================================= */
    
    let todosAgentes         = [];
    let statsLigacoes        = {};
    let primeiroCarregamento = true;
    let statsPerformance = {};
    let statsPausas = {};
    
    /* Filtros */
    let filtroStatus = "online";
    let filtroFila   = "all";
    let filtroNome   = "";
    
    /* Ordenação */
    let sortColuna  = null;
    let sortDirecao = "asc";
    
    /* Contadores regressivos */
    let countdownAgentes  = 5;
    let countdownLigacoes = 60;
    
    
    
    /* Rastreamento de tempo em status
       { agent_id: { key: "online"|"offline"|"pausado", since: timestamp } } */
    let statusSince = {};
    
    
    /* =========================================================
    HELPER — RAMAL
    Extrai o número do ramal corretamente, pois current_extension
    pode ser um objeto ou uma string como "sip/1065"
    ========================================================= */
    
    function extrairRamal(agent) {
        const ext = agent.current_extension;
        if (!ext) return null;
    
        /* Se for objeto com propriedade "exten" ou "number" */
        if (typeof ext === "object") {
            const val = ext.exten || ext.number || ext.name || ext.id || null;
            if (!val) return null;
            /* Remove prefixo "sip/" se existir */
            return String(val).replace(/^sip\//i, "");
        }
    
        /* Se for string "sip/1065" → "1065" */
        if (typeof ext === "string") {
            return ext.replace(/^sip\//i, "");
        }
    
        return String(ext);
    }
    
    
    /* =========================================================
    HELPER — STATUS DO AGENTE
    ========================================================= */
    
    function isOnline(agent) {
        return !!(agent.current_extension);
    }
    
    function getStatusKey(agent) {
        if (!isOnline(agent))      return "offline";
        if (agent.current_pause)   return "pausado";
        return "online";
    }
    
    
    /* =========================================================
    HELPER — TEMPO EM STATUS
    Acumula o tempo desde a última mudança de status.
    Só reseta quando o status muda de verdade.
    ========================================================= */
    
    function atualizarStatusSince(agentes) {
        agentes.forEach(agent => {
            const key = getStatusKey(agent);
            let since;
    
            if (key === "pausado") {
                since = agent.current_pause?.time_start
                    ? new Date(agent.current_pause.time_start).getTime()
                    : Date.now();
            } else if (key === "online") {
                since = agent.last_login?.time_login
                    ? new Date(agent.last_login.time_login).getTime()
                    : Date.now();
            } else {
                /* Offline — preserva timestamp anterior se já existia */
                const atual = statusSince[agent.id];
                since = (atual && atual.key === "offline") ? atual.since : Date.now();
            }
    
            statusSince[agent.id] = { key, since };
        });
    }
    
    function formatarDuracao(ms) {
        const seg = Math.floor(ms / 1000);
        const min = Math.floor(seg / 60);
        const h   = Math.floor(min / 60);
        if (h > 0)   return `${h}h${String(min % 60).padStart(2,'0')}m`;
        if (min > 0) return `${min}m${String(seg % 60).padStart(2,'0')}s`;
        return `${seg}s`;
    }
    
    function tempoEmStatus(agentId) {
        const reg = statusSince[agentId];
        if (!reg) return "";
        return formatarDuracao(Date.now() - reg.since);
    }
    
    
    /* =========================================================
    HELPER — TIPO DE FILA
    ========================================================= */
    
    function tipoFila(agent) {
        const nome = (agent.current_outbound_queue?.name || "").toLowerCase();
    
        const filasTel  = ["suporte matriz"];
        const filasChat = ["fila matriz chat/whats"];
    
        if (filasTel.some(f  => nome.includes(f))) return "tel";
        if (filasChat.some(f => nome.includes(f))) return "chat";
    
        if (nome.includes("tel") || nome.includes("fone") || nome.includes("phone")) return "tel";
        if (nome.includes("chat") || nome.includes("whats") || nome.includes("msg"))  return "chat";
    
        return "outro";
    }
    
    
    /* =========================================================
    LOADING
    ========================================================= */
    
    function mostrarLoading() {
        const el = document.getElementById("loading-overlay");
        if (el) el.style.display = "flex";
    }
    
    function esconderLoading() {
        const el = document.getElementById("loading-overlay");
        if (el) el.style.display = "none";
    }
    
    
    /* =========================================================
    CONTAGEM REGRESSIVA
    ========================================================= */
    
    function atualizarCountdown() {
        const elA = document.getElementById("countdown-agentes");
        const elL = document.getElementById("countdown-ligacoes");
    
        if (elA) elA.textContent = countdownAgentes + "s";
        if (elL) elL.textContent = countdownLigacoes + "s";
    
        if (elA) elA.classList.toggle("countdown-urgente", countdownAgentes <= 2);
        if (elL) elL.classList.toggle("countdown-urgente", countdownLigacoes <= 5);
    }
    
    
    /* =========================================================
    FORMATAR SEGUNDOS EM mm:ss
    ========================================================= */
    
    function formatarTempo(segundos) {
        if (!segundos) return "—";
        const m = Math.floor(segundos / 60);
        const s = segundos % 60;
        return `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    }
    
    
    /* =========================================================
    CARREGAR LIGAÇÕES (a cada 60s)
    ========================================================= */
    
    async function carregarLigacoes() {
        try {
            let pagina = 1, continuar = true, todasLigacoes = [];
    
            while (continuar) {
                const response = await fetch(`api/ligacoes.php?page=${pagina}`);
                const json     = await response.json();
                if (json.erro) { console.warn("Ligações:", json.erro); return; }
                todasLigacoes = todasLigacoes.concat(json.data?.calls || []);
                if (json.pagination?.next_url) { pagina++; } else { continuar = false; }
            }
    
            const stats = {};
    
            todasLigacoes.forEach(call => {
                const id = call.agent_id;
                if (!id) return;
                if (!stats[id]) {
                    stats[id] = { feitas: 0, recebidas: 0, atendidas: 0, totalTempo: 0, qtdTempo: 0 };
                }
                const tipo     = call.call_type_description;
                const atendida = call.asa === "Sim" || call.time_connect !== null;
                const duracao  = parseInt(call.call_duration) || 0;
                if (tipo === "Saída")   stats[id].feitas++;
                if (tipo === "Entrada") stats[id].recebidas++;
                if (atendida)           stats[id].atendidas++;
                if (duracao > 0) { stats[id].totalTempo += duracao; stats[id].qtdTempo++; }
            });
    
            Object.keys(stats).forEach(id => {
                const s = stats[id];
                const media = s.qtdTempo > 0 ? Math.round(s.totalTempo / s.qtdTempo) : 0;
                s.tempoMedioSeg = media;
                s.tempoMedio    = formatarTempo(media);
            });
    
            statsLigacoes = stats;
    
        } catch (error) {
            console.error("Erro ao carregar ligações:", error);
        }
    }
    
/* =========================================================
PERFORMACE
========================================================= */

function toSegundos(str) {
    if (!str || str === "—") return 0;
    const partes = str.split(":").map(Number);
    if (partes.length === 3) return partes[0]*3600 + partes[1]*60 + partes[2];
    if (partes.length === 2) return partes[0]*60 + partes[1];
    return 0;
}

function somarDuracoes(d1, d2) {
    const total = toSegundos(d1) + toSegundos(d2);
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;
    return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
}

async function carregarPerformance() {
    try {
        const res  = await fetch('api/performance.php');
        const json = await res.json();
        statsPerformance = {};
        (json.data || []).forEach(a => {
            const logonSeg   = toSegundos(a.logon);
            const pausaSeg   = toSegundos(a.pause_total);
            const chamadaSeg = toSegundos(a.total_in_duration) + toSegundos(a.total_out_completed_duration);
            const tempoAtivo = logonSeg - pausaSeg;
            const prodCalc   = tempoAtivo > 0
                ? (Math.ceil(chamadaSeg / tempoAtivo * 100)) + "%"
                : "0.00%";

            statsPerformance[a.id] = {
                logon:           a.logon       || "—",
                pausaTotal:      a.pause_total || "—",
                produtividade:   prodCalc,
                duracaoChamadas: somarDuracoes(
                    a.total_in_duration,
                    a.total_out_completed_duration
                )
            };
        });
    } catch(e) {
        console.error("Erro performance:", e);
    }
}
    
    /* =========================================================
    CARREGAMENTO INICIAL
    ========================================================= */
    
    async function carregarInicial() {
        mostrarLoading();
        try {
           await Promise.all([ carregarAgentes(), carregarLigacoes(), carregarPerformance(), carregarPausas() ]);
            montarFiltroFilas();
            sincronizarSelects();
            aplicarFiltros();
        } catch (error) {
            console.error("Erro no carregamento inicial:", error);
        } finally {
            esconderLoading();
            primeiroCarregamento = false;
            atualizarCountdown();
        }
    }
    
    
    /* =========================================================
    ATUALIZAR AGENTES (a cada 5s)
    ========================================================= */
    
    async function atualizarAgentes() {
        await carregarAgentes();
        montarFiltroFilas();
        sincronizarSelects();
        aplicarFiltros();
        countdownAgentes = 5;
    }
    
    
    /* =========================================================
    ATUALIZAR LIGAÇÕES (a cada 60s)
    ========================================================= */
    
async function atualizarLigacoes() {
    await Promise.all([ carregarLigacoes(), carregarPerformance(), carregarPausas() ]);
    aplicarFiltros();
    countdownLigacoes = 60;
}
    
    
    /* =========================================================
    CARREGAR AGENTES
    ========================================================= */
    
    async function carregarAgentes() {
        let pagina = 1, continuar = true, listaCompleta = [];
    
        while (continuar) {
            const response = await fetch(`api/agentes.php?page=${pagina}`);
            const json     = await response.json();
            if (json.erro) { console.error("Erro agentes:", json.erro); return; }
            listaCompleta = listaCompleta.concat(json.data || []);
            if (json.pagination?.next_url) { pagina++; } else { continuar = false; }
        }
    
        atualizarStatusSince(listaCompleta);
        todosAgentes = listaCompleta;
    }
    
    
    /* =========================================================
    SINCRONIZAR SELECTS
    ========================================================= */
    
    function sincronizarSelects() {
        const elStatus = document.getElementById("filter_status");
        const elFila   = document.getElementById("filter_queue");
    
        if (elStatus) elStatus.value = filtroStatus;
    
        if (elFila) {
            const existe = [...elFila.options].some(o => o.value === filtroFila);
            elFila.value = existe ? filtroFila : "all";
            filtroFila   = elFila.value;
        }
    }
    
    
    /* =========================================================
    MONTAR SELECT DE FILAS
    ========================================================= */
    
    function montarFiltroFilas() {
        const select = document.getElementById("filter_queue");
        if (!select) return;
    
        const filaSalva = filtroFila; /* preserva antes de limpar */
    
        select.innerHTML = `<option value="all">Todas</option>`;
    
        const filas = new Set();
        todosAgentes.forEach(a => {
            if (a.current_outbound_queue?.name) filas.add(a.current_outbound_queue.name);
        });
    
        filas.forEach(fila => {
            const opt = document.createElement("option");
            opt.value = opt.textContent = fila;
            select.appendChild(opt);
        });
    
        /* Restaura a seleção anterior se ainda existir */
        const existe = [...select.options].some(o => o.value === filaSalva);
        select.value = existe ? filaSalva : "all";
        filtroFila   = select.value;
    }
    
    
    /* =========================================================
    APLICAR FILTROS
    ========================================================= */
    
    function aplicarFiltros() {
        let filtrados = todosAgentes;
        if (filtroStatus === "online")  filtrados = filtrados.filter(a => isOnline(a) && !a.current_pause);
        if (filtroStatus === "ativos")  filtrados = filtrados.filter(a => isOnline(a)); 
        if (filtroStatus === "offline") filtrados = filtrados.filter(a => !isOnline(a));
        if (filtroStatus === "pausado") filtrados = filtrados.filter(a => isOnline(a) && !!a.current_pause);
        if (filtroFila !== "all") {
            filtrados = filtrados.filter(a => a.current_outbound_queue?.name === filtroFila);
        }
    
        /* BUSCA POR NOMe e ramal— normaliza acentos para facilitar */
    if (filtroNome.trim()) {
        const busca = filtroNome.trim().toLowerCase()
            .normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        filtrados = filtrados.filter(a => {
            const nome = (a.name || "").toLowerCase()
                .normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            const ramal = (extrairRamal(a) || "").toLowerCase();
            return nome.includes(busca) || ramal.includes(busca);
        });
    }
    
        filtrados = ordenar(filtrados);
    
        renderTabela(filtrados);
        atualizarCards();
        atualizarHeadersSortaveis();
    }
    
    
    /* =========================================================
    ORDENAÇÃO
    ========================================================= */
    
    function ordenar(lista) {
        if (!sortColuna) return lista;
    
        return [...lista].sort((a, b) => {
            let valA = getValorSort(a, sortColuna);
            let valB = getValorSort(b, sortColuna);
    
            if (typeof valA === "string" && typeof valB === "string") {
                const cmp = valA.localeCompare(valB, "pt-BR");
                return sortDirecao === "asc" ? cmp : -cmp;
            }
    
            valA = valA ?? -Infinity;
            valB = valB ?? -Infinity;
    
            return sortDirecao === "asc" ? valA - valB : valB - valA;
        });
    }
    
    function getValorSort(agent, coluna) {
        const stats = statsLigacoes[agent.id] || {};
    
        switch (coluna) {
            case "nome":      return agent.name || "";
            case "status":    return isOnline(agent) ? (agent.current_pause ? 1 : 0) : 2;
            case "fila":      return agent.current_outbound_queue?.name || "";
            case "total":     return (stats.feitas || 0) + (stats.recebidas || 0);
            case "feitas":    return stats.feitas        ?? null;
            case "recebidas": return stats.recebidas     ?? null;
            case "atendidas": return stats.atendidas     ?? null;
           case "tempo":         return stats.tempoMedioSeg ?? null;
case "dur_chamadas":  return toSegundos(statsPerformance[agent.id]?.duracaoChamadas);
case "dur_pausas":    return toSegundos(statsPerformance[agent.id]?.pausaTotal);
case "produtividade": return parseFloat(statsPerformance[agent.id]?.produtividade) || null;
case "tickets":       return null;
case "vencidos":      return null;
default:              return null;
        }
    }
    
    function toSegundos(str) {
    if (!str || str === "—") return null;
    const partes = str.split(":").map(Number);
    if (partes.length === 3) return partes[0]*3600 + partes[1]*60 + partes[2];
    if (partes.length === 2) return partes[0]*60 + partes[1];
    return null;
}
    /* =========================================================
    ATUALIZAR ÍCONES DOS HEADERS
    ========================================================= */
    
    function atualizarHeadersSortaveis() {
        document.querySelectorAll("th[data-sort]").forEach(th => {
            const col  = th.dataset.sort;
            const icon = th.querySelector(".sort-icon");
            if (!icon) return;
            if (col !== sortColuna) {
                icon.textContent = "⇅";
                icon.className   = "sort-icon sort-idle";
            } else {
                icon.textContent = sortDirecao === "asc" ? "↑" : "↓";
                icon.className   = "sort-icon sort-active";
            }
        });
    }
    
    
    /* =========================================================
    RENDERIZAR TABELA
    ========================================================= */
    
    function renderTabela(agents) {
        const tbody = document.getElementById("agents-body");
        tbody.innerHTML = "";
    
        /* Rodapé com contador */
        const tfoot = document.getElementById("agents-footer");
        if (tfoot) {
            tfoot.innerHTML = `
                <tr>
                   <td colspan="13" class="table-footer">
                        Exibindo <strong>${agents.length}</strong> de <strong>${todosAgentes.length}</strong> agentes
                    </td>
                </tr>`;
        }
    
        if (agents.length === 0) {
           tbody.innerHTML = `<tr><td colspan="13" class="empty-row">Nenhum agente encontrado</td></tr>`;
            return;
        }
    
        agents.forEach(agent => {
            const online      = isOnline(agent);
            const pausado     = !!agent.current_pause;
            const emLigacao   = !!(agent.current_call);
            const fila        = agent.current_outbound_queue?.name || null;
            const ramal       = extrairRamal(agent); /* ← usa helper corrigido */
            const login       = agent.login || null;
            const motivoPausa = agent.current_pause?.reason?.description || null;
            const detalhesPausas = formatarPausas(agent.id);
            
            /* Stats de performance */
const perf            = statsPerformance[agent.id] || {};
const duracaoChamadas = perf.duracaoChamadas || "—";
const pausaTotal      = perf.pausaTotal      || "—";
const produtividade   = perf.produtividade   || "—";
const prodBaixa       = produtividade !== "—" && parseFloat(produtividade) < 30;
    
            /* Status badge + tempo */
            let statusLabel, statusClass;
            if (!online)      { statusLabel = "Offline"; statusClass = "badge-offline"; }
            else if (pausado) { statusLabel = motivoPausa || "Pausado"; statusClass = "badge-paused"; }
            else              { statusLabel = "Online";   statusClass = "badge-online"; }
    
            const tempo = tempoEmStatus(agent.id);
    
            /* Tooltip do agente */
            const tooltipParts = [];
            if (login)  tooltipParts.push(`Login: ${login}`);
            if (ramal)  tooltipParts.push(`Ramal: ${ramal}`);
            const tooltip = tooltipParts.join(" | ");
    
            /* Stats de ligações */
            const stats     = statsLigacoes[agent.id] || {};
            const feitas    = stats.feitas      ?? "—";
            const recebidas = stats.recebidas   ?? "—";
            const atendidas = stats.atendidas   ?? "—";
            const tMedio    = stats.tempoMedio  ?? "—";
            const total     = (stats.feitas || 0) + (stats.recebidas || 0) || "—";
    
            const tr = document.createElement("tr");
            if (!online)   tr.classList.add("row-offline");
            if (emLigacao) tr.classList.add("row-em-ligacao");
    
            tr.innerHTML = `
                <td>
                    <div class="agent-cell" ${tooltip ? `title="${tooltip}"` : ""}>
                        <div class="avatar ${online ? 'avatar-online' : 'avatar-offline'}">
                            ${initials(agent.name)}
                        </div>
                       <div class="agent-info">
    <span class="agent-name">${agent.name}</span>
    ${ramal ? `<span class="agent-ramal">Ramal ${ramal}</span>` : ""}
    ${detalhesPausas !== "—" ? `<span class="agent-pausas">${detalhesPausas}</span>` : ""}
</div>
                        ${emLigacao ? `<span class="ligacao-badge" title="Em ligação agora">📞</span>` : ""}
                    </div>
                </td>
                <td>
                    <div class="status-cell">
                        <span class="${statusClass}">${statusLabel}</span>
                        ${tempo ? `<span class="status-time">${tempo}</span>` : ""}
                    </div>
                </td>
                <td>${fila ? `<span class="queue-chip">${fila}</span>` : "—"}</td>
                <td class="stat-num">${total}</td>
                <td class="stat-num">${feitas}</td>
                <td class="stat-num">${recebidas}</td>
                <td class="stat-num">${atendidas}</td>
                <td class="stat-tempo">${tMedio}</td>
               <td class="stat-tempo">${duracaoChamadas}</td>
<td class="stat-tempo">${pausaTotal}</td>
<td class="stat-prod ${prodBaixa ? 'prod-baixa' : ''}" title="Cálculo: Dur. Chamadas ÷ (Tempo Logado − Dur. Pausas) × 100">${produtividade}</td>
<td>—</td>
<td>—</td>
            `;
    
            tbody.appendChild(tr);
        });
    }
    
    
    /* =========================================================
    INICIAIS DO NOME
    ========================================================= */
    
    function initials(name) {
        return name.split(/[\s.]/).filter(Boolean).map(p => p[0]).join('').toUpperCase().slice(0,2);
    }
    
    
    /* =========================================================
    ATUALIZAR CARDS
    ========================================================= */
    
    function atualizarCards() {
        let ativos = 0, pausados = 0, filaTel = 0, filaChat = 0;
    
        todosAgentes.forEach(agent => {
            if (!isOnline(agent)) return;
            if (agent.current_pause) pausados++; else ativos++;
            const tipo = tipoFila(agent);
            if (tipo === "tel")  filaTel++;
            if (tipo === "chat") filaChat++;
        });
    
        const set = (id, val) => { const el = document.getElementById(id); if (el) el.innerText = val; };
    
        set("card_ativos",   ativos);
        set("card_pausados", pausados);
        set("card_tel",      filaTel  || "—");
        set("card_chat",     filaChat || "—");
    }
    
    
    /* =========================================================
    EVENTOS
    ========================================================= */
    
    document.addEventListener("DOMContentLoaded", function () {
    
        /* Filtro de status */
        document.getElementById("filter_status")?.addEventListener("change", function () {
            filtroStatus = this.value;
            aplicarFiltros();
        });
    
        /* Filtro de fila */
        document.getElementById("filter_queue")?.addEventListener("change", function () {
            filtroFila = this.value;
            aplicarFiltros();
        });
    
        /* Busca por nome — dispara a cada tecla */
        document.getElementById("filter_nome")?.addEventListener("input", function () {
            filtroNome = this.value;
            aplicarFiltros();
        });
    
        /* Ordenação por coluna */
        document.addEventListener("click", function (e) {
            const th = e.target.closest("th[data-sort]");
            if (!th) return;
            const col = th.dataset.sort;
            if (sortColuna === col) {
                sortDirecao = sortDirecao === "asc" ? "desc" : "asc";
            } else {
                sortColuna  = col;
                sortDirecao = "asc";
            }
            aplicarFiltros();
        });
    
    });
    
    
    /* =========================================================
    INICIALIZAÇÃO
    ========================================================= */
    
    carregarInicial();
    
    
/* =========================================================
PAUSAS
========================================================= */
async function carregarPausas() {
    try {
        let pagina = 1, continuar = true, todasPausas = [];

        while (continuar) {
            const res  = await fetch(`api/pausas.php?page=${pagina}`);
            const json = await res.json();
            todasPausas = todasPausas.concat(json.data || []);
            if (json.pagination?.next_url) { pagina++; } else { continuar = false; }
        }

        statsPausas = {};

        todasPausas.forEach(p => {
            const id = p.agent?.id;
            if (!id) return;
            if (!statsPausas[id]) statsPausas[id] = {};

            const desc = p.description?.trim() || "Sem motivo";
            if (!statsPausas[id][desc]) statsPausas[id][desc] = 0;
            statsPausas[id][desc] += p.duration || 0;
        });

    } catch(e) {
        console.error("Erro pausas:", e);
    }
}

function formatarPausas(agentId) {
    const pausas = statsPausas[agentId];
    if (!pausas || Object.keys(pausas).length === 0) return "—";

    const itens = Object.entries(pausas)
        .map(([desc, seg]) => `${desc}: ${formatarSegHMS(seg)}`);

    const linhas = [];
    for (let i = 0; i < itens.length; i += 3) {
        linhas.push(itens.slice(i, i + 3).join(" | "));
    }
    return linhas.join("<br>");
}

function formatarSegHMS(seg) {
    const h = Math.floor(seg / 3600);
    const m = Math.floor((seg % 3600) / 60);
    const s = seg % 60;
    return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
}
    
    /* =========================================================
    TICK A CADA 1 SEGUNDO
    — atualiza contadores e re-renderiza tempo em status
    ========================================================= */
    
    setInterval(async function () {
    
        countdownAgentes--;
        countdownLigacoes--;
    
        if (countdownAgentes <= 0) {
            countdownAgentes = 5;
            await atualizarAgentes();
        }
    
        if (countdownLigacoes <= 0) {
            countdownLigacoes = 60;
            await atualizarLigacoes();
        }
    
        /* Re-renderiza a tabela a cada segundo para atualizar o tempo em status ao vivo */
        if (todosAgentes.length > 0) {
            aplicarFiltros();
        }
    
        atualizarCountdown();
    
    }, 1000);