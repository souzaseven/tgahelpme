<?php
// ── Dados iniciais para formulários ──────────────────────────
$p = TABLE_PREFIX;

$_catsRecRaw = $pdo->query(
    "SELECT c.id, c.nome, c.cor, c.icone, c.categoria_pai
     FROM `{$p}categorias` c
     WHERE c.tipo IN ('receita','ambos') AND c.ativo=1
     ORDER BY COALESCE((SELECT cp.nome FROM `{$p}categorias` cp WHERE cp.id=c.categoria_pai), c.nome),
              c.categoria_pai IS NOT NULL, c.nome"
)->fetchAll();
$_recPais = []; $_recSubs = [];
foreach ($_catsRecRaw as $c) {
    if ($c['categoria_pai']) $_recSubs[$c['categoria_pai']][] = $c;
    else                     $_recPais[] = $c;
}
$categoriasRec = $_catsRecRaw;

$contasRec = $pdo->query(
    "SELECT id, nome FROM `{$p}contas` WHERE ativo=1 ORDER BY nome"
)->fetchAll();

try {
    $cartoesValeRec = $pdo->query(
        "SELECT id, nome FROM `{$p}cartoes` WHERE ativo=1 AND tipo != 'credito' ORDER BY nome"
    )->fetchAll();
} catch (PDOException $e) { $cartoesValeRec = []; }

try {
    $respRec = $pdo->query(
        "SELECT id, nome, cor, icone FROM `{$p}responsaveis` WHERE ativo=1 ORDER BY nome"
    )->fetchAll();
} catch (PDOException $e) { $respRec = []; }

try {
    $terceirosRec = $pdo->query(
        "SELECT id, nome, cor, icone FROM `{$p}terceiros` WHERE ativo=1 ORDER BY nome"
    )->fetchAll();
} catch (PDOException $e) { $terceirosRec = []; }

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
.resp-sep { border: none; border-top: 1px solid var(--border); margin: .25rem 0; }

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
    .sidebar, .header, .filters-bar, #bulkBarRec, .no-print { display: none !important; }
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
        <div class="page-title">Receitas</div>
        <div class="page-sub" id="pageSub">Carregando...</div>
    </div>
    <button class="btn btn-success btn-sm no-print" onclick="abrirModal()">
        <i class="fa-solid fa-plus"></i> Nova Receita
    </button>
</div>

<!-- ── Filtros ────────────────────────────────────────────── -->
<button type="button" class="filters-toggle" onclick="toggleFiltrosMobile()">
    <span><i class="fa-solid fa-filter fa-xs"></i> Filtros</span>
    <i class="fa-solid fa-chevron-down fa-xs" id="filtrosToggleIcon"></i>
</button>
<div class="filters-bar" id="filtersBar">
    <div class="filter-group" style="flex-direction:column;align-items:flex-start;gap:.3rem">
        <div style="display:flex;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden">
            <button id="fBtnMes" onclick="setModoFiltro('mes')"
                    style="padding:.22rem .65rem;font-size:.72rem;font-weight:600;border:none;cursor:pointer;transition:var(--ease);background:var(--indigo);color:#fff">
                Mês/Ano
            </button>
            <button id="fBtnPer" onclick="setModoFiltro('periodo')"
                    style="padding:.22rem .65rem;font-size:.72rem;font-weight:600;border:none;cursor:pointer;transition:var(--ease);background:transparent;color:var(--text-500)">
                Período
            </button>
        </div>
        <div id="filtroMes" style="display:flex;align-items:center;gap:.3rem">
            <select id="filMes" class="form-control" style="min-width:118px" onchange="filtroCarregar()">
                <?php foreach ($nomesMeses as $n => $nome): ?>
                <option value="<?= $n ?>" <?= $n === $mesAtual ? 'selected' : '' ?>><?= $nome ?></option>
                <?php endforeach ?>
            </select>
            <select id="filAno" class="form-control" style="min-width:82px" onchange="filtroCarregar()">
                <?php for ($y = $anoAtual; $y >= $anoAtual - 4; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $anoAtual ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor ?>
            </select>
        </div>
        <div id="filtroPeriodo" style="display:none;align-items:center;gap:.3rem">
            <input type="date" id="filDe" class="form-control" style="width:140px" onchange="filtroCarregar()">
            <span style="color:var(--text-500);font-size:.8rem">até</span>
            <input type="date" id="filAte" class="form-control" style="width:140px" onchange="filtroCarregar()">
        </div>
    </div>
    <div class="filter-group">
        <span class="filter-label">Categoria</span>
        <select id="filCat" class="form-control" style="min-width:148px" onchange="filtroCarregar()">
            <option value="">Todas</option>
            <option value="nenhuma">— Sem categoria —</option>
            <?php foreach ($_recPais as $p): $s = $_recSubs[$p['id']] ?? []; if ($s): ?>
            <optgroup label="<?= htmlspecialchars($p['nome']) ?>">
                <?php foreach ($s as $sub): ?>
                <option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['nome']) ?></option>
                <?php endforeach ?>
            </optgroup>
            <?php else: ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
            <?php endif; endforeach ?>
        </select>
    </div>
    <div class="filter-group">
        <span class="filter-label">Status</span>
        <select id="filStatus" class="form-control" style="min-width:118px" onchange="filtroCarregar()">
            <option value="">Todos</option>
            <option value="pago">Recebido</option>
            <option value="pendente">Pendente</option>
            <option value="cancelado">Cancelado</option>
        </select>
    </div>
    <?php if (!empty($respRec) || !empty($terceirosRec)): ?>
    <div class="filter-group">
        <span class="filter-label">Pessoa</span>
        <div class="resp-dropdown" id="respDropdown">
            <button type="button" id="respToggleBtn" class="form-control resp-toggle"
                    onclick="_toggleRespDropdown(event)">
                <span id="respLabel">Todos</span>
                <i class="fa-solid fa-chevron-down fa-xs" style="margin-left:auto;opacity:.5"></i>
            </button>
            <div class="resp-panel" id="respPanel">
                <label class="resp-item">
                    <input type="checkbox" class="resp-cb" id="cbRespTodos" value="todos" checked
                           onchange="_onRespCbTodos(this)">
                    <i class="fa-solid fa-users fa-xs" style="color:var(--text-400)"></i>
                    <span>Todos (pessoal)</span>
                </label>
                <?php if (!empty($respRec)): ?>
                <hr class="resp-sep">
                <div style="padding:.3rem .875rem;font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-600)">Meus responsáveis</div>
                <label class="resp-item">
                    <input type="checkbox" class="resp-cb" value="-1" onchange="_onRespCb(this)">
                    <div class="resp-avatar-sm" style="background:#64748b22;color:#64748b">
                        <i class="fa-solid fa-user-slash fa-xs"></i>
                    </div>
                    <span>Sem responsável</span>
                </label>
                <?php foreach ($respRec as $r):
                    $cor = htmlspecialchars($r['cor']);
                ?>
                <label class="resp-item">
                    <input type="checkbox" class="resp-cb" value="<?= $r['id'] ?>"
                           onchange="_onRespCb(this)">
                    <div class="resp-avatar-sm" style="background:<?= $cor ?>22;color:<?= $cor ?>">
                        <i class="fa-solid fa-<?= htmlspecialchars($r['icone']) ?> fa-xs"></i>
                    </div>
                    <span><?= htmlspecialchars($r['nome']) ?></span>
                </label>
                <?php endforeach ?>
                <?php endif ?>
                <?php if (!empty($terceirosRec)): ?>
                <hr class="resp-sep">
                <div style="padding:.3rem .875rem;font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--amber)">Terceiros</div>
                <?php foreach ($terceirosRec as $r):
                    $cor = htmlspecialchars($r['cor']);
                ?>
                <label class="resp-item">
                    <input type="checkbox" class="resp-cb" value="tcr_<?= $r['id'] ?>"
                           onchange="_onRespCb(this)">
                    <div class="resp-avatar-sm" style="background:<?= $cor ?>22;color:<?= $cor ?>">
                        <i class="fa-solid fa-<?= htmlspecialchars($r['icone']) ?> fa-xs"></i>
                    </div>
                    <span><?= htmlspecialchars($r['nome']) ?></span>
                </label>
                <?php endforeach ?>
                <?php endif ?>
            </div>
        </div>
    </div>
    <?php endif ?>
    <div class="filter-group" style="flex:1;min-width:180px">
        <span class="filter-label">Buscar</span>
        <input type="search" id="filBusca" class="form-control"
               placeholder="Descrição da receita..." oninput="debounceCarregar()">
    </div>
    <div class="filter-group" style="justify-content:flex-end">
        <span class="filter-label">&nbsp;</span>
        <button type="button" id="btnMaisFiltros" class="filters-more-btn" onclick="toggleFiltrosAvancados()">
            <i class="fa-solid fa-sliders fa-xs"></i> Mais filtros
            <span id="maisFiltrosBadge" class="filters-more-badge" style="display:none">0</span>
            <i class="fa-solid fa-chevron-down fa-xs"></i>
        </button>
    </div>

    <div class="filters-secondary" id="filtrosSecundarios">
        <div class="filter-group">
            <span class="filter-label">Conta</span>
            <select id="filConta" class="form-control" style="min-width:140px" onchange="filtroCarregar()">
                <option value="">Todas</option>
                <?php foreach ($contasRec as $ct): ?>
                <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['nome']) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="filter-group">
            <span class="filter-label">Valor Mín (R$)</span>
            <input type="number" id="filValMin" class="form-control" style="min-width:100px"
                   placeholder="0,00" min="0" step="0.01" oninput="debounceCarregar()">
        </div>
        <div class="filter-group">
            <span class="filter-label">Valor Máx (R$)</span>
            <input type="number" id="filValMax" class="form-control" style="min-width:100px"
                   placeholder="9.999,99" min="0" step="0.01" oninput="debounceCarregar()">
        </div>
    </div>
