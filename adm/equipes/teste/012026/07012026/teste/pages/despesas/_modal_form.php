<?php
// ============================================================
// _modal_form.php — Modal de Nova / Editar Despesa.
// Partial de pages/despesas.php — usa $respDes, $terceirosDes,
// $contasDes, $cartoesDes e a função optGroupDes() do arquivo pai.
// ============================================================
?>
<!-- ── Modal Nova / Editar Despesa ───────────────────────── -->
<div id="modalOverlay" class="modal-overlay" onclick="if(event.target===this)fecharModal()">
    <div class="modal-box" style="max-width:860px">
        <div class="modal-header">
            <div class="modal-title" id="modalTitulo">Nova Despesa</div>
            <div class="d-flex gap-1 align-center">
                <button type="button" class="btn btn-ghost btn-sm" onclick="abrirTemplates()" title="Usar template">
                    <i class="fa-solid fa-layer-group fa-xs"></i> Templates
                </button>
                <button type="button" class="btn-icon" onclick="fecharModal()" title="Fechar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <form id="formDespesa" onsubmit="salvarDespesa(event)">
            <div class="modal-body">
                <input type="hidden" id="editId" value="">

                <!-- Descrição -->
                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label" for="fDesc">
                        Descrição <span style="color:var(--rose)">*</span>
                    </label>
                    <input type="text" id="fDesc" class="form-control"
                           placeholder="Ex: Supermercado, Aluguel, Internet..." required maxlength="200">
                </div>

                <!-- Valor | Data da Compra | Vencimento | Status -->
                <div class="form-grid form-grid-4" style="margin-bottom:.4rem">
                    <div class="form-group">
                        <label class="form-label" for="fValor">
                            Valor (R$) <span style="color:var(--rose)">*</span>
                        </label>
                        <input type="text" id="fValor" class="form-control"
                               placeholder="0,00" inputmode="numeric" data-currency required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="fData">
                            Data da Compra <span style="color:var(--rose)">*</span>
                        </label>
                        <input type="date" id="fData" class="form-control" required onchange="atualizarInfoFatura()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="fDataVenc">
                            Vencimento
                            <span id="fDataVencBadge" style="display:none;font-size:.65rem;font-weight:400;color:var(--indigo)"
                                  title="Calculado pela fatura do cartão">
                                <i class="fa-solid fa-credit-card fa-xs"></i>
                            </span>
                        </label>
                        <input type="date" id="fDataVenc" class="form-control" onchange="_onDataVencChange()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="fStatus">Status</label>
                        <select id="fStatus" class="form-control">
                            <option value="pago">Pago</option>
                            <option value="pendente">Pendente</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                </div>
                <div class="text-xs text-muted" id="fDataVencHint" style="margin-bottom:1.1rem">
                    <i class="fa-solid fa-circle-info fa-xs"></i>
                    Vencimento é opcional para despesas comuns — no cartão é calculado automaticamente pela fatura.
                </div>

                <!-- Categoria | Conta | Cartão -->
                <div class="form-grid form-grid-3" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label" for="fCat">Categoria</label>
                        <select id="fCat" class="form-control">
                            <?= optGroupDes($_desPais, $_desSubs) ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="fConta">Conta</label>
                        <select id="fConta" class="form-control">
                            <option value="">— Sem conta —</option>
                            <?php foreach ($contasDes as $ct): ?>
                            <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['nome']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="fCartao">Cartão</label>
                        <select id="fCartao" class="form-control" onchange="_faturaManual=null;atualizarInfoFatura()">
                            <option value="">— Sem cartão —</option>
                            <?php foreach ($cartoesDes as $cc): ?>
                            <option value="<?= $cc['id'] ?>"
                                    data-fechamento="<?= (int)$cc['dia_fechamento'] ?>"
                                    data-vencimento="<?= (int)$cc['dia_vencimento'] ?>">
                                <?= htmlspecialchars($cc['nome']) ?>
                            </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>

                <!-- Campos ocultos da fatura + banner -->
                <input type="hidden" id="fMesFatura" value="">
                <input type="hidden" id="fAnoFatura"  value="">

                <div id="faturaInfoBanner" style="display:none;background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.25);border-radius:var(--radius);padding:.55rem .875rem;margin-bottom:1.1rem;font-size:.82rem">
                    <div style="display:flex;align-items:center;gap:.45rem;flex-wrap:wrap">
                        <i class="fa-solid fa-calendar-check fa-sm" style="color:var(--indigo)"></i>
                        <span>Esta compra entrará na</span>
                        <strong id="faturaInfoLabel" style="color:var(--indigo)">—</strong>
                        <span class="text-muted text-xs" id="faturaInfoVenc"></span>
                        <button type="button" id="btnFaturaAuto"
                                onclick="_faturaManual=null;atualizarInfoFatura()"
                                class="btn btn-ghost btn-sm"
                                style="display:none;padding:.1rem .45rem;font-size:.7rem">
                            <i class="fa-solid fa-rotate-left fa-xs"></i> Automático
                        </button>
                        <button type="button" onclick="toggleFaturaOpcoes()"
                                class="btn btn-ghost btn-sm"
                                style="padding:.1rem .45rem;font-size:.7rem;margin-left:auto"
                                title="Alterar mês da fatura">
                            <i class="fa-solid fa-pencil fa-xs"></i> Mudar
                        </button>
                    </div>
                    <div id="faturaOpcoes" style="display:none;margin-top:.5rem;flex-wrap:wrap;gap:.35rem"></div>
                </div>

                <!-- Responsável | Parcelas -->
                <div class="form-grid <?= (!empty($respDes) || !empty($terceirosDes)) ? 'form-grid-2' : '' ?>" style="margin-bottom:.5rem">
                    <?php if (!empty($respDes) || !empty($terceirosDes)): ?>
                    <div class="form-group" id="respWrap">
                        <label class="form-label" for="fResp">Pessoa / Responsável</label>
                        <select id="fResp" class="form-control">
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
                    </div>
                    <?php endif ?>

                    <div class="form-group" id="parcelasWrap">
                        <label class="form-label" for="fParcelas">Parcelar em</label>
                        <div class="d-flex align-center gap-1">
                            <input type="number" id="fParcelas" class="form-control"
                                   value="1" min="1" max="48" style="max-width:90px">
                            <span class="text-sm text-muted">parcelas · 1 = sem parcelamento</span>
                        </div>
                    </div>
                </div>

                <?php if (!empty($respDes) || !empty($terceirosDes)): ?>
                <!-- Divisão entre responsáveis -->
                <div style="margin-bottom:1.1rem">
                    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.82rem;font-weight:600;color:var(--text-400)">
                        <input type="checkbox" id="fDivisaoToggle" onchange="toggleDivisao()"
                               style="width:14px;height:14px;accent-color:var(--indigo)">
                        Dividir entre responsáveis
                    </label>
                    <div id="divisaoWrap" style="display:none;margin-top:.75rem;background:var(--bg-700);
                         border:1px solid var(--border);border-radius:var(--radius);padding:.875rem">
                        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
                                    color:var(--text-600);margin-bottom:.6rem">
                            Selecione quem participará (valor será dividido igualmente):
                        </div>
                        <div id="divisaoCheckboxes" style="display:flex;flex-direction:column;gap:.4rem">
                            <label style="display:flex;align-items:center;gap:.5rem;font-size:.84rem;cursor:pointer">
                                <input type="checkbox" value="0" class="divisao-cb"
                                       style="width:14px;height:14px;accent-color:var(--indigo)">
                                <span style="color:var(--text-400)">Eu (sem responsável)</span>
                            </label>
                            <?php foreach ($respDes as $r): ?>
                            <label style="display:flex;align-items:center;gap:.5rem;font-size:.84rem;cursor:pointer">
                                <input type="checkbox" value="<?= $r['id'] ?>" class="divisao-cb"
                                       style="width:14px;height:14px;accent-color:var(--indigo)">
                                <span style="display:inline-flex;align-items:center;gap:.35rem">
                                    <span style="width:8px;height:8px;border-radius:50%;background:<?= htmlspecialchars($r['cor']) ?>;flex-shrink:0;display:inline-block"></span>
                                    <?= htmlspecialchars($r['nome']) ?>
                                </span>
                            </label>
                            <?php endforeach ?>
                        </div>
                        <div id="divisaoInfo" class="text-xs text-muted" style="margin-top:.6rem"></div>
                    </div>
                </div>
                <?php endif ?>

                <!-- Observação | Tags -->
                <div class="form-grid form-grid-2" style="margin-bottom:.75rem">
                    <div class="form-group">
                        <label class="form-label" for="fObs">Observação</label>
                        <textarea id="fObs" class="form-control" rows="2"
                                  placeholder="Notas adicionais (opcional)"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="fTags">Tags</label>
                        <input type="text" id="fTags" class="form-control"
                               placeholder="viagem, trabalho, família..."
                               maxlength="255">
                        <div class="text-xs text-muted" style="margin-top:.25rem">
                            <i class="fa-solid fa-circle-info fa-xs"></i> Separadas por vírgula
                        </div>
                    </div>
                </div>

                <!-- Comprovante (foto) -->
                <div class="form-group" style="margin-top:.75rem">
                    <label class="form-label">Comprovante</label>
                    <input type="hidden" id="fComprovantePath" value="">
                    <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
                        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('fComprovanteCamera').click()">
                            <i class="fa-solid fa-camera"></i> Câmera
                        </button>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('fComprovanteGaleria').click()">
                            <i class="fa-solid fa-image"></i> Galeria
                        </button>
                        <input type="file" id="fComprovanteCamera" accept="image/*" capture="environment"
                               style="display:none" onchange="onSelecionarComprovante(this)">
                        <input type="file" id="fComprovanteGaleria" accept="image/*"
                               style="display:none" onchange="onSelecionarComprovante(this)">
                        <div id="fComprovantePreview" style="display:none;align-items:center;gap:.5rem"></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="fecharModal()">Cancelar</button>
                <button type="button" class="btn btn-ghost btn-sm" onclick="salvarComoTemplate()" id="btnTemplate"
                        title="Salvar campos atuais como template para reutilização">
                    <i class="fa-solid fa-bookmark fa-xs"></i> Salvar Template
                </button>
                <button type="submit" class="btn btn-primary" id="btnSalvar">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>
