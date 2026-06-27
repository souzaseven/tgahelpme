<?php
$mes    = (int) date('m');
$ano    = (int) date('Y');
$nomesMeses = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho',
               'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
?>

<style>
/* ── Tabs ────────────────────────────────────────────────── */
.tabs {
    display: flex;
    gap: 0;
    border-bottom: 2px solid var(--border);
    margin-bottom: 1.5rem;
    overflow-x: auto;
    scrollbar-width: none;
}
.tab-btn {
    padding: .6rem 1.1rem;
    font-size: .85rem;
    font-weight: 500;
    color: var(--text-400);
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    cursor: pointer;
    white-space: nowrap;
    transition: color .15s, border-color .15s;
}
.tab-btn:hover  { color: var(--text-200); }
.tab-btn.active { color: var(--indigo); border-bottom-color: var(--indigo); }
.tab-pane       { display: none; }
.tab-pane.active{ display: block; animation: fadeIn .18s ease; }
@keyframes fadeIn { from{opacity:0;transform:translateY(4px)} to{opacity:1;transform:none} }
/* ── Helpers visuais ─────────────────────────────────────── */
.stat-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: .5rem .875rem;
    border-bottom: 1px solid var(--border);
    font-size: .85rem;
}
.stat-row:last-child { border-bottom: none; }
.stat-bar-inline {
    height: 6px; border-radius: 99px;
    background: var(--bg-600); flex: 1;
    margin: 0 .875rem; max-width: 120px;
    overflow: hidden;
}
.stat-bar-inline-fill {
    height: 100%; border-radius: 99px;
    background: var(--rose); transition: width .5s ease;
}
/* ── Comparativo titular ─────────────────────────────────── */
.tit-row {
    display: flex; align-items: center; gap: .875rem;
    padding: .875rem 1.25rem;
    border-bottom: 1px solid var(--border);
}
.tit-row:last-child { border-bottom: none; }
.tit-avatar {
    width:36px; height:36px; border-radius:50%;
    background: var(--indigo-soft); color: var(--indigo);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: .85rem; flex-shrink: 0;
}
/* ── Loading state ───────────────────────────────────────── */
.loading-overlay {
    display: flex; align-items: center; justify-content: center;
    padding: 3rem; color: var(--text-600);
    gap: .75rem; font-size: .9rem;
}
/* ── Tabela anual ────────────────────────────────────────── */
.anual-table th, .anual-table td { padding: .55rem .875rem; }
.anual-table td.pos { color: var(--emerald); font-weight: 600; }
.anual-table td.neg { color: var(--rose);    font-weight: 600; }
.anual-table tr.totais-row td { background: var(--bg-700); font-weight: 700; border-top: 2px solid var(--border); }
.anual-table tr.atual-row td  { background: var(--indigo-soft); }
/* ── Patrimônio cards ────────────────────────────────────── */
.pat-group { margin-bottom: 1.25rem; }
.pat-group-title {
    font-size: .7rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .07em; color: var(--text-600);
    padding: .5rem 1rem;
    border-bottom: 1px solid var(--border);
}
.pat-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: .625rem 1rem;
    border-bottom: 1px solid var(--border);
    font-size: .85rem;
}
.pat-item:last-child { border-bottom: none; }
</style>

<!-- ── Cabeçalho ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <div class="page-title">Relatórios & BI</div>
        <div class="page-sub">Análise consolidada de toda a sua vida financeira</div>
    </div>
</div>

<!-- ── Tabs ───────────────────────────────────────────────── -->
<div class="tabs">
    <button class="tab-btn active" onclick="ativarTab('resumo')">
        <i class="fa-solid fa-chart-pie fa-xs"></i> Visão Geral
    </button>
    <button class="tab-btn" onclick="ativarTab('fluxo')">
        <i class="fa-solid fa-chart-bar fa-xs"></i> Fluxo de Caixa
    </button>
    <button class="tab-btn" onclick="ativarTab('categorias')">
        <i class="fa-solid fa-tags fa-xs"></i> Por Categoria
    </button>
    <button class="tab-btn" onclick="ativarTab('patrimonio')">
        <i class="fa-solid fa-scale-balanced fa-xs"></i> Patrimônio
    </button>
    <button class="tab-btn" onclick="ativarTab('anual')">
        <i class="fa-solid fa-table fa-xs"></i> Resumo Anual
    </button>
    <button class="tab-btn" onclick="ativarTab('previsao')">
        <i class="fa-solid fa-calendar-check fa-xs"></i> Previsão do Mês
    </button>
    <button class="tab-btn" onclick="ativarTab('ranking')">
        <i class="fa-solid fa-trophy fa-xs"></i> Ranking
    </button>
    <button class="tab-btn" onclick="ativarTab('por_resp')">
        <i class="fa-solid fa-people-group fa-xs"></i> Por Responsável
    </button>
    <button class="tab-btn" onclick="ativarTab('ir')">
        <i class="fa-solid fa-file-invoice-dollar fa-xs"></i> Imposto de Renda
    </button>
</div>

<!-- ═══════════════════════════════════════════════════════════
     TAB 1 — VISÃO GERAL
