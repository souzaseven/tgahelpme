<?php
// ============================================================
// _tab_resumo.php — Aba "Visão Geral" de Relatórios & BI.
// Partial de pages/relatorios.php — usa $nomesMeses, $mes, $ano
// do arquivo pai.
// ============================================================
?>
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
