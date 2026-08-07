<?php
// ── Dados iniciais para formulários ──────────────────────────
$p = TABLE_PREFIX;

$_catsDesRaw = $pdo->query(
    "SELECT c.id, c.nome, c.cor, c.icone, c.categoria_pai
     FROM `{$p}categorias` c
     WHERE c.tipo IN ('despesa','ambos') AND c.ativo=1
     ORDER BY COALESCE((SELECT cp.nome FROM `{$p}categorias` cp WHERE cp.id=c.categoria_pai), c.nome),
              c.categoria_pai IS NOT NULL, c.nome"
)->fetchAll();

// Monta árvore para os dropdowns
$_desPais = []; $_desSubs = [];
foreach ($_catsDesRaw as $c) {
    if ($c['categoria_pai']) $_desSubs[$c['categoria_pai']][] = $c;
    else                     $_desPais[] = $c;
}
$categoriasDes = $_catsDesRaw; // mantém compatibilidade

function optGroupDes(array $pais, array $subs, string $selecionado = ''): string {
    $html = '<option value="">— Sem categoria —</option>';
    foreach ($pais as $p) {
        $sub = $subs[$p['id']] ?? [];
        $sel = $selecionado == $p['id'] ? ' selected' : '';
        if ($sub) {
            $html .= '<optgroup label="' . htmlspecialchars($p['nome']) . '">';
            foreach ($sub as $s) {
                $ss = $selecionado == $s['id'] ? ' selected' : '';
                $html .= '<option value="' . $s['id'] . '"' . $ss . '>' . htmlspecialchars($s['nome']) . '</option>';
            }
            $html .= '</optgroup>';
        } else {
            $html .= '<option value="' . $p['id'] . '"' . $sel . '>' . htmlspecialchars($p['nome']) . '</option>';
        }
    }
    return $html;
}

$contasDes = $pdo->query(
    "SELECT id, nome FROM `{$p}contas` WHERE ativo=1 ORDER BY nome"
)->fetchAll();

$cartoesDes = $pdo->query(
    "SELECT id, nome, dia_fechamento, dia_vencimento FROM `{$p}cartoes` WHERE ativo=1 ORDER BY nome"
)->fetchAll();

try {
    $respDes = $pdo->query(
        "SELECT id, nome, cor, icone FROM `{$p}responsaveis` WHERE ativo=1 ORDER BY nome"
    )->fetchAll();
} catch (PDOException $e) { $respDes = []; }

try {
    $terceirosDes = $pdo->query(
        "SELECT id, nome, cor, icone FROM `{$p}terceiros` WHERE ativo=1 ORDER BY nome"
    )->fetchAll();
} catch (PDOException $e) { $terceirosDes = []; }

$mesAtual = (int) date('m');
$anoAtual = (int) date('Y');

$nomesMeses = [
    1=>'Janeiro', 2=>'Fevereiro', 3=>'Março',    4=>'Abril',
    5=>'Maio',    6=>'Junho',     7=>'Julho',     8=>'Agosto',
    9=>'Setembro',10=>'Outubro', 11=>'Novembro', 12=>'Dezembro'
];
?>

<style>
/* Modal base e barra de filtros (base desktop) agora vivem em
   assets/css/main.css. O comportamento mobile-recolhível abaixo
   (@media max-width:768px) é específico desta página. */
/* ── Dropdown multi-select de responsável ────────────────── */
.resp-dropdown { position: relative; }
.resp-toggle {
    display: flex; align-items: center; gap: .5rem;
    cursor: pointer; min-width: 150px; text-align: left;
    background: var(--bg-700); color: var(--text-200);
    font-size: .82rem;
}
.resp-toggle:hover { border-color: var(--indigo); }
.resp-panel {
    display: none;
    position: absolute;
    top: calc(100% + 4px); left: 0;
    min-width: 210px;
    background: var(--bg-800);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    z-index: 500;
    padding: .35rem 0;
    max-height: 260px;
    overflow-y: auto;
}
.resp-panel.aberto { display: block; animation: fadeIn .12s ease; }
.resp-item {
    display: flex; align-items: center; gap: .6rem;
    padding: .45rem .875rem; cursor: pointer;
    font-size: .82rem; color: var(--text-200);
    transition: background .1s; user-select: none;
}
.resp-item:hover { background: var(--bg-700); }
.resp-item input[type=checkbox] {
    accent-color: var(--indigo);
    width: 14px; height: 14px; flex-shrink: 0; cursor: pointer;
}
.resp-avatar-sm {
    width: 20px; height: 20px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .6rem; flex-shrink: 0;
}
.resp-sep { border:none; border-top:1px solid var(--border); margin:.25rem 0; }

/* ── Lançamentos em card (mobile) ─────────────────────────── */
.cards-mobile { display: none; }
.lanc-card {
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: .75rem .875rem;
    margin-bottom: .6rem;
}
.lanc-card-top { display: flex; align-items: flex-start; gap: .6rem; }
.lanc-card-desc { flex: 1; min-width: 0; }
.lanc-card-valor { text-align: right; white-space: nowrap; flex-shrink: 0; }
.lanc-card-meta { font-size: .78rem; color: var(--text-500); margin: .45rem 0 .15rem; }
.lanc-card-extra { margin-top: .2rem; }
.lanc-card-bottom {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;
    gap: .5rem; margin-top: .6rem; padding-top: .6rem; border-top: 1px solid var(--border);
}
.lanc-card-actions { display: flex; gap: .25rem; margin-left: auto; }

/* ── Filtros recolhíveis (mobile) ──────────────────────────── */
.filters-toggle { display: none; }

@media (max-width: 768px) {
    .table-wrap, .card-footer #porPagina { display: none !important; }
    .cards-mobile { display: block; }

    .filters-toggle {
        display: flex; align-items: center; justify-content: space-between;
        width: 100%; cursor: pointer; background: none; border: none;
        color: var(--text-200); font-size: .85rem; font-weight: 600; padding: 0;
    }
    .filters-bar {
        display: none;
        grid-template-columns: 1fr;
    }
    .filters-bar.aberto { display: grid; }
    .filters-bar .filter-group { min-width: 0 !important; width: 100%; }
    .filters-bar #filtroMes { flex-wrap: wrap; }
}

