/* =========================================================
STATE GLOBAL
========================================================= */

let todosAgentes         = [];
let statsLigacoes        = {}; /* { agent_id: { feitas, recebidas, atendidas, tempoMedio } } */
let primeiroCarregamento = true;

/* Filtros guardados em variáveis JS — fonte da verdade */
let filtroStatus = "online";
let filtroFila   = "all";


/* =========================================================
HELPER — STATUS DO AGENTE
========================================================= */

function isOnline(agent) {
    return !!(agent.current_extension);
}


/* =========================================================
HELPER — TIPO DE FILA
========================================================= */

function tipoFila(agent) {
    const nome = (agent.current_outbound_queue?.name || "").toLowerCase();
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
CARREGAR LIGAÇÕES DO DIA
Busca histórico de chamadas com paginação e
agrupa as estatísticas por agent_id
========================================================= */

async function carregarLigacoes() {

    try {

        let pagina        = 1;
        let continuar     = true;
        let todasLigacoes = [];

        while (continuar) {

            const response = await fetch(`api/ligacoes.php?page=${pagina}`);
            const json     = await response.json();

            if (json.erro) {
                console.warn("Ligações:", json.erro);
                return;
            }

            const calls = json.data?.calls || [];
            todasLigacoes = todasLigacoes.concat(calls);

            if (json.pagination?.next_url) {
                pagina++;
            } else {
                continuar = false;
            }

        }

        /* -------------------------------------------------
        AGRUPAR ESTATÍSTICAS POR AGENTE
        ------------------------------------------------- */

        const stats = {};

        todasLigacoes.forEach(call => {

            const id = call.agent_id;
            if (!id) return;

            if (!stats[id]) {
                stats[id] = {
                    feitas:     0,
                    recebidas:  0,
                    atendidas:  0,
                    totalTempo: 0,
                    qtdTempo:   0,
                };
            }

            const tipo      = call.call_type_description; /* "Saída" ou "Entrada" */
            const atendida  = call.asa === "Sim" || call.time_connect !== null;
            const duracao   = parseInt(call.call_duration) || 0;

            if (tipo === "Saída")   stats[id].feitas++;
            if (tipo === "Entrada") stats[id].recebidas++;
            if (atendida)           stats[id].atendidas++;

            if (duracao > 0) {
                stats[id].totalTempo += duracao;
                stats[id].qtdTempo++;
            }

        });

        /* Calcular tempo médio em mm:ss */
        Object.keys(stats).forEach(id => {
            const s = stats[id];
            const media = s.qtdTempo > 0 ? Math.round(s.totalTempo / s.qtdTempo) : 0;
            s.tempoMedio = formatarTempo(media);
        });

        statsLigacoes = stats;

    } catch (error) {

        console.error("Erro ao carregar ligações:", error);

    }

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
FUNÇÃO PRINCIPAL
========================================================= */

async function carregarTudo() {

    if (primeiroCarregamento) mostrarLoading();

    try {

        /* Carregar agentes e ligações em paralelo */
        await Promise.all([
            carregarAgentes(),
            carregarLigacoes(),
        ]);

        montarFiltroFilas();
        sincronizarSelects();
        aplicarFiltros();

    } catch (error) {

        console.error("Erro ao carregar dados:", error);

    } finally {

        esconderLoading();
        primeiroCarregamento = false;

    }

}


/* =========================================================
CARREGAR AGENTES
========================================================= */

async function carregarAgentes() {

    let pagina        = 1;
    let continuar     = true;
    let listaCompleta = [];

    while (continuar) {

        const response = await fetch(`api/agentes.php?page=${pagina}`);
        const json     = await response.json();

        if (json.erro) {
            console.error("Erro da API de agentes:", json.erro);
            return;
        }

        listaCompleta = listaCompleta.concat(json.data || []);

        if (json.pagination?.next_url) {
            pagina++;
        } else {
            continuar = false;
        }

    }

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

    select.innerHTML = `<option value="all">Todas</option>`;

    const filas = new Set();

    todosAgentes.forEach(agent => {
        if (agent.current_outbound_queue?.name) {
            filas.add(agent.current_outbound_queue.name);
        }
    });

    filas.forEach(fila => {
        const opt       = document.createElement("option");
        opt.value       = fila;
        opt.textContent = fila;
        select.appendChild(opt);
    });

}


/* =========================================================
APLICAR FILTROS
========================================================= */

function aplicarFiltros() {

    let filtrados = todosAgentes;

    if (filtroStatus === "online") {
        filtrados = filtrados.filter(a => isOnline(a));
    } else if (filtroStatus === "offline") {
        filtrados = filtrados.filter(a => !isOnline(a));
    }

    if (filtroFila !== "all") {
        filtrados = filtrados.filter(a =>
            a.current_outbound_queue?.name === filtroFila
        );
    }

    renderTabela(filtrados);
    atualizarCards();

}


/* =========================================================
RENDERIZAR TABELA
========================================================= */

function renderTabela(agents) {

    const tbody = document.getElementById("agents-body");
    tbody.innerHTML = "";

    if (agents.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="empty-row">Nenhum agente encontrado</td>
            </tr>`;
        return;
    }

    agents.forEach(agent => {

        const online  = isOnline(agent);
        const pausado = !!agent.current_pause;
        const fila    = agent.current_outbound_queue?.name || null;

        /* Status */
        let statusLabel, statusClass;
        if (!online)       { statusLabel = "Offline";  statusClass = "badge-offline"; }
        else if (pausado)  { statusLabel = "Pausado";  statusClass = "badge-paused";  }
        else               { statusLabel = "Online";   statusClass = "badge-online";  }

        /* Stats de ligações */
        const stats     = statsLigacoes[agent.id] || {};
        const feitas    = stats.feitas    ?? "—";
        const recebidas = stats.recebidas ?? "—";
        const atendidas = stats.atendidas ?? "—";
        const tempo     = stats.tempoMedio ?? "—";
        const total     = (stats.feitas || 0) + (stats.recebidas || 0) || "—";

        const tr = document.createElement("tr");
        if (!online) tr.classList.add("row-offline");

        tr.innerHTML = `
            <td>
                <div class="agent-cell">
                    <div class="avatar ${online ? 'avatar-online' : 'avatar-offline'}">${initials(agent.name)}</div>
                    <span class="agent-name">${agent.name}</span>
                </div>
            </td>
            <td><span class="${statusClass}">${statusLabel}</span></td>
            <td>${fila ? `<span class="queue-chip">${fila}</span>` : "—"}</td>
            <td class="stat-num">${total}</td>
            <td class="stat-num">${feitas}</td>
            <td class="stat-num">${recebidas}</td>
            <td class="stat-num">${atendidas}</td>
            <td class="stat-tempo">${tempo}</td>
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
    return name
        .split(/[\s.]/)
        .filter(Boolean)
        .map(p => p[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}


/* =========================================================
ATUALIZAR CARDS
========================================================= */

function atualizarCards() {

    let ativos   = 0;
    let pausados = 0;
    let filaTel  = 0;
    let filaChat = 0;

    todosAgentes.forEach(agent => {
        if (!isOnline(agent)) return;
        if (agent.current_pause) pausados++;
        else ativos++;
        const tipo = tipoFila(agent);
        if (tipo === "tel")  filaTel++;
        if (tipo === "chat") filaChat++;
    });

    const set = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.innerText = val;
    };

    set("card_ativos",   ativos);
    set("card_pausados", pausados);
    set("card_tel",      filaTel  || "—");
    set("card_chat",     filaChat || "—");

}


/* =========================================================
EVENTOS DOS FILTROS
========================================================= */

document.addEventListener("change", function (e) {

    if (e.target?.id === "filter_status") {
        filtroStatus = e.target.value;
        aplicarFiltros();
    }

    if (e.target?.id === "filter_queue") {
        filtroFila = e.target.value;
        aplicarFiltros();
    }

});


/* =========================================================
INICIALIZAÇÃO E AUTO ATUALIZAÇÃO
========================================================= */

carregarTudo();

setInterval(carregarTudo, 30000); /* Ligações a cada 30s (evitar sobrecarga) */