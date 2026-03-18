/* =========================================================
STATE GLOBAL
========================================================= */

let todosAgentes         = [];
let statsLigacoes        = {};
let primeiroCarregamento = true;
let statsPerformance     = {};
let statsPausas          = {};
let filtroFilasMulti     = [];
let buscandoAgentes  = false;
let buscandoLigacoes = false;

/* Filtros */
let filtroStatus = "online";
let filtroFila   = "all";
let filtroNome   = "";

/* Ordenação */
let sortColuna  = null;
let sortDirecao = "asc";

/* Contadores regressivos */
let countdownAgentes  = 5;
let countdownLigacoes = 30;

/* Rastreamento de tempo em status
   { agent_id: { key: "online"|"offline"|"pausado", since: timestamp } } */
let statusSince = {};

/* Restaurar filtros salvos */
filtroFilasMulti = JSON.parse(localStorage.getItem("filtroFilasMulti") || "[]");
filtroStatus     = localStorage.getItem("filtroStatus") || "online";
filtroFila       = localStorage.getItem("filtroFila")   || "all";


/* =========================================================
HELPER — RAMAL
========================================================= */

function extrairRamal(agent) {
    const ext = agent.current_extension;
    if (!ext) return null;
    if (typeof ext === "object") {
        const val = ext.exten || ext.number || ext.name || ext.id || null;
        if (!val) return null;
        return String(val).replace(/^sip\//i, "");
    }
    if (typeof ext === "string") return ext.replace(/^sip\//i, "");
    return String(ext);
}


/* =========================================================
HELPER — STATUS DO AGENTE
========================================================= */

function isOnline(agent) {
    return !!(agent.current_extension);
}

function getStatusKey(agent) {
    if (!isOnline(agent))    return "offline";
    if (agent.current_pause) return "pausado";
    return "online";
}


/* =========================================================
HELPER — TEMPO EM STATUS
========================================================= */
function atualizarStatusSince(agentes) {
    agentes.forEach(agent => {
        const keyNova   = getStatusKey(agent);
        const keyAntiga = statusSince[agent.id]?.key;

        /* Disparar toast apenas após o primeiro carregamento */
        if (!primeiroCarregamento && keyAntiga && keyAntiga !== keyNova) {
            mostrarToast(agent.name, keyNova);
        }

        let since;
        if (keyNova === "pausado") {
            since = agent.current_pause?.time_start
                ? new Date(agent.current_pause.time_start).getTime()
                : Date.now();
        } else if (keyNova === "online") {
            since = agent.last_login?.time_login
                ? new Date(agent.last_login.time_login).getTime()
                : Date.now();
        } else {
            const atual = statusSince[agent.id];
            since = (atual && atual.key === "offline") ? atual.since : Date.now();
        }

        statusSince[agent.id] = { key: keyNova, since };
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
HELPER — CONVERSÃO DE TEMPO
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

function formatarSegHMS(seg) {
    const h = Math.floor(seg / 3600);
    const m = Math.floor((seg % 3600) / 60);
    const s = seg % 60;
    return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
}

function formatarTempo(segundos) {
    if (!segundos) return "—";
    const m = Math.floor(segundos / 60);
    const s = segundos % 60;
    return `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
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
            if (!stats[id]) stats[id] = { feitas: 0, recebidas: 0, atendidas: 0, totalTempo: 0, qtdTempo: 0 };
            const tipo     = call.call_type_description;
            const atendida = call.asa === "Sim" || call.time_connect !== null;
            const duracao  = parseInt(call.call_duration) || 0;
const emAndamento = !call.time_leave;
            if (tipo === "Saída")   stats[id].feitas++;
            if (tipo === "Entrada") stats[id].recebidas++;
            if (atendida)           stats[id].atendidas++;
if (emAndamento) stats[id].emLigacao = true;
            if (duracao > 0) { stats[id].totalTempo += duracao; stats[id].qtdTempo++; }
        });
        Object.keys(stats).forEach(id => {
            const s     = stats[id];
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
CARREGAR PERFORMANCE (a cada 60s)
========================================================= */

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
                duracaoChamadas: somarDuracoes(a.total_in_duration, a.total_out_completed_duration)
            };
        });
    } catch(e) {
        console.error("Erro performance:", e);
    }
}


/* =========================================================
CARREGAR PAUSAS (a cada 60s)
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
            let duracao = p.duration || 0;
            if (!p.time_end && p.time_start) {
                duracao = Math.floor((Date.now() - new Date(p.time_start).getTime()) / 1000);
            }
            statsPausas[id][desc] += duracao;
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
    if (buscandoAgentes) return;
    buscandoAgentes = true;
    try {
        await carregarAgentes();
        montarFiltroFilas();
        sincronizarSelects();
        aplicarFiltros();
    } finally {
        buscandoAgentes  = false;
        countdownAgentes = 5;
    }
}

async function atualizarLigacoes() {
    if (buscandoLigacoes) return;
    buscandoLigacoes = true;
    try {
        await Promise.all([ carregarLigacoes(), carregarPerformance(), carregarPausas() ]);
        aplicarFiltros();
    } finally {
        buscandoLigacoes  = false;
        countdownLigacoes = 60;
    }
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

    const filaSalva = filtroFila;
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

    const existe = [...select.options].some(o => o.value === filaSalva);
    select.value = existe ? filaSalva : "all";
    filtroFila   = select.value;

    montarMultiSelectFilas();
}


/* =========================================================
MULTI-SELECT DE FILAS
========================================================= */

function montarMultiSelectFilas() {
    const dropdown = document.getElementById("multi-queue-dropdown");
    if (!dropdown) return;

    const filas = [...new Set(
        todosAgentes.map(a => a.current_outbound_queue?.name).filter(Boolean)
    )].sort();

    dropdown.innerHTML = "";

    filas.forEach(fila => {
        const checked = filtroFilasMulti.includes(fila);
        const item = document.createElement("label");
        item.className = "multi-option";
        item.innerHTML = `<input type="checkbox" value="${fila}" ${checked ? "checked" : ""}> ${fila}`;
        item.querySelector("input").addEventListener("change", function () {
            if (this.checked) {
                filtroFilasMulti.push(this.value);
            } else {
                filtroFilasMulti = filtroFilasMulti.filter(f => f !== this.value);
            }
            atualizarLabelMulti();
            aplicarFiltros();
        });
        dropdown.appendChild(item);
    });
}

function atualizarLabelMulti() {
    const label = document.getElementById("multi-queue-label");
    if (!label) return;
    if (filtroFilasMulti.length === 0)      label.textContent = "Todas";
    else if (filtroFilasMulti.length === 1) label.textContent = filtroFilasMulti[0];
    else                                    label.textContent = `${filtroFilasMulti.length} filas`;
    localStorage.setItem("filtroFilasMulti", JSON.stringify(filtroFilasMulti));
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
if (filtroStatus === "ligacao") filtrados = filtrados.filter(a => isOnline(a) && !!statsLigacoes[a.id]?.emLigacao);

    if (filtroFila !== "all") {
        filtrados = filtrados.filter(a => a.current_outbound_queue?.name === filtroFila);
    }

    if (filtroFilasMulti.length > 0) {
        filtrados = filtrados.filter(a =>
            filtroFilasMulti.includes(a.current_outbound_queue?.name)
        );
    }

    if (filtroNome.trim()) {
        const busca = filtroNome.trim().toLowerCase()
            .normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        filtrados = filtrados.filter(a => {
            const nome  = (a.name || "").toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
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
        case "nome":         return agent.name || "";
        case "status":       return isOnline(agent) ? (agent.current_pause ? 1 : 0) : 2;
        case "fila":         return agent.current_outbound_queue?.name || "";
        case "total":        return (stats.feitas || 0) + (stats.recebidas || 0);
        case "feitas":       return stats.feitas        ?? null;
        case "recebidas":    return stats.recebidas     ?? null;
        case "atendidas":    return stats.atendidas     ?? null;
        case "tempo":        return stats.tempoMedioSeg ?? null;
        case "dur_chamadas": return toSegundos(statsPerformance[agent.id]?.duracaoChamadas);
        case "dur_pausas":   return toSegundos(statsPerformance[agent.id]?.pausaTotal);
        case "produtividade":return parseFloat(statsPerformance[agent.id]?.produtividade) || null;
        case "tickets":      return null;
        case "vencidos":     return null;
        case "atend_semana": return null;
        case "atend_mes":    return null;
        default:             return null;
    }
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

    const tfoot = document.getElementById("agents-footer");
    if (tfoot) {
        tfoot.innerHTML = `
            <tr>
                <td colspan="15" class="table-footer">
                    Exibindo <strong>${agents.length}</strong> de <strong>${todosAgentes.length}</strong> agentes
                </td>
            </tr>`;
    }

    if (agents.length === 0) {
        tbody.innerHTML = `<tr><td colspan="15" class="empty-row">Nenhum agente encontrado</td></tr>`;
        return;
    }

    agents.forEach(agent => {
        const online         = isOnline(agent);
        const pausado        = !!agent.current_pause;
        const emLigacao = !!(statsLigacoes[agent.id]?.emLigacao);
        const fila           = agent.current_outbound_queue?.name || null;
        const ramal          = extrairRamal(agent);
        const login          = agent.login || null;
        const motivoPausa    = agent.current_pause?.reason?.description || null;
        const detalhesPausas = formatarPausas(agent.id);

        const perf            = statsPerformance[agent.id] || {};
        const duracaoChamadas = perf.duracaoChamadas || "—";
        const pausaTotal      = perf.pausaTotal      || "—";
        const produtividade   = perf.produtividade   || "—";
        const prodBaixa       = produtividade !== "—" && parseFloat(produtividade) < 30;

let statusLabel, statusClass;
if (!online)        { statusLabel = "Offline";      statusClass = "badge-offline";  }
else if (pausado)   { statusLabel = motivoPausa || "Pausado"; statusClass = "badge-paused";  }
else if (emLigacao) { statusLabel = "Em ligação";   statusClass = "badge-ligacao";  }
else                { statusLabel = "Online";        statusClass = "badge-online";   }

        const tempo = tempoEmStatus(agent.id);

        const tooltipParts = [];
        if (login)  tooltipParts.push(`Login: ${login}`);
        if (ramal)  tooltipParts.push(`Ramal: ${ramal}`);
        const tooltip = tooltipParts.join(" | ");

        const stats     = statsLigacoes[agent.id] || {};
        const feitas    = stats.feitas     ?? "—";
        const recebidas = stats.recebidas  ?? "—";
        const atendidas = stats.atendidas  ?? "—";
        const tMedio    = stats.tempoMedio ?? "—";
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
                       <a class="agent-name agent-link" 
   href="https://tgasistemas.evolux.io/callcenter/supervisor/login_as_agent/${agent.login}" 
   target="_blank" 
   title="Abrir painel de ${agent.name}">${agent.name}</a>
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
            <td class="col-movidesk stat-num">—</td>
            <td class="col-movidesk stat-num">—</td>
            <td class="col-movidesk stat-num">—</td>
            <td class="col-movidesk stat-num">—</td>
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

    document.getElementById("filter_status")?.addEventListener("change", function () {
        filtroStatus = this.value;
        localStorage.setItem("filtroStatus", filtroStatus);
        aplicarFiltros();
    });

    document.getElementById("filter_queue")?.addEventListener("change", function () {
        filtroFila = this.value;
        localStorage.setItem("filtroFila", filtroFila);
        aplicarFiltros();
    });

    document.getElementById("filter_nome")?.addEventListener("input", function () {
        filtroNome = this.value;
        aplicarFiltros();
    });

    /* Multi-select — abrir/fechar */
    document.getElementById("multi-queue-trigger")?.addEventListener("click", function () {
        document.getElementById("multi-queue-dropdown")?.classList.toggle("open");
    });

    /* Multi-select — fechar ao clicar fora */
    document.addEventListener("click", function (e) {
        if (!e.target.closest("#multi-queue-wrap")) {
            document.getElementById("multi-queue-dropdown")?.classList.remove("open");
        }
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
TICK A CADA 1 SEGUNDO
========================================================= */

setInterval(async function () {

    countdownAgentes--;
    countdownLigacoes--;

    if (countdownAgentes <= 0) {
        countdownAgentes = 5;
        await atualizarAgentes();
    }

   if (countdownLigacoes <= 0) {
    countdownLigacoes = 30;
    await atualizarLigacoes();
}

    if (todosAgentes.length > 0) {
        aplicarFiltros();
    }

    atualizarCountdown();

}, 1000);


/* =========================================================
TOAST AVISO DE STATUS
========================================================= */
function mostrarToast(nome, tipo) {
    const container = document.getElementById("toast-container");
    if (!container) return;

    const icon  = tipo === "online"  ? "🟢" : tipo === "offline" ? "🔴" : "🟡";
    const label = tipo === "online"  ? "entrou online" 
                : tipo === "offline" ? "ficou offline" 
                : "entrou em pausa";

    const toast = document.createElement("div");
    toast.className = `toast toast-${tipo}`;
    toast.innerHTML = `<span class="toast-icon">${icon}</span><span class="toast-msg"><strong>${nome}</strong> ${label}</span>`;

    container.appendChild(toast);

setTimeout(() => toast.classList.add("toast-hide"), 4600);
setTimeout(() => toast.remove(), 5100);
}