<div id="cfg-sistema" class="cfg-pane">
    <div class="cfg-section">
        <div class="cfg-section-title">Estatísticas do banco de dados</div>
        <div class="card-body">
            <div id="statsGrid" class="stats-grid">
                <div class="empty-state" style="grid-column:1/-1">
                    <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
                </div>
            </div>
        </div>
    </div>

    <div class="cfg-section">
        <div class="cfg-section-title">Informações do sistema</div>
        <div class="cfg-field">
            <div class="cfg-field-label">Versão do Controle Financeiro</div>
            <span class="badge indigo"><?= APP_VERSION ?></span>
        </div>
        <div class="cfg-field">
            <div class="cfg-field-label">Prefixo das tabelas</div>
            <code style="font-family:monospace;font-size:.8rem;color:var(--cyan)"><?= TABLE_PREFIX ?></code>
        </div>
        <div class="cfg-field">
            <div class="cfg-field-label">PHP</div>
            <span class="text-muted text-sm"><?= PHP_VERSION ?></span>
        </div>
        <div class="cfg-field">
            <div class="cfg-field-label">Data/hora do servidor</div>
            <span class="text-muted text-sm"><?= date('d/m/Y H:i:s') ?></span>
        </div>
        <div class="cfg-field" id="statsSince">
            <div class="cfg-field-label">Primeiro lançamento registrado</div>
            <span class="text-muted text-sm" id="statsDesde">—</span>
        </div>
    </div>
</div>