</div>

<!-- ── KPIs ──────────────────────────────────────────────── -->
<div class="kpi-grid" style="margin-bottom:1.25rem">
    <div class="kpi-card emerald">
        <div class="kpi-header">
            <div class="kpi-label">Total do Mês</div>
            <div class="kpi-icon emerald"><i class="fa-solid fa-arrow-trend-up"></i></div>
        </div>
        <div class="kpi-value" id="kpiTotal">—</div>
        <div class="kpi-trend neutral" id="kpiTotalTrend">
            <i class="fa-solid fa-circle-info fa-xs"></i> Todas as receitas
        </div>
    </div>
    <div class="kpi-card indigo">
        <div class="kpi-header">
            <div class="kpi-label">Recebido</div>
            <div class="kpi-icon indigo"><i class="fa-solid fa-circle-check"></i></div>
        </div>
        <div class="kpi-value" id="kpiPago">—</div>
        <div class="kpi-trend neutral">
            <i class="fa-solid fa-circle-info fa-xs"></i> Já creditado em conta
        </div>
    </div>
    <div class="kpi-card amber">
        <div class="kpi-header">
            <div class="kpi-label">A Receber</div>
            <div class="kpi-icon amber"><i class="fa-solid fa-clock"></i></div>
        </div>
        <div class="kpi-value" id="kpiPendente">—</div>
        <div class="kpi-trend neutral">
            <i class="fa-solid fa-circle-info fa-xs"></i> Receitas pendentes
        </div>
    </div>
    <div class="kpi-card slate">
        <div class="kpi-header">
            <div class="kpi-label">Lançamentos</div>
            <div class="kpi-icon slate"><i class="fa-solid fa-receipt"></i></div>
        </div>
        <div class="kpi-value" id="kpiQtd">—</div>
        <div class="kpi-trend neutral">
            <i class="fa-solid fa-circle-info fa-xs"></i> No período selecionado
        </div>
    </div>
</div>