/* ── Impressão: resumo estilo fatura ────────────────────── */
@page { margin: 8mm; }
@media print {
    /* Remove TODO container com altura/overflow fixo na cadeia de pais —
       cada um deles corta o conteúdo em vez de deixar a página paginar. */
    html, body,
    .layout, .main-wrapper, .content,
    .card, .table-wrap {
        display: block !important;
        width: 100% !important;
        height: auto !important;
        min-height: 0 !important;
        max-height: none !important;
        overflow: visible !important;
        padding: 0 !important;
        margin: 0 !important;
        box-shadow: none !important;
        border: none !important;
    }
    .sidebar, .header, .filters-bar, #bulkBarDes, .no-print { display: none !important; }
    body { background: #fff !important; color: #111 !important; }
    .kpi-card { background: #fff !important; border: 1px solid #ccc !important; box-shadow: none !important; }
    .kpi-card *, .card-title, .card-subtitle, .page-title, .page-sub { color: #111 !important; }
    .kpi-icon, .kpi-trend { display: none !important; }
    .card-header { border-bottom: 1px solid #ccc !important; padding: .3rem 0 !important; }

    table { border-collapse: collapse; width: 100% !important; table-layout: auto; }
    th, td { border: 1px solid #ccc; padding: .1rem .2rem; font-size: .6rem; line-height: 1.25; color: #111 !important; white-space: normal !important; }
    th { background: #f0f0f0 !important; }
    table thead th:first-child, table tbody td:first-child,
    table thead th:last-child,  table tbody td:last-child { display: none !important; }
    .badge { background: #fff !important; border: 1px solid currentColor !important; padding: .03rem .25rem !important; font-size: .58rem; }

    /* Descrição não pode truncar/forçar largura fixa na impressão */
    .truncate { white-space: normal !important; overflow: visible !important; max-width: none !important; text-overflow: clip !important; }
    td div[style*="max-width"] { max-width: none !important; }
}
</style>

<!-- ── Cabeçalho ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <div class="page-title">Despesas</div>
        <div class="page-sub" id="pageSub">Carregando...</div>
    </div>
    <button class="btn btn-primary btn-sm no-print" onclick="abrirModal()">
        <i class="fa-solid fa-plus"></i> Nova Despesa
    </button>
</div>

<?php include __DIR__ . '/despesas/_filtros.php'; ?>

<?php include __DIR__ . '/despesas/_kpis.php'; ?>

<?php include __DIR__ . '/despesas/_tabela.php'; ?>

<?php include __DIR__ . '/despesas/_modal_form.php'; ?>

<?php include __DIR__ . '/despesas/_modal_templates.php'; ?>

<?php include __DIR__ . '/despesas/_modal_antecipar.php'; ?>

<script>
// ── Estado ────────────────────────────────────────────────
let _lancamentos   = [];
let _selecionados  = new Set();
let _debounceTimer = null;
let _sortCol       = 'data';
let _sortDir       = 'desc';
let _paginaDes     = 1;
let _nomeMesDes    = '';
let _anoDes        = '';
let _totalRegistros = 0;
let _totalPaginas   = 1;

// ── Dropdown multi-select de responsável ──────────────────────
let _respSelecionados = new Set();

function _toggleRespDropdown(e) {
    if (e) e.stopPropagation();
    const panel = document.getElementById('respPanel');
    if (!panel) return;
    const aberto = panel.classList.toggle('aberto');
    if (aberto) {
        setTimeout(() => document.addEventListener('click', _fecharRespFora, { once: true }), 0);
    }
}

function _fecharRespFora(e) {
    const dd = document.getElementById('respDropdown');
    if (dd && dd.contains(e.target)) {
        setTimeout(() => document.addEventListener('click', _fecharRespFora, { once: true }), 0);
        return;
    }
    document.getElementById('respPanel')?.classList.remove('aberto');
}

function _onRespCb(cb) {
    if (cb.checked) _respSelecionados.add(cb.value);
    else            _respSelecionados.delete(cb.value);
    const todoCb = document.getElementById('cbRespTodos');
    if (todoCb) todoCb.checked = false;
    _atualizarRespLabel();
    filtroCarregar();
}

function _onRespCbTodos(cb) {
    if (cb.checked) {
        _respSelecionados.clear();
        document.querySelectorAll('.resp-cb:not(#cbRespTodos)').forEach(c => c.checked = false);
    }
    _atualizarRespLabel();
    filtroCarregar();
}

function _atualizarRespLabel() {
    const el = document.getElementById('respLabel');
    if (!el) return;
    const n = _respSelecionados.size;
    if (n === 0) { el.textContent = 'Todos'; return; }
    if (n === 1) {
        const val  = [..._respSelecionados][0];
        const span = document.querySelector(`.resp-cb[value="${val}"]`)
                         ?.closest('.resp-item')?.querySelector('span');
        el.textContent = span ? span.textContent.trim() : `1 selecionado`;
    } else {
        el.textContent = `${n} selecionados`;
    }
}

function _renderRespChips() {
    const todoCb = document.getElementById('cbRespTodos');
    if (todoCb) todoCb.checked = _respSelecionados.size === 0;
    document.querySelectorAll('.resp-cb:not(#cbRespTodos)').forEach(cb => {
        cb.checked = _respSelecionados.has(cb.value);
    });
    _atualizarRespLabel();
}

function _getRespIds() {
    return [..._respSelecionados].join(',');
}

// ── Filtros recolhíveis (mobile) ──────────────────────────
function toggleFiltrosMobile() {
    const bar  = document.getElementById('filtersBar');
    const icon = document.getElementById('filtrosToggleIcon');
    const abrir = !bar.classList.contains('aberto');
    bar.classList.toggle('aberto', abrir);
    icon.style.transform = abrir ? 'rotate(180deg)' : '';
}

// ── Modo de filtro: Mês/Ano ou Período ───────────────────
let _modoFiltro = 'mes';

function setModoFiltro(modo, carregar = true) {
    _modoFiltro = modo;
    aplicarModoFiltroUI(modo);
    if (carregar) filtroCarregar();
}

function _getDateRange() {
    if (_modoFiltro === 'periodo') {
        return { de: document.getElementById('filDe').value, ate: document.getElementById('filAte').value };
    }
    const mes = parseInt(document.getElementById('filMes').value);
    const ano = parseInt(document.getElementById('filAno').value);
    const ult = new Date(ano, mes, 0).getDate();
    const m   = String(mes).padStart(2,'0');
    return { de: `${ano}-${m}-01`, ate: `${ano}-${m}-${String(ult).padStart(2,'0')}` };
}

function _initPeriodoDatas() {
    const hoje = new Date();
    const y = hoje.getFullYear();
    const m = String(hoje.getMonth() + 1).padStart(2, '0');
    const ult = new Date(y, hoje.getMonth() + 1, 0).getDate();
    document.getElementById('filDe').value  = `${y}-${m}-01`;
    document.getElementById('filAte').value = `${y}-${m}-${String(ult).padStart(2, '0')}`;
}

// Reseta paginação e carrega — usado nos filtros
function filtroCarregar() {
    _paginaDes = 1;
    carregarDados();
}

function debounceCarregar() {
    _paginaDes = 1;
    clearTimeout(_debounceTimer);
    _debounceTimer = setTimeout(carregarDados, 400);
}

// ── Carregar dados da API ─────────────────────────────────
async function carregarDados() {
    const { de, ate } = _getDateRange();
    const cat    = document.getElementById('filCat').value;
    const status = document.getElementById('filStatus').value;
    const busca  = document.getElementById('filBusca').value.trim();

    salvarFiltro();

    if (_modoFiltro === 'periodo') {
        const fmt = d => d.split('-').reverse().join('/');
        document.getElementById('pageSub').textContent = `${fmt(de)} — ${fmt(ate)}`;
    } else {
        const mesEl = document.getElementById('filMes');
        const ano2  = document.getElementById('filAno').value;
        document.getElementById('pageSub').textContent = `${mesEl.options[mesEl.selectedIndex].text} de ${ano2}`;
    }

    const nomeMes = _modoFiltro === 'mes' ? document.getElementById('filMes').options[document.getElementById('filMes').selectedIndex].text : '';
    const ano     = _modoFiltro === 'mes' ? document.getElementById('filAno').value : '';

    const respIds = _getRespIds();
    const conta   = document.getElementById('filConta')?.value    || '';
    const cartaoF = document.getElementById('filCartaoF')?.value  || '';
    const valMin  = document.getElementById('filValMin')?.value   || '';
    const valMax  = document.getElementById('filValMax')?.value   || '';
    const tag     = document.getElementById('filTag')?.value.trim() || '';
    _atualizarBadgeFiltrosAvancados();

    // Paginação server-side
    const porPagina = parseInt(document.getElementById('porPagina')?.value || '50');
    const params = new URLSearchParams({ de, ate });
    if (cat)     params.set('categoria_id', cat);
    if (status)  params.set('status', status);
    if (busca)   params.set('busca', busca);
    if (respIds) params.set('responsaveis', respIds);
    if (conta)   params.set('conta_id', conta);
    if (cartaoF)params.set('cartao_id', cartaoF);
    if (valMin) params.set('valor_min', valMin);
    if (valMax) params.set('valor_max', valMax);
    if (tag)    params.set('tag', tag);
    params.set('pagina',    _paginaDes);
    params.set('por_pagina', porPagina);

    // Skeleton na tabela
    document.getElementById('tabelaBody').innerHTML = _skeletonRows(8);
    // Skeleton nos KPIs
    ['kpiTotal','kpiPago','kpiPendente','kpiQtd'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = '<div class="skel skel-kpi"></div>';
    });

    try {
        const res  = await fetch('backend/api/despesas.php?' + params);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro || 'Erro ao buscar dados');
        _lancamentos    = json.dados;
        _nomeMesDes     = _modoFiltro === 'mes' ? nomeMes : de;
        _anoDes         = _modoFiltro === 'mes' ? ano : ate;
        _totalRegistros = json.total || 0;
        _totalPaginas   = json.total_paginas || 1;
        atualizarKPIs(json.kpi);
        renderTabela(json.dados, nomeMes, ano);
    } catch (err) {
        document.getElementById('tabelaBody').innerHTML =
            `<tr><td colspan="9" style="text-align:center;padding:2rem;color:var(--rose)">
                <i class="fa-solid fa-triangle-exclamation"></i> ${esc(err.message)}
            </td></tr>`;
        toast('Erro ao carregar: ' + err.message, 'error');
    }
}

// ── KPIs ──────────────────────────────────────────────────
function atualizarKPIs(kpi) {
    document.getElementById('kpiTotal').textContent    = brl(kpi.total    || 0);
    document.getElementById('kpiPago').textContent     = brl(kpi.pago     || 0);
    document.getElementById('kpiPendente').textContent = brl(kpi.pendente || 0);
    document.getElementById('kpiQtd').textContent      = kpi.qtd || 0;
    atualizarTrendTotal(kpi.total || 0, kpi.total_anterior);
}

// Compara o total do período com o período anterior de mesma duração (o
// backend já resolve isso, aqui só formata). Numa despesa, gastar mais que
// antes é "ruim" (vermelho) — o oposto de receita, por isso .bad/.good em
// vez de reusar .up/.down (que têm o significado invertido).
function atualizarTrendTotal(total, totalAnterior) {
    const el = document.getElementById('kpiTotalTrend');
    if (!el) return;
    if (totalAnterior === undefined || totalAnterior === null || totalAnterior <= 0) {
        el.className = 'kpi-trend neutral';
        el.innerHTML = '<i class="fa-solid fa-circle-info fa-xs"></i> Todas as despesas';
        return;
    }
    const dif = total - totalAnterior;
    const pct = Math.abs(dif / totalAnterior * 100);
    if (Math.abs(dif) < 0.005) {
        el.className = 'kpi-trend neutral';
        el.innerHTML = '<i class="fa-solid fa-minus fa-xs"></i> Igual ao período anterior';
        return;
    }
    const subiu = dif > 0;
    el.className = `kpi-trend ${subiu ? 'bad' : 'good'}`;
    el.innerHTML = `<i class="fa-solid fa-arrow-trend-${subiu ? 'up' : 'down'} fa-xs"></i> ${pct.toFixed(1)}% vs. período anterior`;
}

// ── Filtros secundários (recolhíveis) ──────────────────────
function toggleFiltrosAvancados() {
    const sec = document.getElementById('filtrosSecundarios');
    const btn = document.getElementById('btnMaisFiltros');
    if (!sec || !btn) return;
    const aberto = sec.classList.toggle('open');
    // Mesmo fechado, o botão continua "aceso" se algum filtro secundário
    // seguir ativo — senão o usuário perde de vista que há filtro escondido.
    btn.classList.toggle('active', aberto || _contarFiltrosAvancados() > 0);
}

function _contarFiltrosAvancados() {
    const conta   = document.getElementById('filConta')?.value    || '';
    const cartaoF = document.getElementById('filCartaoF')?.value  || '';
    const valMin  = document.getElementById('filValMin')?.value   || '';
    const valMax  = document.getElementById('filValMax')?.value   || '';
    const tag     = document.getElementById('filTag')?.value.trim() || '';
    return [conta, cartaoF, valMin, valMax, tag].filter(v => v !== '').length;
}

function _atualizarBadgeFiltrosAvancados() {
    const badge = document.getElementById('maisFiltrosBadge');
    const btn   = document.getElementById('btnMaisFiltros');
    if (!badge || !btn) return;
    const n = _contarFiltrosAvancados();
    badge.textContent   = n;
    badge.style.display = n > 0 ? '' : 'none';
    const sec = document.getElementById('filtrosSecundarios');
    btn.classList.toggle('active', n > 0 || !!sec?.classList.contains('open'));
}

// ── Ordenação da tabela ──────────────────────────────────
function sortarTabela(col) {
    _sortDir = (_sortCol === col && _sortDir === 'desc') ? 'asc' : 'desc';
    if (_sortCol !== col && col === 'data') _sortDir = 'desc';
    _sortCol = col;
    _paginaDes = 1;

    document.querySelectorAll('thead th.sortable').forEach(th => {
        const isAtivo = th.getAttribute('onclick') === `sortarTabela('${col}')`;
        th.classList.toggle('sort-ativo', isAtivo);
        const ic = th.querySelector('.sort-ic');
        if (ic) ic.className = isAtivo
            ? `fa-solid fa-arrow-${_sortDir === 'asc' ? 'up' : 'down'} fa-xs sort-ic`
            : 'fa-solid fa-arrows-up-down fa-xs sort-ic';
    });

    // Ordena localmente os dados da página atual e re-renderiza sem nova requisição
    renderTabela(_lancamentos, _nomeMesDes, _anoDes);
}

function sortarDados(dados) {
    return [...dados].sort((a, b) => {
        let va = a[_sortCol] ?? '';
        let vb = b[_sortCol] ?? '';
        if (_sortCol === 'valor') {
            va = parseFloat(va) || 0;
            vb = parseFloat(vb) || 0;
        } else if (_sortCol === 'data') {
            va = va ? new Date(va).getTime() : 0;
            vb = vb ? new Date(vb).getTime() : 0;
        } else {
            va = String(va).toLowerCase();
            vb = String(vb).toLowerCase();
        }
        if (va < vb) return _sortDir === 'asc' ? -1 : 1;
        if (va > vb) return _sortDir === 'asc' ?  1 : -1;
        return 0;
    });
}

// ── Tabela ────────────────────────────────────────────────
function renderTabela(dados, nomeMes, ano) {
    dados = sortarDados(dados);
    const total    = _totalRegistros;
    const porPag   = parseInt(document.getElementById('porPagina')?.value || '50');
    const totPags  = _totalPaginas;
    const ini      = (_paginaDes - 1) * porPag;

    const tbody = document.getElementById('tabelaBody');
    document.getElementById('tabelaInfo').textContent =
        `${total} lançamento${total !== 1 ? 's' : ''} — ${nomeMes}/${ano}`;

    // Controles de paginação server-side
    const infoEl = document.getElementById('pagInfo');
    const btnsEl = document.getElementById('pagBtns');
    if (infoEl) infoEl.textContent = total > 0
        ? `Exibindo ${ini + 1}–${Math.min(_paginaDes * porPag, total)} de ${total}`
        : 'Nenhum registro';
    if (btnsEl) {
        if (totPags <= 1) { btnsEl.innerHTML = ''; }
        else {
            const ir = p => { _paginaDes = p; carregarDados(); };
            let h = `<button class="btn btn-ghost btn-sm" onclick="(${ir})(1)" ${_paginaDes===1?'disabled':''}>«</button>`;
            h    += `<button class="btn btn-ghost btn-sm" onclick="(${ir})(${_paginaDes-1})" ${_paginaDes===1?'disabled':''}>‹</button>`;
            const s = Math.max(1, _paginaDes-2), e = Math.min(totPags, _paginaDes+2);
            if (s > 1) h += `<span class="text-muted text-sm pag-ellipsis">…</span>`;
            for (let p = s; p <= e; p++)
                h += `<button class="btn btn-sm ${p===_paginaDes?'btn-primary':'btn-ghost'}" onclick="(${ir})(${p})">${p}</button>`;
            if (e < totPags) h += `<span class="text-muted text-sm pag-ellipsis">…</span>`;
            h += `<button class="btn btn-ghost btn-sm" onclick="(${ir})(${_paginaDes+1})" ${_paginaDes===totPags?'disabled':''}>›</button>`;
            h += `<button class="btn btn-ghost btn-sm" onclick="(${ir})(${totPags})" ${_paginaDes===totPags?'disabled':''}>»</button>`;
            btnsEl.innerHTML = h;
        }
    }

    // dados já vem paginado do servidor — usa diretamente
    const paginados = dados;

    if (!paginados.length) {
        const _filAtivo = (document.getElementById('filBusca')?.value.trim() || '') +
            (document.getElementById('filCat')?.value  || '') +
            (document.getElementById('filStatus')?.value || '');
        tbody.innerHTML = `<tr><td colspan="9" class="empty-state lg">
            <i class="fa-solid fa-receipt fa-3x empty-state-icon"></i>
            <div class="empty-state-title">
                ${_filAtivo ? 'Nenhum resultado encontrado' : 'Nenhuma despesa neste período'}
            </div>
            <div class="empty-state-sub">
                ${_filAtivo ? 'Tente remover os filtros ou ampliar o período de busca.' : 'Registre sua primeira despesa do mês e mantenha o controle.'}
            </div>
            ${!_filAtivo ? `<button class="btn btn-primary btn-sm" onclick="abrirModal()">
                <i class="fa-solid fa-plus"></i> Adicionar despesa
            </button>` : `<button class="btn btn-ghost btn-sm" onclick="document.getElementById('filBusca').value='';document.getElementById('filCat').value='';document.getElementById('filStatus').value='';carregarDados()">
                <i class="fa-solid fa-filter-slash"></i> Limpar filtros
            </button>`}
        </td></tr>`;
        document.getElementById('cardsMobile').innerHTML = '';
        return;
    }

    _selecionados.clear();
    atualizarBulkBar();
    const cbAll = document.getElementById('cbTodos');
    if (cbAll) cbAll.checked = false;

    const cardsHtml = [];
    tbody.innerHTML = paginados.map(tx => {
        const catLabel = tx.cat_pai_nome
            ? `${esc(tx.cat_pai_nome)} <span style="opacity:.4;font-weight:400">|</span> ${esc(tx.cat_nome)}`
            : esc(tx.cat_nome || '');
        const catBadge = tx.cat_nome
            ? `<span class="badge" style="background:${tx.cat_cor||'#334155'}22;color:${tx.cat_cor||'#94a3b8'}">${catLabel}</span>`
            : '<span class="text-muted text-xs">—</span>';

        let contaCartao = '<span class="text-muted text-xs">—</span>';
        if (tx.cartao_nome) {
            contaCartao = `<span class="text-sm"><i class="fa-solid fa-credit-card fa-xs" style="color:var(--indigo);margin-right:.3rem"></i>${esc(tx.cartao_nome)}</span>`;
        } else if (tx.conta_nome) {
            contaCartao = `<span class="text-sm text-muted">${esc(tx.conta_nome)}</span>`;
        }

        const parcelas = tx.parcela_total
            ? `<div class="text-xs text-muted">${tx.parcela_atual}/${tx.parcela_total} parcelas</div>` : '';
        const divisaoBadge = tx.grupo_id
            ? `<div class="text-xs" style="color:var(--indigo)"><i class="fa-solid fa-people-arrows fa-xs"></i> divisão</div>` : '';

        const tagsBadges = tx.tags
            ? tx.tags.split(',').filter(t => t.trim()).map(t =>
                `<span style="display:inline-block;background:rgba(99,102,241,.15);color:var(--indigo);border-radius:3px;padding:.05rem .35rem;font-size:.65rem;font-weight:600;margin:.1rem .1rem 0 0">${esc(t.trim())}</span>`
              ).join('')
            : '';
        const comprovanteBadge = tx.comprovante_path
            ? `<a href="${esc(tx.comprovante_path)}" target="_blank" rel="noopener" title="Ver comprovante" onclick="event.stopPropagation()"
                   style="display:inline-block;color:var(--text-500);margin-top:.15rem"><i class="fa-solid fa-paperclip fa-xs"></i></a>`
            : '';

        // ── Detecção de parcela de empréstimo ─────────────────
        const hoje       = new Date().toISOString().split('T')[0];
        const isEmp      = !!tx.parcela_id;
        const foiAntecip = isEmp && tx.parcela_status === 'antecipado';
        const podeAntec  = isEmp && tx.parcela_vencimento && tx.parcela_vencimento > hoje && tx.status === 'pendente';

        // Badge de empréstimo + comparativo de antecipação
        let empBadge = '';
        if (isEmp) {
            const parcelaLabel = `<div class="text-xs" style="color:var(--amber);margin-top:.15rem">
                <i class="fa-solid fa-landmark fa-xs"></i>
                Parcela ${tx.parcela_numero}/${tx.emp_total_parcelas}
                ${foiAntecip ? '<span style="background:rgba(245,158,11,.18);padding:.05rem .35rem;border-radius:3px;margin-left:.25rem">⚡ Antecipado</span>' : ''}
            </div>`;

            let comparativo = '';
            if (foiAntecip && tx.valor_original_parcela) {
                const orig  = parseFloat(tx.valor_original_parcela);
                const pago  = parseFloat(tx.valor);
                const econ  = +(orig - pago).toFixed(2);

                if (econ > 0.005) {
                    comparativo = `
                    <div style="margin-top:.3rem;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);
                                border-radius:4px;padding:.3rem .55rem;display:inline-block">
                        <div class="text-xs" style="color:var(--emerald);font-weight:700">
                            💰 Economia: ${brl(econ)}
                        </div>
                        <div class="text-xs text-muted" style="margin-top:.1rem">
                            Pagou ${brl(pago)} · Pagaria ${brl(orig)}
                        </div>
                    </div>`;
                } else if (Math.abs(econ) <= 0.005) {
                    comparativo = `<div class="text-xs text-muted" style="margin-top:.15rem">Valor igual ao original — sem desconto</div>`;
                }
            }
            empBadge = parcelaLabel + comparativo;
        }

        // ── Cor do valor: neutro se já pago (o badge de status já avisa);
        // só chama atenção quando ainda pesa no bolso — âmbar se pendente
        // dentro do prazo, vermelho se pendente e já venceu.
        let valorClass = 'fw-600';
        if (tx.status === 'cancelado') {
            valorClass += ' text-muted';
        } else if (tx.status === 'pendente') {
            const vencRef = tx.data_vencimento || tx.data;
            valorClass += (vencRef && vencRef < hoje) ? ' text-rose' : ' text-amber';
        }

        // ── Coluna de valor: risca o original se houve desconto ─
        let valorCell = `<span class="${valorClass}">${brl(tx.valor)}</span>`;
        if (foiAntecip && tx.valor_original_parcela) {
            const orig = parseFloat(tx.valor_original_parcela);
            const pago = parseFloat(tx.valor);
            if (orig - pago > 0.005) {
                valorCell = `
                    <div class="${valorClass}">${brl(pago)}</div>
                    <div class="text-xs text-muted" style="text-decoration:line-through">${brl(orig)}</div>`;
            }
        }

        // ── Botão de ação ──────────────────────────────────────
        let btnPago = '';
        if (tx.status !== 'pago') {
            if (isEmp) {
                const cor   = podeAntec ? 'var(--amber)'   : 'var(--emerald)';
                const icone = podeAntec ? 'fa-forward'     : 'fa-circle-check';
                const title = podeAntec ? 'Antecipar pagamento' : 'Pagar parcela';
                btnPago = `<button class="btn-icon avatar-sm" onclick="abrirAntecipar(${+tx.id})" title="${title}"
                                   style="color:${cor}">
                               <i class="fa-solid ${icone}"></i>
                           </button>`;
            } else {
                btnPago = `<button class="btn-icon avatar-sm" onclick="marcarPago(${+tx.id})" title="Marcar como pago"
                                   style="color:var(--emerald)">
                               <i class="fa-solid fa-circle-check"></i>
                           </button>`;
            }
        }

        const pessoaBadge = (() => {
            if (tx.terceiro_nome) {
                const c = tx.terceiro_cor || '#f59e0b';
                return `<span style="display:inline-flex;align-items:center;gap:.3rem;font-size:.72rem;font-weight:600;padding:.15rem .5rem;border-radius:999px;background:${c}22;color:${c}">
                    <i class="fa-solid fa-${tx.terceiro_icone||'handshake'} fa-xs"></i>${esc(tx.terceiro_nome)}
                    <span style="font-size:.6rem;opacity:.7">3º</span></span>`;
            }
            if (tx.resp_nome) {
                const c = tx.resp_cor || '#6366f1';
                return `<span style="display:inline-flex;align-items:center;gap:.3rem;font-size:.72rem;font-weight:600;padding:.15rem .5rem;border-radius:999px;background:${c}22;color:${c}">
                    <i class="fa-solid fa-${tx.resp_icone||'user'} fa-xs"></i>${esc(tx.resp_nome)}</span>`;
            }
            return '<span class="text-muted text-xs">—</span>';
        })();
        const respBadge = pessoaBadge;

        const vencimentoHtml = tx.cartao_id && tx.fat_mes
            ? `<div style="font-size:.63rem;color:var(--indigo);white-space:nowrap;line-height:1.4">
                   <i class="fa-solid fa-calendar-check fa-xs"></i>
                   Fat. ${_MESES_ABREV[+tx.fat_mes - 1]}/${String(tx.fat_ano || '').slice(-2)}
                   ${tx.fat_vencimento ? `· vence ${fmtData(tx.fat_vencimento)}` : ''}
               </div>`
            : tx.data_vencimento && tx.data_vencimento !== tx.data
                ? `<div style="font-size:.63rem;color:var(--amber);white-space:nowrap">
                       <i class="fa-solid fa-calendar-exclamation fa-xs"></i> vence ${fmtData(tx.data_vencimento)}
                   </div>`
                : '';

        const acoesHtml = `${btnPago}
            <button class="btn-icon avatar-sm" onclick="abrirModal(${+tx.id})" title="Editar">
                <i class="fa-solid fa-pen-to-square"></i>
            </button>
            <button class="btn-icon avatar-sm" onclick="excluirDespesa(${+tx.id})" title="Excluir"
                    style="color:var(--rose)">
                <i class="fa-solid fa-trash"></i>
            </button>`;

        cardsHtml.push(`<div class="lanc-card">
            <div class="lanc-card-top">
                <div class="tx-icon" style="background:var(--rose-soft);color:var(--rose)">
                    <i class="fa-solid fa-arrow-down fa-xs"></i>
                </div>
                <div class="lanc-card-desc">
                    <div class="fw-600 text-sm">${esc(tx.descricao)}</div>
                    <div class="lanc-card-meta">${catBadge} · ${contaCartao}</div>
                </div>
                <div class="lanc-card-valor">${valorCell}</div>
            </div>
            <div class="lanc-card-extra">
                ${parcelas}${empBadge}${divisaoBadge}${tagsBadges}${comprovanteBadge}
            </div>
            <div class="lanc-card-meta">
                <i class="fa-solid fa-calendar fa-xs"></i> ${fmtData(tx.data)}
                ${vencimentoHtml}
                ${respBadge !== '<span class="text-muted text-xs">—</span>' ? `<div style="margin-top:.3rem">${respBadge}</div>` : ''}
            </div>
            <div class="lanc-card-bottom">
                <span class="badge ${esc(tx.status)}">${foiAntecip ? '⚡ Antecipado' : ucFirst(tx.status)}</span>
                <div class="lanc-card-actions">${acoesHtml}</div>
            </div>
        </div>`);

        return `<tr>
            <td style="padding-right:0;width:36px">
                <input type="checkbox" class="row-cb" data-id="${+tx.id}"
                       style="accent-color:var(--indigo);width:15px;height:15px;cursor:pointer"
                       onchange="onChangeCb(this)">
            </td>
            <td>
                <div class="d-flex align-center gap-1">
                    <div class="tx-icon" style="background:var(--rose-soft);color:var(--rose)">
                        <i class="fa-solid fa-arrow-down fa-xs"></i>
                    </div>
                    <div>
                        <div class="fw-600 text-sm truncate" style="max-width:220px">${esc(tx.descricao)}</div>
                        ${parcelas}${empBadge}${divisaoBadge}${tagsBadges}${comprovanteBadge}
                    </div>
                </div>
            </td>
            <td>${catBadge}</td>
            <td>${contaCartao}</td>
            <td>${respBadge}</td>
            <td class="text-sm text-muted">${fmtData(tx.data)}${vencimentoHtml}</td>
            <td><span class="badge ${esc(tx.status)}">${foiAntecip ? '⚡ Antecipado' : ucFirst(tx.status)}</span></td>
            <td class="text-right">${valorCell}</td>
            <td>
                <div class="d-flex gap-1 row-actions" style="justify-content:flex-end">
                    ${acoesHtml}
                </div>
            </td>
        </tr>`;
    }).join('');

    document.getElementById('cardsMobile').innerHTML = cardsHtml.join('');
}

// ── Seleção em lote ───────────────────────────────────────
function onChangeCb(cb) {
    const id = +cb.dataset.id;
    if (cb.checked) _selecionados.add(id);
    else            _selecionados.delete(id);
    atualizarBulkBar();
    const total = document.querySelectorAll('.row-cb').length;
    const cbAll = document.getElementById('cbTodos');
    if (cbAll) cbAll.checked = _selecionados.size === total && total > 0;
}

function toggleTodos(el) {
    document.querySelectorAll('.row-cb').forEach(cb => {
        cb.checked = el.checked;
        const id = +cb.dataset.id;
        if (el.checked) _selecionados.add(id);
        else            _selecionados.delete(id);
    });
    atualizarBulkBar();
}

function atualizarBulkBar() {
    const bar   = document.getElementById('bulkBarDes');
    const count = document.getElementById('bulkCountDes');
    if (!bar) return;
    if (_selecionados.size > 0) {
        bar.style.display = 'flex';
        count.textContent = `${_selecionados.size} selecionado${_selecionados.size > 1 ? 's' : ''}`;
    } else {
        bar.style.display = 'none';
    }
}

function limparSelecao() {
    _selecionados.clear();
    document.querySelectorAll('.row-cb').forEach(cb => cb.checked = false);
    const cbAll = document.getElementById('cbTodos');
    if (cbAll) cbAll.checked = false;
    atualizarBulkBar();
}

async function excluirSelecionados() {
    if (!_selecionados.size) return;
    const n = _selecionados.size;
    confirmar(
        `Excluir ${n} lançamento${n > 1 ? 's' : ''}`,
        'Esta ação não pode ser desfeita.',
        async () => {
            try {
                const res  = await fetch('backend/api/despesas.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ acao: 'excluir_bulk', ids: [..._selecionados] }),
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.erro || 'Erro ao excluir');
                toast(json.msg, 'success');
                _selecionados.clear();
                carregarDados();
            } catch (err) {
                toast('Erro: ' + err.message, 'error');
            }
        }
    );
}

function _bulkToggle(cb) {
    (cb.dataset.target || '').split(',').forEach(id => {
        const el = document.getElementById(id);
        if (el) el.disabled = !cb.checked;
    });
}

function abrirModalBulkEditDes() {
    if (!_selecionados.size) return;
    document.getElementById('modalBulkEditDes').style.display = 'flex';
}

function fecharModalBulkEditDes() {
    document.getElementById('modalBulkEditDes').style.display = 'none';
    document.querySelectorAll('#modalBulkEditDes .bulk-toggle').forEach(cb => {
        cb.checked = false;
        _bulkToggle(cb);
    });
}

async function confirmarEditarBulkDes() {
    const campos = {};

    if (document.getElementById('bkChkStatusDes').checked)
        campos.status = document.getElementById('bkStatusDes').value;

    if (document.getElementById('bkChkCatDes').checked)
        campos.categoria_id = document.getElementById('bkCatDes').value || null;

    if (document.getElementById('bkChkContaDes').checked)
        campos.conta_id = document.getElementById('bkContaDes').value || null;

    if (document.getElementById('bkChkCartaoDes').checked)
        campos.cartao_id = document.getElementById('bkCartaoDes').value || null;

    const chkResp = document.getElementById('bkChkRespDes');
    if (chkResp && chkResp.checked) {
        const v = document.getElementById('bkRespDes').value;
        if (v.startsWith('tcr_')) { campos.terceiro_id = +v.slice(4); campos.responsavel_id = null; }
        else if (v)               { campos.responsavel_id = +v; campos.terceiro_id = null; }
        else                      { campos.responsavel_id = null; campos.terceiro_id = null; }
    }

    if (document.getElementById('bkChkVencDes').checked)
        campos.data_vencimento = document.getElementById('bkDataVencDes').value || null;

    if (document.getElementById('bkChkTagsDes').checked)
        campos.tags = { modo: document.getElementById('bkTagsModoDes').value, valor: document.getElementById('bkTagsDes').value.trim() };

    if (document.getElementById('bkChkObsDes').checked)
        campos.observacao = document.getElementById('bkObsDes').value.trim();

    if (!Object.keys(campos).length) {
        toast('Marque ao menos um campo para alterar.', 'error');
        return;
    }

    fecharModalBulkEditDes();
    try {
        const res  = await fetch('backend/api/despesas.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ acao: 'editar_bulk', ids: [..._selecionados], campos }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro || 'Erro ao alterar');
        toast(json.msg, 'success');
        _selecionados.clear();
        carregarDados();
    } catch (err) {
        toast('Erro: ' + err.message, 'error');
    }
}

// ── Preenchimento automático por descrição ──────────────────
// Atalhos cadastrados em Configurações > Atalhos (backend/api/atalhos.php):
// ao sair do campo Descrição com um texto conhecido, preenche
// valor/categoria/conta/cartão/responsável/observação com o padrão salvo.
let _atalhosDespesa = [];

async function carregarAtalhosDespesa() {
    try {
        const res  = await fetch('backend/api/atalhos.php?tipo=despesa');
        const json = await res.json();
        if (json.success) _atalhosDespesa = json.dados;
    } catch (_) { /* atalho é um bônus — falha silenciosa não deve travar o formulário */ }
}

function _normTxt(s) {
    return String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}

function aplicarAutoPreenchimentoDespesa() {
    const desc  = _normTxt(document.getElementById('fDesc')?.value);
    const regra = _atalhosDespesa.find(a => +a.ativo && _normTxt(a.descricao_chave) === desc);
    if (!regra) return;

    if (regra.valor !== null)  document.getElementById('fValor').value = brlMask(regra.valor);
    if (regra.categoria_id)    document.getElementById('fCat').value   = regra.categoria_id;
    if (regra.conta_id)        document.getElementById('fConta').value = regra.conta_id;
    if (regra.observacao)      document.getElementById('fObs').value   = regra.observacao;

    if (regra.cartao_id && document.getElementById('fCartao')) {
        document.getElementById('fCartao').value = regra.cartao_id;
        _faturaManual = null;
        atualizarInfoFatura();
    }
    if (document.getElementById('fResp')) {
        if (regra.terceiro_id)         document.getElementById('fResp').value = 'tcr_' + regra.terceiro_id;
        else if (regra.responsavel_id) document.getElementById('fResp').value = regra.responsavel_id;
    }
}
document.getElementById('fDesc')?.addEventListener('blur', aplicarAutoPreenchimentoDespesa);

// ── Modal ─────────────────────────────────────────────────
function abrirModal(id = null) {
    document.getElementById('formDespesa').reset();
    document.getElementById('editId').value  = id || '';
    document.getElementById('fData').value   = new Date().toISOString().split('T')[0];
    document.getElementById('fStatus').value = 'pago';
    document.getElementById('parcelasWrap').style.display = id ? 'none' : '';
    // Reset divisão
    const divToggle = document.getElementById('fDivisaoToggle');
    const divWrap   = document.getElementById('divisaoWrap');
    if (divToggle) { divToggle.checked = false; }
    if (divWrap)   { divWrap.style.display = 'none'; }
    if (document.getElementById('fResp')) document.getElementById('fResp').disabled = false;
    document.querySelectorAll('.divisao-cb').forEach(cb => cb.checked = false);
    // Reset data de vencimento
    const dvf = document.getElementById('fDataVenc');
    if (dvf) dvf.value = '';
    document.getElementById('fDataVencBadge')?.style && (document.getElementById('fDataVencBadge').style.display = 'none');
    document.getElementById('fDataVencHint')?.style  && (document.getElementById('fDataVencHint').style.display  = '');

    // Reset do comprovante
    document.getElementById('fComprovantePath').value = '';
    renderComprovantePreview(document.getElementById('fComprovantePreview'), null);

    if (id) {
        document.getElementById('modalTitulo').textContent = 'Editar Despesa';
        const tx = _lancamentos.find(t => +t.id === +id);
        if (tx) {
            document.getElementById('fDesc').value   = tx.descricao    || '';
            document.getElementById('fValor').value  = brlMask(tx.valor || 0);
            document.getElementById('fData').value   = (tx.data || '').split('T')[0];
            document.getElementById('fStatus').value = tx.status       || 'pendente';
            document.getElementById('fCat').value    = tx.categoria_id   || '';
            document.getElementById('fConta').value  = tx.conta_id       || '';
            document.getElementById('fCartao').value = tx.cartao_id      || '';
            if (document.getElementById('fResp'))
                document.getElementById('fResp').value = tx.terceiro_id
                    ? 'tcr_' + tx.terceiro_id
                    : (tx.responsavel_id || '');
            document.getElementById('fObs').value    = tx.observacao      || '';
            document.getElementById('fTags').value   = tx.tags || '';
            const dvfEdit = document.getElementById('fDataVenc');
            if (dvfEdit) dvfEdit.value = (tx.data_vencimento || '').split('T')[0];
            if (tx.comprovante_path) {
                document.getElementById('fComprovantePath').value = tx.comprovante_path;
                renderComprovantePreview(document.getElementById('fComprovantePreview'), tx.comprovante_path);
            }
        }
    } else {
        document.getElementById('modalTitulo').textContent = 'Nova Despesa';
        document.getElementById('fTags').value = '';
        const last = JSON.parse(localStorage.getItem('financeos_despesa_last') || '{}');
        if (last.categoria_id !== undefined) document.getElementById('fCat').value    = last.categoria_id;
        if (last.conta_id     !== undefined) document.getElementById('fConta').value  = last.conta_id;
        if (last.cartao_id    !== undefined) document.getElementById('fCartao').value = last.cartao_id;
        if (last.status       !== undefined) document.getElementById('fStatus').value = last.status;
        if (last.resp && document.getElementById('fResp')) document.getElementById('fResp').value = last.resp;
    }

    _faturaManual = null;
    atualizarInfoFatura();
    document.getElementById('modalOverlay').classList.add('open');
    setTimeout(() => document.getElementById('fDesc').focus(), 80);
}

function fecharModal() {
    document.getElementById('modalOverlay').classList.remove('open');
}

// ── Salvar ────────────────────────────────────────────────
async function salvarDespesa(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Salvando...';

    const id       = document.getElementById('editId').value;
    const parcelas = parseInt(document.getElementById('fParcelas').value) || 1;

    // Divisão de despesas
    const divisaoAtiva = document.getElementById('fDivisaoToggle')?.checked && !id;
    if (divisaoAtiva) {
        const selecionados = [...document.querySelectorAll('.divisao-cb:checked')].map(cb => cb.value);
        if (selecionados.length < 2) {
            toast('Selecione pelo menos 2 responsáveis para dividir.', 'warning');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar';
            return;
        }
    }

    const _fRespVal = document.getElementById('fResp')?.value || '';
    const _isTcr    = _fRespVal.startsWith('tcr_');
    const payload = {
        descricao:      document.getElementById('fDesc').value.trim(),
        valor:          parseCurrency(document.getElementById('fValor').value),
        data:           document.getElementById('fData').value,
        categoria_id:   document.getElementById('fCat').value    || null,
        conta_id:       document.getElementById('fConta').value  || null,
        cartao_id:      document.getElementById('fCartao').value || null,
        responsavel_id: !_isTcr && _fRespVal ? parseInt(_fRespVal) : null,
        terceiro_id:    _isTcr ? parseInt(_fRespVal.replace('tcr_', '')) : null,
        status:         document.getElementById('fStatus').value,
        observacao:     document.getElementById('fObs').value.trim(),
        tags:           document.getElementById('fTags').value.trim() || null,
        mes_fatura:      parseInt(document.getElementById('fMesFatura').value) || null,
        ano_fatura:      parseInt(document.getElementById('fAnoFatura').value)  || null,
        data_vencimento: document.getElementById('fDataVenc').value || null,
        comprovante_path: document.getElementById('fComprovantePath').value || null,
    };

    if (id) {
        payload.id = parseInt(id);
    } else if (divisaoAtiva) {
        payload.divisao = [...document.querySelectorAll('.divisao-cb:checked')].map(cb => cb.value);
    } else {
        payload.parcela_total = parcelas;
    }

    try {
        const res  = await fetch('backend/api/despesas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro || 'Erro ao salvar');
        if (!id) {
            localStorage.setItem('financeos_despesa_last', JSON.stringify({
                categoria_id: document.getElementById('fCat').value    || '',
                conta_id:     document.getElementById('fConta').value  || '',
                cartao_id:    document.getElementById('fCartao').value || '',
                status:       document.getElementById('fStatus').value,
                resp:         document.getElementById('fResp')?.value || '',
            }));
        }
        toast(json.msg || 'Salvo com sucesso!', 'success');
        fecharModal();
        carregarDados();
    } catch (err) {
        toast('Erro: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar';
    }
}

// ── Marcar como pago ─────────────────────────────────────
async function marcarPago(id) {
    try {
        const res  = await fetch('backend/api/despesas.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ acao: 'marcar_pago', id }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        carregarDados();
    } catch (err) {
        toast('Erro: ' + err.message, 'error');
    }
}

// ── Excluir ───────────────────────────────────────────────
function excluirDespesa(id) {
    const tx   = _lancamentos.find(t => +t.id === +id);
    const desc = tx ? `"${tx.descricao}"` : 'esta despesa';
    confirmar('Excluir despesa', `Excluir ${desc}? Esta ação não pode ser desfeita.`, async () => {
        try {
            const res  = await fetch(`backend/api/despesas.php?id=${id}`, { method: 'DELETE' });
            const json = await res.json();
            if (!json.success) throw new Error(json.erro || 'Erro ao excluir');
            toast(json.msg || 'Despesa excluída.', 'success');
            carregarDados();
        } catch (err) {
            toast('Erro: ' + err.message, 'error');
        }
    });
}

// ── Helpers ───────────────────────────────────────────────
// esc()/fmtData() vêm de assets/js/app.js (carregado globalmente por index.php)

function ucFirst(s) {
    return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') { fecharModal(); fecharModalAntecipar(); } });

// ── Antecipar / Pagar parcela de empréstimo ───────────────
let _antTx = null;

function abrirAntecipar(txId) {
    _antTx = _lancamentos.find(t => +t.id === +txId) || null;
    if (!_antTx || !_antTx.parcela_id) return;

    const hoje      = new Date().toISOString().split('T')[0];
    const antecip   = _antTx.parcela_vencimento > hoje;
    const valorOrig = parseFloat(_antTx.valor_original_parcela || _antTx.valor || 0);

    // Cabeçalho
    document.getElementById('antTitulo').textContent = antecip ? '⚡ Antecipar Parcela' : 'Pagar Parcela';
    document.getElementById('antEmpNome').textContent = _antTx.emp_nome || '—';
    document.getElementById('antParcelaInfo').textContent =
        `Parcela ${_antTx.parcela_numero} de ${_antTx.emp_total_parcelas}` +
        (_antTx.parcela_vencimento ? `  ·  Vence em ${fmtData(_antTx.parcela_vencimento)}` : '');

    document.getElementById('antValorOriginalRef').textContent = brl(valorOrig);
    document.getElementById('antSaldoRef').textContent         = brl(parseFloat(_antTx.emp_saldo_devedor || 0));

    // Campos do form
    document.getElementById('antData').value     = hoje;
    document.getElementById('antValorPago').value = brlMask(valorOrig);
    document.getElementById('antConta').value    = _antTx.emp_conta_id || _antTx.conta_id || '';

    // Botão: âmbar para antecipação, verde para pagamento normal
    const btn = document.getElementById('btnConfirmarAnt');
    btn.style.background = antecip ? 'var(--amber)' : '';
    btn.innerHTML = antecip
        ? '<i class="fa-solid fa-forward"></i> Confirmar Antecipação'
        : '<i class="fa-solid fa-circle-check"></i> Confirmar Pagamento';

    // Escuta keyup no campo de valor para recalcular em tempo real
    document.getElementById('antValorPago').onkeyup = recalcularAnt;

    recalcularAnt();
    document.getElementById('modalAntecipar').classList.add('open');
}

function fecharModalAntecipar() {
    document.getElementById('modalAntecipar').classList.remove('open');
    _antTx = null;
}

function recalcularAnt() {
    if (!_antTx) return;
    const valorOrig = parseFloat(_antTx.valor_original_parcela || _antTx.valor || 0);
    const valorPago = parseCurrency(document.getElementById('antValorPago').value) || valorOrig;
    const diff      = +(valorOrig - valorPago).toFixed(2);

    document.getElementById('antComparativo').style.display = '';
    document.getElementById('antCmpOriginal').textContent   = brl(valorOrig);
    document.getElementById('antCmpPago').textContent       = brl(valorPago);

    const econEl  = document.getElementById('antEconWrap');
    const igualEl = document.getElementById('antIgualWrap');
    const sobraEl = document.getElementById('antSobraWrap');

    econEl.style.display  = 'none';
    igualEl.style.display = 'none';
    sobraEl.style.display = 'none';

    if (diff > 0.005) {
        econEl.style.display = '';
        document.getElementById('antEconValor').textContent = brl(diff);
        document.getElementById('antEconPct').textContent   =
            `${(diff / valorOrig * 100).toFixed(2).replace('.', ',')}% de desconto na antecipação`;
    } else if (diff < -0.005) {
        sobraEl.style.display = '';
        document.getElementById('antSobraValor').textContent = brl(Math.abs(diff));
    } else {
        igualEl.style.display = '';
    }
}

async function confirmarAntecipacao() {
    if (!_antTx) return;
    const btn = document.getElementById('btnConfirmarAnt');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    const valorPago = parseCurrency(document.getElementById('antValorPago').value);

    try {
        const res  = await fetch('backend/api/emprestimos.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                acao:           'pagar_parcela',
                parcela_id:     parseInt(_antTx.parcela_id),
                data_pagamento: document.getElementById('antData').value,
                conta_id:       document.getElementById('antConta').value || null,
                valor_pago:     valorPago > 0 ? valorPago : null,
            }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);

        // Toast com comparativo de economia
        const valorOrig = parseFloat(_antTx.valor_original_parcela || _antTx.valor || 0);
        const econ      = +(valorOrig - valorPago).toFixed(2);
        let msgToast = json.msg;
        if (json.antecipado && econ > 0.005) {
            msgToast += ` 💰 Economia: ${brl(econ)} (pagou ${brl(valorPago)} · seria ${brl(valorOrig)})`;
        }
        toast(msgToast, 'success');
        fecharModalAntecipar();
        carregarDados();
    } catch (err) {
        toast('Erro: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Confirmar';
    }
}

// ── Persistência de filtros ───────────────────────────────
const _FILTRO_KEY = 'financeos_despesas_filtro';

function salvarFiltro() {
    localStorage.setItem(_FILTRO_KEY, JSON.stringify({
        mes:    document.getElementById('filMes').value,
        ano:    document.getElementById('filAno').value,
        cat:    document.getElementById('filCat').value,
        status: document.getElementById('filStatus').value,
        resp:   _getRespIds(),
        conta:  document.getElementById('filConta')?.value  || '',
        cartao: document.getElementById('filCartaoF')?.value|| '',
        tag:    document.getElementById('filTag')?.value    || '',
        modo:   _modoFiltro,
        de:     document.getElementById('filDe')?.value  || '',
        ate:    document.getElementById('filAte')?.value || '',
    }));
}

function restaurarFiltro() {
    try {
        const f = JSON.parse(localStorage.getItem(_FILTRO_KEY) || '{}');
        const set = (id, v) => { const el = document.getElementById(id); if (el && v !== undefined) el.value = v; };
        set('filMes',    f.mes);
        set('filAno',    f.ano);
        set('filCat',    f.cat);
        set('filStatus', f.status);
        set('filConta',  f.conta);
        set('filCartaoF',f.cartao);
        set('filTag',    f.tag);
        // Restaura seleção dos chips de responsável
        if (f.resp) {
            _respSelecionados = new Set(f.resp.split(',').filter(Boolean));
            _renderRespChips();
        }
        if (f.de)   set('filDe',  f.de);
        if (f.ate)  set('filAte', f.ate);
        if (f.modo) setModoFiltro(f.modo, false);
        // Se algum filtro secundário voltou preenchido do último uso, já
        // abre o painel — senão o usuário nem percebe que está ativo.
        if (f.conta || f.cartao || f.tag) {
            document.getElementById('filtrosSecundarios')?.classList.add('open');
            document.getElementById('btnMaisFiltros')?.classList.add('active');
        }
    } catch (_) {}
}

// ── Divisão de despesas ───────────────────────────────────────
function toggleDivisao() {
    const ativo = document.getElementById('fDivisaoToggle')?.checked;
    const wrap  = document.getElementById('divisaoWrap');
    const resp  = document.getElementById('fResp');
    const parc  = document.getElementById('parcelasWrap');
    if (!wrap) return;
    wrap.style.display = ativo ? 'block' : 'none';
    if (resp) resp.disabled = ativo;
    if (parc) parc.style.display = ativo ? 'none' : '';
    if (ativo) atualizarDivisaoInfo();
}

function atualizarDivisaoInfo() {
    const cbs   = [...document.querySelectorAll('.divisao-cb:checked')];
    const info  = document.getElementById('divisaoInfo');
    const valor = parseCurrency(document.getElementById('fValor').value);
    if (!info) return;
    if (!cbs.length) { info.textContent = 'Selecione os responsáveis.'; return; }
    const vlUnit = valor > 0 ? (valor / cbs.length).toFixed(2) : 0;
    info.innerHTML = `<i class="fa-solid fa-circle-info fa-xs"></i> ${cbs.length} participantes · cada um: <strong>R$ ${vlUnit.replace('.', ',')}</strong>`;
}

// Atualiza info quando o valor muda
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('fValor')?.addEventListener('input', () => {
        if (document.getElementById('fDivisaoToggle')?.checked) atualizarDivisaoInfo();
    });
    document.querySelectorAll('.divisao-cb').forEach(cb =>
        cb.addEventListener('change', atualizarDivisaoInfo)
    );
});

// ── Templates ─────────────────────────────────────────────────
let _templates = [];

async function abrirTemplates() {
    document.getElementById('modalTemplates').classList.add('open');
    document.getElementById('templatesList').innerHTML =
        '<div class="empty-state"><i class="fa-solid fa-spinner fa-spin"></i></div>';
    try {
        const res  = await fetch('backend/api/templates.php?tipo=despesa');
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        _templates = json.dados;
        renderTemplates(json.dados);
    } catch (err) {
        document.getElementById('templatesList').innerHTML =
            `<div style="color:var(--rose);padding:1rem;text-align:center">${esc(err.message)}</div>`;
    }
}

function fecharTemplates() {
    document.getElementById('modalTemplates').classList.remove('open');
}

function renderTemplates(lista) {
    const wrap = document.getElementById('templatesList');
    if (!lista.length) {
        wrap.innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-layer-group fa-2x" style="margin-bottom:.75rem;display:block"></i>
                <div class="text-sm">Nenhum template salvo ainda.</div>
                <div class="text-xs" style="margin-top:.3rem">Preencha uma despesa e clique em "Salvar Template".</div>
            </div>`;
        return;
    }
    wrap.innerHTML = lista.map(t => {
        const catBadge = t.cat_nome
            ? `<span class="badge" style="background:${t.cat_cor||'#334155'}22;color:${t.cat_cor||'#94a3b8'};font-size:.7rem">${esc(t.cat_nome)}</span>`
            : '';
        const valorStr = t.valor ? brl(t.valor) : '—';
        const contaStr = t.cartao_nome || t.conta_nome || '—';
        return `<div style="background:var(--bg-700);border:1px solid var(--border);border-radius:var(--radius);
                            padding:.75rem 1rem;display:flex;align-items:center;gap:.75rem">
            <div style="flex:1;min-width:0">
                <div class="fw-600 text-sm">${esc(t.nome)}</div>
                <div class="text-xs text-muted">${esc(t.descricao)} &nbsp;·&nbsp; ${valorStr} &nbsp;·&nbsp; ${esc(contaStr)} ${catBadge}</div>
            </div>
            <div class="d-flex gap-1">
                <button class="btn btn-primary btn-sm" onclick="aplicarTemplate(${+t.id})">Usar</button>
                <button class="btn-icon avatar-sm" onclick="excluirTemplate(${+t.id})" title="Remover"
                        style="color:var(--rose)">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>`;
    }).join('');
}

async function aplicarTemplate(id) {
    const t = _templates.find(x => +x.id === +id);
    if (!t) return;

    document.getElementById('fDesc').value   = t.descricao || '';
    if (t.valor) {
        document.getElementById('fValor').value = brlMask(t.valor);
        document.getElementById('fValor').dispatchEvent(new Event('focus'));
    }
    if (t.categoria_id)   document.getElementById('fCat').value    = t.categoria_id;
    if (t.conta_id)       document.getElementById('fConta').value  = t.conta_id;
    if (t.cartao_id)      document.getElementById('fCartao').value = t.cartao_id;
    if (t.responsavel_id && document.getElementById('fResp'))
        document.getElementById('fResp').value = t.responsavel_id;
    if (t.observacao)     document.getElementById('fObs').value    = t.observacao;

    // Incrementa contador de uso sem aguardar
    fetch('backend/api/templates.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ acao: 'registrar_uso', id }),
    });

    fecharTemplates();
    toast(`Template "${t.nome}" aplicado.`, 'success');
    document.getElementById('fValor').focus();
}

async function salvarComoTemplate() {
    const desc = document.getElementById('fDesc').value.trim();
    if (!desc) { toast('Preencha a descrição antes de salvar o template.', 'warning'); return; }

    const nome = prompt('Nome do template (ex: "Conta de luz fixa"):', desc);
    if (!nome) return;

    const _tplRespVal = document.getElementById('fResp')?.value || '';
    const payload = {
        acao:           'salvar',
        tipo:           'despesa',
        nome:           nome.trim(),
        descricao:      desc,
        valor:          parseCurrency(document.getElementById('fValor').value) || null,
        categoria_id:   document.getElementById('fCat').value    || null,
        conta_id:       document.getElementById('fConta').value  || null,
        cartao_id:      document.getElementById('fCartao').value || null,
        responsavel_id: !_tplRespVal.startsWith('tcr_') && _tplRespVal ? parseInt(_tplRespVal) : null,
        observacao:     document.getElementById('fObs').value.trim(),
    };

    try {
        const res  = await fetch('backend/api/templates.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg || 'Template salvo!', 'success');
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

async function excluirTemplate(id) {
    const t = _templates.find(x => +x.id === +id);
    confirmar('Remover template', `Remover "${t ? t.nome : 'este template'}"?`, async () => {
        try {
            const res  = await fetch(`backend/api/templates.php?id=${id}`, { method: 'DELETE' });
            const json = await res.json();
            if (!json.success) throw new Error(json.erro);
            toast(json.msg, 'success');
            abrirTemplates();
        } catch (err) { toast('Erro: ' + err.message, 'error'); }
    });
}

// ── Lógica de fatura do cartão de crédito ─────────────────────
const _MESES_FAT   = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                      'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
const _MESES_ABREV = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

let _faturaManual = null; // null = automático | { mes, ano } = escolha manual

// Gera N linhas skeleton para a tabela de despesas (8 colunas)
function _skeletonRows(n) {
    const ws = [68, 82, 55, 73, 60, 77, 50, 65];
    return Array.from({length: n || 6}, (_, i) => `
        <tr style="pointer-events:none;user-select:none">
            <td style="width:36px"></td>
            <td>
                <div class="d-flex align-center gap-1">
                    <div class="skel" style="width:28px;height:28px;border-radius:50%;flex-shrink:0"></div>
                    <div style="flex:1;min-width:0">
                        <div class="skel skel-text" style="width:${ws[i % 8]}%"></div>
                        <div class="skel skel-sm"   style="width:38%;margin-top:.35rem"></div>
                    </div>
                </div>
            </td>
            <td><div class="skel skel-badge"></div></td>
            <td><div class="skel skel-text" style="width:72%"></div></td>
            <td><div class="skel skel-badge" style="width:76px"></div></td>
            <td>
                <div class="skel skel-text" style="width:62px"></div>
                <div class="skel skel-sm"   style="width:80px;margin-top:.3rem"></div>
            </td>
            <td><div class="skel skel-badge" style="width:56px"></div></td>
            <td class="text-right"><div class="skel skel-text" style="width:68px;margin-left:auto"></div></td>
            <td><div class="skel" style="width:24px;height:24px;border-radius:50%;margin-left:auto"></div></td>
        </tr>`
    ).join('');
}

function _calcFatura(data, diaFechamento, diaVencimento) {
    if (!data || !diaFechamento) return null;
    const [a, m] = data.split('-').map(Number);
    // Toda compra no cartão vence sempre no mês seguinte ao da compra,
    // independente do dia de fechamento (mesma regra de Calculos::mesFatura).
    const mF = m === 12 ? 1 : m + 1;
    const aF = m === 12 ? a + 1 : a;
    return { mF, aF };
}

function atualizarInfoFatura() {
    const sel    = document.getElementById('fCartao');
    const dataEl = document.getElementById('fData');
    const banner = document.getElementById('faturaInfoBanner');
    if (!sel || !dataEl || !banner) return;

    const cartaoId = sel.value;
    const data     = dataEl.value;

    if (!cartaoId || !data) {
        banner.style.display = 'none';
        document.getElementById('fMesFatura').value = '';
        document.getElementById('fAnoFatura').value  = '';
        // Limpa campo de vencimento ao remover cartão
        const dvfReset = document.getElementById('fDataVenc');
        if (dvfReset) dvfReset.value = '';
        document.getElementById('fDataVencBadge')?.style && (document.getElementById('fDataVencBadge').style.display = 'none');
        document.getElementById('fDataVencHint')?.style  && (document.getElementById('fDataVencHint').style.display  = '');
        return;
    }

    const opt     = sel.options[sel.selectedIndex];
    const diaFech = parseInt(opt.dataset.fechamento || '0');
    const diaVenc = parseInt(opt.dataset.vencimento || '10');
    if (!diaFech) { banner.style.display = 'none'; return; }

    const auto = _calcFatura(data, diaFech, diaVenc);
    if (!auto) { banner.style.display = 'none'; return; }

    const mF = _faturaManual ? _faturaManual.mes : auto.mF;
    const aF = _faturaManual ? _faturaManual.ano : auto.aF;

    document.getElementById('fMesFatura').value = mF;
    document.getElementById('fAnoFatura').value  = aF;

    banner.style.display = '';
    document.getElementById('faturaInfoLabel').textContent =
        `Fatura de ${_MESES_FAT[mF - 1]}/${aF}`;
    document.getElementById('faturaInfoVenc').textContent  =
        `(vence ${String(diaVenc).padStart(2,'0')}/${String(mF).padStart(2,'0')}/${aF})`;

    // Auto-preenche data de vencimento com o vencimento da fatura do cartão
    const dvfAuto = document.getElementById('fDataVenc');
    if (dvfAuto) {
        dvfAuto.value = `${aF}-${String(mF).padStart(2,'0')}-${String(diaVenc).padStart(2,'0')}`;
        document.getElementById('fDataVencBadge')?.style && (document.getElementById('fDataVencBadge').style.display = '');
        document.getElementById('fDataVencHint')?.style  && (document.getElementById('fDataVencHint').style.display  = 'none');
    }

    const btnAuto = document.getElementById('btnFaturaAuto');
    if (btnAuto) btnAuto.style.display = _faturaManual ? '' : 'none';

    _renderFaturaOpcoes(data, diaFech, diaVenc, mF, aF, auto);
}

// Chamado quando o usuário edita manualmente a data de vencimento
function _onDataVencChange() {
    const sel = document.getElementById('fCartao');
    if (!sel || !sel.value) return; // só relevante quando há cartão selecionado

    const dvf = document.getElementById('fDataVenc');
    if (!dvf || !dvf.value) return;

    const [aF, mF, dF] = dvf.value.split('-').map(Number);
    if (!mF || !aF) return;

    // Marca como seleção manual para que atualizarInfoFatura() não sobrescreva
    _faturaManual = { mes: mF, ano: aF };

    // Atualiza campos ocultos usados no payload
    document.getElementById('fMesFatura').value = mF;
    document.getElementById('fAnoFatura').value  = aF;

    // Atualiza banner
    const banner = document.getElementById('faturaInfoBanner');
    if (banner) banner.style.display = '';

    document.getElementById('faturaInfoLabel').textContent =
        `Fatura de ${_MESES_FAT[mF - 1]}/${aF}`;
    document.getElementById('faturaInfoVenc').textContent  =
        `(vence ${String(dF).padStart(2,'0')}/${String(mF).padStart(2,'0')}/${aF})`;

    // Mostra botão "Automático" para o usuário poder reverter
    const btnAuto = document.getElementById('btnFaturaAuto');
    if (btnAuto) btnAuto.style.display = '';
}

function _renderFaturaOpcoes(data, diaFech, diaVenc, mFAtual, aFAtual, auto) {
    const el = document.getElementById('faturaOpcoes');
    if (!el) return;

    const opcoes = [];
    for (let delta = -1; delta <= 2; delta++) {
        let m = auto.mF + delta;
        let a = auto.aF;
        while (m <= 0)  { m += 12; a--; }
        while (m > 12)  { m -= 12; a++; }
        opcoes.push({ m, a, isAtual: m === mFAtual && a === aFAtual, isAuto: m === auto.mF && a === auto.aF });
    }

    el.innerHTML = opcoes.map(o =>
        `<button type="button"
                 class="btn btn-sm ${o.isAtual ? 'btn-primary' : 'btn-ghost'}"
                 onclick="selecionarFatura(${o.m},${o.a})"
                 style="font-size:.78rem;padding:.2rem .65rem">
             ${_MESES_FAT[o.m - 1]}/${o.a}
             ${o.isAuto ? '<span style="font-size:.65rem;opacity:.55"> ✓ auto</span>' : ''}
         </button>`
    ).join('');
}

function toggleFaturaOpcoes() {
    const el = document.getElementById('faturaOpcoes');
    if (!el) return;
    const show = el.style.display === 'none' || el.style.display === '';
    if (show) {
        const sel    = document.getElementById('fCartao');
        const dataEl = document.getElementById('fData');
        const opt    = sel.options[sel.selectedIndex];
        const diaFech = parseInt(opt.dataset.fechamento || '0');
        const diaVenc = parseInt(opt.dataset.vencimento || '10');
        const auto    = _calcFatura(dataEl.value, diaFech, diaVenc);
        const mFAtual = parseInt(document.getElementById('fMesFatura').value) || (auto ? auto.mF : 0);
        const aFAtual = parseInt(document.getElementById('fAnoFatura').value)  || (auto ? auto.aF : 0);
        if (auto) _renderFaturaOpcoes(dataEl.value, diaFech, diaVenc, mFAtual, aFAtual, auto);
        el.style.display = 'flex';
    } else {
        el.style.display = 'none';
    }
}

function selecionarFatura(mes, ano) {
    const sel    = document.getElementById('fCartao');
    const dataEl = document.getElementById('fData');
    const opt    = sel.options[sel.selectedIndex];
    const diaFech = parseInt(opt.dataset.fechamento || '0');
    const diaVenc = parseInt(opt.dataset.vencimento || '10');
    const auto    = _calcFatura(dataEl.value, diaFech, diaVenc);

    _faturaManual = (auto && mes === auto.mF && ano === auto.aF) ? null : { mes, ano };
    document.getElementById('faturaOpcoes').style.display = 'none';
    atualizarInfoFatura();
}
// ───────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    _initPeriodoDatas();
    restaurarFiltro();

    // Se veio da página de empréstimos com mes/ano na URL, sobrepõe o filtro
    const urlParams = new URLSearchParams(window.location.search);
    const mesPar = urlParams.get('mes');
    const anoPar = urlParams.get('ano');
    if (mesPar) { const el = document.getElementById('filMes'); if (el) el.value = mesPar; }
    if (anoPar) { const el = document.getElementById('filAno'); if (el) el.value = anoPar; }

    carregarDados();
    carregarAtalhosDespesa();
    initAutocomplete('fDesc', 'despesa');
});
</script>

<?php include __DIR__ . '/despesas/_modal_bulk_edit.php'; ?>
