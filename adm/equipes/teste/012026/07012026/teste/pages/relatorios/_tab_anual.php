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