═══════════════════════════════════════════════════════════ -->
<div id="tab-resumo" class="tab-pane active">

    <!-- Seletor de mês/ano / período -->
    <div class="d-flex gap-1 align-center" style="margin-bottom:1.25rem;flex-wrap:wrap">
        <div style="display:flex;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden">
            <button id="fBtnMesR" onclick="setModoFiltroR('mes')"
                    style="padding:.22rem .65rem;font-size:.72rem;font-weight:600;border:none;cursor:pointer;transition:var(--ease);background:var(--indigo);color:#fff">
                Mês/Ano
            </button>
            <button id="fBtnPerR" onclick="setModoFiltroR('periodo')"
                    style="padding:.22rem .65rem;font-size:.72rem;font-weight:600;border:none;cursor:pointer;transition:var(--ease);background:transparent;color:var(--text-500)">
                Período
            </button>
        </div>
        <div id="filtroMesR" style="display:flex;align-items:center;gap:.5rem">
            <select id="rMes" class="form-control" style="max-width:140px" onchange="carregarResumo()">
                <?php foreach ($nomesMeses as $n => $nome): if (!$n) continue; ?>
                <option value="<?= $n ?>" <?= $n === $mes ? 'selected' : '' ?>><?= $nome ?></option>
                <?php endforeach ?>
            </select>
            <select id="rAno" class="form-control" style="max-width:95px" onchange="carregarResumo()">
                <?php for ($y = $ano; $y >= $ano - 4; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $ano ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor ?>
            </select>
        </div>
        <div id="filtroPeriodoR" style="display:none;align-items:center;gap:.3rem">
            <input type="date" id="filDeR" class="form-control" style="width:140px" onchange="carregarResumo()">
            <span style="color:var(--text-500);font-size:.8rem">até</span>
            <input type="date" id="filAteR" class="form-control" style="width:140px" onchange="carregarResumo()">
        </div>
    </div>

    <!-- KPIs dinâmicos -->
    <div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.25rem" id="resumoKPIs">
        <div class="loading-overlay" style="grid-column:1/-1;padding:2rem">
            <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
        </div>
    </div>

    <!-- Gráfico fluxo + Top categorias -->
    <div class="dash-grid" style="margin-bottom:1.25rem">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Fluxo de Caixa — 6 meses</div>
            </div>
            <div class="card-body">
                <div class="chart-wrap" style="height:220px">
                    <canvas id="chartResumoFluxo"></canvas>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">Top Categorias de Despesa</div>
                <div class="card-subtitle" id="resumoCatSubtitulo">—</div>
            </div>
            <div id="resumoCatLista" class="card-body" style="padding:.5rem 0">
                <div class="loading-overlay"><i class="fa-solid fa-spinner fa-spin"></i></div>
            </div>
        </div>
    </div>

    <!-- Patrimônio resumo -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Patrimônio Líquido</div>
            <div class="card-subtitle">Snapshot atual</div>
        </div>
        <div id="resumoPatGrid" class="card-body">
            <div class="loading-overlay"><i class="fa-solid fa-spinner fa-spin"></i></div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     TAB 2 — FLUXO DE CAIXA
═══════════════════════════════════════════════════════════ -->
<div id="tab-fluxo" class="tab-pane">
    <div class="d-flex gap-1 align-center" style="margin-bottom:.875rem;flex-wrap:wrap">
        <span class="text-sm text-muted">Período:</span>
        <?php foreach ([6=>'6 meses',12=>'12 meses',24=>'24 meses'] as $n=>$l): ?>
        <button class="btn btn-sm <?= $n===12?'btn-primary':'btn-ghost' ?>"
                onclick="carregarFluxo(<?= $n ?>)" id="btnFluxo<?= $n ?>"><?= $l ?></button>
        <?php endforeach ?>
    </div>
    <!-- Comparativo de períodos -->
    <div style="background:var(--bg-700);border:1px solid var(--border);border-radius:var(--radius);
                padding:.75rem 1rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.875rem;flex-wrap:wrap">
        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.85rem;font-weight:600">
            <input type="checkbox" id="fluxoComparativo" onchange="toggleComparativo()"
                   style="width:16px;height:16px;accent-color:var(--indigo)">
            Comparar com:
        </label>
        <div id="compControls" style="display:none;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
            <select id="compMes" class="form-control" style="max-width:145px;font-size:.82rem" onchange="recarregarComparativo()">
                <?php foreach ($nomesMeses as $n => $nome): if (!$n) continue; ?>
                <option value="<?= $n ?>"><?= $nome ?></option>
                <?php endforeach ?>
            </select>
            <span class="text-muted text-sm">/</span>
            <select id="compAno" class="form-control" style="max-width:88px;font-size:.82rem" onchange="recarregarComparativo()">
                <?php for ($y = $ano; $y >= $ano - 4; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $ano - 1 ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor ?>
            </select>
            <span class="text-xs text-muted" style="align-self:center">(mês final do período comparado)</span>
        </div>
        <div id="compLegenda" style="margin-left:auto;display:none;font-size:.78rem;color:var(--text-600)">
            <span style="display:inline-flex;align-items:center;gap:.3rem">
                <span style="width:20px;height:3px;background:var(--emerald);display:inline-block;border-radius:2px"></span> Atual
            </span>
            &nbsp;
            <span style="display:inline-flex;align-items:center;gap:.3rem">
                <span style="width:20px;height:3px;background:var(--emerald);display:inline-block;border-radius:2px;opacity:.4"></span> Comparação
            </span>
        </div>
    </div>

    <div class="card" style="margin-bottom:1.25rem">
        <div class="card-header">
            <div class="card-title">Receitas vs Despesas</div>
        </div>
        <div class="card-body">
            <div class="chart-wrap" style="height:260px">
                <canvas id="chartFluxo"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Tabela Detalhada</div>
            <div id="fluxoTotais" class="text-sm text-muted">—</div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Mês</th>
                        <th class="text-right">Receitas</th>
                        <th class="text-right">Despesas</th>
                        <th class="text-right">Saldo do mês</th>
                        <th class="text-right">Variação</th>
                    </tr>
                </thead>
                <tbody id="fluxoTbody">
                    <tr><td colspan="5" class="loading-overlay"><i class="fa-solid fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     TAB 3 — CATEGORIAS
═══════════════════════════════════════════════════════════ -->
<div id="tab-categorias" class="tab-pane">
    <div class="d-flex gap-1 align-center" style="margin-bottom:1.25rem;flex-wrap:wrap">
        <div style="display:flex;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden">
            <button id="fBtnMesC" onclick="setModoFiltroC('mes')"
                    style="padding:.22rem .65rem;font-size:.72rem;font-weight:600;border:none;cursor:pointer;transition:var(--ease);background:var(--indigo);color:#fff">
                Mês/Ano
            </button>
            <button id="fBtnPerC" onclick="setModoFiltroC('periodo')"
                    style="padding:.22rem .65rem;font-size:.72rem;font-weight:600;border:none;cursor:pointer;transition:var(--ease);background:transparent;color:var(--text-500)">
                Período
            </button>
        </div>
        <div id="filtroMesC" style="display:flex;align-items:center;gap:.5rem">
            <select id="cMes" class="form-control" style="max-width:140px" onchange="carregarCategorias()">
                <?php foreach ($nomesMeses as $n => $nome): if (!$n) continue; ?>
                <option value="<?= $n ?>" <?= $n === $mes ? 'selected' : '' ?>><?= $nome ?></option>
                <?php endforeach ?>
            </select>
            <select id="cAno" class="form-control" style="max-width:95px" onchange="carregarCategorias()">
                <?php for ($y = $ano; $y >= $ano - 4; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $ano ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor ?>
            </select>
        </div>
        <div id="filtroPeriodoC" style="display:none;align-items:center;gap:.3rem">
            <input type="date" id="filDeC" class="form-control" style="width:140px" onchange="carregarCategorias()">
            <span style="color:var(--text-500);font-size:.8rem">até</span>
            <input type="date" id="filAteC" class="form-control" style="width:140px" onchange="carregarCategorias()">
        </div>
        <select id="cTipo" class="form-control" style="max-width:140px" onchange="carregarCategorias()">
            <option value="despesa">Despesas</option>
            <option value="receita">Receitas</option>
        </select>
    </div>

    <div class="dash-grid" style="margin-bottom:1.25rem">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Distribuição</div>
                <div class="card-subtitle" id="catSubtitulo">—</div>
            </div>
            <div class="card-body" style="display:flex;gap:1.5rem;align-items:center;flex-wrap:wrap">
                <div class="chart-wrap" style="height:200px;width:200px;flex-shrink:0">
                    <canvas id="chartCat"></canvas>
                </div>
                <div id="catLegenda" style="flex:1;min-width:160px;display:flex;flex-direction:column;gap:.4rem"></div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">Ranking</div>
            </div>
            <div id="catRanking" style="padding:.5rem 0">
                <div class="loading-overlay"><i class="fa-solid fa-spinner fa-spin"></i></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Tabela por Categoria</div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th class="text-right">Lançamentos</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">% do total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="catTbody">
                    <tr><td colspan="5" class="loading-overlay"><i class="fa-solid fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     TAB 4 — PATRIMÔNIO
═══════════════════════════════════════════════════════════ -->
<div id="tab-patrimonio" class="tab-pane">

    <!-- KPIs patrimônio -->
    <div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.25rem" id="patKPIs">
        <div class="loading-overlay" style="grid-column:1/-1"><i class="fa-solid fa-spinner fa-spin"></i></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem">

        <!-- Contas bancárias -->
        <div class="card">
            <div class="pat-group-title">Contas Bancárias</div>
            <div id="patContas"><div class="loading-overlay"><i class="fa-solid fa-spinner fa-spin"></i></div></div>
        </div>

        <!-- Investimentos -->
        <div class="card">
            <div class="pat-group-title">Investimentos</div>
            <div id="patInvest"><div class="loading-overlay"><i class="fa-solid fa-spinner fa-spin"></i></div></div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">

        <!-- Comparativo por titular -->
        <div class="card">
            <div class="card-header"><div class="card-title">Por Titular</div></div>
            <div id="patTitular"><div class="loading-overlay"><i class="fa-solid fa-spinner fa-spin"></i></div></div>
        </div>

        <!-- Empréstimos + Metas -->
        <div class="card">
            <div class="pat-group-title">Compromissos & Metas</div>
            <div id="patCompromissos"><div class="loading-overlay"><i class="fa-solid fa-spinner fa-spin"></i></div></div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     TAB 5 — RESUMO ANUAL
═══════════════════════════════════════════════════════════ -->
<div id="tab-anual" class="tab-pane">
    <div class="d-flex gap-1 align-center" style="margin-bottom:1.25rem">
        <span class="text-sm text-muted">Ano:</span>
        <?php for ($y = $ano; $y >= $ano - 4; $y--): ?>
        <button class="btn btn-sm <?= $y===$ano?'btn-primary':'btn-ghost' ?>"
                onclick="carregarAnual(<?= $y ?>)" id="btnAnual<?= $y ?>"><?= $y ?></button>
        <?php endfor ?>
    </div>

    <div class="card" style="margin-bottom:1.25rem">
        <div class="card-header">
            <div class="card-title" id="anualTitulo">Resumo Anual</div>
        </div>
        <div class="card-body">
            <div class="chart-wrap" style="height:220px">
                <canvas id="chartAnual"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="anual-table">
                <thead>
                    <tr>
                        <th>Mês</th>
                        <th class="text-right">Receitas</th>
                        <th class="text-right">Despesas</th>
                        <th class="text-right">Saldo do Mês</th>
                        <th class="text-right">Acumulado</th>
                    </tr>
                </thead>
                <tbody id="anualTbody">
                    <tr><td colspan="5" class="loading-overlay"><i class="fa-solid fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     TAB 6 — PREVISÃO DO MÊS
═══════════════════════════════════════════════════════════ -->
<div id="tab-previsao" class="tab-pane">

    <!-- Seletor -->
    <div class="d-flex gap-1 align-center" style="margin-bottom:1.25rem;flex-wrap:wrap">
        <select id="pvMes" class="form-control" style="max-width:140px" onchange="carregarPrevisao()">
            <?php foreach ($nomesMeses as $n => $nome): if (!$n) continue; ?>
            <option value="<?= $n ?>" <?= $n === $mes ? 'selected' : '' ?>><?= $nome ?></option>
            <?php endforeach ?>
        </select>
        <select id="pvAno" class="form-control" style="max-width:95px" onchange="carregarPrevisao()">
            <?php for ($y = $ano + 1; $y >= $ano - 1; $y--): ?>
            <option value="<?= $y ?>" <?= $y === $ano ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor ?>
        </select>
        <span class="text-xs text-muted" style="align-self:center">
            <i class="fa-solid fa-circle-info fa-xs"></i> Baseado nas contas fixas cadastradas
        </span>
    </div>

    <!-- KPIs de previsão -->
    <div id="pvKPIs" class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.25rem">
        <div class="loading-overlay" style="grid-column:1/-1;padding:2rem">
            <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
        </div>
    </div>

    <!-- Barras de progresso -->
    <div id="pvProgress" class="card" style="margin-bottom:1.25rem;display:none">
        <div class="card-body" style="display:flex;flex-direction:column;gap:1rem">
            <div>
                <div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:.4rem">
                    <span style="color:var(--rose)"><i class="fa-solid fa-arrow-down fa-xs"></i> Despesas realizadas</span>
                    <span id="pvPctDes" class="fw-600 text-rose">0%</span>
                </div>
                <div class="progress-bar-bg">
                    <div id="pvBarDes" class="progress-bar-fill rose" style="width:0%"></div>
                </div>
            </div>
            <div>
                <div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:.4rem">
                    <span style="color:var(--emerald)"><i class="fa-solid fa-arrow-up fa-xs"></i> Receitas realizadas</span>
                    <span id="pvPctRec" class="fw-600 text-emerald">0%</span>
                </div>
                <div class="progress-bar-bg">
                    <div id="pvBarRec" class="progress-bar-fill emerald" style="width:0%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela de itens -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title" id="pvTabelaTitulo">Contas do Mês</div>
                <div class="card-subtitle" id="pvTabelaSub">—</div>
            </div>
            <div class="d-flex gap-1 text-xs align-center">
                <span style="color:var(--emerald)"><i class="fa-solid fa-circle-check fa-xs"></i> Pago/Recebido</span>
                <span style="color:var(--amber)"><i class="fa-solid fa-clock fa-xs"></i> Pendente</span>
                <span style="color:var(--text-600)"><i class="fa-solid fa-circle fa-xs"></i> Não gerado</span>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th>Conta / Cartão</th>
                        <th>Vencimento</th>
                        <th style="text-align:center">Status</th>
                        <th class="text-right">Valor</th>
                    </tr>
                </thead>
                <tbody id="pvTbody">
                    <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-600)">
                        <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     TAB 7 — RANKING
═══════════════════════════════════════════════════════════ -->
<div id="tab-ranking" class="tab-pane">

    <!-- Seletor de período -->
    <div class="d-flex gap-1 align-center" style="margin-bottom:1.25rem;flex-wrap:wrap">
        <select id="rkMes" class="form-control" style="max-width:155px" onchange="carregarRanking()">
            <option value="0">Ano completo</option>
            <?php foreach ($nomesMeses as $n => $nome): if (!$n) continue; ?>
            <option value="<?= $n ?>" <?= $n === $mes ? 'selected' : '' ?>><?= $nome ?></option>
            <?php endforeach ?>
        </select>
        <select id="rkAno" class="form-control" style="max-width:95px" onchange="carregarRanking()">
            <?php for ($y = $ano; $y >= $ano - 4; $y--): ?>
            <option value="<?= $y ?>" <?= $y === $ano ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor ?>
        </select>
    </div>

    <!-- KPIs do ranking -->
    <div id="rkKPIs" class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.25rem">
        <div class="loading-overlay" style="grid-column:1/-1;padding:2rem">
            <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
        </div>
    </div>

    <!-- Dois rankings lado a lado -->
    <div class="dash-grid-2" style="margin-bottom:1.25rem">

        <!-- Quem mais gasta -->
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">
                        <i class="fa-solid fa-arrow-trend-down fa-xs" style="color:var(--rose)"></i>
                        Quem mais gasta
                    </div>
                    <div class="card-subtitle" id="rkDesSub">—</div>
                </div>
                <span class="badge" style="background:var(--rose-soft);color:var(--rose)">Despesas</span>
            </div>
            <div id="rkDesLista" class="card-body" style="padding:.5rem 0">
                <div class="loading-overlay"><i class="fa-solid fa-spinner fa-spin"></i></div>
            </div>
        </div>

        <!-- Quem mais ganha -->
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">
                        <i class="fa-solid fa-arrow-trend-up fa-xs" style="color:var(--emerald)"></i>
                        Quem mais ganha
                    </div>
                    <div class="card-subtitle" id="rkRecSub">—</div>
                </div>
                <span class="badge" style="background:var(--emerald-soft);color:var(--emerald)">Receitas</span>
            </div>
            <div id="rkRecLista" class="card-body" style="padding:.5rem 0">
                <div class="loading-overlay"><i class="fa-solid fa-spinner fa-spin"></i></div>
            </div>
        </div>

    </div>

    <!-- Top categorias por responsável -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-layer-group fa-xs" style="color:var(--indigo)"></i>
                Despesas por categoria — por responsável
            </div>
            <div class="card-subtitle" id="rkCatSub">—</div>
        </div>
        <div id="rkCatGrid" class="card-body">
            <div class="loading-overlay"><i class="fa-solid fa-spinner fa-spin"></i></div>
        </div>
    </div>

</div>

<!-- ═══════════════════════════════════════════════════════════
     TAB 8 — POR RESPONSÁVEL
═══════════════════════════════════════════════════════════ -->
<div id="tab-por_resp" class="tab-pane">

    <!-- Seletor de período -->
    <div class="d-flex gap-1 align-center" style="margin-bottom:1.25rem;flex-wrap:wrap">
        <select id="prMes" class="form-control" style="max-width:155px" onchange="carregarPorResp()">
            <option value="0">Ano completo</option>
            <?php foreach ($nomesMeses as $n => $nome): if (!$n) continue; ?>
            <option value="<?= $n ?>" <?= $n === $mes ? 'selected' : '' ?>><?= $nome ?></option>
            <?php endforeach ?>
        </select>
        <select id="prAno" class="form-control" style="max-width:95px" onchange="carregarPorResp()">
            <?php for ($y = $ano; $y >= $ano - 4; $y--): ?>
            <option value="<?= $y ?>" <?= $y === $ano ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor ?>
        </select>
    </div>

    <!-- KPIs -->
    <div id="prKPIs" class="kpi-grid" style="margin-bottom:1.25rem">
        <div class="loading-overlay" style="grid-column:1/-1;padding:2rem">
            <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
        </div>
    </div>

    <!-- Gráfico comparativo -->
    <div class="card" style="margin-bottom:1.25rem">
        <div class="card-header">
            <div class="card-title">Receitas vs Despesas por Responsável</div>
            <div class="card-subtitle" id="prChartSub">—</div>
        </div>
        <div class="card-body">
            <div class="chart-wrap" style="height:240px">
                <canvas id="chartPorResp"></canvas>
            </div>
        </div>
    </div>

    <!-- Cards por responsável -->
    <div id="prCards" class="card-body">
        <div class="loading-overlay"><i class="fa-solid fa-spinner fa-spin"></i></div>
    </div>

</div>

<!-- ═══════════════════════════════════════════════════════════
     TAB IR — IMPOSTO DE RENDA
═══════════════════════════════════════════════════════════ -->
<div id="tab-ir" class="tab-pane">

<style>
/* ── Print: oculta tudo exceto a aba IR ────────────────── */
@media print {
    .sidebar, .header, .tabs, .btn, select, .page-header { display: none !important; }
    .content { padding: 0 !important; }
    #tab-ir   { display: block !important; }
    .ir-card  { break-inside: avoid; margin-bottom: 1rem; }
    .ir-no-print { display: none !important; }
    body { background: #fff !important; color: #111 !important; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ccc; padding: .3rem .5rem; font-size: .78rem; }
    th { background: #f0f0f0 !important; }
}
.ir-card {
    background: var(--bg-800);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    margin-bottom: 1.25rem;
    overflow: hidden;
}
.ir-card-header {
    padding: .875rem 1.25rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
}
.ir-card-title {
    display: flex;
    align-items: center;
    gap: .65rem;
    font-size: .9rem;
    font-weight: 700;
}
.ir-icon {
    width: 32px; height: 32px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .8rem; flex-shrink: 0;
}
.ir-total {
    font-size: 1.05rem;
    font-weight: 700;
}
.ir-limite-warn {
    font-size: .72rem;
    font-weight: 600;
    padding: .2rem .5rem;
    border-radius: 999px;
}
.ir-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.ir-table th {
    padding: .45rem .875rem;
    text-align: right;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--text-600);
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}
.ir-table th:first-child { text-align: left; }
.ir-table td {
    padding: .45rem .875rem;
    text-align: right;
    border-bottom: 1px solid var(--border);
    font-size: .82rem;
}
.ir-table td:first-child { text-align: left; font-weight: 600; }
.ir-table tr:last-child td { border-bottom: none; }
.ir-table tr.total-row td { font-weight: 700; background: var(--bg-700); font-size: .85rem; }
.ir-table .zero { color: var(--text-600); }
.ir-resumo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
}
.ir-resumo-item {
    background: var(--bg-700);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: .875rem 1rem;
    text-align: center;
}
</style>

    <!-- Cabeçalho + controles -->
    <div class="page-header" style="margin-bottom:1.25rem">
        <div>
            <div class="page-title">
                <i class="fa-solid fa-file-invoice-dollar" style="color:var(--amber)"></i>
                Relatório — Imposto de Renda
            </div>
            <div class="page-sub" id="irSub">Carregando...</div>
        </div>
        <div class="d-flex gap-1 align-center ir-no-print">
            <select id="irAno" class="form-control" style="max-width:90px" onchange="carregarIR()">
                <?php for ($y = $ano - 1; $y >= $ano - 5; $y--): ?>
                <option value="<?= $y ?>"><?= $y ?></option>
                <?php endfor ?>
            </select>
            <button class="btn btn-ghost btn-sm" onclick="window.print()">
                <i class="fa-solid fa-print fa-xs"></i> Imprimir
            </button>
            <button class="btn btn-ghost btn-sm" onclick="exportarIRCSV()">
                <i class="fa-solid fa-download fa-xs"></i> CSV
            </button>
            <a href="?p=categorias" class="btn btn-ghost btn-sm" target="_blank">
                <i class="fa-solid fa-tags fa-xs"></i> Configurar Categorias
            </a>
        </div>
    </div>

    <!-- Aviso de configuração -->
    <div id="irAviso" style="display:none;background:var(--amber-bg);border:1px solid var(--amber);
         border-radius:var(--radius);padding:1rem 1.25rem;margin-bottom:1.25rem;gap:.875rem">
        <i class="fa-solid fa-triangle-exclamation" style="color:var(--amber);font-size:1.1rem;flex-shrink:0"></i>
        <div>
            <div class="fw-600 text-sm" style="color:var(--amber)">Categorias não classificadas para IR</div>
            <div class="text-xs text-muted" style="margin-top:.2rem">
                Acesse <a href="?p=categorias" target="_blank" style="color:var(--indigo)">Categorias</a>,
                edite cada uma e defina a "Classificação Imposto de Renda" para que apareçam neste relatório.
            </div>
        </div>
    </div>

    <!-- Conteúdo carregado dinamicamente -->
    <div id="irContent">
        <div style="text-align:center;padding:3rem;color:var(--text-600)">
            <i class="fa-solid fa-spinner fa-spin fa-2x" style="margin-bottom:.875rem;display:block"></i>
            Carregando relatório...
        </div>
    </div>

</div>

<!-- ── Scripts ────────────────────────────────────────────── -->
<script>
// ── Instâncias dos gráficos ──────────────────────────────
let _charts = {};
const mesAtualNum = <?= $mes ?>;
const anoAtualNum = <?= $ano ?>;

function mkChart(id, type, data, options = {}) {
    if (_charts[id]) { _charts[id].destroy(); delete _charts[id]; }
    const ctx = document.getElementById(id);
    if (!ctx) return;
    _charts[id] = new Chart(ctx, { type, data, options });
}

const gridColor  = 'rgba(255,255,255,.06)';
const fontColor  = '#64748b';
const baseOpts   = {
    responsive: true, maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => ' ' + brl(ctx.parsed.y ?? ctx.parsed) } }
    },
    scales: {
        x: { grid: { color: gridColor }, ticks: { color: fontColor, font: { size: 11 } } },
        y: { grid: { color: gridColor }, ticks: { color: fontColor, font: { size: 11 },
             callback: v => 'R$ ' + (Math.abs(v) >= 1000 ? (v/1000).toFixed(0)+'k' : v) } }
    }
};

