<div id="cfg-notificacoes" class="cfg-pane">
    <div class="cfg-section">
        <div class="cfg-section-title">Notificações do navegador</div>

        <div class="cfg-field">
            <div>
                <div class="cfg-field-label">Status atual</div>
                <div class="text-xs text-muted" id="notifStatus">Verificando...</div>
            </div>
            <span class="badge" id="notifStatusBadge">—</span>
        </div>

        <div style="background:var(--bg-700);border-radius:var(--radius);padding:1rem;margin:.75rem 0">
            <div class="fw-600 text-sm" style="margin-bottom:.4rem">
                <i class="fa-solid fa-bell" style="color:var(--indigo);margin-right:.4rem"></i>
                O que você vai receber:
            </div>
            <ul style="list-style:disc;padding-left:1.25rem;font-size:.825rem;color:var(--text-400);line-height:1.9">
                <li>Contas em atraso ao abrir o sistema</li>
                <li>Lembretes de vencimentos de hoje e amanhã</li>
                <li>Alertas configurados no módulo de alertas</li>
            </ul>
        </div>

        <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.5rem">
            <button class="btn btn-primary" onclick="solicitarNotificacoes()" id="btnAtivarNotif">
                <i class="fa-solid fa-bell"></i> Ativar notificações
            </button>
            <button class="btn btn-ghost" onclick="verificarVencimentos(true)">
                <i class="fa-solid fa-rotate"></i> Verificar agora
            </button>
        </div>
    </div>
</div>
