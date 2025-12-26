// ===================================================
// EDITAR AGENTE — LISTA, FILTROS, AGRUPAMENTO, MODAL
// ===================================================

let agentesCache = [];
let agenteSelecionado = null;
let agruparPorFila = true;

// ===================================================
// INIT
// ===================================================
document.addEventListener('DOMContentLoaded', () => {
    carregarAgentes();

    document
        .getElementById('form-editar-agente')
        ?.addEventListener('submit', salvarEdicao);

    // filtros reativos
    ['busca', 'filtro-fila', 'filtro-arquivados', 'filtro-offline']
        .forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;

            if (el.type === 'checkbox') {
                el.addEventListener('change', aplicarFiltros);
            } else {
                el.addEventListener('input', aplicarFiltros);
                el.addEventListener('change', aplicarFiltros);
            }
        });

    // fechar modal com ESC
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') fecharModal();
    });

    document
        .getElementById('modal-overlay')
        ?.addEventListener('click', fecharModal);

    console.log('[Editar Agente] Módulo inicializado');
});

// ===================================================
// CARREGAR AGENTES
// ===================================================
async function carregarAgentes() {
    const box = document.getElementById('lista-agentes');
    box.innerHTML = '<p class="muted">Carregando agentes...</p>';

    try {
        const r = await fetch('backend/listar_agentes.php', { cache: 'no-store' });
        const j = await r.json();

        if (!j.success) {
            throw new Error(j.erro || 'Erro ao listar agentes');
        }

        agentesCache = j.data?.agents || j.data || j.agentes || [];

        popularFiltroFilas(agentesCache);
        aplicarFiltros();

    } catch (e) {
        console.error('[Agentes] Erro:', e);
        box.innerHTML = '<p class="muted">Erro ao carregar agentes</p>';
    }
}

// ===================================================
// FILTRO DE FILAS
// ===================================================
function popularFiltroFilas(lista) {
    const select = document.getElementById('filtro-fila');
    if (!select) return;

    select.innerHTML = '<option value="">Todas as filas</option>';

    [...new Set(lista.map(a => a.fila || 'Sem fila'))]
        .sort()
        .forEach(fila => {
            const opt = document.createElement('option');
            opt.value = fila;
            opt.textContent = fila;
            select.appendChild(opt);
        });
}

// ===================================================
// APLICAR FILTROS
// ===================================================
function aplicarFiltros() {
    const termo =
        (document.getElementById('busca')?.value || '').toLowerCase();

    const filaSel =
        document.getElementById('filtro-fila')?.value || '';

    const verArquivados =
        document.getElementById('filtro-arquivados')?.checked ?? false;

    const verOffline =
        document.getElementById('filtro-offline')?.checked ?? true; // default marcado

    const filtrados = agentesCache.filter(a => {
        const nome = (a.nome || '').toLowerCase();
        const login = (a.login || '').toLowerCase();
        const status = (a.status || '').toLowerCase();

        if (!nome.includes(termo) && !login.includes(termo)) return false;
        if (filaSel && (a.fila || 'Sem fila') !== filaSel) return false;

        const arquivado = status.includes('arquiv');
        const offline = status.includes('off') || status.includes('deslog');

        if (!verArquivados && arquivado) return false;
        if (!verOffline && offline) return false;

        return true;
    });

    renderizarLista(filtrados);
    atualizarContadores(filtrados);
}

// ===================================================
// RENDERIZAÇÃO
// ===================================================
function renderizarLista(lista) {
    const box = document.getElementById('lista-agentes');
    box.innerHTML = '';

    if (!lista.length) {
        box.innerHTML = '<p class="muted">Nenhum agente encontrado</p>';
        return;
    }

    if (agruparPorFila) {
        const grupos = {};

        lista.forEach(a => {
            const fila = a.fila || 'Sem fila';
            grupos[fila] = grupos[fila] || [];
            grupos[fila].push(a);
        });

        Object.keys(grupos).sort().forEach(fila => {
            const bloco = document.createElement('div');
            bloco.className = 'grupo-fila collapsed'; // inicia fechado

            const titulo = document.createElement('h3');
            titulo.innerHTML = `
                <span>${fila}</span>
                <span class="badge">${grupos[fila].length}</span>
            `;
            titulo.onclick = () => bloco.classList.toggle('collapsed');

            bloco.appendChild(titulo);
            grupos[fila].forEach(a => bloco.appendChild(criarItemAgente(a)));

            box.appendChild(bloco);
        });

    } else {
        lista.forEach(a => box.appendChild(criarItemAgente(a)));
    }
}

// ===================================================
// ITEM DO AGENTE
// ===================================================
function criarItemAgente(a) {
    const div = document.createElement('div');
    div.className = 'agente-item';

    const statusTexto = a.status || (a.arquivado ? 'Arquivado' : 'Ativo');
    const statusLower = statusTexto.toLowerCase();

    const statusClass =
        statusLower.includes('arquiv') ? 'arquivado' :
        statusLower.includes('off') || statusLower.includes('deslog') ? 'deslogado' :
        'ativo';

    div.innerHTML = `
        <div>
            <strong>${a.nome}</strong>
            <p class="muted">${a.login}</p>
        </div>
        <span class="status ${statusClass}">
            ${statusTexto}
        </span>
    `;

    div.onclick = () => selecionarAgente(a, div);
    return div;
}

// ===================================================
// CONTADOR
// ===================================================
function atualizarContadores(lista) {
    const cont = {};

    lista.forEach(a => {
        const fila = a.fila || 'Sem fila';
        cont[fila] = (cont[fila] || 0) + 1;
    });

    document.getElementById('contador-filas').textContent =
        Object.entries(cont)
            .map(([f, q]) => `${f}: ${q}`)
            .join(' • ');
}

// ===================================================
// AGRUPAMENTO
// ===================================================
function toggleAgruparFila() {
    agruparPorFila = !agruparPorFila;
    aplicarFiltros();
}

// ===================================================
// MODAL
// ===================================================
function selecionarAgente(a, el) {
    agenteSelecionado = a;

    document.querySelectorAll('.agente-item.selected')
        .forEach(i => i.classList.remove('selected'));

    el.classList.add('selected');

    nome.value  = a.nome || '';
    login.value = a.login || '';
    fila.value  = a.fila || '';

    abrirModal();
}

function abrirModal() {
    document.getElementById('modal-edicao')?.classList.remove('hidden');
    document.getElementById('modal-overlay')?.classList.remove('hidden');
}

function fecharModal() {
    document.getElementById('modal-edicao')?.classList.add('hidden');
    document.getElementById('modal-overlay')?.classList.add('hidden');

    document.querySelectorAll('.agente-item.selected')
        .forEach(i => i.classList.remove('selected'));

    agenteSelecionado = null;
}

// ===================================================
// SALVAR (frontend OK — backend precisa PUT)
// ===================================================
async function salvarEdicao(e) {
    e.preventDefault();
    if (!agenteSelecionado) return;

    const res = document.getElementById('resultado-edicao');
    res.textContent = 'Salvando...';

    try {
        const r = await fetch('backend/editar_agente.php', {
            method: 'POST', // backend deve fazer PUT na Evolux
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: agenteSelecionado.id,
                nome: nome.value,
                login: login.value,
                fila: fila.value
            })
        });

        const j = await r.json();
        if (!j.success) throw new Error();

        Object.assign(agenteSelecionado, {
            nome: nome.value,
            login: login.value,
            fila: fila.value
        });

        aplicarFiltros();
        res.textContent = 'Alterações salvas com sucesso.';

    } catch {
        res.textContent = 'Erro ao salvar alterações.';
    }
}