// ── Tab logic ────────────────────────────────────────────
const _tabLoaded = {};
function ativarTab(id) {
    document.querySelectorAll('.tab-btn').forEach((b, i) => {
        const tabs = ['resumo','fluxo','categorias','patrimonio','anual','previsao','ranking','por_resp','ir'];
        b.classList.toggle('active', tabs[i] === id);
    });
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');

    if (!_tabLoaded[id]) {
        _tabLoaded[id] = true;
        if (id === 'fluxo')      carregarFluxo(12);
        if (id === 'categorias') carregarCategorias();
        if (id === 'patrimonio') carregarPatrimonio();
        if (id === 'anual')      carregarAnual(anoAtualNum);
        if (id === 'previsao')   carregarPrevisao();
        if (id === 'ranking')    carregarRanking();
        if (id === 'por_resp')   carregarPorResp();
        if (id === 'ir')         carregarIR();
    }
}

// ═══════════════════════════════════════════════════════════
// FILTROS COM TOGGLE MÊS/ANO | PERÍODO
// ═══════════════════════════════════════════════════════════
let _modoFiltroR = 'mes';
let _modoFiltroC = 'mes';

function setModoFiltroR(modo, carregar = true) {
    _modoFiltroR = modo;
    document.getElementById('filtroMesR').style.display     = modo === 'mes'     ? 'flex' : 'none';
    document.getElementById('filtroPeriodoR').style.display = modo === 'periodo' ? 'flex' : 'none';
    const bm = document.getElementById('fBtnMesR');
    const bp = document.getElementById('fBtnPerR');
    bm.style.background = modo === 'mes'     ? 'var(--indigo)' : 'transparent';
    bm.style.color      = modo === 'mes'     ? '#fff'          : 'var(--text-500)';
    bp.style.background = modo === 'periodo' ? 'var(--indigo)' : 'transparent';
    bp.style.color      = modo === 'periodo' ? '#fff'          : 'var(--text-500)';
    if (carregar) carregarResumo();
}

function setModoFiltroC(modo, carregar = true) {
    _modoFiltroC = modo;
    document.getElementById('filtroMesC').style.display     = modo === 'mes'     ? 'flex' : 'none';
    document.getElementById('filtroPeriodoC').style.display = modo === 'periodo' ? 'flex' : 'none';
    const bm = document.getElementById('fBtnMesC');
    const bp = document.getElementById('fBtnPerC');
    bm.style.background = modo === 'mes'     ? 'var(--indigo)' : 'transparent';
    bm.style.color      = modo === 'mes'     ? '#fff'          : 'var(--text-500)';
    bp.style.background = modo === 'periodo' ? 'var(--indigo)' : 'transparent';
    bp.style.color      = modo === 'periodo' ? '#fff'          : 'var(--text-500)';
    if (carregar) carregarCategorias();
}

function _initPeriodoDatasRelatorios() {
    const hoje = new Date();
    const y = hoje.getFullYear();
    const m = String(hoje.getMonth() + 1).padStart(2, '0');
    const ult = new Date(y, hoje.getMonth() + 1, 0).getDate();
    const de  = `${y}-${m}-01`;
    const ate = `${y}-${m}-${String(ult).padStart(2, '0')}`;
    const filDeR = document.getElementById('filDeR');   if (filDeR)  filDeR.value  = de;
    const filAteR = document.getElementById('filAteR'); if (filAteR) filAteR.value = ate;
    const filDeC = document.getElementById('filDeC');   if (filDeC)  filDeC.value  = de;
    const filAteC = document.getElementById('filAteC'); if (filAteC) filAteC.value = ate;
}

function _getMesAnoR() {
    if (_modoFiltroR === 'periodo') {
        const de = document.getElementById('filDeR').value;
        const partes = de.split('-');
        return { mes: parseInt(partes[1]).toString(), ano: partes[0] };
    }
    return { mes: document.getElementById('rMes').value, ano: document.getElementById('rAno').value };
}

function _getMesAnoC() {
    if (_modoFiltroC === 'periodo') {
        const de = document.getElementById('filDeC').value;
        const partes = de.split('-');
        return { mes: parseInt(partes[1]).toString(), ano: partes[0] };
    }
    return { mes: document.getElementById('cMes').value, ano: document.getElementById('cAno').value };
}

