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
