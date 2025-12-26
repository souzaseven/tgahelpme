// ===================================================
// agente_massa.js — Ações em Massa (FINAL)
// ===================================================

let agentesCache = [];
let selecionados = new Set();
let idsParaPausar = [];

// 🔹 MAPA DE PAUSAS (ID → DESCRIÇÃO MANUAL)
const MAPA_PAUSAS = {
    1: 'Almoço',
    2: 'Lanche da manhã',
    3: 'Lanche da tarde'
};

// ===================================================
// INIT
// ===================================================
document.addEventListener('DOMContentLoaded', () => {
    carregarAgentes();
    fecharModalPausa();

    ['filtro-texto', 'filtro-fila', 'filtro-status', 'agrupamento']
        .forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', aplicarFiltros);
            el.addEventListener('change', aplicarFiltros);
        });
});

// ===================================================
// CARREGAR AGENTES
// ===================================================
async function carregarAgentes() {
    const tbody = document.getElementById('tbody-agentes');
    tbody.innerHTML = `<tr><td colspan="7" class="muted">Carregando agentes...</td></tr>`;

    try {
        const r = await fetch('backend/listar_agentes.php', { cache: 'no-store' });
        const j = await r.json();
        if (!j.success) throw new Error(j.erro);

        agentesCache = j.data?.agents || j.data || j.agentes || [];
        popularFiltros(agentesCache);
        aplicarFiltros();

    } catch (e) {
        console.error(e);
        tbody.innerHTML = `<tr><td colspan="7" class="muted">Erro ao carregar agentes</td></tr>`;
    }
}

// ===================================================
// FILTROS
// ===================================================
function popularFiltros(lista) {
    const selectFila = document.getElementById('filtro-fila');
    const filas = [...new Set(lista.map(a => a.fila || 'Sem fila'))];

    selectFila.innerHTML = '<option value="">Todas as filas</option>';
    filas.sort().forEach(f => {
        const opt = document.createElement('option');
        opt.value = f;
        opt.textContent = f;
        selectFila.appendChild(opt);
    });
}

function aplicarFiltros() {
    const texto = document.getElementById('filtro-texto').value.toLowerCase();
    const fila = document.getElementById('filtro-fila').value;
    const statusFiltro = document.getElementById('filtro-status').value;
    const agrupamento = document.getElementById('agrupamento').value;

    const filtrados = agentesCache.filter(a => {
        const nome = (a.nome || '').toLowerCase();
        const login = (a.login || '').toLowerCase();
        const status = (a.status || '').toLowerCase();

        if (texto && !nome.includes(texto) && !login.includes(texto)) return false;
        if (fila && (a.fila || 'Sem fila') !== fila) return false;
        if (statusFiltro && !status.includes(statusFiltro)) return false;

        return true;
    });

    renderizarPreviewStatus(filtrados);
    renderizarTabela(filtrados, agrupamento);
}

// ===================================================
// PREVIEW STATUS
// ===================================================
function renderizarPreviewStatus(lista) {
    let ativos = 0, pausados = 0, offline = 0;

    lista.forEach(a => {
        const s = (a.status || '').toLowerCase();
        if (s.includes('paus')) pausados++;
        else if (s.includes('off') || s.includes('deslog')) offline++;
        else ativos++;
    });

    let box = document.getElementById('preview-status');
    if (!box) {
        box = document.createElement('div');
        box.id = 'preview-status';
        box.className = 'preview-status';
        document.querySelector('.tabela').parentNode.insertBefore(box, document.querySelector('.tabela'));
    }

    box.innerHTML = `
        <strong>Ativos:</strong> ${ativos} •
        <strong>Pausados:</strong> ${pausados} •
        <strong>Offline:</strong> ${offline} •
        <strong>Total:</strong> ${lista.length}
    `;
}