// ═══════════════════════════════════════════════════════════
// TAB 1 — RESUMO
// ═══════════════════════════════════════════════════════════
async function carregarResumo() {
    const { mes, ano } = _getMesAnoR();

    document.getElementById('resumoKPIs').innerHTML =
        `<div class="loading-overlay" style="grid-column:1/-1"><i class="fa-solid fa-spinner fa-spin"></i></div>`;

    try {
        const res  = await fetch(`backend/api/relatorios.php?acao=resumo&mes=${mes}&ano=${ano}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        renderResumoKPIs(json);
        renderResumoFluxo(json.fluxo);
        renderResumoCat(json.top_categorias, json.atual.despesas);
        renderResumoPatrimonio(json.patrimonio);
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

function renderResumoKPIs(d) {
    const r = d.atual, a = d.anterior;

    // Usa o total real (pago + pendente) como valor principal
    const recTotal  = parseFloat(r.receitas_total  || 0);
    const desTotal  = parseFloat(r.despesas_total  || 0);
    const recPago   = parseFloat(r.receitas        || 0);
    const desPago   = parseFloat(r.despesas        || 0);
    const aPagar    = parseFloat(r.a_pagar         || 0);
    const recPend   = recTotal - recPago;

    const aTotal    = parseFloat(a.receitas_total  || 0);
    const adTotal   = parseFloat(a.despesas_total  || 0);

    const varRec    = aTotal  > 0 ? ((recTotal - aTotal)  / aTotal  * 100).toFixed(1) : 0;
    const varDes    = adTotal > 0 ? ((desTotal - adTotal) / adTotal * 100).toFixed(1) : 0;
    const saldo     = recTotal - desTotal;
    const poupanca  = recTotal > 0 ? (saldo / recTotal * 100).toFixed(1) : 0;

    // Subtextos informativos com breakdown pago × pendente
    const tRec = recPago > 0
        ? `✓ Recebido: ${brl(recPago)}` + (recPend > 0 ? ` · Pendente: ${brl(recPend)}` : '')
        : recTotal > 0 ? `Pendente: ${brl(recTotal)}` : `${varRec >= 0 ? '+' : ''}${varRec}% vs mês anterior`;

    const tDes = desPago > 0
        ? `✓ Pago: ${brl(desPago)}` + (aPagar > 0 ? ` · A pagar: ${brl(aPagar)}` : '')
        : aPagar > 0 ? `A pagar: ${brl(aPagar)}` : `${varDes <= 0 ? '+' : ''}${Math.abs(varDes)}% vs mês anterior`;

    document.getElementById('resumoKPIs').innerHTML = `
        ${kpiCard('emerald','arrow-trend-up','Receitas do Mês', brl(recTotal), tRec, 'neutral')}
        ${kpiCard('rose','arrow-trend-down','Despesas do Mês', brl(desTotal), tDes, 'neutral')}
        ${kpiCard(saldo >= 0 ? 'indigo':'rose', saldo >= 0 ? 'arrow-up':'arrow-down','Saldo do Mês',
            (saldo >= 0 ? '+' : '') + brl(saldo), 'Receitas − Despesas (total)', 'neutral')}
        ${kpiCard(poupanca >= 20 ? 'emerald' : poupanca >= 10 ? 'amber' : 'rose',
            'piggy-bank','Taxa de Poupança', poupanca + '%',
            poupanca >= 20 ? 'Meta atingida!' : 'Meta: 20%', 'neutral')}`;
}

function kpiCard(color, icon, label, value, trend, trendCls) {
    return `<div class="kpi-card ${color}">
        <div class="kpi-header">
            <div class="kpi-label">${label}</div>
            <div class="kpi-icon ${color}"><i class="fa-solid fa-${icon}"></i></div>
        </div>
        <div class="kpi-value">${value}</div>
        <div class="kpi-trend ${trendCls}"><i class="fa-solid fa-circle-info fa-xs"></i> ${trend}</div>
    </div>`;
}

function renderResumoFluxo(fluxo) {
    mkChart('chartResumoFluxo', 'bar', {
        labels: fluxo.map(d => d.label),
        datasets: [
            { label: 'Receitas', data: fluxo.map(d => d.receitas), backgroundColor: 'rgba(16,185,129,.75)', borderRadius: 5, borderSkipped: false },
            { label: 'Despesas', data: fluxo.map(d => d.despesas), backgroundColor: 'rgba(244,63,94,.75)',  borderRadius: 5, borderSkipped: false },
        ]
    }, { ...baseOpts,
        plugins: { ...baseOpts.plugins, legend: { display: true, labels: { color: fontColor, boxWidth: 10, font: { size: 11 } } } }
    });
}

function renderResumoCat(cats, totalDes) {
    const mes = document.getElementById('rMes').options;
    const nomeMes = mes[document.getElementById('rMes').selectedIndex].text;
    document.getElementById('resumoCatSubtitulo').textContent =
        `${nomeMes} — ${brl(totalDes)} em despesas`;

    if (!cats.length) {
        document.getElementById('resumoCatLista').innerHTML =
            '<p class="text-muted text-sm" style="padding:1rem;text-align:center">Sem dados neste período.</p>';
        return;
    }
    const maxVal = parseFloat(cats[0].total);
    document.getElementById('resumoCatLista').innerHTML = cats.map(c => {
        const pct = maxVal > 0 ? Math.round(parseFloat(c.total) / maxVal * 100) : 0;
        return `<div class="stat-row">
            <div class="d-flex align-center gap-1" style="min-width:120px">
                <span style="width:10px;height:10px;border-radius:50%;background:${c.cor};flex-shrink:0;display:inline-block"></span>
                <span class="truncate" style="max-width:100px">${esc(c.nome)}</span>
            </div>
            <div class="stat-bar-inline"><div class="stat-bar-fill" style="width:${pct}%;background:${c.cor}"></div></div>
            <span class="fw-600 text-sm">${brl(c.total)}</span>
        </div>`;
    }).join('');
}

function renderResumoPatrimonio(pat) {
    const liquido = parseFloat(pat.liquido);
    document.getElementById('resumoPatGrid').innerHTML = `
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem">
            <div style="text-align:center;padding:.875rem;background:var(--bg-700);border-radius:var(--radius)">
                <div class="text-xs text-muted">Contas Bancárias</div>
                <div class="fw-700 text-emerald" style="font-size:1.1rem;margin-top:.25rem">${brl(pat.contas)}</div>
            </div>
            <div style="text-align:center;padding:.875rem;background:var(--bg-700);border-radius:var(--radius)">
                <div class="text-xs text-muted">Investimentos</div>
                <div class="fw-700 text-violet" style="font-size:1.1rem;margin-top:.25rem">${brl(pat.investimentos)}</div>
            </div>
            <div style="text-align:center;padding:.875rem;background:var(--bg-700);border-radius:var(--radius)">
                <div class="text-xs text-muted">Dívidas</div>
                <div class="fw-700 text-rose" style="font-size:1.1rem;margin-top:.25rem">-${brl(pat.dividas)}</div>
            </div>
            <div style="text-align:center;padding:.875rem;background:${liquido >= 0 ? 'var(--emerald-bg)' : 'var(--rose-bg)'};border-radius:var(--radius);border:1px solid ${liquido >= 0 ? 'var(--emerald)' : 'var(--rose)'}">
                <div class="text-xs text-muted">Patrimônio Líquido</div>
                <div class="fw-700 ${liquido >= 0 ? 'text-emerald' : 'text-rose'}" style="font-size:1.25rem;margin-top:.25rem">${brl(liquido)}</div>
            </div>
        </div>`;
}

// ═══════════════════════════════════════════════════════════
// TAB 2 — FLUXO
// ═══════════════════════════════════════════════════════════
let _fluxoAtual = 12;

function toggleComparativo() {
    const ativo = document.getElementById('fluxoComparativo').checked;
    document.getElementById('compControls').style.display = ativo ? 'flex' : 'none';
    document.getElementById('compLegenda').style.display  = ativo ? 'flex' : 'none';
    carregarFluxo(_fluxoAtual);
}

function recarregarComparativo() { carregarFluxo(_fluxoAtual); }

async function carregarFluxo(meses) {
    _fluxoAtual = meses;
    [6, 12, 24].forEach(n => {
        const btn = document.getElementById('btnFluxo' + n);
        if (btn) btn.className = 'btn btn-sm ' + (n === meses ? 'btn-primary' : 'btn-ghost');
    });

    const comparativo = document.getElementById('fluxoComparativo')?.checked;
    const compMes = document.getElementById('compMes')?.value || mesAtualNum;
    const compAno = document.getElementById('compAno')?.value || (anoAtualNum - 1);

    try {
        // Sempre busca o período atual (até hoje)
        const [res1, res2] = await Promise.all([
            fetch(`backend/api/relatorios.php?acao=fluxo&meses=${meses}`),
            comparativo
                ? fetch(`backend/api/relatorios.php?acao=fluxo&meses=${meses}&ate_mes=${compMes}&ate_ano=${compAno}`)
                : Promise.resolve(null),
        ]);

        const json1 = await res1.json();
        if (!json1.success) throw new Error(json1.erro);

        let json2 = null;
        if (res2) { json2 = await res2.json(); if (!json2.success) json2 = null; }

        renderFluxo(json1, json2);
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

function renderFluxo(data, dataComp = null) {
    const rows = data.dados;
    const tots = data.totais;

    document.getElementById('fluxoTotais').innerHTML =
        `<span class="text-emerald">${brl(tots.receitas)}</span> &nbsp;—&nbsp; ` +
        `<span class="text-rose">${brl(tots.despesas)}</span> &nbsp;=&nbsp; ` +
        `<span class="${tots.saldo >= 0 ? 'text-emerald' : 'text-rose'} fw-600">${brl(tots.saldo)}</span>`;

    const datasets = [
        { label: 'Receitas',  data: rows.map(d => d.receitas), backgroundColor: 'rgba(16,185,129,.8)',  borderRadius: 4, order: 2 },
        { label: 'Despesas',  data: rows.map(d => d.despesas), backgroundColor: 'rgba(244,63,94,.8)',   borderRadius: 4, order: 2 },
        { label: 'Saldo',     data: rows.map(d => d.saldo),
          type: 'line', borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,.12)',
          fill: true, tension: .3, pointRadius: 3, order: 1 },
    ];

    if (dataComp) {
        const rowsC = dataComp.dados;
        datasets.push(
            { label: 'Receitas (comp.)', data: rowsC.map(d => d.receitas),
              backgroundColor: 'rgba(16,185,129,.3)', borderRadius: 4, order: 3,
              borderColor: 'rgba(16,185,129,.6)', borderWidth: 1.5 },
            { label: 'Despesas (comp.)', data: rowsC.map(d => d.despesas),
              backgroundColor: 'rgba(244,63,94,.3)', borderRadius: 4, order: 3,
              borderColor: 'rgba(244,63,94,.6)', borderWidth: 1.5 }
        );
    }

    mkChart('chartFluxo', 'bar', { labels: rows.map(d => d.label), datasets }, {
        ...baseOpts,
        plugins: { ...baseOpts.plugins,
            legend: { display: true, labels: { color: fontColor, boxWidth: 10, font: { size: 11 } } },
            tooltip: { callbacks: { label: ctx => ` ${ctx.dataset.label}: ${brl(ctx.parsed.y)}` } }
        }
    });

    // Tabela — inclui coluna de comparação se houver
    const compIdx = dataComp ? Object.fromEntries(dataComp.dados.map(r => [r.mes + '-' + r.ano, r])) : null;
    let prevSaldo = null;
    const linhas  = rows.map(row => {
        const varPct = prevSaldo !== null && prevSaldo !== 0
            ? ((row.saldo - prevSaldo) / Math.abs(prevSaldo) * 100).toFixed(1) : null;
        const varStr = varPct !== null
            ? `<span class="${parseFloat(varPct) >= 0 ? 'text-emerald':'text-rose'}">${parseFloat(varPct) >= 0?'+':''}${varPct}%</span>` : '—';
        prevSaldo = row.saldo;

        let compCol = '';
        if (compIdx) {
            const c = compIdx[row.mes + '-' + (row.ano - (anoAtualNum - parseInt(document.getElementById('compAno')?.value || anoAtualNum - 1)))] || null;
            const cSaldo = c ? c.saldo : null;
            const diff   = cSaldo !== null ? row.saldo - cSaldo : null;
            compCol = `<td class="text-right text-xs text-muted" title="Comparado">${
                cSaldo !== null ? `${brl(cSaldo)}<br><span class="${diff >= 0?'text-emerald':'text-rose'}">${diff>=0?'+':''}${brl(diff)}</span>` : '—'
            }</td>`;
        }

        return `<tr>
            <td class="fw-600 text-sm">${row.label}</td>
            <td class="text-right text-emerald">${brl(row.receitas)}</td>
            <td class="text-right text-rose">${brl(row.despesas)}</td>
            <td class="text-right ${row.saldo >= 0?'text-emerald':'text-rose'} fw-600">${brl(row.saldo)}</td>
            ${compIdx ? compCol : `<td class="text-right">${varStr}</td>`}
        </tr>`;
    });

    // Cabeçalho dinâmico da tabela
    document.querySelector('#tab-fluxo thead tr').innerHTML = `
        <th>Mês</th>
        <th class="text-right">Receitas</th>
        <th class="text-right">Despesas</th>
        <th class="text-right">Saldo do mês</th>
        <th class="text-right">${compIdx ? 'Comparação / Δ' : 'Variação'}</th>`;

    document.getElementById('fluxoTbody').innerHTML = linhas.join('') +
        `<tr class="totais-row">
            <td>TOTAL</td>
            <td class="text-right text-emerald">${brl(tots.receitas)}</td>
            <td class="text-right text-rose">${brl(tots.despesas)}</td>
            <td class="text-right ${tots.saldo >= 0?'text-emerald':'text-rose'}">${brl(tots.saldo)}</td>
            <td></td>
        </tr>`;
}

// ═══════════════════════════════════════════════════════════
// TAB 3 — CATEGORIAS
// ═══════════════════════════════════════════════════════════
async function carregarCategorias() {
    const { mes, ano } = _getMesAnoC();
    const tipo = document.getElementById('cTipo').value;

    try {
        const res  = await fetch(`backend/api/relatorios.php?acao=categorias&mes=${mes}&ano=${ano}&tipo=${tipo}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        renderCategorias(json, mes, ano, tipo);
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

function renderCategorias(data, mes, ano, tipo) {
    const cats   = data.dados;
    const total  = data.total;
    const nomeMes = document.getElementById('cMes').options[mes-1].text;
    document.getElementById('catSubtitulo').textContent =
        `${nomeMes}/${ano} — ${brl(total)} em ${tipo === 'despesa' ? 'despesas' : 'receitas'}`;

    if (!cats.length) {
        document.getElementById('catLegenda').innerHTML = '<p class="text-muted text-sm">Sem dados.</p>';
        document.getElementById('catRanking').innerHTML = '<p class="text-muted text-sm" style="padding:1rem">Sem dados.</p>';
        document.getElementById('catTbody').innerHTML   = '<tr><td colspan="5" style="text-align:center;padding:1.5rem;color:var(--text-600)">Sem lançamentos neste período.</td></tr>';
        return;
    }

    // Doughnut
    mkChart('chartCat', 'doughnut', {
        labels: cats.map(c => c.nome),
        datasets: [{ data: cats.map(c => c.total), backgroundColor: cats.map(c => c.cor || '#64748b'), borderWidth: 0 }]
    }, { responsive: true, maintainAspectRatio: false, cutout: '70%',
         plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${brl(ctx.parsed)}` } } }
    });

    // Legenda
    document.getElementById('catLegenda').innerHTML = cats.slice(0, 8).map(c =>
        `<div class="d-flex align-center justify-between text-sm">
            <div class="d-flex align-center gap-1">
                <span style="width:10px;height:10px;border-radius:50%;background:${c.cor};flex-shrink:0;display:inline-block"></span>
                <span class="truncate" style="max-width:120px">${esc(c.nome)}</span>
            </div>
            <span class="text-muted text-xs">${c.pct}%</span>
        </div>`
    ).join('');

    // Ranking com barras
    document.getElementById('catRanking').innerHTML = cats.map(c =>
        `<div class="stat-row">
            <div class="d-flex align-center gap-1" style="min-width:130px">
                <span style="width:9px;height:9px;border-radius:50%;background:${c.cor};flex-shrink:0;display:inline-block"></span>
                <span class="truncate text-sm" style="max-width:120px">${esc(c.nome)}</span>
            </div>
            <div class="stat-bar-inline"><div class="stat-bar-fill" style="width:${c.pct}%;background:${c.cor}"></div></div>
            <span class="fw-600 text-sm">${brl(c.total)}</span>
        </div>`
    ).join('');

    // Tabela
    document.getElementById('catTbody').innerHTML = cats.map((c, i) =>
        `<tr>
            <td>
                <div class="d-flex align-center gap-1">
                    <span style="width:10px;height:10px;border-radius:50%;background:${c.cor};flex-shrink:0;display:inline-block"></span>
                    <span class="fw-600 text-sm">${esc(c.nome)}</span>
                </div>
            </td>
            <td class="text-right text-muted text-sm">${c.qtd}</td>
            <td class="text-right fw-600 ${tipo === 'despesa' ? 'text-rose' : 'text-emerald'}">${brl(c.total)}</td>
            <td class="text-right text-sm">
                <div class="d-flex align-center justify-end gap-1">
                    <div style="width:60px;height:6px;border-radius:99px;background:var(--bg-600);overflow:hidden">
                        <div style="height:100%;width:${c.pct}%;background:${c.cor};border-radius:99px"></div>
                    </div>
                    ${c.pct}%
                </div>
            </td>
            <td class="text-muted text-sm">#${i+1}</td>
        </tr>`
    ).join('');
}

// ═══════════════════════════════════════════════════════════
// TAB 4 — PATRIMÔNIO
// ═══════════════════════════════════════════════════════════
async function carregarPatrimonio() {
    try {
        const res  = await fetch('backend/api/relatorios.php?acao=patrimonio');
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        renderPatrimonio(json);
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

function renderPatrimonio(d) {
    const t = d.totais;
    const liquido = t.contas + t.investimentos - t.dividas;

    document.getElementById('patKPIs').innerHTML = `
        ${kpiCard('emerald','university','Contas Bancárias', brl(t.contas), 'Saldo disponível', 'neutral')}
        ${kpiCard('violet','chart-line','Investimentos', brl(t.investimentos), 'Valor de mercado', 'neutral')}
        ${kpiCard('rose','landmark','Dívidas', brl(t.dividas), 'Empréstimos ativos', 'neutral')}
        ${kpiCard(liquido >= 0 ? 'indigo':'rose','scale-balanced','Patrimônio Líquido', brl(liquido), 'Ativos − Passivos', 'neutral')}`;

    // Contas
    document.getElementById('patContas').innerHTML = !d.contas.length
        ? '<p class="text-muted text-sm" style="padding:1rem">Nenhuma conta.</p>'
        : d.contas.map(c =>
            `<div class="pat-item">
                <div class="d-flex align-center gap-1">
                    <span style="width:8px;height:8px;border-radius:50%;background:${c.cor || '#6366f1'};flex-shrink:0;display:inline-block"></span>
                    <div>
                        <div class="fw-600 text-sm">${esc(c.nome)}</div>
                        ${c.titular ? `<div class="text-xs text-muted">${esc(c.titular)}</div>` : ''}
                    </div>
                </div>
                <span class="fw-600 ${parseFloat(c.saldo_atual) >= 0 ? 'text-emerald' : 'text-rose'}">${brl(c.saldo_atual)}</span>
            </div>`).join('');

    // Investimentos
    document.getElementById('patInvest').innerHTML = !d.investimentos.length
        ? '<p class="text-muted text-sm" style="padding:1rem">Nenhum investimento.</p>'
        : d.investimentos.map(i => {
            const rend = parseFloat(i.valor_atual) - parseFloat(i.valor_aplicado);
            return `<div class="pat-item">
                <div>
                    <div class="fw-600 text-sm">${esc(i.nome)}</div>
                    <div class="text-xs text-muted">${i.tipo.toUpperCase()}</div>
                </div>
                <div class="text-right">
                    <div class="fw-600">${brl(i.valor_atual)}</div>
                    <div class="text-xs ${rend >= 0 ? 'text-emerald' : 'text-rose'}">${rend >= 0 ? '+' : ''}${brl(rend)}</div>
                </div>
            </div>`;
        }).join('');

    // Por titular
    const titulares = d.por_titular;
    const titTotal  = Object.values(titulares).reduce((a, b) => a + b, 0);
    document.getElementById('patTitular').innerHTML = !Object.keys(titulares).length
        ? '<p class="text-muted text-sm" style="padding:1rem">Cadastre o titular nas contas.</p>'
        : Object.entries(titulares).map(([tit, val]) => {
            const pct = titTotal > 0 ? Math.round(val / titTotal * 100) : 0;
            const ini = tit.charAt(0).toUpperCase();
            return `<div class="tit-row">
                <div class="tit-avatar">${ini}</div>
                <div style="flex:1">
                    <div class="fw-600 text-sm">${esc(tit)}</div>
                    <div class="progress-bar-bg" style="margin-top:.3rem">
                        <div class="progress-bar-fill indigo" style="width:${pct}%"></div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="fw-600">${brl(val)}</div>
                    <div class="text-xs text-muted">${pct}%</div>
                </div>
            </div>`;
        }).join('');

    // Compromissos
    const empHtml = d.emprestimos.map(e =>
        `<div class="pat-item">
            <div>
                <div class="fw-600 text-sm">${esc(e.nome)}</div>
                <div class="text-xs text-muted">${brl(e.valor_parcela)}/mês</div>
            </div>
            <span class="text-rose fw-600">${brl(e.saldo_devedor)}</span>
        </div>`).join('');
    const metHtml = d.metas.map(m => {
        const pct = parseFloat(m.valor_alvo) > 0 ? Math.min(100, Math.round(parseFloat(m.valor_atual) / parseFloat(m.valor_alvo) * 100)) : 0;
        return `<div class="pat-item">
            <div style="flex:1">
                <div class="d-flex justify-between text-sm"><span class="fw-600">${esc(m.nome)}</span><span class="text-xs">${pct}%</span></div>
                <div class="progress-bar-bg" style="margin-top:.3rem">
                    <div class="progress-bar-fill" style="width:${pct}%;background:${m.cor}"></div>
                </div>
            </div>
        </div>`;
    }).join('');
    document.getElementById('patCompromissos').innerHTML =
        (empHtml || metHtml)
            ? `<div class="pat-group-title" style="margin-top:0">Empréstimos</div>${empHtml || '<p class="text-muted text-sm" style="padding:.75rem">Nenhum.</p>'}
               <div class="pat-group-title">Metas Ativas</div>${metHtml || '<p class="text-muted text-sm" style="padding:.75rem">Nenhuma.</p>'}`
            : '<p class="text-muted text-sm" style="padding:1rem">Sem dados.</p>';
}

// ═══════════════════════════════════════════════════════════
// TAB 5 — ANUAL
// ═══════════════════════════════════════════════════════════
async function carregarAnual(ano) {
    [anoAtualNum, anoAtualNum-1, anoAtualNum-2, anoAtualNum-3, anoAtualNum-4].forEach(y => {
        const btn = document.getElementById('btnAnual' + y);
        if (btn) btn.className = 'btn btn-sm ' + (y === ano ? 'btn-primary' : 'btn-ghost');
    });

    try {
        const res  = await fetch(`backend/api/relatorios.php?acao=anual&ano=${ano}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        renderAnual(json);
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

function renderAnual(data) {
    const rows = data.dados;
    const tots = data.totais;
    const mesAtual = new Date().getMonth() + 1;
    const anoAtual = new Date().getFullYear();

    document.getElementById('anualTitulo').textContent = `Resumo Anual ${data.ano}`;

    mkChart('chartAnual', 'bar', {
        labels: rows.map(r => r.mes),
        datasets: [
            { label: 'Receitas', data: rows.map(r => r.receitas), backgroundColor: 'rgba(16,185,129,.75)', borderRadius: 4 },
            { label: 'Despesas', data: rows.map(r => r.despesas), backgroundColor: 'rgba(244,63,94,.75)',  borderRadius: 4 },
        ]
    }, { ...baseOpts,
        plugins: { ...baseOpts.plugins, legend: { display: true, labels: { color: fontColor, boxWidth: 10, font: { size: 11 } } } }
    });

    document.getElementById('anualTbody').innerHTML =
        rows.map(r => {
            const isCurrent = r.numero === mesAtual && data.ano === anoAtual;
            return `<tr class="${isCurrent ? 'atual-row' : ''}">
                <td class="fw-600 text-sm">${r.mes}${isCurrent ? ' ◀' : ''}</td>
                <td class="text-right text-emerald">${r.receitas > 0 ? brl(r.receitas) : '—'}</td>
                <td class="text-right text-rose">${r.despesas > 0 ? brl(r.despesas) : '—'}</td>
                <td class="text-right ${r.saldo >= 0 ? 'pos' : 'neg'}">${r.receitas > 0 || r.despesas > 0 ? brl(r.saldo) : '—'}</td>
                <td class="text-right ${r.acumulado >= 0 ? 'pos' : 'neg'}">${r.receitas > 0 || r.despesas > 0 ? brl(r.acumulado) : '—'}</td>
            </tr>`;
        }).join('') +
        `<tr class="totais-row">
            <td>TOTAL ${data.ano}</td>
            <td class="text-right text-emerald">${brl(tots.receitas)}</td>
            <td class="text-right text-rose">${brl(tots.despesas)}</td>
            <td class="text-right ${tots.saldo >= 0 ? 'pos' : 'neg'}">${brl(tots.saldo)}</td>
            <td class="text-right text-muted">—</td>
        </tr>`;
}

// ── Utilitários ───────────────────────────────────────────
function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ═══════════════════════════════════════════════════════════
// TAB 7 — RANKING
// ═══════════════════════════════════════════════════════════
async function carregarRanking() {
    const mes = document.getElementById('rkMes').value;
    const ano = document.getElementById('rkAno').value;

    const loading = `<div class="loading-overlay" style="padding:2rem"><i class="fa-solid fa-spinner fa-spin"></i></div>`;
    document.getElementById('rkKPIs').innerHTML     = `<div class="loading-overlay" style="grid-column:1/-1;padding:2rem"><i class="fa-solid fa-spinner fa-spin"></i></div>`;
    document.getElementById('rkDesLista').innerHTML = loading;
    document.getElementById('rkRecLista').innerHTML = loading;
    document.getElementById('rkCatGrid').innerHTML  = loading;

    try {
        const res  = await fetch(`backend/api/relatorios.php?acao=ranking&mes=${mes}&ano=${ano}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);

        renderRankingKPIs(json);
        renderRankingLista(json.despesas, 'Des', 'rose', 'Despesas');
        renderRankingLista(json.receitas, 'Rec', 'emerald', 'Receitas');
        renderRankingCats(json.cats_por_resp);
    } catch (err) {
        const msg = err.message.includes('exist') || err.message.includes('responsav')
            ? 'Execute a migração do banco de dados primeiro (<a href="database/migrate.php" style="color:var(--indigo)">migrate.php</a>).'
            : esc(err.message);
        ['rkKPIs','rkDesLista','rkRecLista','rkCatGrid'].forEach(id => {
            document.getElementById(id).innerHTML = `<div style="padding:1.5rem;color:var(--text-600);text-align:center;font-size:.85rem">${msg}</div>`;
        });
    }
}

function renderRankingKPIs(d) {
    const totalDes = d.despesas.reduce((s, r) => s + r.total, 0);
    const totalRec = d.receitas.reduce((s, r) => s + r.total, 0);
    const qtdPessoas = new Set([...d.despesas, ...d.receitas].map(r => (r.tipo || 'responsavel') + '_' + r.id)).size;
    const maior    = d.despesas[0];
    const maiorTcr = maior && maior.tipo === 'terceiro';

    document.getElementById('rkKPIs').innerHTML = `
        ${kpiCard('rose','arrow-trend-down','Total gasto', brl(totalDes),
            `${d.despesas.length} pessoa${d.despesas.length !== 1 ? 's' : ''}`, 'neutral')}
        ${kpiCard('emerald','arrow-trend-up','Total recebido', brl(totalRec),
            `${d.receitas.length} pessoa${d.receitas.length !== 1 ? 's' : ''}`, 'neutral')}
        ${kpiCard('amber','trophy','Maior gastador',
            maior ? esc(maior.nome) + (maiorTcr ? ' <span style="font-size:.6rem;vertical-align:middle;background:rgba(245,158,11,.18);color:var(--amber);padding:.1rem .3rem;border-radius:3px">3º</span>' : '') : '—',
            maior ? brl(maior.total) + ' · ' + maior.qtd + ' transações' : 'Sem dados', 'neutral')}
        ${kpiCard('indigo','users','Pessoas ativas', qtdPessoas,
            'com transações no período', 'neutral')}
    `;
}

const _medals = ['🥇','🥈','🥉'];

function renderRankingLista(lista, key, cor, label) {
    const elLista = document.getElementById(`rk${key}Lista`);
    const elSub   = document.getElementById(`rk${key}Sub`);
    const total   = lista.reduce((s, r) => s + r.total, 0);

    elSub.textContent = lista.length
        ? `${lista.length} pessoa${lista.length !== 1 ? 's' : ''} · ${brl(total)} total`
        : 'Sem dados no período';

    if (!lista.length) {
        elLista.innerHTML = `
            <div style="text-align:center;padding:2.5rem;color:var(--text-600)">
                <i class="fa-solid fa-user-slash fa-2x" style="margin-bottom:.75rem;display:block"></i>
                <div class="text-sm">Nenhuma transação vinculada a responsável.</div>
                <div class="text-xs" style="margin-top:.3rem">Vincule responsáveis às despesas em <a href="?p=despesas" style="color:var(--indigo)">Despesas</a>.</div>
            </div>`;
        return;
    }

    const maximo = lista[0].total;
    let html = '';

    lista.forEach((r, i) => {
        const pct      = maximo > 0 ? Math.round((r.total / maximo) * 100) : 0;
        const medal    = _medals[i] || `<span class="text-muted text-xs fw-600">${i + 1}º</span>`;
        const isTcr    = r.tipo === 'terceiro';
        const corFx    = r.cor || (isTcr ? '#f59e0b' : '#6366f1');
        const icone    = r.icone || (isTcr ? 'handshake' : 'user');
        const tcrBadge = isTcr
            ? `<span style="font-size:.6rem;font-weight:700;padding:.1rem .3rem;border-radius:3px;background:rgba(245,158,11,.18);color:var(--amber);margin-left:.3rem">3º</span>`
            : '';

        html += `
        <div style="padding:.75rem 1.25rem;border-bottom:1px solid var(--border)">
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.5rem">
                <span style="font-size:1.1rem;flex-shrink:0;min-width:1.5rem;text-align:center">${medal}</span>
                <div style="width:32px;height:32px;border-radius:50%;background:${corFx}22;color:${corFx};display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0">
                    <i class="fa-solid fa-${esc(icone)}"></i>
                </div>
                <div style="flex:1;min-width:0">
                    <div class="fw-600 text-sm">${esc(r.nome)}${tcrBadge}</div>
                    <div class="text-xs text-muted">${r.qtd} transação${r.qtd !== 1 ? 'ões' : ''}</div>
                </div>
                <div style="text-align:right;flex-shrink:0">
                    <div class="fw-700 text-sm" style="color:var(--${cor})">${brl(r.total)}</div>
                    <div class="text-xs text-muted">${pct}% do 1º</div>
                </div>
            </div>
            <div class="progress-bar-bg">
                <div class="progress-bar-fill ${cor}" style="width:${pct}%;background:${corFx}"></div>
            </div>
        </div>`;
    });

    elLista.innerHTML = html;
}

function renderRankingCats(cats) {
    const el    = document.getElementById('rkCatGrid');
    const elSub = document.getElementById('rkCatSub');

    if (!cats || !cats.length) {
        elSub.textContent = 'Sem dados';
        el.innerHTML = `<div style="text-align:center;padding:2rem;color:var(--text-600);font-size:.85rem">
            Sem categorias de despesa vinculadas a responsáveis no período.
        </div>`;
        return;
    }

    elSub.textContent = `${cats.length} responsável${cats.length !== 1 ? 'is' : ''} com despesas categorizadas`;

    let html = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem">';

    cats.forEach(r => {
        const isTcrCat  = r.resp_tipo === 'terceiro';
        const corFx     = r.resp_cor || (isTcrCat ? '#f59e0b' : '#6366f1');
        const icone     = r.resp_icone || (isTcrCat ? 'handshake' : 'user');
        const tcrBadge  = isTcrCat
            ? `<span style="font-size:.6rem;font-weight:700;padding:.1rem .3rem;border-radius:3px;background:rgba(245,158,11,.18);color:var(--amber);margin-left:.3rem">3º</span>`
            : '';
        html += `
        <div style="border:1px solid var(--border);border-radius:var(--radius);overflow:hidden">
            <div style="background:${corFx}15;padding:.75rem 1rem;display:flex;align-items:center;gap:.6rem;border-bottom:1px solid var(--border)">
                <div style="width:28px;height:28px;border-radius:50%;background:${corFx}33;color:${corFx};display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0">
                    <i class="fa-solid fa-${esc(icone)}"></i>
                </div>
                <div class="fw-700 text-sm" style="color:${corFx}">${esc(r.resp_nome)}${tcrBadge}</div>
                <span class="text-muted text-xs" style="margin-left:auto">${brl(r.total_resp)}</span>
            </div>
            <div>`;

        r.categorias.forEach(c => {
            const pct = r.total_resp > 0 ? Math.round((c.total / r.total_resp) * 100) : 0;
            const catCor = c.cor || '#64748b';
            html += `
                <div style="display:flex;align-items:center;gap:.75rem;padding:.5rem 1rem;border-bottom:1px solid var(--border)">
                    <span style="width:8px;height:8px;border-radius:50%;background:${catCor};flex-shrink:0;display:inline-block"></span>
                    <span class="text-sm" style="flex:1">${esc(c.cat_nome)}</span>
                    <span style="font-size:.72rem;color:var(--text-400)">${pct}%</span>
                    <span class="fw-600 text-sm text-rose">${brl(c.total)}</span>
                </div>`;
        });

        html += `</div></div>`;
    });

    html += '</div>';
    el.innerHTML = html;
}

// ═══════════════════════════════════════════════════════════
// TAB 8 — POR RESPONSÁVEL
// ═══════════════════════════════════════════════════════════
async function carregarPorResp() {
    const mes = document.getElementById('prMes').value;
    const ano = document.getElementById('prAno').value;

    document.getElementById('prKPIs').innerHTML =
        `<div class="loading-overlay" style="grid-column:1/-1;padding:2rem"><i class="fa-solid fa-spinner fa-spin"></i></div>`;
    document.getElementById('prCards').innerHTML =
        `<div class="loading-overlay"><i class="fa-solid fa-spinner fa-spin"></i></div>`;

    try {
        const res  = await fetch(`backend/api/relatorios.php?acao=por_responsavel&mes=${mes}&ano=${ano}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        renderPorRespKPIs(json);
        renderPorRespChart(json);
        renderPorRespCards(json);
    } catch (err) {
        ['prKPIs','prCards'].forEach(id => {
            document.getElementById(id).innerHTML =
                `<div style="padding:1.5rem;color:var(--rose);text-align:center">${esc(err.message)}</div>`;
        });
    }
}

function renderPorRespKPIs(d) {
    const t    = d.totais;
    const qtd  = d.dados.length;
    const qtdR = d.dados.filter(r => r.tipo !== 'terceiro').length;
    const qtdT = d.dados.filter(r => r.tipo === 'terceiro').length;
    const sub  = [qtdR ? `${qtdR} responsável${qtdR !== 1 ? 'is' : ''}` : '',
                  qtdT ? `${qtdT} terceiro${qtdT !== 1 ? 's' : ''}` : ''].filter(Boolean).join(' · ') || 'Sem dados';
    document.getElementById('prKPIs').innerHTML = `
        ${kpiCard('emerald','arrow-trend-up','Receitas Totais', brl(t.receitas),
            sub, 'neutral')}
        ${kpiCard('rose','arrow-trend-down','Despesas Totais', brl(t.despesas),
            'Soma de todas as pessoas', 'neutral')}
        ${kpiCard(t.saldo >= 0 ? 'indigo':'rose', t.saldo >= 0 ? 'arrow-up':'arrow-down',
            'Saldo Consolidado', (t.saldo >= 0 ? '+' : '') + brl(t.saldo),
            'Receitas − Despesas', 'neutral')}
        ${kpiCard('amber','people-group','Pessoas', qtd,
            'com movimentações no período', 'neutral')}`;
}

function renderPorRespChart(d) {
    const resps = d.dados;
    document.getElementById('prChartSub').textContent = d.label_ano;

    if (!resps.length) return;

    mkChart('chartPorResp', 'bar', {
        labels: resps.map(r => r.nome),
        datasets: [
            {
                label: 'Receitas',
                data: resps.map(r => parseFloat(r.receitas)),
                backgroundColor: 'rgba(16,185,129,.75)',
                borderRadius: 5,
            },
            {
                label: 'Despesas',
                data: resps.map(r => parseFloat(r.despesas)),
                backgroundColor: 'rgba(244,63,94,.75)',
                borderRadius: 5,
            },
        ]
    }, { ...baseOpts,
        plugins: { ...baseOpts.plugins,
            legend: { display: true, labels: { color: fontColor, boxWidth: 10, font: { size: 11 } } },
            tooltip: { callbacks: { label: ctx => ` ${ctx.dataset.label}: ${brl(ctx.parsed.y)}` } }
        }
    });
}

function renderPorRespCards(d) {
    const resps = d.dados;
    const el    = document.getElementById('prCards');

    if (!resps.length) {
        el.innerHTML = `
            <div style="text-align:center;padding:3rem;color:var(--text-600)">
                <i class="fa-solid fa-people-group fa-2x" style="margin-bottom:.75rem;display:block"></i>
                <div>Nenhum responsável com transações no período.</div>
                <div class="text-sm" style="margin-top:.3rem">
                    Vincule responsáveis às despesas/receitas nas páginas de
                    <a href="?p=despesas" style="color:var(--indigo)">Despesas</a> e
                    <a href="?p=receitas" style="color:var(--indigo)">Receitas</a>.
                </div>
            </div>`;
        return;
    }

    const maxDes = Math.max(...resps.map(r => parseFloat(r.despesas)), 1);

    el.innerHTML = `<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1rem;padding:.5rem 0">` +
    resps.map(r => {
        const isTcr     = r.tipo === 'terceiro';
        const cor       = r.cor  || (isTcr ? '#f59e0b' : '#6366f1');
        const icone     = r.icone || (isTcr ? 'handshake' : 'user');
        const rec       = parseFloat(r.receitas);
        const des       = parseFloat(r.despesas);
        const saldo     = parseFloat(r.saldo);
        const pctDes    = maxDes > 0 ? Math.round(des / maxDes * 100) : 0;
        const saldoCls  = saldo >= 0 ? 'text-emerald' : 'text-rose';
        const cats      = r.categorias || [];
        const tcrBadge  = isTcr
            ? `<span style="font-size:.6rem;font-weight:700;padding:.1rem .3rem;border-radius:3px;background:rgba(245,158,11,.18);color:var(--amber);margin-left:.4rem;vertical-align:middle">3º</span>`
            : '';

        const mensal = r.mensal && r.mensal.length > 0
            ? `<div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid var(--border)">
                <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
                            color:var(--text-600);margin-bottom:.5rem">Evolução mensal</div>
                <div style="display:grid;grid-template-columns:repeat(12,1fr);gap:2px;align-items:end;height:40px">
                    ${r.mensal.map(m => {
                        const h = Math.round((parseFloat(m.des) / (maxDes / r.mensal.length * 2 || 1)) * 36);
                        return `<div title="${m.label}: Desp ${brl(m.des)}"
                                     style="background:rgba(244,63,94,.65);border-radius:2px 2px 0 0;
                                            height:${Math.max(2, Math.min(36, h))}px"></div>`;
                    }).join('')}
                </div>
                <div style="display:flex;justify-content:space-between;font-size:.6rem;color:var(--text-600);margin-top:2px">
                    <span>${r.mensal[0]?.label || ''}</span><span>${r.mensal[11]?.label || ''}</span>
                </div>
              </div>` : '';

        const catsHtml = cats.length
            ? cats.map(c => {
                const pct = des > 0 ? Math.round(c.total / des * 100) : 0;
                return `<div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.3rem">
                    <span style="width:8px;height:8px;border-radius:50%;background:${c.cor};flex-shrink:0;display:inline-block"></span>
                    <span class="text-sm" style="flex:1;min-width:0" class="truncate">${esc(c.nome)}</span>
                    <span class="text-xs text-muted">${pct}%</span>
                    <span class="fw-600 text-sm text-rose">${brl(c.total)}</span>
                </div>`;
            }).join('')
            : '<div class="text-xs text-muted" style="padding:.25rem 0">Sem categorias</div>';

        return `
        <div style="background:var(--bg-800);border:1px solid var(--border);border-radius:var(--radius-lg);
                    overflow:hidden;border-top:3px solid ${cor}">
            <div style="padding:.875rem 1rem;display:flex;align-items:center;gap:.75rem">
                <div style="width:40px;height:40px;border-radius:50%;flex-shrink:0;
                            background:${cor}22;color:${cor};
                            display:flex;align-items:center;justify-content:center;font-size:.9rem">
                    <i class="fa-solid fa-${esc(icone)}"></i>
                </div>
                <div style="flex:1;min-width:0">
                    <div class="fw-700" style="font-size:.9rem">${esc(r.nome)}${tcrBadge}</div>
                    <div class="text-xs text-muted">${r.qtd} transação${r.qtd != 1 ? 'ões' : ''}</div>
                </div>
                <div style="text-align:right">
                    <div class="fw-700 ${saldoCls}" style="font-size:.95rem">
                        ${saldo >= 0 ? '+' : ''}${brl(saldo)}
                    </div>
                    <div class="text-xs text-muted">saldo</div>
                </div>
            </div>

            <div style="padding:.25rem 1rem .75rem">
                <div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:.4rem">
                    <div>
                        <span class="text-emerald fw-600">${brl(rec)}</span>
                        <span class="text-muted text-xs"> receitas</span>
                    </div>
                    <div>
                        <span class="text-rose fw-600">${brl(des)}</span>
                        <span class="text-muted text-xs"> despesas</span>
                    </div>
                </div>
                <div style="height:5px;border-radius:99px;background:var(--bg-600);overflow:hidden">
                    <div style="height:100%;width:${pctDes}%;background:${cor};border-radius:99px"></div>
                </div>
            </div>

            ${cats.length ? `
            <div style="padding:.5rem 1rem .875rem;border-top:1px solid var(--border)">
                <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
                            color:var(--text-600);margin-bottom:.5rem">Top categorias de despesa</div>
                ${catsHtml}
            </div>` : ''}

            ${mensal}
        </div>`;
    }).join('') + `</div>`;
}

// ═══════════════════════════════════════════════════════════
// TAB IR — IMPOSTO DE RENDA
// ═══════════════════════════════════════════════════════════
let _irData = null;

const IR_CORES = {
    tributavel:       '#f59e0b',
    isento:           '#10b981',
    deducao_saude:    '#f43f5e',
    deducao_educacao: '#6366f1',
    deducao_previd:   '#8b5cf6',
    deducao_outro:    '#06b6d4',
};

async function carregarIR() {
    const ano = document.getElementById('irAno')?.value || (anoAtualNum - 1);
    document.getElementById('irSub').textContent = `Ano-Calendário ${ano}`;
    document.getElementById('irContent').innerHTML =
        `<div style="text-align:center;padding:3rem;color:var(--text-600)">
            <i class="fa-solid fa-spinner fa-spin fa-2x" style="margin-bottom:.875rem;display:block"></i>
            Carregando...
         </div>`;

    try {
        const res  = await fetch(`backend/api/relatorios.php?acao=ir&ano=${ano}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        _irData = json;

        // Aviso: coluna tipo_ir ainda não existe (migrate.php não rodou)
        if (json.aviso_migracao) {
            document.getElementById('irContent').innerHTML = `
                <div style="background:var(--rose-bg);border:1px solid var(--rose);border-radius:var(--radius);
                            padding:1.25rem;display:flex;align-items:flex-start;gap:.875rem">
                    <i class="fa-solid fa-circle-exclamation" style="color:var(--rose);font-size:1.1rem;flex-shrink:0;margin-top:.1rem"></i>
                    <div>
                        <div class="fw-600 text-sm" style="color:var(--rose);margin-bottom:.3rem">Migração necessária</div>
                        <div class="text-sm text-muted">
                            A coluna <code style="background:var(--bg-600);padding:.1rem .35rem;border-radius:.25rem">tipo_ir</code>
                            ainda não existe no banco. Execute
                            <a href="database/migrate.php" target="_blank" style="color:var(--indigo)">database/migrate.php</a>
                            e recarregue esta página.
                        </div>
                    </div>
                </div>`;
            document.getElementById('irAviso').style.display = 'none';
            return;
        }

        // Aviso se não há categorias classificadas
        const aviso = document.getElementById('irAviso');
        if (aviso) {
            aviso.style.display    = json.total_classificadas === 0 ? 'flex' : 'none';
            aviso.style.alignItems = 'center';
        }

        renderIR(json);
    } catch (err) {
        document.getElementById('irContent').innerHTML =
            `<div style="color:var(--rose);padding:2rem;text-align:center">${esc(err.message)}</div>`;
    }
}

function renderIR(d) {
    const grupos = d.grupos;
    const meses  = d.meses; // ['','Jan','Fev',...,'Dez']
    const ano    = d.ano;

    let html = '';
    let totalRendimentos = 0;
    let totalDeducoes    = 0;

    // Renderiza cada grupo
    for (const [chave, g] of Object.entries(grupos)) {
        if (!g.categorias.length) continue;

        const cor        = IR_CORES[chave] || '#64748b';
        const isDed      = chave.startsWith('deducao');
        totalRendimentos += isDed ? 0 : g.total;
        totalDeducoes    += isDed ? g.total : 0;

        // Alerta de limite (educação)
        let limiteHtml = '';
        if (g.limite) {
            const pct  = g.total > 0 ? Math.min(100, Math.round(g.total / g.limite * 100)) : 0;
            const over = g.total > g.limite;
            limiteHtml = `
                <span class="ir-limite-warn"
                      style="background:${over ? 'rgba(244,63,94,.15)' : 'rgba(245,158,11,.12)'};
                             color:${over ? 'var(--rose)' : 'var(--amber)'}">
                    <i class="fa-solid fa-${over ? 'triangle-exclamation' : 'circle-info'} fa-xs"></i>
                    Limite: ${brl(g.limite)} — ${pct}% utilizado${over ? ' ⚠ EXCEDIDO' : ''}
                </span>`;
        }

        html += `<div class="ir-card">
            <div class="ir-card-header">
                <div class="ir-card-title">
                    <div class="ir-icon" style="background:${cor}22;color:${cor}">
                        <i class="fa-solid fa-${g.icone}"></i>
                    </div>
                    <span>${g.label}</span>
                    ${limiteHtml}
                </div>
                <div class="ir-total" style="color:${cor}">${brl(g.total)}</div>
            </div>
            <div style="overflow-x:auto">
                <table class="ir-table">
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            ${[...Array(12)].map((_, i) => `<th>${meses[i+1]}</th>`).join('')}
                            <th>Total Anual</th>
                            <th>Lançamentos</th>
                        </tr>
                    </thead>
                    <tbody>`;

        // Totais por mês para este grupo
        const totMensal = Array(13).fill(0);

        g.categorias.forEach(cat => {
            html += `<tr>
                <td>
                    <div style="display:flex;align-items:center;gap:.4rem">
                        <span style="width:8px;height:8px;border-radius:50%;background:${cat.cor};flex-shrink:0;display:inline-block"></span>
                        ${esc(cat.nome)}
                    </div>
                </td>`;
            for (let m = 1; m <= 12; m++) {
                const v = cat.mensal[m] || 0;
                totMensal[m] += v;
                html += `<td class="${v === 0 ? 'zero' : ''}">${v > 0 ? brl(v) : '—'}</td>`;
            }
            html += `<td class="fw-600">${brl(cat.total)}</td>
                     <td class="text-muted">${cat.qtd}</td>
                 </tr>`;
        });

        // Linha de total do grupo
        html += `<tr class="total-row">
            <td>TOTAL — ${g.label}</td>
            ${totMensal.slice(1).map(v => `<td>${v > 0 ? brl(v) : '—'}</td>`).join('')}
            <td style="color:${cor}">${brl(g.total)}</td>
            <td></td>
        </tr>`;

        html += `</tbody></table></div></div>`;
    }

    // Resumo final
    const baseCalculo = totalRendimentos - totalDeducoes;
    html += `
    <div class="ir-card">
        <div class="ir-card-header">
            <div class="ir-card-title">
                <div class="ir-icon" style="background:rgba(99,102,241,.15);color:var(--indigo)">
                    <i class="fa-solid fa-scale-balanced"></i>
                </div>
                Resumo — Ano-Calendário ${ano}
            </div>
        </div>
        <div class="ir-resumo-grid" style="padding:1.25rem">
            <div class="ir-resumo-item">
                <div class="text-xs text-muted" style="margin-bottom:.35rem">Rendimentos Tributáveis</div>
                <div class="fw-700 text-amber" style="font-size:1.05rem">${brl(grupos.tributavel?.total || 0)}</div>
            </div>
            <div class="ir-resumo-item">
                <div class="text-xs text-muted" style="margin-bottom:.35rem">Rendimentos Isentos</div>
                <div class="fw-700 text-emerald" style="font-size:1.05rem">${brl(grupos.isento?.total || 0)}</div>
            </div>
            <div class="ir-resumo-item">
                <div class="text-xs text-muted" style="margin-bottom:.35rem">Total Deduções</div>
                <div class="fw-700 text-rose" style="font-size:1.05rem">- ${brl(totalDeducoes)}</div>
            </div>
            <div class="ir-resumo-item" style="border:1px solid var(--indigo);background:var(--indigo-soft)">
                <div class="text-xs" style="color:var(--indigo);font-weight:600;margin-bottom:.35rem">Base de Cálculo</div>
                <div class="fw-700" style="font-size:1.1rem;color:var(--indigo)">${brl(Math.max(0, baseCalculo))}</div>
                <div class="text-xs text-muted" style="margin-top:.2rem">Tributável − Deduções</div>
            </div>
        </div>
        <div style="padding:.75rem 1.25rem;border-top:1px solid var(--border);font-size:.72rem;color:var(--text-600)">
            <i class="fa-solid fa-circle-info fa-xs"></i>
            Este relatório é informativo e considera apenas as transações lançadas no sistema com categorias classificadas para IR.
            Consulte um contador para declaração oficial.
        </div>
    </div>`;

    if (!html) {
        html = `<div style="text-align:center;padding:3rem;color:var(--text-600)">
            <i class="fa-solid fa-file-invoice-dollar fa-2x" style="margin-bottom:.875rem;display:block;color:var(--amber)"></i>
            <div class="fw-600">Nenhuma transação classificada para IR em ${ano}.</div>
            <div class="text-sm" style="margin-top:.4rem">
                Acesse <a href="?p=categorias" style="color:var(--indigo)">Categorias</a> e defina a
                "Classificação Imposto de Renda" para cada categoria relevante.
            </div>
        </div>`;
    }

    document.getElementById('irContent').innerHTML = html;
}

// ── Exportar CSV do IR ─────────────────────────────────────
function exportarIRCSV() {
    if (!_irData) return;
    const ano    = _irData.ano;
    const meses  = _irData.meses;
    const grupos = _irData.grupos;

    const linhas = [
        ['Tipo IR', 'Grupo', 'Categoria', ...meses.slice(1), 'Total Anual', 'Lançamentos'],
    ];

    for (const [chave, g] of Object.entries(grupos)) {
        if (!g.categorias.length) continue;
        g.categorias.forEach(cat => {
            linhas.push([
                chave, g.label, cat.nome,
                ...[...Array(12)].map((_, i) => (cat.mensal[i+1] || 0).toFixed(2).replace('.', ',')),
                cat.total.toFixed(2).replace('.', ','),
                cat.qtd,
            ]);
        });
        // Linha de total do grupo
        const totMensal = Array(12).fill(0);
        g.categorias.forEach(cat => {
            for (let m = 1; m <= 12; m++) totMensal[m-1] += cat.mensal[m] || 0;
        });
        linhas.push([
            chave, g.label, `TOTAL — ${g.label}`,
            ...totMensal.map(v => v.toFixed(2).replace('.', ',')),
            g.total.toFixed(2).replace('.', ','), '',
        ]);
        linhas.push([]);
    }

    const csv = linhas.map(l =>
        l.map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(';')
    ).join('\r\n');

    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `IR_${ano}_relatorio.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

// ── Inicialização ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    _initPeriodoDatasRelatorios();
    carregarResumo();
});

// ═══════════════════════════════════════════════════════════
// TAB 6 — PREVISÃO DO MÊS
// ═══════════════════════════════════════════════════════════
async function carregarPrevisao() {
    const mes = document.getElementById('pvMes').value;
    const ano = document.getElementById('pvAno').value;

    document.getElementById('pvKPIs').innerHTML =
        `<div class="loading-overlay" style="grid-column:1/-1;padding:2rem"><i class="fa-solid fa-spinner fa-spin"></i></div>`;
    document.getElementById('pvTbody').innerHTML =
        `<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-600)"><i class="fa-solid fa-spinner fa-spin"></i></td></tr>`;
    document.getElementById('pvProgress').style.display = 'none';

    try {
        const res  = await fetch(`backend/api/relatorios.php?acao=previsao&mes=${mes}&ano=${ano}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);

        renderPrevisaoKPIs(json.kpi);
        renderPrevisaoTabela(json.previsao, json.mes, json.ano);
        renderPrevisaoProgress(json.kpi);
    } catch (err) {
        document.getElementById('pvKPIs').innerHTML =
            `<div style="grid-column:1/-1;color:var(--rose);padding:1rem">${esc(err.message)}</div>`;
    }
}

function renderPrevisaoKPIs(k) {
    const pctDes = k.prev_despesas > 0 ? Math.round(k.pago_despesas / k.prev_despesas * 100) : 0;
    const pctRec = k.prev_receitas > 0 ? Math.round(k.pago_receitas / k.prev_receitas * 100) : 0;
    const saldoCls = k.saldo_previsto >= 0 ? 'indigo' : 'rose';
    const saldoIcon = k.saldo_previsto >= 0 ? 'arrow-up' : 'arrow-down';

    document.getElementById('pvKPIs').innerHTML = `
        ${kpiCard('emerald','arrow-trend-up','Receitas Previstas', brl(k.prev_receitas),
            `${brl(k.pago_receitas)} recebido · ${pctRec}%`, 'neutral')}
        ${kpiCard('rose','arrow-trend-down','Despesas Previstas', brl(k.prev_despesas),
            `${brl(k.pago_despesas)} pago · ${pctDes}%`, 'neutral')}
        ${kpiCard(saldoCls, saldoIcon, 'Saldo Previsto',
            (k.saldo_previsto >= 0 ? '+' : '') + brl(k.saldo_previsto),
            `Realizado: ${(k.saldo_realizado >= 0 ? '+' : '') + brl(k.saldo_realizado)}`, 'neutral')}
        ${kpiCard(k.pend_despesas > 0 ? 'amber' : 'emerald', 'clock', 'A Realizar',
            brl(k.pend_despesas + k.pend_receitas > 0 ? k.pend_despesas : k.nao_des),
            `${k.pend_despesas > 0 ? brl(k.pend_despesas) + ' pendente' : 'Não há pendências'}`, 'neutral')}
    `;
}

function renderPrevisaoProgress(k) {
    const pctDes = k.prev_despesas > 0 ? Math.min(100, Math.round(k.pago_despesas / k.prev_despesas * 100)) : 0;
    const pctRec = k.prev_receitas > 0 ? Math.min(100, Math.round(k.pago_receitas / k.prev_receitas * 100)) : 0;
    document.getElementById('pvPctDes').textContent = pctDes + '%';
    document.getElementById('pvPctRec').textContent = pctRec + '%';
    document.getElementById('pvBarDes').style.width  = pctDes + '%';
    document.getElementById('pvBarRec').style.width  = pctRec + '%';
    document.getElementById('pvProgress').style.display = '';
}

function fmtData(s) {
    if (!s) return '—';
    const d = s.split('T')[0].split('-');
    return `${d[2]}/${d[1]}/${d[0]}`;
}

const _labFreqPv = {
    diaria:'Diária',semanal:'Semanal',quinzenal:'Quinzenal',
    mensal:'Mensal',bimestral:'Bimestral',trimestral:'Trimestral',
    semestral:'Semestral',anual:'Anual'
};

function renderPrevisaoTabela(itens, mes, ano) {
    const meses = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                   'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

    document.getElementById('pvTabelaTitulo').textContent = `Contas de ${meses[+mes]} / ${ano}`;
    document.getElementById('pvTabelaSub').textContent = `${itens.length} lançamento${itens.length !== 1 ? 's' : ''} previstos`;

    if (!itens.length) {
        document.getElementById('pvTbody').innerHTML =
            `<tr><td colspan="6" style="text-align:center;padding:3rem;color:var(--text-600)">
                <i class="fa-solid fa-calendar-xmark fa-2x" style="margin-bottom:.75rem;display:block"></i>
                Nenhuma conta fixa prevista para este mês.
                <br><span class="text-xs">Cadastre contas fixas em <a href="?p=recorrencias" style="color:var(--indigo)">Contas Fixas</a>.</span>
            </td></tr>`;
        return;
    }

    const statusInfo = {
        pago:        { cls: 'pago',     icon: 'circle-check', label: 'Pago',      cor: 'var(--emerald)' },
        pendente:    { cls: 'pendente', icon: 'clock',         label: 'Pendente', cor: 'var(--amber)' },
        nao_gerado:  { cls: 'cancelado',icon: 'circle',        label: 'A gerar',  cor: 'var(--text-600)' },
    };

    // Agrupa por receitas e despesas dentro de cada tipo por status
    const grupos = { pago: [], pendente: [], nao_gerado: [] };
    itens.forEach(it => { (grupos[it.tx_status] || grupos.nao_gerado).push(it); });

    let html = '';
    const ordemGrupos = [
        ['pendente', 'Pendentes'],
        ['nao_gerado', 'Não Geradas'],
        ['pago', 'Já Realizadas'],
    ];

    for (const [key, labelGrupo] of ordemGrupos) {
        const lista = grupos[key];
        if (!lista.length) continue;

        const totalGrupo = lista.reduce((s, r) => s + (r.tipo === 'despesa' ? -r.valor : r.valor), 0);
        const totalStr   = (totalGrupo >= 0 ? '+' : '') + brl(Math.abs(totalGrupo));

        html += `<tr style="background:var(--bg-700)">
            <td colspan="5" style="padding:.4rem 1rem;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-600)">
                ${labelGrupo} · ${lista.length} item${lista.length !== 1 ? 's' : ''}
            </td>
            <td style="padding:.4rem 1rem;text-align:right;font-size:.75rem;font-weight:700;color:${totalGrupo >= 0 ? 'var(--emerald)' : 'var(--rose)'}">${totalStr}</td>
        </tr>`;

        lista.forEach(it => {
            const si  = statusInfo[it.tx_status] || statusInfo.nao_gerado;
            const tipoColor = it.tipo === 'receita' ? 'var(--emerald)' : 'var(--rose)';
            const tipoIcon  = it.tipo === 'receita' ? 'arrow-up' : 'arrow-down';
            const tipoSoft  = it.tipo === 'receita' ? 'var(--emerald-soft)' : 'var(--rose-soft)';
            const valorStr  = (it.tipo === 'receita' ? '+' : '-') + brl(it.valor);

            const catBadge = it.cat_nome
                ? `<span class="badge" style="background:${it.cat_cor||'#334155'}22;color:${it.cat_cor||'#94a3b8'}">${esc(it.cat_nome)}</span>`
                : '<span class="text-muted text-xs">—</span>';

            const dataStr = it.tx_data
                ? fmtData(it.tx_data) + ' <span class="text-xs text-muted">(real)</span>'
                : `Dia ${it.dia_venc}`;

            html += `<tr>
                <td>
                    <div class="d-flex align-center gap-1">
                        <div class="tx-icon" style="background:${tipoSoft};color:${tipoColor};width:28px;height:28px;font-size:.72rem">
                            <i class="fa-solid fa-${tipoIcon} fa-xs"></i>
                        </div>
                        <div>
                            <div class="fw-600 text-sm">${esc(it.descricao)}</div>
                            <div class="text-xs text-muted">${_labFreqPv[it.frequencia] || it.frequencia}</div>
                        </div>
                    </div>
                </td>
                <td>${catBadge}</td>
                <td class="text-sm text-muted">${esc(it.conta_nome || '—')}</td>
                <td class="text-sm text-muted">${dataStr}</td>
                <td style="text-align:center">
                    <span class="badge ${si.cls}" style="gap:.3rem">
                        <i class="fa-solid fa-${si.icon} fa-xs" style="color:${si.cor}"></i>
                        ${si.label}
                    </span>
                </td>
                <td class="text-right fw-600" style="color:${tipoColor}">${valorStr}</td>
            </tr>`;
        });
    }

    document.getElementById('pvTbody').innerHTML = html;
}
</script>
