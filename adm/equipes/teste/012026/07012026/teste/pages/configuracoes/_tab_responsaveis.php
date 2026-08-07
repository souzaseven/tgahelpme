<?php
// ============================================================
// _tab_responsaveis.php — Aba "Responsáveis" de Configurações,
// incluindo o modal de novo/editar responsável.
// Partial de pages/configuracoes.php — usa $responsaveis do
// arquivo pai.
// ============================================================
?>
<div id="cfg-responsaveis" class="cfg-pane">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem">
        <div class="text-sm text-muted">
            Cadastre os membros da família ou responsáveis para vincular às transações.
        </div>
        <button class="btn btn-primary btn-sm" onclick="abrirModalResp()">
            <i class="fa-solid fa-plus"></i> Novo Responsável
        </button>
    </div>

    <?php if (empty($responsaveis)): ?>
    <div class="cfg-section">
        <div class="card-body empty-state lg">
            <i class="fa-solid fa-users fa-3x" style="margin-bottom:1rem;color:var(--indigo)"></i>
            <div class="fw-700" style="margin-bottom:.5rem">Nenhum responsável cadastrado</div>
            <div class="text-sm" style="margin-bottom:1.25rem">
                Cadastre os membros da família para vincular às despesas e receitas.
            </div>
            <button class="btn btn-primary btn-sm" onclick="abrirModalResp()">
                <i class="fa-solid fa-plus"></i> Cadastrar primeiro responsável
            </button>
        </div>
    </div>
    <?php else: ?>
    <div class="cfg-section">
        <?php foreach ($responsaveis as $r): ?>
        <div class="cat-row <?= !$r['ativo'] ? 'inativa' : '' ?>">
            <div class="cat-icon-preview"
                 style="background:<?= htmlspecialchars($r['cor']) ?>22;color:<?= htmlspecialchars($r['cor']) ?>">
                <i class="fa-solid fa-<?= htmlspecialchars($r['icone'] ?? 'user') ?>"></i>
            </div>
            <div style="flex:1">
                <div class="fw-600 text-sm"><?= htmlspecialchars($r['nome']) ?></div>
                <div class="text-xs text-muted"><?= $r['total'] ?> transação<?= $r['total'] != 1 ? 'ões' : '' ?> vinculada<?= $r['total'] != 1 ? 's' : '' ?></div>
            </div>
            <span style="width:12px;height:12px;border-radius:50%;background:<?= htmlspecialchars($r['cor']) ?>;display:inline-block;border:1px solid rgba(255,255,255,.15)"></span>
            <div class="d-flex gap-1">
                <button class="btn-icon" onclick="abrirEditarResp(<?= $r['id'] ?>)" title="Editar"
                        style="width:28px;height:28px;font-size:.72rem">
                    <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button class="btn-icon" onclick="toggleResp(<?= $r['id'] ?>)"
                        title="<?= $r['ativo'] ? 'Desativar' : 'Ativar' ?>"
                        style="width:28px;height:28px;font-size:.72rem;color:<?= $r['ativo'] ? 'var(--amber)' : 'var(--emerald)' ?>">
                    <i class="fa-solid fa-<?= $r['ativo'] ? 'eye-slash' : 'eye' ?>"></i>
                </button>
                <button class="btn-icon" onclick="excluirResp(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['nome'])) ?>')"
                        title="Excluir" style="width:28px;height:28px;font-size:.72rem;color:var(--rose)">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</div>

<!-- Modal: Novo / Editar Responsável -->
<div id="modalResp" class="modal-overlay" onclick="if(event.target===this)fecharModalResp()">
    <div class="modal-box" style="max-width:420px">
        <div class="modal-header">
            <div class="modal-title" id="respModalTitulo">Novo Responsável</div>
            <button type="button" class="btn-icon" onclick="fecharModalResp()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formResp" onsubmit="salvarResp(event)">
            <div class="modal-body">
                <input type="hidden" id="respId">
                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Nome <span style="color:var(--rose)">*</span></label>
                    <input type="text" id="respNome" class="form-control" required
                           placeholder="Ex: João, Maria, Filho...">
                </div>
                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Cor</label>
                        <input type="color" id="respCor" class="form-control" value="#6366f1"
                               style="height:40px;padding:.3rem;cursor:pointer"
                               oninput="atualizarPreviewResp()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ícone FontAwesome</label>
                        <div class="d-flex gap-1 align-center">
                            <div id="respIconePreview" style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:var(--radius-sm);background:#6366f122;color:#6366f1;font-size:1.1rem;flex-shrink:0">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <input type="text" id="respIcone" class="form-control" value="user"
                                   placeholder="user, person, child..."
                                   oninput="atualizarPreviewResp()">
                        </div>
                    </div>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:.35rem">
                    <?php foreach (['user','person','child','person-dress','baby','user-tie','user-graduate','users','user-nurse','user-secret'] as $ic): ?>
                    <button type="button"
                            onclick="document.getElementById('respIcone').value='<?= $ic ?>'; atualizarPreviewResp();"
                            style="width:32px;height:32px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--bg-700);cursor:pointer;font-size:.8rem;color:var(--text-200);display:flex;align-items:center;justify-content:center"
                            title="<?= $ic ?>">
                        <i class="fa-solid fa-<?= $ic ?>"></i>
                    </button>
                    <?php endforeach ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="fecharModalResp()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnSalvarResp">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>
