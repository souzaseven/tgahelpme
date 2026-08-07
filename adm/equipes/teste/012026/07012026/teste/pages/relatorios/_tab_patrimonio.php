<div id="tab-patrimonio" class="tab-pane">

    <!-- KPIs patrimônio -->
    <div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.25rem" id="patKPIs">
        <div class="loading-overlay" style="grid-column:1/-1"><i class="fa-solid fa-spinner fa-spin"></i></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem">

        <!-- Contas bancárias -->
        <div class="card">
            <div class="pat-group-title">Contas Bancárias</div>
            <div id="patContas"><div class="loading-overlay"><i class="fa-solid fa-spinner fa-spin"></i></div></div>
        </div>

        <!-- Investimentos -->
        <div class="card">
            <div class="pat-group-title">Investimentos</div>
            <div id="patInvest"><div class="loading-overlay"><i class="fa-solid fa-spinner fa-spin"></i></div></div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">

        <!-- Comparativo por titular -->
        <div class="card">
            <div class="card-header"><div class="card-title">Por Titular</div></div>
            <div id="patTitular"><div class="loading-overlay"><i class="fa-solid fa-spinner fa-spin"></i></div></div>
        </div>

        <!-- Empréstimos + Metas -->
        <div class="card">
            <div class="pat-group-title">Compromissos & Metas</div>
            <div id="patCompromissos"><div class="loading-overlay"><i class="fa-solid fa-spinner fa-spin"></i></div></div>
        </div>
    </div>
</div>
