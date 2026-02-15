<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title mb-0">📡 PBX - Ramais Evolux</h2>
        <span id="totalRamaisBadge" class="badge bg-primary">0 ramais</span>
    </div>

    <div class="card-body">

        <!-- FILTROS -->
        <div class="row mb-3">

            <div class="col-md-2">
                <label class="form-label">Limite</label>
                <select id="limitRamais" class="form-control">
                    <option value="50">50</option>
                    <option value="100" selected>100</option>
                    <option value="200">200</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Buscar</label>
                <input type="text" id="buscarRamalInput"
                       class="form-control"
                       placeholder="Nome ou número..."
                       onkeyup="filtrarRamaisLocal()">
            </div>

            <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-primary me-2" onclick="carregarRamais()">
                    🔄 Atualizar
                </button>

                <button class="btn btn-outline-secondary" onclick="toggleAutoRefreshRamais()">
                    ⏱ Auto Refresh
                </button>
            </div>

        </div>

        <!-- INFO -->
        <div id="infoRamais" class="mb-2 text-muted small"></div>

        <!-- TABELA -->
        <div id="listaRamais"></div>

        <!-- PAGINAÇÃO -->
        <div id="paginacaoRamais" class="mt-3"></div>

    </div>
</div>

<script>

let paginaAtualRamais = 1;
let dadosRamais = [];
let autoRefreshInterval = null;
let colunaOrdenacao = null;
let ordemAsc = true;

document.addEventListener('DOMContentLoaded', () => {
    carregarRamais();
});

/* ============================================================
   CARREGAR RAMAIS
============================================================ */

async function carregarRamais(page = 1) {

    paginaAtualRamais = page;

    const limit = document.getElementById('limitRamais').value;

    document.getElementById('listaRamais').innerHTML =
        `<div class="text-center p-4">🔄 Carregando ramais...</div>`;

    const response = await api.post(
        `api-handler.php?action=listar_ramais&limit=${limit}&page=${page}`
    );

    if (!response.success) {
        showNotification('Erro ao carregar ramais', 'danger');
        return;
    }

    dadosRamais = response.data || [];
    const pagination = response.pagination || {};

    document.getElementById('totalRamaisBadge').innerText =
        `${pagination.total || dadosRamais.length} ramais`;

    document.getElementById('infoRamais').innerHTML =
        `Mostrando ${dadosRamais.length} de ${pagination.total || dadosRamais.length}`;

    renderizarTabelaRamais(dadosRamais);
    montarPaginacaoRamais(pagination);
}

/* ============================================================
   RENDERIZAR TABELA
============================================================ */

function renderizarTabelaRamais(ramais) {

    let html = `
        <table class="table table-dark table-hover table-bordered align-middle">
            <thead>
                <tr>
                    <th onclick="ordenarRamais('id')" style="cursor:pointer">ID ⬍</th>
                    <th onclick="ordenarRamais('name')" style="cursor:pointer">Nome ⬍</th>
                    <th onclick="ordenarRamais('number')" style="cursor:pointer">Número ⬍</th>
                    <th onclick="ordenarRamais('group_name')" style="cursor:pointer">Grupo ⬍</th>
                    <th>Regras</th>
                </tr>
            </thead>
            <tbody>
    `;

    ramais.forEach(ramal => {

        let regras = '';

        if (ramal.rules && ramal.rules.length > 0) {
            regras = ramal.rules.map(r =>
                `<span class="badge bg-info me-1">${r.name}</span>`
            ).join('');
        } else {
            regras = '<span class="badge bg-secondary">Sem regras</span>';
        }

        html += `
            <tr>
                <td>${ramal.id}</td>
                <td>${ramal.name}</td>
                <td><strong>${ramal.number}</strong></td>
                <td>${ramal.group_name || '-'}</td>
                <td>${regras}</td>
            </tr>
        `;
    });

    html += '</tbody></table>';

    document.getElementById('listaRamais').innerHTML = html;
}

/* ============================================================
   FILTRO LOCAL
============================================================ */

function filtrarRamaisLocal() {

    const termo = document.getElementById('buscarRamalInput').value.toLowerCase();

    const filtrados = dadosRamais.filter(r =>
        r.name.toLowerCase().includes(termo) ||
        r.number.includes(termo)
    );

    renderizarTabelaRamais(filtrados);
}

/* ============================================================
   ORDENAÇÃO
============================================================ */

function ordenarRamais(coluna) {

    if (colunaOrdenacao === coluna) {
        ordemAsc = !ordemAsc;
    } else {
        colunaOrdenacao = coluna;
        ordemAsc = true;
    }

    dadosRamais.sort((a, b) => {

        let valA = a[coluna] || '';
        let valB = b[coluna] || '';

        if (!isNaN(valA) && !isNaN(valB)) {
            return ordemAsc ? valA - valB : valB - valA;
        }

        valA = valA.toString().toLowerCase();
        valB = valB.toString().toLowerCase();

        if (valA < valB) return ordemAsc ? -1 : 1;
        if (valA > valB) return ordemAsc ? 1 : -1;
        return 0;
    });

    renderizarTabelaRamais(dadosRamais);
}

/* ============================================================
   PAGINAÇÃO
============================================================ */

function montarPaginacaoRamais(pagination) {

    if (!pagination.total || pagination.total <= pagination.limit) {
        document.getElementById('paginacaoRamais').innerHTML = '';
        return;
    }

    const totalPaginas = Math.ceil(pagination.total / pagination.limit);

    let html = '';

    for (let i = 1; i <= totalPaginas; i++) {
        html += `
            <button class="btn btn-sm ${i === paginaAtualRamais ? 'btn-primary' : 'btn-outline-primary'} me-1"
                onclick="carregarRamais(${i})">
                ${i}
            </button>
        `;
    }

    document.getElementById('paginacaoRamais').innerHTML = html;
}

/* ============================================================
   AUTO REFRESH
============================================================ */

function toggleAutoRefreshRamais() {

    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
        showNotification('Auto refresh desativado', 'warning');
    } else {
        autoRefreshInterval = setInterval(() => {
            carregarRamais(paginaAtualRamais);
        }, 30000);
        showNotification('Auto refresh ativado (30s)', 'success');
    }
}

</script>
