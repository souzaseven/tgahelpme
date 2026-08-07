<?php
// ============================================================
// _filtros.php — Barra de filtros da listagem de despesas.
// Partial de pages/despesas.php — usa as variáveis já preparadas
// pelo arquivo pai ($nomesMeses, $mesAtual, $anoAtual, $_desPais,
// $_desSubs, $respDes, $terceirosDes, $contasDes, $cartoesDes).
// ============================================================
?>
<!-- ── Filtros ────────────────────────────────────────────── -->
<button type="button" class="filters-toggle" onclick="toggleFiltrosMobile()">
    <span><i class="fa-solid fa-filter fa-xs"></i> Filtros</span>
    <i class="fa-solid fa-chevron-down fa-xs" id="filtrosToggleIcon"></i>
</button>
<div class="filters-bar" id="filtersBar">
    <div class="filter-group" style="flex-direction:column;align-items:flex-start;gap:.3rem">
        <div style="display:flex;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden">
            <button id="fBtnMes" onclick="setModoFiltro('mes')"
                    style="padding:.22rem .65rem;font-size:.72rem;font-weight:600;border:none;cursor:pointer;transition:var(--ease);background:var(--indigo);color:#fff">
                Mês/Ano
            </button>
            <button id="fBtnPer" onclick="setModoFiltro('periodo')"
                    style="padding:.22rem .65rem;font-size:.72rem;font-weight:600;border:none;cursor:pointer;transition:var(--ease);background:transparent;color:var(--text-500)">
                Período
            </button>
        </div>
        <div id="filtroMes" style="display:flex;align-items:center;gap:.3rem">
            <select id="filMes" class="form-control" style="min-width:118px" onchange="filtroCarregar()">
                <?php foreach ($nomesMeses as $n => $nome): ?>
                <option value="<?= $n ?>" <?= $n === $mesAtual ? 'selected' : '' ?>><?= $nome ?></option>
                <?php endforeach ?>
            </select>
            <select id="filAno" class="form-control" style="min-width:82px" onchange="filtroCarregar()">
                <?php for ($y = $anoAtual + 5; $y >= $anoAtual - 4; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $anoAtual ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor ?>
            </select>
        </div>
        <div id="filtroPeriodo" style="display:none;align-items:center;gap:.3rem">
            <input type="date" id="filDe" class="form-control" style="width:140px" onchange="filtroCarregar()">
            <span style="color:var(--text-500);font-size:.8rem">até</span>
            <input type="date" id="filAte" class="form-control" style="width:140px" onchange="filtroCarregar()">
        </div>
    </div>
    <div class="filter-group">
        <span class="filter-label">Categoria</span>
        <select id="filCat" class="form-control" style="min-width:148px" onchange="filtroCarregar()">
            <option value="">Todas</option>
            <option value="nenhuma">— Sem categoria —</option>
            <?php foreach ($_desPais as $p): $s = $_desSubs[$p['id']] ?? []; if ($s): ?>
            <optgroup label="<?= htmlspecialchars($p['nome']) ?>">
                <?php foreach ($s as $sub): ?>
                <option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['nome']) ?></option>
                <?php endforeach ?>
            </optgroup>
            <?php else: ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
            <?php endif; endforeach ?>
        </select>
    </div>
    <div class="filter-group">
        <span class="filter-label">Status</span>
        <select id="filStatus" class="form-control" style="min-width:118px" onchange="filtroCarregar()">
            <option value="">Todos</option>
            <option value="pago">Pago</option>
            <option value="pendente">Pendente</option>
            <option value="cancelado">Cancelado</option>
        </select>
    </div>
    <?php if (!empty($respDes) || !empty($terceirosDes)): ?>
    <div class="filter-group">
        <span class="filter-label">Pessoa</span>
        <div class="resp-dropdown" id="respDropdown">
            <button type="button" id="respToggleBtn" class="form-control resp-toggle"
                    onclick="_toggleRespDropdown(event)">
                <span id="respLabel">Todos</span>
                <i class="fa-solid fa-chevron-down fa-xs" style="margin-left:auto;opacity:.5"></i>
            </button>
            <div class="resp-panel" id="respPanel">
                <label class="resp-item">
                    <input type="checkbox" class="resp-cb" id="cbRespTodos" value="todos" checked
                           onchange="_onRespCbTodos(this)">
                    <i class="fa-solid fa-users fa-xs" style="color:var(--text-400)"></i>
                    <span>Todos (pessoal)</span>
                </label>
                <?php if (!empty($respDes)): ?>
                <hr class="resp-sep">
                <div style="padding:.3rem .875rem;font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-600)">Meus responsáveis</div>
                <label class="resp-item">
                    <input type="checkbox" class="resp-cb" value="-1" onchange="_onRespCb(this)">
                    <div class="resp-avatar-sm" style="background:#64748b22;color:#64748b">
                        <i class="fa-solid fa-user-slash fa-xs"></i>
                    </div>
                    <span>Sem responsável</span>
                </label>
                <?php foreach ($respDes as $r):
                    $cor = htmlspecialchars($r['cor']);
                ?>
                <label class="resp-item">
                    <input type="checkbox" class="resp-cb" value="<?= $r['id'] ?>"
                           onchange="_onRespCb(this)">
                    <div class="resp-avatar-sm" style="background:<?= $cor ?>22;color:<?= $cor ?>">
                        <i class="fa-solid fa-<?= htmlspecialchars($r['icone']) ?> fa-xs"></i>
                    </div>
                    <span><?= htmlspecialchars($r['nome']) ?></span>
                </label>
                <?php endforeach ?>
                <?php endif ?>
                <?php if (!empty($terceirosDes)): ?>
                <hr class="resp-sep">
                <div style="padding:.3rem .875rem;font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--amber)">Terceiros</div>
                <?php foreach ($terceirosDes as $r):
                    $cor = htmlspecialchars($r['cor']);
                ?>
                <label class="resp-item">
                    <input type="checkbox" class="resp-cb" value="tcr_<?= $r['id'] ?>"
                           onchange="_onRespCb(this)">
                    <div class="resp-avatar-sm" style="background:<?= $cor ?>22;color:<?= $cor ?>">
                        <i class="fa-solid fa-<?= htmlspecialchars($r['icone']) ?> fa-xs"></i>
                    </div>
                    <span><?= htmlspecialchars($r['nome']) ?></span>
                </label>
                <?php endforeach ?>
                <?php endif ?>
            </div>
        </div>
    </div>
    <?php endif ?>
    <div class="filter-group" style="flex:1;min-width:180px">
        <span class="filter-label">Buscar</span>
        <input type="search" id="filBusca" class="form-control"
               placeholder="Descrição da despesa..." oninput="debounceCarregar()">
    </div>
    <div class="filter-group" style="justify-content:flex-end">
        <span class="filter-label">&nbsp;</span>
        <button type="button" id="btnMaisFiltros" class="filters-more-btn" onclick="toggleFiltrosAvancados()">
            <i class="fa-solid fa-sliders fa-xs"></i> Mais filtros
            <span id="maisFiltrosBadge" class="filters-more-badge" style="display:none">0</span>
            <i class="fa-solid fa-chevron-down fa-xs"></i>
        </button>
    </div>

    <div class="filters-secondary" id="filtrosSecundarios">
        <div class="filter-group">
            <span class="filter-label">Conta</span>
            <select id="filConta" class="form-control" style="min-width:140px" onchange="filtroCarregar()">
                <option value="">Todas</option>
                <?php foreach ($contasDes as $ct): ?>
                <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['nome']) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="filter-group">
            <span class="filter-label">Cartão</span>
            <select id="filCartaoF" class="form-control" style="min-width:140px" onchange="filtroCarregar()">
                <option value="">Todos</option>
                <?php foreach ($cartoesDes as $cc): ?>
                <option value="<?= $cc['id'] ?>"><?= htmlspecialchars($cc['nome']) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="filter-group">
            <span class="filter-label">Valor Mín (R$)</span>
            <input type="number" id="filValMin" class="form-control" style="min-width:100px"
                   placeholder="0,00" min="0" step="0.01" oninput="debounceCarregar()">
        </div>
        <div class="filter-group">
            <span class="filter-label">Valor Máx (R$)</span>
            <input type="number" id="filValMax" class="form-control" style="min-width:100px"
                   placeholder="9.999,99" min="0" step="0.01" oninput="debounceCarregar()">
        </div>
        <div class="filter-group" style="min-width:140px">
            <span class="filter-label">Tag</span>
            <input type="text" id="filTag" class="form-control"
                   placeholder="Ex: viagem" oninput="debounceCarregar()">
        </div>
    </div>
</div>
