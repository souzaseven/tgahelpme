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
