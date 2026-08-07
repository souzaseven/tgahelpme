<?php
// ============================================================
// _tab_dados.php — Aba "Dados" de Configurações (exclusão de
// registros / zona de risco), seguida do modal de Nova/Editar
// Categoria — que no arquivo original já vinha fisicamente
// posicionado aqui (após a aba Dados), não junto da aba
// Categorias. Mantido nessa ordem para não alterar o DOM.
// Partial de pages/configuracoes.php — usa $_catPais do pai.
// ============================================================
?>
<div id="cfg-dados" class="cfg-pane">
    <div class="cfg-section">
        <div class="cfg-section-title">Excluir registros por tipo</div>

        <div style="display:flex;flex-direction:column;gap:.625rem;margin-top:.5rem" id="dadosLista">
            <div class="empty-state sm">
                <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
            </div>
        </div>
    </div>

    <div class="cfg-section" style="border:1px solid rgba(244,63,94,.35);border-radius:var(--radius-lg);padding:1.25rem">
        <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem">
            <i class="fa-solid fa-triangle-exclamation" style="color:var(--rose)"></i>
            <div class="cfg-section-title" style="color:var(--rose);margin:0">Zona de risco</div>
        </div>
        <div class="text-sm text-muted" style="margin-bottom:1rem">
            Apaga <strong>absolutamente tudo</strong>: despesas, receitas, empréstimos, investimentos, metas,
            orçamentos e alertas. Os saldos das contas são resetados ao valor inicial.
            Esta ação <strong>não pode ser desfeita</strong>.
        </div>
        <button class="btn" style="background:var(--rose);color:#fff;width:100%"
                onclick="limparTudo()">
            <i class="fa-solid fa-bomb"></i> Excluir TODOS os dados do sistema
        </button>
    </div>
</div>

<!-- ── Modal: Nova / Editar Categoria ────────────────────── -->
<div id="modalCat" class="modal-overlay" onclick="if(event.target===this)fecharModal()">
    <div class="modal-box" style="max-width:480px">
        <div class="modal-header">
            <div class="modal-title" id="catModalTitulo">Nova Categoria</div>
            <button type="button" class="btn-icon" onclick="fecharModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formCat" onsubmit="salvarCat(event)">
            <div class="modal-body">
                <input type="hidden" id="catId">

                <!-- Subcategoria de -->
                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Subcategoria de</label>
                    <select id="catPai" class="form-control" onchange="onChangeCatPai()">
                        <option value="">— Categoria principal (nível 1) —</option>
                        <?php foreach (array_filter($_catPais, fn($c) => $c['ativo']) as $pai): ?>
                        <option value="<?= $pai['id'] ?>"
                                data-tipo="<?= $pai['tipo'] ?>"
                                data-cor="<?= htmlspecialchars($pai['cor'] ?? '#6366f1') ?>">
                            <?= htmlspecialchars($pai['nome']) ?>
                        </option>
                        <?php endforeach ?>
                    </select>
                    <div class="text-xs text-muted" style="margin-top:.3rem">
                        Deixe vazio para criar uma categoria de nível 1
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Nome <span style="color:var(--rose)">*</span></label>
                    <input type="text" id="catNome" class="form-control" required
                           placeholder="Ex: Água, Energia, Aluguel...">
                </div>

                <div class="form-group" style="margin-bottom:1.1rem" id="catTipoWrap">
                    <label class="form-label">Tipo</label>
                    <div style="display:flex;gap:.5rem">
                        <?php foreach (['despesa'=>'Despesa','receita'=>'Receita','ambos'=>'Ambos'] as $v=>$l): ?>
                        <button type="button" class="btn btn-sm btn-ghost" id="tipoCatBtn-<?= $v ?>"
                                onclick="setTipoCat('<?= $v ?>')">
                            <?= $l ?>
                        </button>
                        <?php endforeach ?>
                    </div>
                    <input type="hidden" id="catTipo" value="despesa">
                </div>

                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Cor</label>
                        <input type="color" id="catCor" class="form-control" value="#6366f1"
                               style="height:40px;padding:.3rem;cursor:pointer"
                               oninput="atualizarPreview()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ícone FontAwesome</label>
                        <div class="d-flex gap-1 align-center">
                            <div id="iconePreview" style="background:#6366f122;color:#6366f1">
                                <i class="fa-solid fa-tag"></i>
                            </div>
                            <input type="text" id="catIcone" class="form-control" value="tag"
                                   placeholder="tag, home, car..."
                                   oninput="atualizarPreview()">
                        </div>
                    </div>
                </div>

                <!-- Sugestões rápidas de ícones -->
                <div style="margin-bottom:.75rem">
                    <div class="text-xs text-muted" style="margin-bottom:.4rem">Ícones rápidos</div>
                    <div style="display:flex;flex-wrap:wrap;gap:.35rem">
                        <?php foreach (['tag','home','car','utensils','heart-pulse','graduation-cap','plane','shirt','gamepad','repeat','landmark','piggy-bank','coins','wallet','chart-line','shield-halved','bolt','phone','wifi','baby'] as $ic): ?>
                        <button type="button"
                                onclick="document.getElementById('catIcone').value='<?= $ic ?>'; atualizarPreview();"
                                style="width:32px;height:32px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--bg-700);cursor:pointer;font-size:.8rem;color:var(--text-200);display:flex;align-items:center;justify-content:center"
                                title="<?= $ic ?>">
                            <i class="fa-solid fa-<?= $ic ?>"></i>
                        </button>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="fecharModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnSalvarCat">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>
