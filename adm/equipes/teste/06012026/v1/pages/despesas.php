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
/* ── Modal ───────────────────────────────────────────────── */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.65);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.modal-overlay.open { display: flex; }
.modal-box {
    background: var(--bg-800);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    width: 100%;
    max-width: 640px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-lg);
    animation: modalIn .18s ease;
}
@keyframes modalIn {
    from { opacity:0; transform:translateY(-10px) scale(.97); }
    to   { opacity:1; transform:none; }
}
.modal-header {
    padding: 1.25rem 1.5rem 1rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.modal-title  { font-size: 1rem; font-weight: 700; }
.modal-body   { padding: 1.5rem; overflow-y: auto; flex: 1; }
.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: .75rem;
    flex-shrink: 0;
}
/* ── Barra de filtros ────────────────────────────────────── */
.filters-bar {
    background: var(--bg-800);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1rem 1.25rem;
    margin-bottom: 1.25rem;
    display: flex;
    flex-wrap: wrap;
    gap: .75rem;
    align-items: flex-end;
}
.filter-group { display: flex; flex-direction: column; gap: .35rem; }
.filter-label {
    font-size: .7rem; font-weight: 600;
    color: var(--text-600);
    text-transform: uppercase;
    letter-spacing: .05em;
}
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
</style>

<!-- ── Cabeçalho ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <div class="page-title">Despesas</div>
        <div class="page-sub" id="pageSub">Carregando...</div>
    </div>
    <button class="btn btn-primary btn-sm" onclick="abrirModal()">
        <i class="fa-solid fa-plus"></i> Nova Despesa
    </button>
</div>

<!-- ── Filtros ────────────────────────────────────────────── -->
<div class="filters-bar">
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
                <?php for ($y = $anoAtual + 5; $y >= $anoAtual - 4; $y--): ?>
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
            <?php foreach ($_desPais as $p): $s = $_desSubs[$p['id']] ?? []; if ($s): ?>
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
            <option value="pago">Pago</option>
            <option value="pendente">Pendente</option>
            <option value="cancelado">Cancelado</option>
        </select>
    </div>
    <?php if (!empty($respDes) || !empty($terceirosDes)): ?>
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
                <?php if (!empty($respDes)): ?>
                <hr class="resp-sep">
                <div style="padding:.3rem .875rem;font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-600)">Meus responsáveis</div>
                <label class="resp-item">
                    <input type="checkbox" class="resp-cb" value="-1" onchange="_onRespCb(this)">
                    <div class="resp-avatar-sm" style="background:#64748b22;color:#64748b">
                        <i class="fa-solid fa-user-slash fa-xs"></i>
                    </div>
                    <span>Sem responsável</span>
                </label>
                <?php foreach ($respDes as $r):
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
                <?php if (!empty($terceirosDes)): ?>
                <hr class="resp-sep">
                <div style="padding:.3rem .875rem;font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--amber)">Terceiros</div>
                <?php foreach ($terceirosDes as $r):
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
    <div class="filter-group">
        <span class="filter-label">Conta</span>
        <select id="filConta" class="form-control" style="min-width:140px" onchange="filtroCarregar()">
            <option value="">Todas</option>
            <?php foreach ($contasDes as $ct): ?>
            <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['nome']) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="filter-group">
        <span class="filter-label">Cartão</span>
        <select id="filCartaoF" class="form-control" style="min-width:140px" onchange="filtroCarregar()">
            <option value="">Todos</option>
            <?php foreach ($cartoesDes as $cc): ?>
            <option value="<?= $cc['id'] ?>"><?= htmlspecialchars($cc['nome']) ?></option>
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
    <div class="filter-group" style="flex:1;min-width:180px">
        <span class="filter-label">Buscar</span>
        <input type="search" id="filBusca" class="form-control"
               placeholder="Descrição da despesa..." oninput="debounceCarregar()">
    </div>
    <div class="filter-group" style="min-width:140px">
        <span class="filter-label">Tag</span>
        <input type="text" id="filTag" class="form-control"
               placeholder="Ex: viagem" oninput="debounceCarregar()">
    </div>
