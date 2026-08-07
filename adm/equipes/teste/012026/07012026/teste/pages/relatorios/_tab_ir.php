<?php
// ============================================================
// _tab_ir.php — Aba "Imposto de Renda" de Relatórios & BI.
// Partial de pages/relatorios.php — usa $ano do arquivo pai.
// Mantém o <style> próprio da aba (impressão + cards .ir-*), que
// já era local a esta seção mesmo antes da divisão em partials.
// ============================================================
?>
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
        <div class="empty-state lg">
            <i class="fa-solid fa-spinner fa-spin fa-2x" style="margin-bottom:.875rem;display:block"></i>
            Carregando relatório...
        </div>
    </div>

</div>
