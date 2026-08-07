<!-- ── Modal de Templates ──────────────────────────────────── -->
<div id="modalTemplates" class="modal-overlay" onclick="if(event.target===this)fecharTemplates()">
    <div class="modal-box" style="max-width:520px">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-layer-group fa-sm" style="color:var(--indigo)"></i> Templates de Despesa</div>
            <button type="button" class="btn-icon" onclick="fecharTemplates()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding:.75rem">
            <div id="templatesList" style="display:flex;flex-direction:column;gap:.5rem">
                <div class="empty-state">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                </div>
            </div>
        </div>
    </div>
</div>