</div>

<!-- ── KPIs ──────────────────────────────────────────────── -->
<div class="kpi-grid" style="margin-bottom:1.25rem">
    <div class="kpi-card rose">
        <div class="kpi-header">
            <div class="kpi-label">Total do Mês</div>
            <div class="kpi-icon rose"><i class="fa-solid fa-arrow-trend-down"></i></div>
        </div>
        <div class="kpi-value" id="kpiTotal">—</div>
        <div class="kpi-trend neutral">
            <i class="fa-solid fa-circle-info fa-xs"></i> Todas as despesas
        </div>
    </div>
    <div class="kpi-card emerald">
        <div class="kpi-header">
            <div class="kpi-label">Pago</div>
            <div class="kpi-icon emerald"><i class="fa-solid fa-circle-check"></i></div>
        </div>
        <div class="kpi-value" id="kpiPago">—</div>
        <div class="kpi-trend neutral">
            <i class="fa-solid fa-circle-info fa-xs"></i> Despesas quitadas
        </div>
    </div>
    <div class="kpi-card amber">
        <div class="kpi-header">
            <div class="kpi-label">Pendente</div>
            <div class="kpi-icon amber"><i class="fa-solid fa-clock"></i></div>
        </div>
        <div class="kpi-value" id="kpiPendente">—</div>
        <div class="kpi-trend neutral">
            <i class="fa-solid fa-circle-info fa-xs"></i> A pagar
        </div>
    </div>
    <div class="kpi-card indigo">
        <div class="kpi-header">
            <div class="kpi-label">Lançamentos</div>
            <div class="kpi-icon indigo"><i class="fa-solid fa-receipt"></i></div>
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
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:36px;padding-right:0"><input type="checkbox" id="cbTodos" style="accent-color:var(--indigo);width:15px;height:15px;cursor:pointer" onclick="toggleTodos(this)" title="Selecionar todos"></th>
                    <th class="sortable" onclick="sortarTabela('descricao')">Descrição <i class="fa-solid fa-arrows-up-down fa-xs sort-ic"></i></th>
                    <th class="sortable" onclick="sortarTabela('cat_nome')">Categoria <i class="fa-solid fa-arrows-up-down fa-xs sort-ic"></i></th>
                    <th>Conta / Cartão</th>
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
    <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
        <span class="text-sm text-muted" id="pagInfo">—</span>
        <div class="d-flex align-center gap-1">
            <select id="porPagina" class="form-control" style="width:auto;font-size:.8rem"
                    onchange="_paginaDes=1;carregarDados()">
                <option value="25">25 / pág</option>
                <option value="50">50 / pág</option>
                <option value="100">100 / pág</option>
            </select>
            <div id="pagBtns" class="d-flex gap-1"></div>
        </div>
    </div>
</div>