// ===================================================
// RENDERIZAÇÃO
// ===================================================
function renderizarTabela(lista, agrupamento) {
    const tbody = document.getElementById('tbody-agentes');
    tbody.innerHTML = '';
    selecionados.clear();
    atualizarContador();

    if (!lista.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="muted">Nenhum agente encontrado</td></tr>`;
        return;
    }

    if (!agrupamento) {
        lista.forEach(a => tbody.appendChild(linhaAgente(a)));
        return;
    }

    const grupos = {};
    lista.forEach(a => {
        const chave = agrupamento === 'fila'
            ? (a.fila || 'Sem fila')
            : (a.status || 'Ativo');

        grupos[chave] = grupos[chave] || [];
        grupos[chave].push(a);
    });

    Object.keys(grupos).sort().forEach(g => {
        tbody.appendChild(blocoGrupo(g, grupos[g]));
    });
}

// ===================================================
// BLOCO DE GRUPO
// ===================================================
function blocoGrupo(nome, agentes) {
    const frag = document.createDocumentFragment();

    const trHeader = document.createElement('tr');
    trHeader.className = 'grupo-header';
    trHeader.innerHTML = `
        <td class="checkbox">
            <input type="checkbox" onclick="toggleGrupo(this,'${nome}')">
        </td>
        <td colspan="6">
            <strong>${nome}</strong>
            <span class="badge">${agentes.length}</span>
            <span class="toggle">▼</span>
        </td>
    `;

    trHeader.onclick = e => {
        if (e.target.tagName === 'INPUT') return;
        agentes.forEach(a => {
            document.getElementById(`agente-${a.id}`)?.classList.toggle('hidden');
        });
        trHeader.classList.toggle('collapsed');
    };

    frag.appendChild(trHeader);
    agentes.forEach(a => frag.appendChild(linhaAgente(a, nome)));
    return frag;
}

// ===================================================
// LINHA DO AGENTE
// ===================================================
function linhaAgente(a, grupo = '') {
    const tr = document.createElement('tr');
    tr.id = `agente-${a.id}`;
    tr.dataset.grupo = grupo;

    const status = (a.status || 'ativo').toLowerCase();
    const pausado = status.includes('paus');

    tr.innerHTML = `
        <td class="checkbox">
            <input type="checkbox" class="chk-agente"
                data-id="${a.id}"
                onchange="toggleSelecao('${a.id}',this)">
        </td>
        <td>${a.id}</td>
        <td>${a.nome || '-'}</td>
        <td>${a.fila || 'Sem fila'}</td>
        <td><span class="status ${status}">${a.status || 'Ativo'}</span></td>
        <td>${MAPA_PAUSAS[a.pause_reason] || '-'}</td>
        <td class="row-actions">
            ${!pausado ? `<button class="btn-warning" onclick="abrirModalPausa(['${a.id}'])">Pausar</button>` : ''}
            ${pausado ? `<button class="btn-success" onclick="acaoDireta('despausar',['${a.id}'])">Despausar</button>` : ''}
            <button class="btn-danger" onclick="acaoDireta('logoff',['${a.id}'])">Logoff</button>
        </td>
    `;
    return tr;
}

// ===================================================
// SELEÇÃO
// ===================================================
function toggleSelecao(id, chk) {
    const tr = chk.closest('tr');

    if (chk.checked) {
        selecionados.add(id);
        tr.classList.add('selected');
    } else {
        selecionados.delete(id);
        tr.classList.remove('selected');
    }

    atualizarContador();
    sincronizarMasterCheckbox();
}

function toggleTodos(master) {
    document.querySelectorAll('.chk-agente').forEach(chk => {
        chk.checked = master.checked;
        toggleSelecao(chk.dataset.id, chk);
    });
}

function toggleGrupo(master, grupo) {
    document.querySelectorAll(`tr[data-grupo="${grupo}"] .chk-agente`)
        .forEach(chk => {
            chk.checked = master.checked;
            toggleSelecao(chk.dataset.id, chk);
        });
}

function sincronizarMasterCheckbox() {
    const master = document.querySelector('thead input[type="checkbox"]');
    if (!master) return;

    const total = document.querySelectorAll('.chk-agente').length;
    const marcados = document.querySelectorAll('.chk-agente:checked').length;
    master.checked = total > 0 && total === marcados;
}

function atualizarContador() {
    const el = document.getElementById('contador-selecionados');
    if (el) el.innerText = selecionados.size;
}

// ===================================================
// MODAL PAUSA
// ===================================================
function abrirModalPausa(ids = null) {
    idsParaPausar = ids ?? [...selecionados];
    if (!idsParaPausar.length) return alert('Selecione ao menos um agente');

    document.getElementById('overlay-pausa')?.classList.remove('hidden');
    document.getElementById('modal-pausa')?.classList.remove('hidden');
}

function fecharModalPausa() {
    document.getElementById('overlay-pausa')?.classList.add('hidden');
    document.getElementById('modal-pausa')?.classList.add('hidden');
    idsParaPausar = [];
}

async function confirmarPausa() {
    const tipo = document.getElementById('pause-type').value;
    if (!tipo) return alert('Selecione o motivo da pausa');

    await executar('pausar', idsParaPausar, { pause_type: tipo });
    fecharModalPausa();
}

// ===================================================
// AÇÕES (CONTRATO EVOLUX CORRETO)
// ===================================================
async function executar(acao, ids, extra = {}) {
    if (!confirm(`Executar ${acao} em ${ids.length} agente(s)?`)) return;

    // 🔴 DESPAUSAR / LOGOFF → 1 POR VEZ
    if (acao === 'despausar' || acao === 'logoff') {
        for (const id of ids) {
            await fetch(`backend/${acao}.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ agent_id: id })
            });
        }
    }

    // 🟢 PAUSAR → MULTIPLOS
    if (acao === 'pausar') {
        await fetch(`backend/pausar.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                agent_ids: ids,
                ...extra
            })
        });
    }

    carregarAgentes();
}

function acaoDireta(acao, ids) {
    executar(acao, ids);
}

function pausarSelecionados() {
    abrirModalPausa();
}

function despausarSelecionados() {
    executar('despausar', [...selecionados]);
}

function logoffSelecionados() {
    executar('logoff', [...selecionados]);
}
