<?php
// ============================================================
// _tab_categorias.php — Aba "Categorias" de Configurações.
// Partial de pages/configuracoes.php — usa $categorias, $_catPais,
// $_catSubs do arquivo pai. Declara renderCatRow(), usada só aqui.
// ============================================================
?>
<div id="cfg-categorias" class="cfg-pane">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem">
        <div class="d-flex gap-1">
            <?php foreach (['todos'=>'Todas','ativo'=>'Ativas','inativo'=>'Inativas'] as $v=>$l): ?>
            <button class="btn btn-sm <?= $v==='todos'?'btn-primary':'btn-ghost' ?>"
                    id="filtCat-<?= $v ?>" onclick="filtrarCats('<?= $v ?>')">
                <?= $l ?>
            </button>
            <?php endforeach ?>
        </div>
        <div class="d-flex gap-1">
            <button class="btn btn-ghost btn-sm" onclick="inserirCatsPadrao()" title="Adiciona categorias comuns que ainda não existem">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Categorias Sugeridas
            </button>
            <button class="btn btn-primary btn-sm" onclick="abrirModalCat()">
                <i class="fa-solid fa-plus"></i> Nova Categoria
            </button>
        </div>
    </div>

    <?php
    function renderCatRow(array $cat, bool $isSub = false): void {
        $cor   = htmlspecialchars($cat['cor']   ?? '#6366f1');
        $icone = htmlspecialchars($cat['icone'] ?? 'tag');
        $nome  = htmlspecialchars($cat['nome']);
        $id    = (int)$cat['id'];
        $ativo = (bool)$cat['ativo'];
        $indent = $isSub ? 'padding-left:2rem;border-left:2px solid ' . $cor . '22;margin-left:.5rem;' : '';
        ?>
        <div class="cat-row cat-item <?= !$ativo ? 'inativa' : '' ?>"
             data-ativo="<?= $ativo ? 1 : 0 ?>"
             style="<?= $indent ?>">
            <div class="cat-icon-preview" style="background:<?= $cor ?>22;color:<?= $cor ?>;
                 <?= $isSub ? 'width:28px;height:28px;font-size:.7rem;' : '' ?>">
                <i class="fa-solid fa-<?= $icone ?>"></i>
            </div>
            <div style="flex:1">
                <div class="fw-600 text-sm"><?= $nome ?></div>
                <?php if ($isSub && $cat['pai_nome']): ?>
                <div class="text-xs text-muted">↳ <?= htmlspecialchars($cat['pai_nome']) ?></div>
                <?php endif ?>
            </div>
            <span style="width:10px;height:10px;border-radius:50%;background:<?= $cor ?>;display:inline-block;flex-shrink:0"></span>
            <div class="d-flex gap-1">
                <?php if (!$isSub): ?>
                <button class="btn-icon" title="Nova subcategoria"
                        onclick="abrirModalCat(null, <?= $id ?>)"
                        style="width:28px;height:28px;font-size:.65rem;color:var(--indigo)">
                    <i class="fa-solid fa-plus"></i>
                </button>
                <?php endif ?>
                <button class="btn-icon" onclick="abrirEditarCat(<?= $id ?>)" title="Editar"
                        style="width:28px;height:28px;font-size:.72rem">
                    <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button class="btn-icon" onclick="toggleCat(<?= $id ?>)"
                        title="<?= $ativo ? 'Desativar' : 'Ativar' ?>"
                        style="width:28px;height:28px;font-size:.72rem;color:<?= $ativo?'var(--amber)':'var(--emerald)' ?>">
                    <i class="fa-solid fa-<?= $ativo ? 'eye-slash' : 'eye' ?>"></i>
                </button>
                <button class="btn-icon" onclick="excluirCat(<?= $id ?>, '<?= htmlspecialchars(addslashes($cat['nome'])) ?>')"
                        title="Excluir" style="width:28px;height:28px;font-size:.72rem;color:var(--rose)">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>
        <?php
    }

    $grupos = ['receita' => 'Receitas', 'despesa' => 'Despesas', 'ambos' => 'Ambos'];
    foreach ($grupos as $tipo => $label):
        // Pais deste tipo
        $paisTipo = array_filter($_catPais, fn($c) => $c['tipo'] === $tipo && $c['ativo'] !== 0 || !$c['ativo']);
        // Conta total (pais + subs)
        $todosTipo = array_filter($categorias, fn($c) => $c['tipo'] === $tipo);
        if (empty($todosTipo)) continue;
    ?>
    <div class="cfg-section cat-grupo" data-tipo="<?= $tipo ?>">
        <div class="cfg-section-title">
            <span>
                <i class="fa-solid fa-<?= $tipo==='receita'?'arrow-up text-emerald':($tipo==='despesa'?'arrow-down text-rose':'arrows-up-down text-indigo') ?> fa-xs"></i>
                &nbsp;<?= $label ?>
            </span>
            <span class="text-muted text-xs"><?= count($todosTipo) ?> categoria<?= count($todosTipo)!==1?'s':'' ?></span>
        </div>
        <?php
        // Render pais deste tipo com filhos embaixo
        $paisTipo = array_filter($_catPais, fn($c) => $c['tipo'] === $tipo);
        foreach ($paisTipo as $pai):
            renderCatRow($pai, false);
            // Render subcategorias deste pai
            foreach ($_catSubs[$pai['id']] ?? [] as $sub):
                renderCatRow($sub, true);
            endforeach;
        endforeach;
        // Subcategorias órfãs deste tipo (pai de outro tipo)
        foreach ($todosTipo as $c):
            if ($c['categoria_pai'] && (!isset($_catPais) || !array_key_exists($c['categoria_pai'], array_column($_catPais,'id',null)))):
                if ($c['tipo'] === $tipo && !array_filter($paisTipo, fn($p) => $p['id'] == $c['categoria_pai'])):
                    renderCatRow($c, true);
                endif;
            endif;
        endforeach;
        ?>
    </div>
    <?php endforeach ?>
</div>