<!-- ── Modal Nova / Editar Despesa ───────────────────────── -->
<div id="modalOverlay" class="modal-overlay" onclick="if(event.target===this)fecharModal()">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title" id="modalTitulo">Nova Despesa</div>
            <div class="d-flex gap-1 align-center">
                <button type="button" class="btn btn-ghost btn-sm" onclick="abrirTemplates()" title="Usar template">
                    <i class="fa-solid fa-layer-group fa-xs"></i> Templates
                </button>
                <button type="button" class="btn-icon" onclick="fecharModal()" title="Fechar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <form id="formDespesa" onsubmit="salvarDespesa(event)">
            <div class="modal-body">
                <input type="hidden" id="editId" value="">

                <!-- Descrição -->
                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label" for="fDesc">
                        Descrição <span style="color:var(--rose)">*</span>
                    </label>
                    <input type="text" id="fDesc" class="form-control"
                           placeholder="Ex: Supermercado, Aluguel, Internet..." required maxlength="200">
                </div>

                <!-- Valor | Data | Status -->
                <div class="form-grid form-grid-3" style="margin-bottom:.75rem">
                    <div class="form-group">
                        <label class="form-label" for="fValor">
                            Valor (R$) <span style="color:var(--rose)">*</span>
                        </label>
                        <input type="text" id="fValor" class="form-control"
                               placeholder="0,00" inputmode="numeric" data-currency required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="fData">
                            Data da Compra <span style="color:var(--rose)">*</span>
                        </label>
                        <input type="date" id="fData" class="form-control" required onchange="atualizarInfoFatura()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="fStatus">Status</label>
                        <select id="fStatus" class="form-control">
                            <option value="pago">Pago</option>
                            <option value="pendente">Pendente</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                </div>

                <!-- Data de Vencimento -->
                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label" for="fDataVenc">
                        Data de Vencimento
                        <span id="fDataVencBadge" style="display:none;font-size:.7rem;font-weight:400;color:var(--indigo);margin-left:.3rem">
                            <i class="fa-solid fa-credit-card fa-xs"></i> calculado pela fatura
                        </span>
                    </label>
                    <input type="date" id="fDataVenc" class="form-control" style="max-width:210px" onchange="_onDataVencChange()">
                    <div class="text-xs text-muted" id="fDataVencHint" style="margin-top:.25rem">
                        <i class="fa-solid fa-circle-info fa-xs"></i>
                        Opcional para despesas comuns. Para cartão, preenchido automaticamente com o vencimento da fatura.
                    </div>
                </div>

                <!-- Categoria | Conta | Cartão -->
                <div class="form-grid form-grid-3" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label" for="fCat">Categoria</label>
                        <select id="fCat" class="form-control">
                            <?= optGroupDes($_desPais, $_desSubs) ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="fConta">Conta</label>
                        <select id="fConta" class="form-control">
                            <option value="">— Sem conta —</option>
                            <?php foreach ($contasDes as $ct): ?>
                            <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['nome']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="fCartao">Cartão</label>
                        <select id="fCartao" class="form-control" onchange="_faturaManual=null;atualizarInfoFatura()">
                            <option value="">— Sem cartão —</option>
                            <?php foreach ($cartoesDes as $cc): ?>
                            <option value="<?= $cc['id'] ?>"
                                    data-fechamento="<?= (int)$cc['dia_fechamento'] ?>"
                                    data-vencimento="<?= (int)$cc['dia_vencimento'] ?>">
                                <?= htmlspecialchars($cc['nome']) ?>
                            </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>

                <!-- Campos ocultos da fatura + banner -->
                <input type="hidden" id="fMesFatura" value="">
                <input type="hidden" id="fAnoFatura"  value="">

                <div id="faturaInfoBanner" style="display:none;background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.25);border-radius:var(--radius);padding:.55rem .875rem;margin-bottom:1.1rem;font-size:.82rem">
                    <div style="display:flex;align-items:center;gap:.45rem;flex-wrap:wrap">
                        <i class="fa-solid fa-calendar-check fa-sm" style="color:var(--indigo)"></i>
                        <span>Esta compra entrará na</span>
                        <strong id="faturaInfoLabel" style="color:var(--indigo)">—</strong>
                        <span class="text-muted text-xs" id="faturaInfoVenc"></span>
                        <button type="button" id="btnFaturaAuto"
                                onclick="_faturaManual=null;atualizarInfoFatura()"
                                class="btn btn-ghost btn-sm"
                                style="display:none;padding:.1rem .45rem;font-size:.7rem">
                            <i class="fa-solid fa-rotate-left fa-xs"></i> Automático
                        </button>
                        <button type="button" onclick="toggleFaturaOpcoes()"
                                class="btn btn-ghost btn-sm"
                                style="padding:.1rem .45rem;font-size:.7rem;margin-left:auto"
                                title="Alterar mês da fatura">
                            <i class="fa-solid fa-pencil fa-xs"></i> Mudar
                        </button>
                    </div>
                    <div id="faturaOpcoes" style="display:none;margin-top:.5rem;flex-wrap:wrap;gap:.35rem"></div>
                </div>

                <!-- Responsável + Divisão -->
                <?php if (!empty($respDes) || !empty($terceirosDes)): ?>
                <div id="respWrap" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label" for="fResp">Pessoa / Responsável</label>
                        <select id="fResp" class="form-control">
                            <option value="">— Sem responsável —</option>
                            <?php if (!empty($respDes)): ?>
                            <optgroup label="Responsáveis">
                                <?php foreach ($respDes as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nome']) ?></option>
                                <?php endforeach ?>
                            </optgroup>
                            <?php endif ?>
                            <?php if (!empty($terceirosDes)): ?>
                            <optgroup label="Terceiros">
                                <?php foreach ($terceirosDes as $r): ?>
                                <option value="tcr_<?= $r['id'] ?>"><?= htmlspecialchars($r['nome']) ?></option>
                                <?php endforeach ?>
                            </optgroup>
                            <?php endif ?>
                        </select>
                    </div>

                    <!-- Divisão entre responsáveis -->
                    <div style="margin-top:.75rem">
                        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.82rem;font-weight:600;color:var(--text-400)">
                            <input type="checkbox" id="fDivisaoToggle" onchange="toggleDivisao()"
                                   style="width:14px;height:14px;accent-color:var(--indigo)">
                            Dividir entre responsáveis
                        </label>
                        <div id="divisaoWrap" style="display:none;margin-top:.75rem;background:var(--bg-700);
                             border:1px solid var(--border);border-radius:var(--radius);padding:.875rem">
                            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
                                        color:var(--text-600);margin-bottom:.6rem">
                                Selecione quem participará (valor será dividido igualmente):
                            </div>
                            <div id="divisaoCheckboxes" style="display:flex;flex-direction:column;gap:.4rem">
                                <label style="display:flex;align-items:center;gap:.5rem;font-size:.84rem;cursor:pointer">
                                    <input type="checkbox" value="0" class="divisao-cb"
                                           style="width:14px;height:14px;accent-color:var(--indigo)">
                                    <span style="color:var(--text-400)">Eu (sem responsável)</span>
                                </label>
                                <?php foreach ($respDes as $r): ?>
                                <label style="display:flex;align-items:center;gap:.5rem;font-size:.84rem;cursor:pointer">
                                    <input type="checkbox" value="<?= $r['id'] ?>" class="divisao-cb"
                                           style="width:14px;height:14px;accent-color:var(--indigo)">
                                    <span style="display:inline-flex;align-items:center;gap:.35rem">
                                        <span style="width:8px;height:8px;border-radius:50%;background:<?= htmlspecialchars($r['cor']) ?>;flex-shrink:0;display:inline-block"></span>
                                        <?= htmlspecialchars($r['nome']) ?>
                                    </span>
                                </label>
                                <?php endforeach ?>
                            </div>
                            <div id="divisaoInfo" class="text-xs text-muted" style="margin-top:.6rem"></div>
                        </div>
                    </div>
                </div>
                <?php endif ?>

                <!-- Parcelas (apenas novo lançamento) -->
                <div id="parcelasWrap" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label" for="fParcelas">Parcelar em</label>
                        <div class="d-flex align-center gap-1">
                            <input type="number" id="fParcelas" class="form-control"
                                   value="1" min="1" max="48" style="max-width:90px">
                            <span class="text-sm text-muted">parcelas &nbsp;·&nbsp; 1 = sem parcelamento</span>
                        </div>
                    </div>
                </div>

                <!-- Observação -->
                <div class="form-group">
                    <label class="form-label" for="fObs">Observação</label>
                    <textarea id="fObs" class="form-control" rows="2"
                              placeholder="Notas adicionais (opcional)"></textarea>
                </div>

                <!-- Tags -->
                <div class="form-group" style="margin-top:.75rem">
                    <label class="form-label" for="fTags">Tags</label>
                    <input type="text" id="fTags" class="form-control"
                           placeholder="viagem, trabalho, família..."
                           maxlength="255">
                    <div class="text-xs text-muted" style="margin-top:.25rem">
                        <i class="fa-solid fa-circle-info fa-xs"></i> Separadas por vírgula
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="fecharModal()">Cancelar</button>
                <button type="button" class="btn btn-ghost btn-sm" onclick="salvarComoTemplate()" id="btnTemplate"
                        title="Salvar campos atuais como template para reutilização">
                    <i class="fa-solid fa-bookmark fa-xs"></i> Salvar Template
                </button>
                <button type="submit" class="btn btn-primary" id="btnSalvar">
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
            <div class="modal-title"><i class="fa-solid fa-layer-group fa-sm" style="color:var(--indigo)"></i> Templates de Despesa</div>
            <button type="button" class="btn-icon" onclick="fecharTemplates()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding:.75rem">
            <div id="templatesList" style="display:flex;flex-direction:column;gap:.5rem">
                <div style="text-align:center;padding:2rem;color:var(--text-600)">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Antecipar / Pagar Parcela de Empréstimo ─── -->
<div id="modalAntecipar" class="modal-overlay" onclick="if(event.target===this)fecharModalAntecipar()">
    <div class="modal-box" style="max-width:480px">
        <div class="modal-header">
            <div class="modal-title" id="antTitulo">Pagamento de Parcela</div>
            <button type="button" class="btn-icon" onclick="fecharModalAntecipar()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">

            <!-- Cabeçalho do empréstimo -->
            <div style="background:var(--bg-700);border-radius:var(--radius);padding:.875rem 1rem;margin-bottom:1.25rem">
                <div class="fw-700 text-sm" id="antEmpNome">—</div>
                <div class="text-xs text-muted" id="antParcelaInfo" style="margin-top:.25rem">—</div>
            </div>

            <!-- Valores de referência -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:1.25rem">
                <div style="background:var(--bg-700);border-radius:var(--radius);padding:.75rem">
                    <div class="text-xs text-muted">Valor da parcela</div>
                    <div class="fw-700" id="antValorOriginalRef" style="margin-top:.2rem">—</div>
                </div>
                <div style="background:var(--bg-700);border-radius:var(--radius);padding:.75rem">
                    <div class="text-xs text-muted">Saldo devedor</div>
                    <div class="fw-700 text-rose" id="antSaldoRef" style="margin-top:.2rem">—</div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:1.1rem">
                <label class="form-label">Data do pagamento</label>
                <input type="date" id="antData" class="form-control">
            </div>

            <div class="form-group" style="margin-bottom:1.25rem">
                <label class="form-label">
                    Valor pago (R$)
                    <span style="font-size:.73rem;color:var(--text-500);font-weight:400">
                        — informe o valor negociado com o banco
                    </span>
                </label>
                <input type="text" id="antValorPago" class="form-control" data-currency inputmode="numeric">
            </div>

            <!-- Comparativo em tempo real -->
            <div id="antComparativo" style="background:var(--bg-700);border-radius:var(--radius);padding:.875rem 1rem;display:none">
                <div class="text-xs fw-600 text-muted" style="margin-bottom:.6rem;text-transform:uppercase;letter-spacing:.05em">
                    Comparativo
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:.2rem 0;font-size:.85rem">
                    <span class="text-muted">Pagaria (na data certa)</span>
                    <span id="antCmpOriginal" class="fw-600">—</span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:.2rem 0;font-size:.85rem">
                    <span class="text-muted">Vai pagar agora</span>
                    <span id="antCmpPago" class="fw-600">—</span>
                </div>
                <!-- Economia -->
                <div id="antEconWrap" style="border-top:1px solid var(--border);margin-top:.5rem;padding-top:.6rem;display:none">
                    <div style="display:flex;justify-content:space-between;font-size:.9rem">
                        <span class="fw-700">💰 Economia</span>
                        <span id="antEconValor" class="fw-700" style="color:var(--emerald)">—</span>
                    </div>
                    <div class="text-xs text-muted" style="margin-top:.2rem" id="antEconPct"></div>
                </div>
                <!-- Sem desconto -->
                <div id="antIgualWrap" style="border-top:1px solid var(--border);margin-top:.5rem;padding-top:.6rem;display:none">
                    <div class="text-xs text-muted">Valor igual ao original — sem desconto na antecipação</div>
                </div>
                <!-- Pagando mais -->
                <div id="antSobraWrap" style="border-top:1px solid var(--border);margin-top:.5rem;padding-top:.6rem;display:none">
                    <div style="display:flex;justify-content:space-between;font-size:.85rem">
                        <span class="text-rose">Acima do valor original</span>
                        <span id="antSobraValor" class="fw-600 text-rose">—</span>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-top:1.25rem">
                <label class="form-label">Conta de débito</label>
                <select id="antConta" class="form-control">
                    <option value="">— Não débitar —</option>
                    <?php foreach ($contasDes as $ct): ?>
                    <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['nome']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="fecharModalAntecipar()">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btnConfirmarAnt" onclick="confirmarAntecipacao()">
                <i class="fa-solid fa-circle-check"></i> Confirmar
            </button>
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

// ── Modo de filtro: Mês/Ano ou Período ───────────────────
let _modoFiltro = 'mes';

function setModoFiltro(modo, carregar = true) {
    _modoFiltro = modo;
    document.getElementById('filtroMes').style.display     = modo === 'mes'     ? 'flex' : 'none';
    document.getElementById('filtroPeriodo').style.display = modo === 'periodo' ? 'flex' : 'none';
    const bm = document.getElementById('fBtnMes');
    const bp = document.getElementById('fBtnPer');
    bm.style.background = modo === 'mes'     ? 'var(--indigo)' : 'transparent';
    bm.style.color      = modo === 'mes'     ? '#fff'          : 'var(--text-500)';
    bp.style.background = modo === 'periodo' ? 'var(--indigo)' : 'transparent';
    bp.style.color      = modo === 'periodo' ? '#fff'          : 'var(--text-500)';
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
            if (s > 1) h += `<span class="text-muted text-sm" style="padding:0 .2rem">…</span>`;
            for (let p = s; p <= e; p++)
                h += `<button class="btn btn-sm ${p===_paginaDes?'btn-primary':'btn-ghost'}" onclick="(${ir})(${p})">${p}</button>`;
            if (e < totPags) h += `<span class="text-muted text-sm" style="padding:0 .2rem">…</span>`;
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
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:3.5rem 1rem">
            <i class="fa-solid fa-receipt fa-3x" style="display:block;margin-bottom:1rem;opacity:.25;color:var(--text-400)"></i>
            <div style="font-weight:700;font-size:1rem;color:var(--text-200);margin-bottom:.4rem">
                ${_filAtivo ? 'Nenhum resultado encontrado' : 'Nenhuma despesa neste período'}
            </div>
            <div style="font-size:.85rem;color:var(--text-600);margin-bottom:1.5rem">
                ${_filAtivo ? 'Tente remover os filtros ou ampliar o período de busca.' : 'Registre sua primeira despesa do mês e mantenha o controle.'}
            </div>
            ${!_filAtivo ? `<button class="btn btn-primary btn-sm" onclick="abrirModal()">
                <i class="fa-solid fa-plus"></i> Adicionar despesa
            </button>` : `<button class="btn btn-ghost btn-sm" onclick="document.getElementById('filBusca').value='';document.getElementById('filCat').value='';document.getElementById('filStatus').value='';carregarDados()">
                <i class="fa-solid fa-filter-slash"></i> Limpar filtros
            </button>`}
        </td></tr>`;
        return;
    }

    _selecionados.clear();
    atualizarBulkBar();
    const cbAll = document.getElementById('cbTodos');
    if (cbAll) cbAll.checked = false;

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

        // ── Coluna de valor: risca o original se houve desconto ─
        let valorCell = `<span class="fw-600 text-rose">${brl(tx.valor)}</span>`;
        if (foiAntecip && tx.valor_original_parcela) {
            const orig = parseFloat(tx.valor_original_parcela);
            const pago = parseFloat(tx.valor);
            if (orig - pago > 0.005) {
                valorCell = `
                    <div class="fw-600 text-rose">${brl(pago)}</div>
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
                btnPago = `<button class="btn-icon" onclick="abrirAntecipar(${+tx.id})" title="${title}"
                                   style="width:28px;height:28px;font-size:.75rem;color:${cor}">
                               <i class="fa-solid ${icone}"></i>
                           </button>`;
            } else {
                btnPago = `<button class="btn-icon" onclick="marcarPago(${+tx.id})" title="Marcar como pago"
                                   style="width:28px;height:28px;font-size:.75rem;color:var(--emerald)">
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
                        ${parcelas}${empBadge}${divisaoBadge}${tagsBadges}
                    </div>
                </div>
            </td>
            <td>${catBadge}</td>
            <td>${contaCartao}</td>
            <td>${respBadge}</td>
            <td class="text-sm text-muted">
                <div>${fmtData(tx.data)}</div>
                ${tx.cartao_id && tx.fat_mes
                    ? `<div style="font-size:.63rem;color:var(--indigo);margin-top:.18rem;white-space:nowrap;line-height:1.4">
                           <i class="fa-solid fa-calendar-check fa-xs"></i>
                           Fat. ${_MESES_ABREV[+tx.fat_mes - 1]}/${String(tx.fat_ano || '').slice(-2)}
                           ${tx.fat_vencimento ? `· vence ${fmtData(tx.fat_vencimento)}` : ''}
                       </div>`
                    : tx.data_vencimento && tx.data_vencimento !== tx.data
                        ? `<div style="font-size:.63rem;color:var(--amber);margin-top:.18rem;white-space:nowrap">
                               <i class="fa-solid fa-calendar-exclamation fa-xs"></i> vence ${fmtData(tx.data_vencimento)}
                           </div>`
                        : ''}
            </td>
            <td><span class="badge ${esc(tx.status)}">${foiAntecip ? '⚡ Antecipado' : ucFirst(tx.status)}</span></td>
            <td class="text-right">${valorCell}</td>
            <td>
                <div class="d-flex gap-1" style="justify-content:flex-end">
                    ${btnPago}
                    <button class="btn-icon" onclick="abrirModal(${+tx.id})" title="Editar"
                            style="width:28px;height:28px;font-size:.75rem">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button class="btn-icon" onclick="excluirDespesa(${+tx.id})" title="Excluir"
                            style="width:28px;height:28px;font-size:.75rem;color:var(--rose)">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');
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

function abrirModalBulkStatusDes() {
    if (!_selecionados.size) return;
    document.getElementById('modalBulkStatusDes').style.display = 'flex';
}

function fecharModalBulkStatusDes() {
    document.getElementById('modalBulkStatusDes').style.display = 'none';
}

async function confirmarAlterarStatusDes() {
    const novoStatus = document.getElementById('bulkNovoStatusDes').value;
    fecharModalBulkStatusDes();
    try {
        const res  = await fetch('backend/api/despesas.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ acao: 'alterar_status_bulk', ids: [..._selecionados], status: novoStatus }),
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
        }
    } else {
        document.getElementById('modalTitulo').textContent = 'Nova Despesa';
        document.getElementById('fTags').value = '';
        const last = JSON.parse(localStorage.getItem('financeos_despesa_last') || '{}');
        if (last.categoria_id !== undefined) document.getElementById('fCat').value    = last.categoria_id;
        if (last.conta_id     !== undefined) document.getElementById('fConta').value  = last.conta_id;
        if (last.cartao_id    !== undefined) document.getElementById('fCartao').value = last.cartao_id;
        if (last.status       !== undefined) document.getElementById('fStatus').value = last.status;
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
function esc(s) {
    return String(s || '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function fmtData(s) {
    if (!s) return '—';
    const d = s.split('T')[0].split('-');
    return `${d[2]}/${d[1]}/${d[0]}`;
}

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
        '<div style="text-align:center;padding:2rem;color:var(--text-600)"><i class="fa-solid fa-spinner fa-spin"></i></div>';
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
            <div style="text-align:center;padding:2rem;color:var(--text-600)">
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
                <button class="btn-icon" onclick="excluirTemplate(${+t.id})" title="Remover"
                        style="width:28px;height:28px;font-size:.75rem;color:var(--rose)">
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
    const [a, m, d] = data.split('-').map(Number);
    // Mês de fechamento
    let mClose = m, aClose = a;
    if (d > diaFechamento) {
        mClose = m === 12 ? 1 : m + 1;
        aClose = m === 12 ? a + 1 : a;
    }
    // Fatura = mês de PAGAMENTO (padrão brasileiro: "Fatura de Julho" = a que pago em julho)
    const mF = mClose === 12 ? 1 : mClose + 1;
    const aF = mClose === 12 ? aClose + 1 : aClose;
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
    initAutocomplete('fDesc', 'despesa');
});
</script>

<!-- ── Bulk action bar ───────────────────────────────────── -->
<div id="bulkBarDes" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:400;
     background:var(--bg-900,#0f172a);border-top:2px solid var(--indigo);
     padding:.65rem 1.25rem;align-items:center;gap:.75rem;flex-wrap:wrap;box-shadow:0 -4px 24px rgba(0,0,0,.35)">
    <i class="fa-solid fa-square-check" style="color:var(--indigo);font-size:1rem"></i>
    <span id="bulkCountDes" style="font-size:.85rem;font-weight:700;color:var(--text-100)">0 selecionados</span>
    <div style="flex:1"></div>
    <button onclick="abrirModalBulkStatusDes()"
            style="display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .85rem;font-size:.8rem;font-weight:600;
                   border-radius:var(--radius);border:1px solid var(--border);background:transparent;
                   color:var(--text-200);cursor:pointer;transition:var(--ease)">
        <i class="fa-solid fa-tag"></i> Alterar status
    </button>
    <button onclick="excluirSelecionados()"
            style="display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .85rem;font-size:.8rem;font-weight:600;
                   border-radius:var(--radius);border:none;background:var(--rose);
                   color:#fff;cursor:pointer;transition:var(--ease)">
        <i class="fa-solid fa-trash"></i> Excluir selecionados
    </button>
    <button onclick="limparSelecao()"
            style="display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .7rem;font-size:.8rem;font-weight:600;
                   border-radius:var(--radius);border:1px solid var(--border);background:transparent;
                   color:var(--text-500);cursor:pointer;transition:var(--ease)">
        <i class="fa-solid fa-xmark"></i> Cancelar
    </button>
</div>

<!-- ── Modal alterar status em lote ─────────────────────── -->
<div id="modalBulkStatusDes" onclick="if(event.target===this)fecharModalBulkStatusDes()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:500;
            align-items:center;justify-content:center">
    <div style="background:var(--bg-800);border:1px solid var(--border);border-radius:var(--radius-lg);
                padding:1.5rem;width:300px;max-width:90vw">
        <div style="font-size:1rem;font-weight:700;color:var(--text-100);margin-bottom:1rem">
            <i class="fa-solid fa-tag" style="color:var(--indigo);margin-right:.4rem"></i> Alterar status
        </div>
        <div style="font-size:.82rem;color:var(--text-500);margin-bottom:.75rem">
            Novo status para os lançamentos selecionados:
        </div>
        <select id="bulkNovoStatusDes" class="form-control" style="margin-bottom:1.25rem">
            <option value="pago">Pago</option>
            <option value="pendente">Pendente</option>
            <option value="cancelado">Cancelado</option>
        </select>
        <div style="display:flex;gap:.5rem;justify-content:flex-end">
            <button onclick="fecharModalBulkStatusDes()"
                    style="padding:.35rem .85rem;font-size:.82rem;font-weight:600;border-radius:var(--radius);
                           border:1px solid var(--border);background:transparent;color:var(--text-400);cursor:pointer">
                Cancelar
            </button>
            <button onclick="confirmarAlterarStatusDes()"
                    style="padding:.35rem .85rem;font-size:.82rem;font-weight:600;border-radius:var(--radius);
                           border:none;background:var(--indigo);color:#fff;cursor:pointer">
                Aplicar
            </button>
        </div>
    </div>
</div>