<!-- ── Tabela de lançamentos ──────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">Lançamentos</div>
            <div class="card-subtitle" id="tabelaInfo">—</div>
        </div>
        <button class="btn btn-ghost btn-sm no-print" onclick="window.print()">
            <i class="fa-solid fa-print fa-xs"></i> Imprimir
        </button>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:36px;padding-right:0"><input type="checkbox" id="cbTodos" style="accent-color:var(--indigo);width:15px;height:15px;cursor:pointer" onclick="toggleTodos(this)" title="Selecionar todos"></th>
                    <th class="sortable" onclick="sortarTabela('descricao')">Descrição <i class="fa-solid fa-arrows-up-down fa-xs sort-ic"></i></th>
                    <th class="sortable" onclick="sortarTabela('cat_nome')">Categoria <i class="fa-solid fa-arrows-up-down fa-xs sort-ic"></i></th>
                    <th>Conta</th>
                    <th>Responsável</th>
                    <th class="sortable" onclick="sortarTabela('data')">Data <i class="fa-solid fa-arrows-up-down fa-xs sort-ic"></i></th>
                    <th class="sortable" onclick="sortarTabela('status')">Status <i class="fa-solid fa-arrows-up-down fa-xs sort-ic"></i></th>
                    <th class="sortable text-right" onclick="sortarTabela('valor')">Valor <i class="fa-solid fa-arrows-up-down fa-xs sort-ic"></i></th>
                    <th style="width:76px"></th>
                </tr>
            </thead>
            <tbody id="tabelaBody">
                <tr>
                    <td colspan="9" style="text-align:center;padding:2.5rem;color:var(--text-600)">
                        <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="cards-mobile" id="cardsMobile"></div>
    <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
        <span class="text-sm text-muted" id="pagInfo">—</span>
        <div class="d-flex align-center gap-1">
            <select id="porPagina" class="form-control" style="width:auto;font-size:.8rem"
                    onchange="_paginaRec=1;carregarDados()">
                <option value="25">25 / pág</option>
                <option value="50">50 / pág</option>
                <option value="100">100 / pág</option>
            </select>
            <div id="pagBtns" class="d-flex gap-1"></div>
        </div>
    </div>
</div>

<!-- ── Modal Nova / Editar Receita ───────────────────────── -->
<div id="modalOverlay" class="modal-overlay" onclick="if(event.target===this)fecharModal()">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title" id="modalTitulo">Nova Receita</div>
            <div class="d-flex gap-1 align-center">
                <button type="button" class="btn btn-ghost btn-sm" onclick="abrirTemplates()" title="Usar template">
                    <i class="fa-solid fa-layer-group fa-xs"></i> Templates
                </button>
                <button type="button" class="btn-icon" onclick="fecharModal()" title="Fechar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <form id="formReceita" onsubmit="salvarReceita(event)">
            <div class="modal-body">
                <input type="hidden" id="editId" value="">

                <!-- Descrição -->
                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label" for="fDesc">
                        Descrição <span style="color:var(--rose)">*</span>
                    </label>
                    <input type="text" id="fDesc" class="form-control"
                           placeholder="Ex: Salário, Freelance, Aluguel recebido..." required maxlength="200">
                </div>

                <!-- Valor | Data | Status -->
                <div class="form-grid form-grid-3" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label" for="fValor">
                            Valor (R$) <span style="color:var(--rose)">*</span>
                        </label>
                        <input type="text" id="fValor" class="form-control"
                               placeholder="0,00" inputmode="numeric" data-currency required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="fData">
                            Data <span style="color:var(--rose)">*</span>
                        </label>
                        <input type="date" id="fData" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="fStatus">Status</label>
                        <select id="fStatus" class="form-control">
                            <option value="pago">Recebido</option>
                            <option value="pendente">Pendente</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                </div>

                <!-- Categoria | Conta -->
                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label" for="fCat">Categoria</label>
                        <select id="fCat" class="form-control">
                            <option value="">— Sem categoria —</option>
                            <?php foreach ($_recPais as $rp): $rs = $_recSubs[$rp['id']] ?? []; if ($rs): ?>
                            <optgroup label="<?= htmlspecialchars($rp['nome']) ?>">
                                <?php foreach ($rs as $rsub): ?>
                                <option value="<?= $rsub['id'] ?>"><?= htmlspecialchars($rsub['nome']) ?></option>
                                <?php endforeach ?>
                            </optgroup>
                            <?php else: ?>
                            <option value="<?= $rp['id'] ?>"><?= htmlspecialchars($rp['nome']) ?></option>
                            <?php endif; endforeach ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="fConta">Conta de destino</label>
                        <select id="fConta" class="form-control" onchange="onFContaChange()">
                            <option value="">— Sem conta —</option>
                            <?php foreach ($contasRec as $ct): ?>
                            <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['nome']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>

                <?php if (!empty($cartoesValeRec)): ?>
                <!-- Recarga de cartão vale-alimentação/refeição -->
                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label" for="fCartao">Cartão (recarga vale)</label>
                    <select id="fCartao" class="form-control" onchange="onFCartaoChange()">
                        <option value="">— Nenhum —</option>
                        <?php foreach ($cartoesValeRec as $cv): ?>
                        <option value="<?= $cv['id'] ?>"><?= htmlspecialchars($cv['nome']) ?></option>
                        <?php endforeach ?>
                    </select>
                    <div class="text-xs text-muted" style="margin-top:.35rem">
                        Se escolher um cartão aqui, o valor vira crédito nele em vez de entrar em uma conta.
                    </div>
                </div>
                <?php endif ?>

                <!-- Responsável -->
                <?php if (!empty($respRec) || !empty($terceirosRec)): ?>
                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label" for="fResp">Pessoa / Responsável</label>
                    <select id="fResp" class="form-control">
                        <option value="">— Sem responsável —</option>
                        <?php if (!empty($respRec)): ?>
                        <optgroup label="Responsáveis">
                            <?php foreach ($respRec as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nome']) ?></option>
                            <?php endforeach ?>
                        </optgroup>
                        <?php endif ?>
                        <?php if (!empty($terceirosRec)): ?>
                        <optgroup label="Terceiros">
                            <?php foreach ($terceirosRec as $r): ?>
                            <option value="tcr_<?= $r['id'] ?>"><?= htmlspecialchars($r['nome']) ?></option>
                            <?php endforeach ?>
                        </optgroup>
                        <?php endif ?>
                    </select>
                </div>
                <?php endif ?>

                <!-- Observação -->
                <div class="form-group">
                    <label class="form-label" for="fObs">Observação</label>
                    <textarea id="fObs" class="form-control" rows="2"
                              placeholder="Notas adicionais (opcional)"></textarea>
                </div>

                <!-- Comprovante (foto) -->
                <div class="form-group" style="margin-top:.75rem">
                    <label class="form-label">Comprovante</label>
                    <input type="hidden" id="fComprovantePath" value="">
                    <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
                        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('fComprovanteCamera').click()">
                            <i class="fa-solid fa-camera"></i> Câmera
                        </button>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('fComprovanteGaleria').click()">
                            <i class="fa-solid fa-image"></i> Galeria
                        </button>
                        <input type="file" id="fComprovanteCamera" accept="image/*" capture="environment"
                               style="display:none" onchange="onSelecionarComprovante(this)">
                        <input type="file" id="fComprovanteGaleria" accept="image/*"
                               style="display:none" onchange="onSelecionarComprovante(this)">
                        <div id="fComprovantePreview" style="display:none;align-items:center;gap:.5rem"></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="fecharModal()">Cancelar</button>
                <button type="button" class="btn btn-ghost btn-sm" onclick="salvarComoTemplate()"
                        title="Salvar como template reutilizável">
                    <i class="fa-solid fa-bookmark fa-xs"></i> Salvar Template
                </button>
                <button type="submit" class="btn btn-success" id="btnSalvar">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal de Templates ──────────────────────────────────── -->
<div id="modalTemplates" class="modal-overlay" onclick="if(event.target===this)fecharTemplates()">
    <div class="modal-box" style="max-width:520px">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-layer-group fa-sm" style="color:var(--emerald)"></i> Templates de Receita</div>
            <button type="button" class="btn-icon" onclick="fecharTemplates()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding:.75rem">
            <div id="templatesList" style="display:flex;flex-direction:column;gap:.5rem">
                <div class="empty-state">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ── Estado ────────────────────────────────────────────────
let _lancamentos   = [];
let _selecionados  = new Set();
let _debounceTimer = null;
let _sortCol       = 'data';
let _sortDir       = 'desc';
let _paginaRec     = 1;
let _nomeMesRec    = '';
let _anoRec        = '';
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
    _paginaRec = 1;
    carregarDados();
}

