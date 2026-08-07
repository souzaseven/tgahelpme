<?php
// ============================================================
// _tab_atalhos.php — Aba "Atalhos de preenchimento" de
// Configurações, incluindo o modal de novo/editar atalho.
// Partial de pages/configuracoes.php — usa $_atalhoContas,
// $_atalhoCartoes, $_atalhoTerceiros, $responsaveis do arquivo pai.
// ============================================================
?>
<div id="cfg-atalhos" class="cfg-pane">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem">
        <div class="text-sm text-muted">
            Ao digitar uma dessas descrições numa receita/despesa nova, os demais campos são preenchidos sozinhos.
        </div>
        <button class="btn btn-primary btn-sm" onclick="abrirModalAtalho()">
            <i class="fa-solid fa-plus"></i> Novo Atalho
        </button>
    </div>

    <div id="atalhosLista">
        <div class="empty-state">
            <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
        </div>
    </div>
</div>

<!-- Modal: Novo / Editar Atalho -->
<div id="modalAtalho" class="modal-overlay" onclick="if(event.target===this)fecharModalAtalho()">
    <div class="modal-box" style="max-width:480px">
        <div class="modal-header">
            <div class="modal-title" id="atalhoModalTitulo">Novo Atalho</div>
            <button type="button" class="btn-icon" onclick="fecharModalAtalho()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formAtalho" onsubmit="salvarAtalho(event)">
            <div class="modal-body">
                <input type="hidden" id="atalhoId">

                <div class="form-group" style="margin-bottom:1.1rem" id="atalhoTipoWrap">
                    <label class="form-label">Tipo</label>
                    <div style="display:flex;gap:.5rem">
                        <?php foreach (['despesa'=>'Despesa','receita'=>'Receita'] as $v=>$l): ?>
                        <button type="button" class="btn btn-sm btn-ghost" id="tipoAtalhoBtn-<?= $v ?>"
                                onclick="setTipoAtalho('<?= $v ?>')">
                            <?= $l ?>
                        </button>
                        <?php endforeach ?>
                    </div>
                    <input type="hidden" id="atalhoTipo" value="despesa">
                </div>

                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Descrição-chave <span style="color:var(--rose)">*</span></label>
                    <input type="text" id="atalhoDescricao" class="form-control" required
                           placeholder="Ex: Oferta Igreja">
                    <div class="text-xs text-muted" style="margin-top:.3rem">
                        Precisa ser digitada exatamente assim no campo Descrição (não diferencia maiúsculas/acentos).
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Valor (R$)</label>
                    <input type="text" id="atalhoValor" class="form-control"
                           placeholder="0,00" inputmode="numeric" data-currency>
                </div>

                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Categoria</label>
                        <select id="atalhoCategoria" class="form-control">
                            <option value="">— Nenhuma —</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Conta</label>
                        <select id="atalhoConta" class="form-control">
                            <option value="">— Nenhuma —</option>
                            <?php foreach ($_atalhoContas as $ct): ?>
                            <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['nome']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Cartão</label>
                        <select id="atalhoCartao" class="form-control">
                            <option value="">— Nenhum —</option>
                            <?php foreach ($_atalhoCartoes as $cc): ?>
                            <option value="<?= $cc['id'] ?>"><?= htmlspecialchars($cc['nome']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pessoa / Responsável</label>
                        <select id="atalhoResponsavel" class="form-control">
                            <option value="">— Nenhum —</option>
                            <?php if (!empty($responsaveis)): ?>
                            <optgroup label="Responsáveis">
                                <?php foreach ($responsaveis as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nome']) ?></option>
                                <?php endforeach ?>
                            </optgroup>
                            <?php endif ?>
                            <?php if (!empty($_atalhoTerceiros)): ?>
                            <optgroup label="Terceiros">
                                <?php foreach ($_atalhoTerceiros as $r): ?>
                                <option value="tcr_<?= $r['id'] ?>"><?= htmlspecialchars($r['nome']) ?></option>
                                <?php endforeach ?>
                            </optgroup>
                            <?php endif ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Observação</label>
                    <textarea id="atalhoObservacao" class="form-control" rows="2"
                              placeholder="Preenchida junto (opcional)"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="fecharModalAtalho()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnSalvarAtalho">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>
