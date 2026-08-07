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
                    <tr><td colspan="6" class="loading-overlay">
                        <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