function debounceCarregar() {
    _paginaRec = 1;
    clearTimeout(_debounceTimer);
    _debounceTimer = setTimeout(carregarDados, 400);
}

function _skeletonRowsRec(n) {
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
            <td><div class="skel skel-text" style="width:65%"></div></td>
            <td><div class="skel skel-badge" style="width:80px"></div></td>
            <td><div class="skel skel-text" style="width:62px"></div></td>
            <td><div class="skel skel-badge" style="width:56px"></div></td>
            <td class="text-right"><div class="skel skel-text" style="width:68px;margin-left:auto"></div></td>
            <td><div class="skel" style="width:24px;height:24px;border-radius:50%;margin-left:auto"></div></td>
        </tr>`
    ).join('');
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
    const conta  = document.getElementById('filConta')?.value || '';
    const valMin = document.getElementById('filValMin')?.value || '';
    const valMax = document.getElementById('filValMax')?.value || '';
    _atualizarBadgeFiltrosAvancados();

    // Paginação server-side
    const porPagina = parseInt(document.getElementById('porPagina')?.value || '50');
    const params = new URLSearchParams({ de, ate });
    if (cat)     params.set('categoria_id', cat);
    if (status)  params.set('status', status);
    if (busca)   params.set('busca', busca);
    if (respIds) params.set('responsaveis', respIds);
    if (conta)   params.set('conta_id', conta);
    if (valMin) params.set('valor_min', valMin);
    if (valMax) params.set('valor_max', valMax);
    params.set('pagina',    _paginaRec);
    params.set('por_pagina', porPagina);

    document.getElementById('tabelaBody').innerHTML = _skeletonRowsRec(8);
    ['kpiTotal','kpiPago','kpiPendente','kpiQtd'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = '<div class="skel skel-kpi"></div>';
    });

    try {
        const res  = await fetch('backend/api/receitas.php?' + params);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro || 'Erro ao buscar dados');
        _lancamentos    = json.dados;
        _nomeMesRec     = _modoFiltro === 'mes' ? nomeMes : de;
        _anoRec         = _modoFiltro === 'mes' ? ano : ate;
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

// Compara com o período anterior de mesma duração (backend já calcula).
// Numa receita, receber mais que antes é "bom" (verde) — o oposto de
// despesa, por isso .good/.bad em vez de reusar .up/.down.
function atualizarTrendTotal(total, totalAnterior) {
    const el = document.getElementById('kpiTotalTrend');
    if (!el) return;
    if (totalAnterior === undefined || totalAnterior === null || totalAnterior <= 0) {
        el.className = 'kpi-trend neutral';
        el.innerHTML = '<i class="fa-solid fa-circle-info fa-xs"></i> Todas as receitas';
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
    el.className = `kpi-trend ${subiu ? 'good' : 'bad'}`;
    el.innerHTML = `<i class="fa-solid fa-arrow-trend-${subiu ? 'up' : 'down'} fa-xs"></i> ${pct.toFixed(1)}% vs. período anterior`;
}

// ── Filtros secundários (recolhíveis) ──────────────────────
function toggleFiltrosAvancados() {
    const sec = document.getElementById('filtrosSecundarios');
    const btn = document.getElementById('btnMaisFiltros');
    if (!sec || !btn) return;
    const aberto = sec.classList.toggle('open');
    btn.classList.toggle('active', aberto || _contarFiltrosAvancados() > 0);
}

function _contarFiltrosAvancados() {
    const conta  = document.getElementById('filConta')?.value  || '';
    const valMin = document.getElementById('filValMin')?.value || '';
    const valMax = document.getElementById('filValMax')?.value || '';
    return [conta, valMin, valMax].filter(v => v !== '').length;
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
    _paginaRec = 1;

    document.querySelectorAll('thead th.sortable').forEach(th => {
        const isAtivo = th.getAttribute('onclick') === `sortarTabela('${col}')`;
        th.classList.toggle('sort-ativo', isAtivo);
        const ic = th.querySelector('.sort-ic');
        if (ic) ic.className = isAtivo
            ? `fa-solid fa-arrow-${_sortDir === 'asc' ? 'up' : 'down'} fa-xs sort-ic`
            : 'fa-solid fa-arrows-up-down fa-xs sort-ic';
    });

    // Ordena localmente os dados da página atual e re-renderiza sem nova requisição
    renderTabela(_lancamentos, _nomeMesRec, _anoRec);
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
    const ini      = (_paginaRec - 1) * porPag;

    const tbody = document.getElementById('tabelaBody');
    document.getElementById('tabelaInfo').textContent =
        `${total} lançamento${total !== 1 ? 's' : ''} — ${nomeMes}/${ano}`;

    // Controles de paginação server-side
    const infoEl = document.getElementById('pagInfo');
    const btnsEl = document.getElementById('pagBtns');
    if (infoEl) infoEl.textContent = total > 0
        ? `Exibindo ${ini + 1}–${Math.min(_paginaRec * porPag, total)} de ${total}`
        : 'Nenhum registro';
    if (btnsEl) {
        if (totPags <= 1) { btnsEl.innerHTML = ''; }
        else {
            const ir = p => { _paginaRec = p; carregarDados(); };
            let h = `<button class="btn btn-ghost btn-sm" onclick="(${ir})(1)" ${_paginaRec===1?'disabled':''}>«</button>`;
            h    += `<button class="btn btn-ghost btn-sm" onclick="(${ir})(${_paginaRec-1})" ${_paginaRec===1?'disabled':''}>‹</button>`;
            const s = Math.max(1, _paginaRec-2), e = Math.min(totPags, _paginaRec+2);
            if (s > 1) h += `<span class="text-muted text-sm" style="padding:0 .2rem">…</span>`;
            for (let p = s; p <= e; p++)
                h += `<button class="btn btn-sm ${p===_paginaRec?'btn-primary':'btn-ghost'}" onclick="(${ir})(${p})">${p}</button>`;
            if (e < totPags) h += `<span class="text-muted text-sm" style="padding:0 .2rem">…</span>`;
            h += `<button class="btn btn-ghost btn-sm" onclick="(${ir})(${_paginaRec+1})" ${_paginaRec===totPags?'disabled':''}>›</button>`;
            h += `<button class="btn btn-ghost btn-sm" onclick="(${ir})(${totPags})" ${_paginaRec===totPags?'disabled':''}>»</button>`;
            btnsEl.innerHTML = h;
        }
    }

    // dados já vem paginado do servidor — usa diretamente
    const paginados = dados;

    if (!paginados.length) {
        const _filAtivo = (document.getElementById('filBusca')?.value.trim() || '') +
            (document.getElementById('filCat')?.value  || '') +
            (document.getElementById('filStatus')?.value || '');
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:3.5rem 1rem">
            <i class="fa-solid fa-hand-holding-dollar fa-3x" style="display:block;margin-bottom:1rem;opacity:.25;color:var(--text-400)"></i>
            <div style="font-weight:700;font-size:1rem;color:var(--text-200);margin-bottom:.4rem">
                ${_filAtivo ? 'Nenhum resultado encontrado' : 'Nenhuma receita neste período'}
            </div>
            <div style="font-size:.85rem;color:var(--text-600);margin-bottom:1.5rem">
                ${_filAtivo ? 'Tente remover os filtros ou ampliar o período de busca.' : 'Registre sua primeira receita do mês e acompanhe seus ganhos.'}
            </div>
            ${!_filAtivo ? `<button class="btn btn-primary btn-sm" onclick="abrirModal()">
                <i class="fa-solid fa-plus"></i> Adicionar receita
            </button>` : `<button class="btn btn-ghost btn-sm" onclick="document.getElementById('filBusca').value='';document.getElementById('filCat').value='';document.getElementById('filStatus').value='';carregarDados()">
                <i class="fa-solid fa-filter-slash"></i> Limpar filtros
            </button>`}
        </td></tr>`;
        document.getElementById('cardsMobile').innerHTML = '';
        return;
    }

    const statusLabel = { pago: 'Recebido', pendente: 'Pendente', cancelado: 'Cancelado' };

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

        const conta = tx.conta_nome
            ? `<span class="text-sm text-muted">${esc(tx.conta_nome)}</span>`
            : '<span class="text-muted text-xs">—</span>';

        const label = statusLabel[tx.status] || ucFirst(tx.status);

        // ── Cor do valor: verde só quando já recebido (bom de verdade);
        // pendente fica âmbar (ainda por vir), cancelado neutro/apagado.
        let valorClass = 'fw-600';
        if (tx.status === 'pago')          valorClass += ' text-emerald';
        else if (tx.status === 'pendente') valorClass += ' text-amber';
        else                                valorClass += ' text-muted';

        const respBadge = (() => {
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

        const comprovanteBadge = tx.comprovante_path
            ? `<a href="${esc(tx.comprovante_path)}" target="_blank" rel="noopener" title="Ver comprovante" onclick="event.stopPropagation()"
                   style="display:inline-block;color:var(--text-500);margin-top:.15rem"><i class="fa-solid fa-paperclip fa-xs"></i></a>`
            : '';

        const btnRecebido = tx.status !== 'pago'
            ? `<button class="btn-icon avatar-sm" onclick="marcarRecebido(${+tx.id})" title="Marcar como recebido"
                       style="color:var(--emerald)">
                   <i class="fa-solid fa-circle-check"></i>
               </button>`
            : '';

        const acoesHtml = `${btnRecebido}
            <button class="btn-icon avatar-sm" onclick="abrirModal(${+tx.id})" title="Editar">
                <i class="fa-solid fa-pen-to-square"></i>
            </button>
            <button class="btn-icon avatar-sm" onclick="excluirReceita(${+tx.id})" title="Excluir"
                    style="color:var(--rose)">
                <i class="fa-solid fa-trash"></i>
            </button>`;

        cardsHtml.push(`<div class="lanc-card">
            <div class="lanc-card-top">
                <div class="tx-icon" style="background:var(--emerald-soft);color:var(--emerald)">
                    <i class="fa-solid fa-arrow-up fa-xs"></i>
                </div>
                <div class="lanc-card-desc">
                    <div class="fw-600 text-sm">${esc(tx.descricao)}</div>
                    <div class="lanc-card-meta">${catBadge} · ${conta}</div>
                </div>
                <div class="lanc-card-valor ${valorClass}">+${brl(tx.valor)}</div>
            </div>
            <div class="lanc-card-extra">${comprovanteBadge}</div>
            <div class="lanc-card-meta">
                <i class="fa-solid fa-calendar fa-xs"></i> ${fmtData(tx.data)}
                ${respBadge !== '<span class="text-muted text-xs">—</span>' ? `<div style="margin-top:.3rem">${respBadge}</div>` : ''}
            </div>
            <div class="lanc-card-bottom">
                <span class="badge ${esc(tx.status)}">${label}</span>
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
                    <div class="tx-icon" style="background:var(--emerald-soft);color:var(--emerald)">
                        <i class="fa-solid fa-arrow-up fa-xs"></i>
                    </div>
                    <div>
                        <div class="fw-600 text-sm truncate" style="max-width:240px">${esc(tx.descricao)}</div>
                        ${comprovanteBadge}
                    </div>
                </div>
            </td>
            <td>${catBadge}</td>
            <td>${conta}</td>
            <td>${respBadge}</td>
            <td class="text-sm text-muted">${fmtData(tx.data)}</td>
            <td><span class="badge ${esc(tx.status)}">${label}</span></td>
            <td class="text-right ${valorClass}">+${brl(tx.valor)}</td>
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
    const bar   = document.getElementById('bulkBarRec');
    const count = document.getElementById('bulkCountRec');
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
                const res  = await fetch('backend/api/receitas.php', {
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

function abrirModalBulkEditRec() {
    if (!_selecionados.size) return;
    document.getElementById('modalBulkEditRec').style.display = 'flex';
}

function fecharModalBulkEditRec() {
    document.getElementById('modalBulkEditRec').style.display = 'none';
    document.querySelectorAll('#modalBulkEditRec .bulk-toggle').forEach(cb => {
        cb.checked = false;
        _bulkToggle(cb);
    });
}

async function confirmarEditarBulkRec() {
    const campos = {};

    if (document.getElementById('bkChkStatusRec').checked)
        campos.status = document.getElementById('bkStatusRec').value;

    if (document.getElementById('bkChkCatRec').checked)
        campos.categoria_id = document.getElementById('bkCatRec').value || null;

    if (document.getElementById('bkChkContaRec').checked)
        campos.conta_id = document.getElementById('bkContaRec').value || null;

    const chkResp = document.getElementById('bkChkRespRec');
    if (chkResp && chkResp.checked) {
        const v = document.getElementById('bkRespRec').value;
        if (v.startsWith('tcr_')) { campos.terceiro_id = +v.slice(4); campos.responsavel_id = null; }
        else if (v)               { campos.responsavel_id = +v; campos.terceiro_id = null; }
        else                      { campos.responsavel_id = null; campos.terceiro_id = null; }
    }

    if (document.getElementById('bkChkObsRec').checked)
        campos.observacao = document.getElementById('bkObsRec').value.trim();

    if (!Object.keys(campos).length) {
        toast('Marque ao menos um campo para alterar.', 'error');
        return;
    }

    fecharModalBulkEditRec();
    try {
        const res  = await fetch('backend/api/receitas.php', {
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

// ── Recarga de cartão vale x conta (mutuamente exclusivos) ─
function onFCartaoChange() {
    const fCartao = document.getElementById('fCartao');
    const fConta  = document.getElementById('fConta');
    if (!fCartao) return;
    if (fCartao.value) {
        fConta.value = '';
        fConta.disabled = true;
    } else {
        fConta.disabled = false;
    }
}

function onFContaChange() {
    const fCartao = document.getElementById('fCartao');
    const fConta  = document.getElementById('fConta');
    if (!fCartao) return;
    if (fConta.value) {
        fCartao.value = '';
        fCartao.disabled = true;
    } else {
        fCartao.disabled = false;
    }
}

// ── Preenchimento automático por descrição ──────────────────
// Atalhos cadastrados em Configurações > Atalhos (backend/api/atalhos.php):
// ao sair do campo Descrição com um texto conhecido, preenche
// valor/categoria/conta/cartão/responsável/observação com o padrão salvo.
let _atalhosReceita = [];

async function carregarAtalhosReceita() {
    try {
        const res  = await fetch('backend/api/atalhos.php?tipo=receita');
        const json = await res.json();
        if (json.success) _atalhosReceita = json.dados;
    } catch (_) { /* atalho é um bônus — falha silenciosa não deve travar o formulário */ }
}

function _normTxt(s) {
    return String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}

function aplicarAutoPreenchimentoReceita() {
    const desc  = _normTxt(document.getElementById('fDesc')?.value);
    const regra = _atalhosReceita.find(a => +a.ativo && _normTxt(a.descricao_chave) === desc);
    if (!regra) return;

    if (regra.valor !== null)  document.getElementById('fValor').value = brlMask(regra.valor);
    if (regra.categoria_id)    document.getElementById('fCat').value   = regra.categoria_id;
    if (regra.observacao)      document.getElementById('fObs').value   = regra.observacao;

    if (regra.cartao_id && document.getElementById('fCartao')) {
        document.getElementById('fCartao').value = regra.cartao_id;
        onFCartaoChange();
    } else if (regra.conta_id && document.getElementById('fConta')) {
        document.getElementById('fConta').value = regra.conta_id;
        onFContaChange();
    }
    if (document.getElementById('fResp')) {
        if (regra.terceiro_id)         document.getElementById('fResp').value = 'tcr_' + regra.terceiro_id;
        else if (regra.responsavel_id) document.getElementById('fResp').value = regra.responsavel_id;
    }
}
document.getElementById('fDesc')?.addEventListener('blur', aplicarAutoPreenchimentoReceita);

// ── Modal ─────────────────────────────────────────────────
function abrirModal(id = null) {
    document.getElementById('formReceita').reset();
    document.getElementById('editId').value  = id || '';
    document.getElementById('fData').value   = new Date().toISOString().split('T')[0];
    document.getElementById('fStatus').value = 'pago';
    if (document.getElementById('fConta'))  document.getElementById('fConta').disabled  = false;
    if (document.getElementById('fCartao')) document.getElementById('fCartao').disabled = false;

    // Reset do comprovante
    document.getElementById('fComprovantePath').value = '';
    renderComprovantePreview(document.getElementById('fComprovantePreview'), null);

    if (id) {
        document.getElementById('modalTitulo').textContent = 'Editar Receita';
        const tx = _lancamentos.find(t => +t.id === +id);
        if (tx) {
            document.getElementById('fDesc').value   = tx.descricao    || '';
            document.getElementById('fValor').value  = brlMask(tx.valor || 0);
            document.getElementById('fData').value   = (tx.data || '').split('T')[0];
            document.getElementById('fStatus').value = tx.status       || 'pago';
            document.getElementById('fCat').value    = tx.categoria_id    || '';
            document.getElementById('fConta').value  = tx.conta_id        || '';
            if (document.getElementById('fCartao')) {
                document.getElementById('fCartao').value = tx.cartao_id || '';
                onFCartaoChange();
            }
            if (document.getElementById('fResp'))
                document.getElementById('fResp').value = tx.terceiro_id
                    ? 'tcr_' + tx.terceiro_id
                    : (tx.responsavel_id || '');
            document.getElementById('fObs').value    = tx.observacao       || '';
            if (tx.comprovante_path) {
                document.getElementById('fComprovantePath').value = tx.comprovante_path;
                renderComprovantePreview(document.getElementById('fComprovantePreview'), tx.comprovante_path);
            }
        }
    } else {
        document.getElementById('modalTitulo').textContent = 'Nova Receita';
        const last = JSON.parse(localStorage.getItem('financeos_receita_last') || '{}');
        if (last.categoria_id !== undefined) document.getElementById('fCat').value   = last.categoria_id;
        if (last.conta_id     !== undefined) document.getElementById('fConta').value = last.conta_id;
        if (last.status       !== undefined) document.getElementById('fStatus').value = last.status;
        if (last.resp && document.getElementById('fResp')) document.getElementById('fResp').value = last.resp;
    }

    document.getElementById('modalOverlay').classList.add('open');
    setTimeout(() => document.getElementById('fDesc').focus(), 80);
}

function fecharModal() {
    document.getElementById('modalOverlay').classList.remove('open');
}

// ── Salvar ────────────────────────────────────────────────
async function salvarReceita(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Salvando...';

    const id = document.getElementById('editId').value;

    const _fRespVal = document.getElementById('fResp')?.value || '';
    const _isTcr    = _fRespVal.startsWith('tcr_');
    const payload = {
        descricao:      document.getElementById('fDesc').value.trim(),
        valor:          parseCurrency(document.getElementById('fValor').value),
        data:           document.getElementById('fData').value,
        categoria_id:   document.getElementById('fCat').value    || null,
        conta_id:       document.getElementById('fConta').value  || null,
        cartao_id:      document.getElementById('fCartao')?.value || null,
        responsavel_id: !_isTcr && _fRespVal ? parseInt(_fRespVal) : null,
        terceiro_id:    _isTcr ? parseInt(_fRespVal.replace('tcr_', '')) : null,
        status:         document.getElementById('fStatus').value,
        observacao:     document.getElementById('fObs').value.trim(),
        comprovante_path: document.getElementById('fComprovantePath').value || null,
    };

    if (id) payload.id = parseInt(id);

    try {
        const res  = await fetch('backend/api/receitas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro || 'Erro ao salvar');
        if (!id) {
            localStorage.setItem('financeos_receita_last', JSON.stringify({
                categoria_id: document.getElementById('fCat').value   || '',
                conta_id:     document.getElementById('fConta').value || '',
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

// ── Marcar como recebido ─────────────────────────────────
async function marcarRecebido(id) {
    try {
        const res  = await fetch('backend/api/receitas.php', {
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
function excluirReceita(id) {
    const tx   = _lancamentos.find(t => +t.id === +id);
    const desc = tx ? `"${tx.descricao}"` : 'esta receita';
    confirmar('Excluir receita', `Excluir ${desc}? Esta ação não pode ser desfeita.`, async () => {
        try {
            const res  = await fetch(`backend/api/receitas.php?id=${id}`, { method: 'DELETE' });
            const json = await res.json();
            if (!json.success) throw new Error(json.erro || 'Erro ao excluir');
            toast(json.msg || 'Receita excluída.', 'success');
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

document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharModal(); });

// ── Persistência de filtros ───────────────────────────────
const _FILTRO_KEY = 'financeos_receitas_filtro';

function salvarFiltro() {
    localStorage.setItem(_FILTRO_KEY, JSON.stringify({
        mes:    document.getElementById('filMes').value,
        ano:    document.getElementById('filAno').value,
        cat:    document.getElementById('filCat').value,
        status: document.getElementById('filStatus').value,
        resp:   _getRespIds(),
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
        if (f.resp) {
            _respSelecionados = new Set(f.resp.split(',').filter(Boolean));
            _renderRespChips();
        }
        if (f.de)   set('filDe',  f.de);
        if (f.ate)  set('filAte', f.ate);
        if (f.modo) setModoFiltro(f.modo, false);
    } catch (_) {}
}

// ── Templates ─────────────────────────────────────────────────
let _templates = [];

async function abrirTemplates() {
    document.getElementById('modalTemplates').classList.add('open');
    document.getElementById('templatesList').innerHTML =
        '<div class="empty-state"><i class="fa-solid fa-spinner fa-spin"></i></div>';
    try {
        const res  = await fetch('backend/api/templates.php?tipo=receita');
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        _templates = json.dados;
        renderTemplates(json.dados);
    } catch (err) {
        document.getElementById('templatesList').innerHTML =
            `<div style="color:var(--rose);padding:1rem;text-align:center">${esc(err.message)}</div>`;
    }
}

function fecharTemplates() { document.getElementById('modalTemplates').classList.remove('open'); }

function renderTemplates(lista) {
    const wrap = document.getElementById('templatesList');
    if (!lista.length) {
        wrap.innerHTML = `<div class="empty-state">
            <i class="fa-solid fa-layer-group fa-2x" style="margin-bottom:.75rem;display:block"></i>
            <div class="text-sm">Nenhum template salvo ainda.</div>
            <div class="text-xs" style="margin-top:.3rem">Preencha uma receita e clique em "Salvar Template".</div>
        </div>`;
        return;
    }
    wrap.innerHTML = lista.map(t => {
        const catBadge = t.cat_nome
            ? `<span class="badge" style="background:${t.cat_cor||'#334155'}22;color:${t.cat_cor||'#94a3b8'};font-size:.7rem">${esc(t.cat_nome)}</span>`
            : '';
        return `<div style="background:var(--bg-700);border:1px solid var(--border);border-radius:var(--radius);
                            padding:.75rem 1rem;display:flex;align-items:center;gap:.75rem">
            <div style="flex:1;min-width:0">
                <div class="fw-600 text-sm">${esc(t.nome)}</div>
                <div class="text-xs text-muted">${esc(t.descricao)} &nbsp;·&nbsp; ${t.valor ? brl(t.valor) : '—'} &nbsp;·&nbsp; ${esc(t.conta_nome || '—')} ${catBadge}</div>
            </div>
            <div class="d-flex gap-1">
                <button class="btn btn-success btn-sm" onclick="aplicarTemplate(${+t.id})">Usar</button>
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
    if (t.categoria_id) document.getElementById('fCat').value   = t.categoria_id;
    if (t.conta_id)     document.getElementById('fConta').value = t.conta_id;
    if (t.responsavel_id && document.getElementById('fResp'))
        document.getElementById('fResp').value = t.responsavel_id;
    if (t.observacao)   document.getElementById('fObs').value   = t.observacao;
    fetch('backend/api/templates.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ acao: 'registrar_uso', id }),
    });
    fecharTemplates();
    toast(`Template "${t.nome}" aplicado.`, 'success');
}

async function salvarComoTemplate() {
    const desc = document.getElementById('fDesc').value.trim();
    if (!desc) { toast('Preencha a descrição antes de salvar o template.', 'warning'); return; }
    const nome = prompt('Nome do template:', desc);
    if (!nome) return;
    const _tplRespVal = document.getElementById('fResp')?.value || '';
    const payload = {
        acao: 'salvar', tipo: 'receita', nome: nome.trim(), descricao: desc,
        valor:          parseCurrency(document.getElementById('fValor').value) || null,
        categoria_id:   document.getElementById('fCat').value    || null,
        conta_id:       document.getElementById('fConta').value  || null,
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

document.addEventListener('DOMContentLoaded', () => {
    _initPeriodoDatas();
    restaurarFiltro();
    carregarDados();
    carregarAtalhosReceita();
    initAutocomplete('fDesc', 'receita');
});
</script>

<!-- ── Bulk action bar ───────────────────────────────────── -->
<div id="bulkBarRec" class="bulk-bar" style="display:none">
    <i class="fa-solid fa-square-check" style="color:var(--indigo);font-size:1rem"></i>
    <span id="bulkCountRec" class="bulk-bar-count">0 selecionados</span>
    <div style="flex:1"></div>
    <button class="bulk-bar-btn" onclick="abrirModalBulkEditRec()">
        <i class="fa-solid fa-pen-to-square"></i> Editar selecionados
    </button>
    <button class="bulk-bar-btn danger" onclick="excluirSelecionados()">
        <i class="fa-solid fa-trash"></i> Excluir selecionados
    </button>
    <button class="bulk-bar-btn ghost" onclick="limparSelecao()">
        <i class="fa-solid fa-xmark"></i> Cancelar
    </button>
</div>

<!-- ── Modal editar campos em lote ──────────────────────── -->
<div id="modalBulkEditRec" class="bulk-modal-overlay" style="display:none"
     onclick="if(event.target===this)fecharModalBulkEditRec()">
    <div class="bulk-modal-box bulk-edit-box">
        <div class="bulk-edit-header">
            <div class="bulk-edit-title">
                <i class="fa-solid fa-pen-to-square" style="color:var(--indigo);margin-right:.4rem"></i> Editar em lote
            </div>
            <div class="bulk-edit-hint">
                Marque os campos que deseja alterar. Só os campos marcados são aplicados aos lançamentos selecionados.
            </div>
        </div>

        <div class="bulk-edit-body">
            <label class="bulk-field-row">
                <span class="bulk-field-check">
                    <input type="checkbox" id="bkChkStatusRec" class="bulk-toggle" data-target="bkStatusRec" onchange="_bulkToggle(this)">
                    Status
                </span>
                <select id="bkStatusRec" class="form-control" disabled>
                    <option value="pago">Recebido</option>
                    <option value="pendente">Pendente</option>
                    <option value="cancelado">Cancelado</option>
                </select>
            </label>

            <label class="bulk-field-row">
                <span class="bulk-field-check">
                    <input type="checkbox" id="bkChkCatRec" class="bulk-toggle" data-target="bkCatRec" onchange="_bulkToggle(this)">
                    Categoria
                </span>
                <select id="bkCatRec" class="form-control" disabled>
                    <option value="">— Sem categoria —</option>
                    <?php foreach ($_recPais as $rp): $rs = $_recSubs[$rp['id']] ?? []; if ($rs): ?>
                    <optgroup label="<?= htmlspecialchars($rp['nome']) ?>">
                        <?php foreach ($rs as $rsub): ?>
                        <option value="<?= $rsub['id'] ?>"><?= htmlspecialchars($rsub['nome']) ?></option>
                        <?php endforeach ?>
                    </optgroup>
                    <?php else: ?>
                    <option value="<?= $rp['id'] ?>"><?= htmlspecialchars($rp['nome']) ?></option>
                    <?php endif; endforeach ?>
                </select>
            </label>

            <label class="bulk-field-row">
                <span class="bulk-field-check">
                    <input type="checkbox" id="bkChkContaRec" class="bulk-toggle" data-target="bkContaRec" onchange="_bulkToggle(this)">
                    Conta de destino
                </span>
                <select id="bkContaRec" class="form-control" disabled>
                    <option value="">— Sem conta —</option>
                    <?php foreach ($contasRec as $ct): ?>
                    <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['nome']) ?></option>
                    <?php endforeach ?>
                </select>
            </label>

            <?php if (!empty($respRec) || !empty($terceirosRec)): ?>
            <label class="bulk-field-row">
                <span class="bulk-field-check">
                    <input type="checkbox" id="bkChkRespRec" class="bulk-toggle" data-target="bkRespRec" onchange="_bulkToggle(this)">
                    Pessoa / Responsável
                </span>
                <select id="bkRespRec" class="form-control" disabled>
                    <option value="">— Sem responsável —</option>
                    <?php if (!empty($respRec)): ?>
                    <optgroup label="Responsáveis">
                        <?php foreach ($respRec as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nome']) ?></option>
                        <?php endforeach ?>
                    </optgroup>
                    <?php endif ?>
                    <?php if (!empty($terceirosRec)): ?>
                    <optgroup label="Terceiros">
                        <?php foreach ($terceirosRec as $r): ?>
                        <option value="tcr_<?= $r['id'] ?>"><?= htmlspecialchars($r['nome']) ?></option>
                        <?php endforeach ?>
                    </optgroup>
                    <?php endif ?>
                </select>
            </label>
            <?php endif ?>

            <label class="bulk-field-row bulk-field-full" style="margin-bottom:0">
                <span class="bulk-field-check">
                    <input type="checkbox" id="bkChkObsRec" class="bulk-toggle" data-target="bkObsRec" onchange="_bulkToggle(this)">
                    Observação
                </span>
                <textarea id="bkObsRec" class="form-control" rows="2" placeholder="Substitui a observação atual" disabled></textarea>
            </label>

            <div class="bulk-field-row bulk-field-full text-xs text-muted" style="margin-bottom:0">
                <i class="fa-solid fa-circle-info fa-xs"></i> Cartão (recarga de vale) não é editável em lote — use a edição individual.
            </div>
        </div>

        <div class="bulk-edit-footer">
            <button onclick="fecharModalBulkEditRec()"
                    style="padding:.35rem .85rem;font-size:.82rem;font-weight:600;border-radius:var(--radius);
                           border:1px solid var(--border);background:transparent;color:var(--text-400);cursor:pointer">
                Cancelar
            </button>
            <button onclick="confirmarEditarBulkRec()"
                    style="padding:.35rem .85rem;font-size:.82rem;font-weight:600;border-radius:var(--radius);
                           border:none;background:var(--indigo);color:#fff;cursor:pointer">
                Aplicar
            </button>
        </div>
    </div>
</div>
