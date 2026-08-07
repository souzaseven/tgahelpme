<?php
// ============================================================
// _modal_bulk_edit.php — Barra de ação em lote + modal de edição
// em lote da listagem de despesas. Partial de pages/despesas.php
// — usa $_desPais, $_desSubs, $contasDes, $cartoesDes, $respDes,
// $terceirosDes e a função optGroupDes() do arquivo pai.
// ============================================================
?>
<!-- ── Bulk action bar ───────────────────────────────────── -->
<div id="bulkBarDes" class="bulk-bar" style="display:none">
    <i class="fa-solid fa-square-check" style="color:var(--indigo);font-size:1rem"></i>
    <span id="bulkCountDes" class="bulk-bar-count">0 selecionados</span>
    <div style="flex:1"></div>
    <button class="bulk-bar-btn" onclick="abrirModalBulkEditDes()">
        <i class="fa-solid fa-pen-to-square"></i> Editar selecionados
    </button>
    <button class="bulk-bar-btn danger" onclick="excluirSelecionados()">
        <i class="fa-solid fa-trash"></i> Excluir selecionados
    </button>
    <button class="bulk-bar-btn ghost" onclick="limparSelecao()">
        <i class="fa-solid fa-xmark"></i> Cancelar
    </button>
</div>

<!-- ── Modal editar campos em lote ──────────────────────── -->
<div id="modalBulkEditDes" class="bulk-modal-overlay" style="display:none"
     onclick="if(event.target===this)fecharModalBulkEditDes()">
    <div class="bulk-modal-box bulk-edit-box">
        <div class="bulk-edit-header">
            <div class="bulk-edit-title">
                <i class="fa-solid fa-pen-to-square" style="color:var(--indigo);margin-right:.4rem"></i> Editar em lote
            </div>
            <div class="bulk-edit-hint">
                Marque os campos que deseja alterar. Só os campos marcados são aplicados aos lançamentos selecionados.
            </div>
        </div>

        <div class="bulk-edit-body">
            <label class="bulk-field-row">
                <span class="bulk-field-check">
                    <input type="checkbox" id="bkChkStatusDes" class="bulk-toggle" data-target="bkStatusDes" onchange="_bulkToggle(this)">
                    Status
                </span>
                <select id="bkStatusDes" class="form-control" disabled>
                    <option value="pago">Pago</option>
                    <option value="pendente">Pendente</option>
                    <option value="cancelado">Cancelado</option>
                </select>
            </label>

            <label class="bulk-field-row">
                <span class="bulk-field-check">
                    <input type="checkbox" id="bkChkCatDes" class="bulk-toggle" data-target="bkCatDes" onchange="_bulkToggle(this)">
                    Categoria
                </span>
                <select id="bkCatDes" class="form-control" disabled>
                    <option value="">— Sem categoria —</option>
                    <?= optGroupDes($_desPais, $_desSubs) ?>
                </select>
            </label>

            <label class="bulk-field-row">
                <span class="bulk-field-check">
                    <input type="checkbox" id="bkChkContaDes" class="bulk-toggle" data-target="bkContaDes" onchange="_bulkToggle(this)">
                    Conta
                </span>
                <select id="bkContaDes" class="form-control" disabled>
                    <option value="">— Sem conta —</option>
                    <?php foreach ($contasDes as $ct): ?>
                    <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['nome']) ?></option>
                    <?php endforeach ?>
                </select>
            </label>

            <label class="bulk-field-row">
                <span class="bulk-field-check">
                    <input type="checkbox" id="bkChkCartaoDes" class="bulk-toggle" data-target="bkCartaoDes" onchange="_bulkToggle(this)">
                    Cartão
                </span>
                <select id="bkCartaoDes" class="form-control" disabled>
                    <option value="">— Sem cartão —</option>
                    <?php foreach ($cartoesDes as $cc): ?>
                    <option value="<?= $cc['id'] ?>"><?= htmlspecialchars($cc['nome']) ?></option>
                    <?php endforeach ?>
                </select>
            </label>

            <?php if (!empty($respDes) || !empty($terceirosDes)): ?>
            <label class="bulk-field-row">
                <span class="bulk-field-check">
                    <input type="checkbox" id="bkChkRespDes" class="bulk-toggle" data-target="bkRespDes" onchange="_bulkToggle(this)">
                    Pessoa / Responsável
                </span>
                <select id="bkRespDes" class="form-control" disabled>
                    <option value="">— Sem responsável —</option>
                    <?php if (!empty($respDes)): ?>
                    <optgroup label="Responsáveis">
                        <?php foreach ($respDes as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nome']) ?></option>
                        <?php endforeach ?>
                    </optgroup>
                    <?php endif ?>
                    <?php if (!empty($terceirosDes)): ?>
                    <optgroup label="Terceiros">
                        <?php foreach ($terceirosDes as $r): ?>
                        <option value="tcr_<?= $r['id'] ?>"><?= htmlspecialchars($r['nome']) ?></option>
                        <?php endforeach ?>
                    </optgroup>
                    <?php endif ?>
                </select>
            </label>
            <?php endif ?>

            <label class="bulk-field-row">
                <span class="bulk-field-check">
                    <input type="checkbox" id="bkChkVencDes" class="bulk-toggle" data-target="bkDataVencDes" onchange="_bulkToggle(this)">
                    Vencimento
                </span>
                <input type="date" id="bkDataVencDes" class="form-control" disabled>
            </label>

            <label class="bulk-field-row bulk-field-full">
                <span class="bulk-field-check">
                    <input type="checkbox" id="bkChkTagsDes" class="bulk-toggle" data-target="bkTagsDes,bkTagsModoDes" onchange="_bulkToggle(this)">
                    Tags
                </span>
                <div class="bulk-subrow">
                    <select id="bkTagsModoDes" class="form-control" disabled>
                        <option value="adicionar">Adicionar</option>
                        <option value="substituir">Substituir</option>
                    </select>
                    <input type="text" id="bkTagsDes" class="form-control" placeholder="viagem, trabalho..." disabled>
                </div>
            </label>

            <label class="bulk-field-row bulk-field-full" style="margin-bottom:0">
                <span class="bulk-field-check">
                    <input type="checkbox" id="bkChkObsDes" class="bulk-toggle" data-target="bkObsDes" onchange="_bulkToggle(this)">
                    Observação
                </span>
                <textarea id="bkObsDes" class="form-control" rows="2" placeholder="Substitui a observação atual" disabled></textarea>
            </label>
        </div>

        <div class="bulk-edit-footer">
            <button class="btn btn-outline btn-sm" onclick="fecharModalBulkEditDes()">Cancelar</button>
            <button class="btn btn-primary btn-sm" onclick="confirmarEditarBulkDes()">Aplicar</button>
        </div>
    </div>
</div>
