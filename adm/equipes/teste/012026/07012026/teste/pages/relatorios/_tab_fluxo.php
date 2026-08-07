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
