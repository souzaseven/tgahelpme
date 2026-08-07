<?php
// ============================================================
// _tab_exportar.php — Aba "Exportar" de Configurações.
// Partial de pages/configuracoes.php — usa $nomesMeses, $anoAtual,
// $mesAtual do arquivo pai.
// ============================================================
?>
<div id="cfg-exportar" class="cfg-pane">

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.25rem">

        <!-- Export transações -->
        <div class="export-card">
            <div class="d-flex align-center gap-1" style="margin-bottom:1rem">
                <div class="kpi-icon emerald"><i class="fa-solid fa-table"></i></div>
                <div>
                    <div class="fw-700">Transações</div>
                    <div class="text-xs text-muted">Exportar lançamentos como CSV</div>
                </div>
            </div>

            <div class="form-grid form-grid-2" style="margin-bottom:.875rem">
                <div class="form-group">
                    <label class="form-label">Mês inicial</label>
                    <select id="exDeMes" class="form-control">
                        <?php foreach ($nomesMeses as $n => $nome): if (!$n) continue; ?>
                        <option value="<?= $n ?>" <?= $n === 1 ? 'selected' : '' ?>><?= $nome ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Ano inicial</label>
                    <select id="exDeAno" class="form-control">
                        <?php for ($y = $anoAtual; $y >= $anoAtual - 4; $y--): ?>
                        <option value="<?= $y ?>" <?= $y === $anoAtual ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Mês final</label>
                    <select id="exAteMes" class="form-control">
                        <?php foreach ($nomesMeses as $n => $nome): if (!$n) continue; ?>
                        <option value="<?= $n ?>" <?= $n === $mesAtual ? 'selected' : '' ?>><?= $nome ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Ano final</label>
                    <select id="exAteAno" class="form-control">
                        <?php for ($y = $anoAtual; $y >= $anoAtual - 4; $y--): ?>
                        <option value="<?= $y ?>" <?= $y === $anoAtual ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor ?>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:.875rem">
                <label class="form-label">Tipo de transação</label>
                <select id="exTxTipo" class="form-control">
                    <option value="">Todos</option>
                    <option value="receita">Receitas</option>
                    <option value="despesa">Despesas</option>
                    <option value="transferencia">Transferências</option>
                </select>
            </div>

            <button class="btn btn-success w-full" onclick="exportarCSV()" style="justify-content:center">
                <i class="fa-solid fa-download"></i> Baixar CSV
            </button>
        </div>

        <!-- Export categorias -->
        <div class="export-card">
            <div class="d-flex align-center gap-1" style="margin-bottom:1rem">
                <div class="kpi-icon violet"><i class="fa-solid fa-tags"></i></div>
                <div>
                    <div class="fw-700">Categorias</div>
                    <div class="text-xs text-muted">Exportar todas as categorias como CSV</div>
                </div>
            </div>
            <p class="text-sm text-muted" style="margin-bottom:1rem">
                Exporta todas as categorias cadastradas (nome, tipo, cor, ícone, status).
                Útil para backup ou migração.
            </p>
            <button class="btn btn-primary w-full" onclick="exportarCategorias()" style="justify-content:center">
                <i class="fa-solid fa-download"></i> Exportar Categorias
            </button>
        </div>

        <!-- JSON completo -->
        <div class="export-card">
            <div class="d-flex align-center gap-1" style="margin-bottom:1rem">
                <div class="kpi-icon amber"><i class="fa-solid fa-code"></i></div>
                <div>
                    <div class="fw-700">JSON Completo</div>
                    <div class="text-xs text-muted">Dados brutos para backup ou análise</div>
                </div>
            </div>
            <p class="text-sm text-muted" style="margin-bottom:1rem">
                Exporta as transações do período selecionado em formato JSON.
                Ideal para importação em outras ferramentas.
            </p>
            <button class="btn btn-ghost w-full" onclick="exportarJSON()" style="justify-content:center">
                <i class="fa-solid fa-download"></i> Exportar JSON
            </button>
        </div>
    </div>

    <div id="exportStatus" style="margin-top:1.25rem;display:none">
        <div class="card">
            <div class="card-body d-flex align-center gap-1" style="padding:.875rem">
                <i class="fa-solid fa-circle-check text-emerald"></i>
                <span id="exportMsg" class="text-sm fw-600"></span>
            </div>
        </div>
    </div>
</div>
