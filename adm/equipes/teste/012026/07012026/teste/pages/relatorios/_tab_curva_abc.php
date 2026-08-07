<div id="tab-curva_abc" class="tab-pane">
    <div class="d-flex gap-1 align-center" style="margin-bottom:1.25rem;flex-wrap:wrap">
        <select id="abcMes" class="form-control" style="max-width:140px" onchange="carregarCurvaAbc()">
            <?php foreach ($nomesMeses as $n => $nome): if (!$n) continue; ?>
            <option value="<?= $n ?>" <?= $n === $mes ? 'selected' : '' ?>><?= $nome ?></option>
            <?php endforeach ?>
        </select>
        <select id="abcAno" class="form-control" style="max-width:95px" onchange="carregarCurvaAbc()">
            <?php for ($y = $ano; $y >= $ano - 4; $y--): ?>
            <option value="<?= $y ?>" <?= $y === $ano ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor ?>
        </select>
        <select id="abcTipo" class="form-control" style="max-width:140px" onchange="carregarCurvaAbc()">
            <option value="despesa">Despesas</option>
            <option value="receita">Receitas</option>
        </select>
    </div>

    <div class="page-sub" style="margin-bottom:1.25rem">
        Classificação 80/15/5: <strong style="color:#f43f5e">A</strong> = categorias que somam os primeiros 80% do total —
        onde cortar gasto rende mais; <strong style="color:#f59e0b">B</strong> = até 95%;
        <strong style="color:#64748b">C</strong> = o restante.
    </div>

    <div class="kpi-grid" style="margin-bottom:1.25rem">
        <div class="kpi-card" style="border-left:3px solid #f43f5e">
            <div class="kpi-header"><div class="kpi-label">Classe A</div></div>
            <div class="kpi-value" id="abcResumoA">—</div>
            <div class="kpi-trend neutral">Prioridade de corte</div>
        </div>
        <div class="kpi-card" style="border-left:3px solid #f59e0b">
            <div class="kpi-header"><div class="kpi-label">Classe B</div></div>
            <div class="kpi-value" id="abcResumoB">—</div>
            <div class="kpi-trend neutral">Impacto moderado</div>
        </div>
        <div class="kpi-card" style="border-left:3px solid #64748b">
            <div class="kpi-header"><div class="kpi-label">Classe C</div></div>
            <div class="kpi-value" id="abcResumoC">—</div>
            <div class="kpi-trend neutral">Baixo impacto individual</div>
        </div>
    </div>

    <div class="card" style="margin-bottom:1.25rem">
        <div class="card-header">
            <div class="card-title">Gráfico de Pareto</div>
        </div>
        <div class="card-body">
            <div class="chart-wrap" style="height:320px">
                <canvas id="chartAbc"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Categorias ordenadas por gasto</div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Categoria</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">% do total</th>
                        <th class="text-right">% acumulado</th>
                        <th class="text-right">Classe</th>
                    </tr>
                </thead>
                <tbody id="abcTbody">
                    <tr><td colspan="6" class="loading-overlay"><i class="fa-solid fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
