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
