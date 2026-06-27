<?php
// ── Dados iniciais ────────────────────────────────────────────
$p = TABLE_PREFIX;

$configs = $pdo->query("SELECT chave, valor FROM `{$p}configuracoes`")->fetchAll();
$cfg = [];
foreach ($configs as $c) $cfg[$c['chave']] = $c['valor'];

// Remove duplicatas silenciosamente (mantém menor id de cada nome+tipo)
try {
    $pdo->exec(
        "DELETE c FROM `{$p}categorias` c
         INNER JOIN (
             SELECT MIN(id) min_id, nome, tipo FROM `{$p}categorias` GROUP BY nome, tipo HAVING COUNT(*) > 1
         ) d ON c.nome = d.nome AND c.tipo = d.tipo AND c.id <> d.min_id"
    );
} catch (Throwable $e) { /* ignora se não houver duplicatas */ }

$categorias = $pdo->query(
    "SELECT c.*, cp.nome pai_nome, cp.cor pai_cor, cp.icone pai_icone
     FROM `{$p}categorias` c
     LEFT JOIN `{$p}categorias` cp ON cp.id = c.categoria_pai
     ORDER BY c.tipo,
              COALESCE(cp.nome, c.nome),
              c.categoria_pai IS NOT NULL,
              c.nome"
)->fetchAll();

// Monta árvore: pais com filhos agrupados
$_catPais = array_filter($categorias, fn($c) => !$c['categoria_pai']);
$_catSubs = [];
foreach ($categorias as $c) {
    if ($c['categoria_pai']) $_catSubs[$c['categoria_pai']][] = $c;
}

try {
    $responsaveis = $pdo->query(
        "SELECT r.*, COUNT(t.id) total
         FROM `{$p}responsaveis` r
         LEFT JOIN `{$p}transacoes` t ON t.responsavel_id = r.id
         GROUP BY r.id ORDER BY r.ativo DESC, r.nome"
    )->fetchAll();
} catch (PDOException $e) { $responsaveis = []; }

$anoAtual = (int) date('Y');
$mesAtual = (int) date('m');
$nomesMeses = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho',
               'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
?>

<style>
/* ── Tabs ────────────────────────────────────────────────── */
.cfg-tabs { display:flex; gap:0; border-bottom:2px solid var(--border); margin-bottom:1.5rem; overflow-x:auto; scrollbar-width:none; }
.cfg-tab  { padding:.6rem 1.1rem; font-size:.85rem; font-weight:500; color:var(--text-400); background:none; border:none; border-bottom:2px solid transparent; margin-bottom:-2px; cursor:pointer; white-space:nowrap; transition:color .15s,border-color .15s; }
.cfg-tab:hover { color:var(--text-200); }
.cfg-tab.active { color:var(--indigo); border-bottom-color:var(--indigo); }
.cfg-pane { display:none; }
.cfg-pane.active { display:block; animation:cfgIn .18s ease; }
@keyframes cfgIn { from{opacity:0;transform:translateY(4px)} to{opacity:1;transform:none} }
/* ── Section card ────────────────────────────────────────── */
.cfg-section {
    background: var(--bg-800);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    margin-bottom: 1.25rem;
    overflow: hidden;
}
.cfg-section-title {
    padding: .875rem 1.25rem;
    font-size: .8rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .07em;
    color: var(--text-400);
    border-bottom: 1px solid var(--border);
    background: var(--bg-700);
    display: flex; align-items: center; justify-content: space-between;
}
.cfg-field {
    display: flex; align-items: center; justify-content: space-between;
    padding: .875rem 1.25rem;
    border-bottom: 1px solid var(--border);
    gap: 1rem;
    flex-wrap: wrap;
}
.cfg-field:last-child { border-bottom: none; }
.cfg-field-label { font-size: .875rem; font-weight: 500; flex: 1; min-width: 160px; }
.cfg-field-desc  { font-size: .75rem; color: var(--text-400); margin-top: .1rem; }
.cfg-field-input { flex: 0 0 auto; min-width: 220px; }
/* ── Linha de categoria ──────────────────────────────────── */
.cat-row {
    display: flex; align-items: center; gap: .875rem;
    padding: .75rem 1.25rem;
    border-bottom: 1px solid var(--border);
    transition: var(--ease);
}
.cat-row:last-child { border-bottom: none; }
.cat-row:hover { background: var(--bg-700); }
.cat-row.inativa { opacity: .45; }
.cat-icon-preview {
    width: 32px; height: 32px; border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: .875rem; flex-shrink: 0;
}
/* ── Modal ───────────────────────────────────────────────── */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.65); z-index:1000; align-items:center; justify-content:center; padding:1rem; }
.modal-overlay.open { display:flex; }
.modal-box { background:var(--bg-800); border:1px solid var(--border); border-radius:var(--radius-lg); width:100%; max-width:480px; max-height:90vh; display:flex; flex-direction:column; box-shadow:var(--shadow-lg); animation:mIn .18s ease; }
@keyframes mIn { from{opacity:0;transform:translateY(-10px) scale(.97)} to{opacity:1;transform:none} }
.modal-header { padding:1.25rem 1.5rem 1rem; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
.modal-title  { font-size:1rem; font-weight:700; }
.modal-body   { padding:1.5rem; overflow-y:auto; flex:1; }
.modal-footer { padding:1rem 1.5rem; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:.75rem; flex-shrink:0; }
/* ── Export card ─────────────────────────────────────────── */
.export-card {
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    background: var(--bg-700);
    transition: var(--ease);
}
.export-card:hover { border-color: var(--indigo); }
/* ── Stats grid ──────────────────────────────────────────── */
.stats-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:.875rem; }
.stat-chip {
    background: var(--bg-700);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: .875rem 1rem;
    text-align: center;
}
.stat-chip-val  { font-size: 1.35rem; font-weight: 700; color: var(--indigo); }
.stat-chip-lbl  { font-size: .72rem; color: var(--text-400); margin-top: .2rem; text-transform: uppercase; letter-spacing: .05em; }
/* ── Preview de ícone ────────────────────────────────────── */
#iconePreview { font-size: 1.2rem; width:36px; height:36px; display:flex; align-items:center; justify-content:center; border-radius:var(--radius-sm); flex-shrink:0; }
</style>

<!-- ── Cabeçalho ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <div class="page-title">Configurações</div>
        <div class="page-sub">Preferências, categorias e ferramentas do sistema</div>
    </div>
</div>

<!-- ── Tabs ───────────────────────────────────────────────── -->
<div class="cfg-tabs">
    <button class="cfg-tab active" onclick="cfgTab('geral')">
        <i class="fa-solid fa-sliders fa-xs"></i> Geral
    </button>
    <button class="cfg-tab" onclick="cfgTab('categorias')">
        <i class="fa-solid fa-tags fa-xs"></i> Categorias
    </button>
    <button class="cfg-tab" onclick="cfgTab('responsaveis')">
        <i class="fa-solid fa-users fa-xs"></i> Responsáveis
    </button>
    <button class="cfg-tab" onclick="cfgTab('exportar')">
        <i class="fa-solid fa-download fa-xs"></i> Exportar
    </button>
    <button class="cfg-tab" onclick="cfgTab('sistema')">
        <i class="fa-solid fa-circle-info fa-xs"></i> Sistema
    </button>
    <button class="cfg-tab" onclick="cfgTab('notificacoes')">
        <i class="fa-solid fa-bell fa-xs"></i> Notificações
    </button>
    <button class="cfg-tab" onclick="cfgTab('dados')">
        <i class="fa-solid fa-trash-can fa-xs"></i> Dados
    </button>
</div>

<!-- ═══════════════════════════════════════════════════════════
     ABA 1 — GERAL
═══════════════════════════════════════════════════════════ -->
<div id="cfg-geral" class="cfg-pane active">

    <div class="cfg-section">
        <div class="cfg-section-title">Identificação</div>

        <div class="cfg-field">
            <div>
                <div class="cfg-field-label">Nome do usuário</div>
                <div class="cfg-field-desc">Exibido no cabeçalho e relatórios</div>
            </div>
            <div class="cfg-field-input">
                <input type="text" id="cfgNome" class="form-control"
                       value="<?= htmlspecialchars($cfg['usuario_nome'] ?? '') ?>"
                       placeholder="Seu nome">
            </div>
        </div>

        <div class="cfg-field">
            <div>
                <div class="cfg-field-label">E-mail</div>
                <div class="cfg-field-desc">Para notificações futuras</div>
            </div>
            <div class="cfg-field-input">
                <input type="email" id="cfgEmail" class="form-control"
                       value="<?= htmlspecialchars($cfg['usuario_email'] ?? '') ?>"
                       placeholder="seu@email.com">
            </div>
        </div>
    </div>

    <div class="cfg-section">
        <div class="cfg-section-title">Finanças</div>

        <div class="cfg-field">
            <div>
                <div class="cfg-field-label">Moeda padrão</div>
                <div class="cfg-field-desc">Símbolo usado em toda a interface</div>
            </div>
            <div class="cfg-field-input">
                <select id="cfgMoeda" class="form-control">
                    <option value="BRL" <?= ($cfg['moeda'] ?? 'BRL') === 'BRL' ? 'selected' : '' ?>>BRL — Real Brasileiro (R$)</option>
                    <option value="USD" <?= ($cfg['moeda'] ?? '') === 'USD' ? 'selected' : '' ?>>USD — Dólar Americano ($)</option>
                    <option value="EUR" <?= ($cfg['moeda'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR — Euro (€)</option>
                </select>
            </div>
        </div>

        <div class="cfg-field">
            <div>
                <div class="cfg-field-label">Dia de fechamento padrão</div>
                <div class="cfg-field-desc">Dia padrão para fechamento de cartões</div>
            </div>
            <div class="cfg-field-input">
                <input type="number" id="cfgDiaFech" class="form-control"
                       value="<?= htmlspecialchars($cfg['dia_fechamento_padrao'] ?? '5') ?>"
                       min="1" max="31" style="max-width:90px">
            </div>
        </div>

        <div class="cfg-field">
            <div>
                <div class="cfg-field-label">Meta de poupança (%)</div>
                <div class="cfg-field-desc">Percentual alvo de poupança mensal (padrão 20%)</div>
            </div>
            <div class="cfg-field-input">
                <input type="number" id="cfgMetaPoupanca" class="form-control"
                       value="<?= htmlspecialchars($cfg['meta_poupanca'] ?? '20') ?>"
                       min="0" max="100" step="1" style="max-width:90px">
            </div>
        </div>
    </div>

    <div class="cfg-section">
        <div class="cfg-section-title">Alertas</div>

        <div class="cfg-field">
            <div>
                <div class="cfg-field-label">Alertar limite de cartão (%)</div>
                <div class="cfg-field-desc">Cria alerta quando o uso do cartão atingir este percentual</div>
            </div>
            <div class="cfg-field-input">
                <input type="number" id="cfgAlertaCartao" class="form-control"
                       value="<?= htmlspecialchars($cfg['alerta_cartao_pct'] ?? '80') ?>"
                       min="10" max="100" step="5" style="max-width:90px">
            </div>
        </div>

        <div class="cfg-field">
            <div>
                <div class="cfg-field-label">Dias de antecedência — vencimentos</div>
                <div class="cfg-field-desc">Quantos dias antes alertar sobre parcelas e contas a vencer</div>
            </div>
            <div class="cfg-field-input">
                <input type="number" id="cfgAlertaDias" class="form-control"
                       value="<?= htmlspecialchars($cfg['alerta_dias_antecedencia'] ?? '5') ?>"
                       min="1" max="30" style="max-width:90px">
            </div>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:.75rem">
        <button class="btn btn-primary" onclick="salvarConfigs()">
            <i class="fa-solid fa-floppy-disk"></i> Salvar configurações
        </button>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     ABA 2 — CATEGORIAS
═══════════════════════════════════════════════════════════ -->
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

<!-- ═══════════════════════════════════════════════════════════
     ABA 3 — RESPONSÁVEIS
═══════════════════════════════════════════════════════════ -->
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
        <div class="card-body" style="text-align:center;padding:3rem;color:var(--text-600)">
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

<!-- ═══════════════════════════════════════════════════════════
     ABA 4 — EXPORTAR
═══════════════════════════════════════════════════════════ -->
<div id="cfg-exportar" class="cfg-pane">

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.25rem">

        <!-- Export transações -->
        <div class="export-card">
            <div class="d-flex align-center gap-1" style="margin-bottom:1rem">
                <div class="kpi-icon emerald"><i class="fa-solid fa-table"></i></div>
                <div>
                    <div class="fw-700">Transações</div>
                    <div class="text-xs text-muted">Exportar lançamentos como CSV</div>
                </div>
            </div>

            <div class="form-grid form-grid-2" style="margin-bottom:.875rem">
                <div class="form-group">
                    <label class="form-label">Mês inicial</label>
                    <select id="exDeMes" class="form-control">
                        <?php foreach ($nomesMeses as $n => $nome): if (!$n) continue; ?>
                        <option value="<?= $n ?>" <?= $n === 1 ? 'selected' : '' ?>><?= $nome ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Ano inicial</label>
                    <select id="exDeAno" class="form-control">
                        <?php for ($y = $anoAtual; $y >= $anoAtual - 4; $y--): ?>
                        <option value="<?= $y ?>" <?= $y === $anoAtual ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Mês final</label>
                    <select id="exAteMes" class="form-control">
                        <?php foreach ($nomesMeses as $n => $nome): if (!$n) continue; ?>
                        <option value="<?= $n ?>" <?= $n === $mesAtual ? 'selected' : '' ?>><?= $nome ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Ano final</label>
                    <select id="exAteAno" class="form-control">
                        <?php for ($y = $anoAtual; $y >= $anoAtual - 4; $y--): ?>
                        <option value="<?= $y ?>" <?= $y === $anoAtual ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor ?>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:.875rem">
                <label class="form-label">Tipo de transação</label>
                <select id="exTxTipo" class="form-control">
                    <option value="">Todos</option>
                    <option value="receita">Receitas</option>
                    <option value="despesa">Despesas</option>
                    <option value="transferencia">Transferências</option>
                </select>
            </div>

            <button class="btn btn-success w-full" onclick="exportarCSV()" style="justify-content:center">
                <i class="fa-solid fa-download"></i> Baixar CSV
            </button>
        </div>

        <!-- Export categorias -->
        <div class="export-card">
            <div class="d-flex align-center gap-1" style="margin-bottom:1rem">
                <div class="kpi-icon violet"><i class="fa-solid fa-tags"></i></div>
                <div>
                    <div class="fw-700">Categorias</div>
                    <div class="text-xs text-muted">Exportar todas as categorias como CSV</div>
                </div>
            </div>
            <p class="text-sm text-muted" style="margin-bottom:1rem">
                Exporta todas as categorias cadastradas (nome, tipo, cor, ícone, status).
                Útil para backup ou migração.
            </p>
            <button class="btn btn-primary w-full" onclick="exportarCategorias()" style="justify-content:center">
                <i class="fa-solid fa-download"></i> Exportar Categorias
            </button>
        </div>

        <!-- JSON completo -->
        <div class="export-card">
            <div class="d-flex align-center gap-1" style="margin-bottom:1rem">
                <div class="kpi-icon amber"><i class="fa-solid fa-code"></i></div>
                <div>
                    <div class="fw-700">JSON Completo</div>
                    <div class="text-xs text-muted">Dados brutos para backup ou análise</div>
                </div>
            </div>
            <p class="text-sm text-muted" style="margin-bottom:1rem">
                Exporta as transações do período selecionado em formato JSON.
                Ideal para importação em outras ferramentas.
            </p>
            <button class="btn btn-ghost w-full" onclick="exportarJSON()" style="justify-content:center">
                <i class="fa-solid fa-download"></i> Exportar JSON
            </button>
        </div>
    </div>

    <div id="exportStatus" style="margin-top:1.25rem;display:none">
        <div class="card">
            <div class="card-body d-flex align-center gap-1" style="padding:.875rem">
                <i class="fa-solid fa-circle-check text-emerald"></i>
                <span id="exportMsg" class="text-sm fw-600"></span>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     ABA 4 — SISTEMA
═══════════════════════════════════════════════════════════ -->
<div id="cfg-sistema" class="cfg-pane">
    <div class="cfg-section">
        <div class="cfg-section-title">Estatísticas do banco de dados</div>
        <div class="card-body">
            <div id="statsGrid" class="stats-grid">
                <div style="grid-column:1/-1;text-align:center;padding:2rem;color:var(--text-600)">
                    <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
                </div>
            </div>
        </div>
    </div>

    <div class="cfg-section">
        <div class="cfg-section-title">Informações do sistema</div>
        <div class="cfg-field">
            <div class="cfg-field-label">Versão do FinanceOS</div>
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

<!-- ═══════════════════════════════════════════════════════════
     ABA — NOTIFICAÇÕES
═══════════════════════════════════════════════════════════ -->
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

<!-- ═══════════════════════════════════════════════════════════
     ABA — DADOS
═══════════════════════════════════════════════════════════ -->
<div id="cfg-dados" class="cfg-pane">
    <div class="cfg-section">
        <div class="cfg-section-title">Excluir registros por tipo</div>

        <div style="display:flex;flex-direction:column;gap:.625rem;margin-top:.5rem" id="dadosLista">
            <div style="text-align:center;padding:1.5rem;color:var(--text-600)">
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
    <div class="modal-box">
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

<script>
const _CATS  = <?= json_encode(array_values($categorias),   JSON_UNESCAPED_UNICODE) ?>;
const _RESPS = <?= json_encode(array_values($responsaveis), JSON_UNESCAPED_UNICODE) ?>;

// ── Tabs ──────────────────────────────────────────────────
const _cfgTabLoaded = {};
function cfgTab(id) {
    const tabIds = ['geral','categorias','responsaveis','exportar','sistema','notificacoes','dados'];
    document.querySelectorAll('.cfg-tab').forEach((b, i) =>
        b.classList.toggle('active', tabIds[i] === id));
    document.querySelectorAll('.cfg-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('cfg-' + id).classList.add('active');
    if (id === 'sistema'       && !_cfgTabLoaded.sistema)      { _cfgTabLoaded.sistema      = true; carregarStats(); }
    if (id === 'dados'         && !_cfgTabLoaded.dados)        { _cfgTabLoaded.dados        = true; carregarContsDados(); }
    if (id === 'notificacoes'  && !_cfgTabLoaded.notificacoes) { _cfgTabLoaded.notificacoes = true; atualizarStatusNotif(); }
}

function atualizarStatusNotif() {
    const statusEl = document.getElementById('notifStatus');
    const badgeEl  = document.getElementById('notifStatusBadge');
    const btnEl    = document.getElementById('btnAtivarNotif');
    if (!('Notification' in window)) {
        if (statusEl) statusEl.textContent = 'Seu navegador não suporta notificações.';
        if (badgeEl)  { badgeEl.textContent = 'Indisponível'; badgeEl.className = 'badge'; }
        if (btnEl)    btnEl.disabled = true;
        return;
    }
    const perm = Notification.permission;
    const map  = {
        granted: ['Ativadas — você receberá alertas automaticamente', 'Ativado', 'emerald'],
        denied:  ['Bloqueadas — habilite nas configurações do navegador', 'Bloqueado', 'rose'],
        default: ['Não solicitado ainda — clique em "Ativar notificações"', 'Não ativado', 'amber'],
    };
    const [txt, badge, cor] = map[perm] || map.default;
    if (statusEl) statusEl.textContent = txt;
    if (badgeEl)  { badgeEl.textContent = badge; badgeEl.className = `badge ${cor}`; }
    if (btnEl)    btnEl.style.display = perm === 'granted' ? 'none' : '';
}

// ═══════════════════════════════════════════════════════════
// ABA — DADOS
// ═══════════════════════════════════════════════════════════
const _TIPOS_DADOS = [
    { tipo: 'despesas',      label: 'Despesas',      icone: 'arrow-down',      cor: 'var(--rose)'    },
    { tipo: 'receitas',      label: 'Receitas',      icone: 'arrow-up',        cor: 'var(--emerald)' },
    { tipo: 'emprestimos',   label: 'Empréstimos',   icone: 'landmark',        cor: 'var(--amber)'   },
    { tipo: 'investimentos', label: 'Investimentos', icone: 'chart-line',      cor: 'var(--indigo)'  },
    { tipo: 'metas',         label: 'Metas',         icone: 'bullseye',        cor: 'var(--cyan)'    },
];

async function carregarContsDados() {
    try {
        const res  = await fetch('backend/api/dados.php');
        const json = await res.json();
        if (!json.success) return;

        const lista = document.getElementById('dadosLista');
        lista.innerHTML = _TIPOS_DADOS.map(t => {
            const qty = json.counts[t.tipo] ?? 0;
            return `
            <div style="display:flex;justify-content:space-between;align-items:center;
                        padding:.75rem 1rem;background:var(--bg-700);border-radius:var(--radius)">
                <div style="display:flex;align-items:center;gap:.75rem">
                    <div style="width:32px;height:32px;border-radius:50%;background:${t.cor}22;
                                color:${t.cor};display:flex;align-items:center;justify-content:center">
                        <i class="fa-solid fa-${t.icone} fa-sm"></i>
                    </div>
                    <div>
                        <div class="fw-600 text-sm">${t.label}</div>
                        <div class="text-xs text-muted" id="cnt-${t.tipo}">${qty} registro${qty !== 1 ? 's' : ''}</div>
                    </div>
                </div>
                <button class="btn btn-sm" style="color:var(--rose);border:1px solid rgba(244,63,94,.4)"
                        onclick="excluirTipo('${t.tipo}','${t.label}')" ${qty === 0 ? 'disabled' : ''}>
                    <i class="fa-solid fa-trash fa-xs"></i> Excluir tudo
                </button>
            </div>`;
        }).join('');
    } catch (e) {
        document.getElementById('dadosLista').innerHTML =
            `<div class="text-muted text-sm">Erro ao carregar contagens.</div>`;
    }
}

function excluirTipo(tipo, label) {
    confirmar(
        `Excluir todas as ${label}`,
        `Todos os registros de "${label}" serão excluídos permanentemente. Esta ação não pode ser desfeita.`,
        async () => {
            try {
                const res  = await fetch(`backend/api/dados.php?tipo=${tipo}`, { method: 'DELETE' });
                const json = await res.json();
                if (!json.success) throw new Error(json.erro);
                toast(json.msg, 'success');
                _cfgTabLoaded.dados = false;
                carregarContsDados();
                _cfgTabLoaded.dados = true;
            } catch (err) {
                toast('Erro: ' + err.message, 'error');
            }
        },
        { btnLabel: 'Sim, excluir tudo', btnCor: 'var(--rose)' }
    );
}

function limparTudo() {
    confirmar(
        '⚠️ Excluir TODOS os dados',
        'Isso apagará permanentemente todas as despesas, receitas, empréstimos, investimentos, metas, orçamentos e alertas. Os saldos das contas voltarão ao valor inicial. ESTA AÇÃO NÃO PODE SER DESFEITA.',
        async () => {
            try {
                const res  = await fetch('backend/api/dados.php?tipo=tudo', { method: 'DELETE' });
                const json = await res.json();
                if (!json.success) throw new Error(json.erro);
                toast(json.msg, 'success');
                _cfgTabLoaded.dados = false;
                carregarContsDados();
                _cfgTabLoaded.dados = true;
            } catch (err) {
                toast('Erro: ' + err.message, 'error');
            }
        },
        { btnLabel: 'Sim, excluir TUDO', btnCor: 'var(--rose)', icone: 'bomb' }
    );
}

// ═══════════════════════════════════════════════════════════
// ABA 1 — GERAL
// ═══════════════════════════════════════════════════════════
async function salvarConfigs() {
    const configs = {
        usuario_nome:            document.getElementById('cfgNome').value.trim(),
        usuario_email:           document.getElementById('cfgEmail').value.trim(),
        moeda:                   document.getElementById('cfgMoeda').value,
        dia_fechamento_padrao:   document.getElementById('cfgDiaFech').value,
        meta_poupanca:           document.getElementById('cfgMetaPoupanca').value,
        alerta_cartao_pct:       document.getElementById('cfgAlertaCartao').value,
        alerta_dias_antecedencia:document.getElementById('cfgAlertaDias').value,
    };
    try {
        const res  = await fetch('backend/api/configuracoes.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ acao: 'salvar_configs', configs })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

// ═══════════════════════════════════════════════════════════
// ABA 2 — CATEGORIAS
// ═══════════════════════════════════════════════════════════
function filtrarCats(filtro) {
    ['todos','ativo','inativo'].forEach(f => {
        document.getElementById('filtCat-' + f)?.classList.toggle('btn-primary', f === filtro);
        document.getElementById('filtCat-' + f)?.classList.toggle('btn-ghost',   f !== filtro);
    });
    document.querySelectorAll('.cat-item').forEach(row => {
        const ativo = row.dataset.ativo === '1';
        row.style.display =
            filtro === 'todos'   ? '' :
            filtro === 'ativo'   ? (ativo  ? '' : 'none') :
                                   (!ativo ? '' : 'none');
    });
}

function abrirModalCat(id = null, catPaiId = null) {
    document.getElementById('formCat').reset();
    document.getElementById('catId').value    = id || '';
    document.getElementById('catCor').value   = '#6366f1';
    document.getElementById('catIcone').value = 'tag';
    document.getElementById('catPai').value   = catPaiId || '';
    document.getElementById('catModalTitulo').textContent = catPaiId ? 'Nova Subcategoria' : 'Nova Categoria';
    setTipoCat('despesa');
    onChangeCatPai();
    atualizarPreview();
    document.getElementById('modalCat').classList.add('open');
    setTimeout(() => document.getElementById('catNome').focus(), 80);
}

function abrirEditarCat(id) {
    const c = _CATS.find(x => +x.id === +id);
    if (!c) return;
    document.getElementById('catId').value    = c.id;
    document.getElementById('catNome').value  = c.nome          || '';
    document.getElementById('catCor').value   = c.cor           || '#6366f1';
    document.getElementById('catIcone').value = c.icone         || 'tag';
    document.getElementById('catPai').value   = c.categoria_pai || '';
    document.getElementById('catModalTitulo').textContent = c.categoria_pai ? 'Editar Subcategoria' : 'Editar Categoria';
    setTipoCat(c.tipo || 'despesa');
    onChangeCatPai();
    atualizarPreview();
    document.getElementById('modalCat').classList.add('open');
}

function onChangeCatPai() {
    const sel  = document.getElementById('catPai');
    const opt  = sel.options[sel.selectedIndex];
    const wrap = document.getElementById('catTipoWrap');
    if (sel.value) {
        // Subcategoria herda o tipo do pai automaticamente
        const tipo = opt.dataset.tipo || 'despesa';
        setTipoCat(tipo);
        if (wrap) wrap.style.display = 'none';
        // Sugere a cor do pai
        const cor = opt.dataset.cor;
        if (cor && !document.getElementById('catId').value) {
            document.getElementById('catCor').value = cor;
            atualizarPreview();
        }
    } else {
        if (wrap) wrap.style.display = '';
    }
}

function setTipoCat(v) {
    document.getElementById('catTipo').value = v;
    ['despesa','receita','ambos'].forEach(t => {
        const btn = document.getElementById('tipoCatBtn-' + t);
        if (!btn) return;
        btn.className = 'btn btn-sm ' + (t === v ? 'btn-primary' : 'btn-ghost');
    });
}

function atualizarPreview() {
    const cor   = document.getElementById('catCor').value;
    const icone = document.getElementById('catIcone').value.trim() || 'tag';
    const prev  = document.getElementById('iconePreview');
    prev.style.background = cor + '22';
    prev.style.color = cor;
    prev.innerHTML = `<i class="fa-solid fa-${esc(icone)}"></i>`;
}

async function salvarCat(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvarCat');
    btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    const id = document.getElementById('catId').value;
    const payload = {
        acao:          'salvar_categoria',
        id:            id ? parseInt(id) : 0,
        nome:          document.getElementById('catNome').value.trim(),
        tipo:          document.getElementById('catTipo').value,
        icone:         document.getElementById('catIcone').value.trim() || 'tag',
        cor:           document.getElementById('catCor').value,
        categoria_pai: parseInt(document.getElementById('catPai').value) || 0,
    };

    try {
        const res  = await fetch('backend/api/configuracoes.php', {
            method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        fecharModal();
        location.reload();
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
    finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar';
    }
}

async function toggleCat(id) {
    try {
        const res  = await fetch('backend/api/configuracoes.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ acao:'toggle_categoria', id })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        location.reload();
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

function inserirCatsPadrao() {
    confirmar('Inserir categorias padrão', 'Adicionar categorias sugeridas? As que já existem serão ignoradas.', async () => {
        try {
            const res  = await fetch('backend/api/configuracoes.php', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ acao: 'inserir_padrao' })
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.erro);
            toast(json.msg, json.inseridas > 0 ? 'success' : 'info');
            if (json.inseridas > 0) location.reload();
        } catch (err) { toast('Erro: ' + err.message, 'error'); }
    }, { btnLabel: 'Inserir', icone: 'wand-magic-sparkles', btnCor: 'var(--indigo)' });
}

function excluirCat(id, nome) {
    confirmar('Remover categoria', `Remover a categoria "${nome}"? Se estiver em uso, será apenas desativada.`, async () => {
        try {
            const res  = await fetch(`backend/api/configuracoes.php?tipo=categoria&id=${id}`, {method:'DELETE'});
            const json = await res.json();
            if (!json.success) throw new Error(json.erro);
            toast(json.msg, 'success');
            location.reload();
        } catch (err) { toast('Erro: ' + err.message, 'error'); }
    });
}

// ═══════════════════════════════════════════════════════════
// ABA 3 — EXPORTAR
// ═══════════════════════════════════════════════════════════
function jsonToCSV(dados, campos) {
    const header = campos.join(';');
    const linhas = dados.map(row =>
        campos.map(f => {
            const v = row[f] ?? '';
            return typeof v === 'string' && v.includes(';') ? `"${v}"` : v;
        }).join(';')
    );
    return [header, ...linhas].join('\n');
}

function download(conteudo, nomeArquivo, tipo) {
    const blob = new Blob(['﻿' + conteudo], { type: tipo });
    const a    = document.createElement('a');
    a.href     = URL.createObjectURL(blob);
    a.download = nomeArquivo;
    a.click();
    URL.revokeObjectURL(a.href);
}

async function exportarCSV() {
    const deMes  = document.getElementById('exDeMes').value;
    const deAno  = document.getElementById('exDeAno').value;
    const ateMes = document.getElementById('exAteMes').value;
    const ateAno = document.getElementById('exAteAno').value;
    const txTipo = document.getElementById('exTxTipo').value;

    try {
        const url = `backend/api/configuracoes.php?acao=exportar&tipo=transacoes&de_mes=${deMes}&de_ano=${deAno}&ate_mes=${ateMes}&ate_ano=${ateAno}&tx_tipo=${txTipo}`;
        const res  = await fetch(url);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);

        const campos = ['id','tipo','descricao','valor','data','status','parcela_atual','parcela_total','categoria','conta','cartao','observacao'];
        const csv    = jsonToCSV(json.dados, campos);
        download(csv, `financeos-transacoes-${deAno}${deMes.padStart(2,'0')}-${ateAno}${ateMes.padStart(2,'0')}.csv`, 'text/csv;charset=utf-8');

        mostrarExportStatus(`${json.total} transações exportadas com sucesso.`);
    } catch (err) { toast('Erro na exportação: ' + err.message, 'error'); }
}

async function exportarCategorias() {
    try {
        const res  = await fetch('backend/api/configuracoes.php?acao=exportar&tipo=categorias');
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);

        const campos = ['id','nome','tipo','icone','cor','ativo'];
        const csv    = jsonToCSV(json.dados, campos);
        download(csv, 'financeos-categorias.csv', 'text/csv;charset=utf-8');
        mostrarExportStatus(`${json.dados.length} categorias exportadas.`);
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

async function exportarJSON() {
    const deMes  = document.getElementById('exDeMes').value;
    const deAno  = document.getElementById('exDeAno').value;
    const ateMes = document.getElementById('exAteMes').value;
    const ateAno = document.getElementById('exAteAno').value;
    const txTipo = document.getElementById('exTxTipo').value;

    try {
        const url = `backend/api/configuracoes.php?acao=exportar&tipo=transacoes&de_mes=${deMes}&de_ano=${deAno}&ate_mes=${ateMes}&ate_ano=${ateAno}&tx_tipo=${txTipo}`;
        const res  = await fetch(url);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);

        const conteudo = JSON.stringify({ exportado_em: new Date().toISOString(), ...json }, null, 2);
        download(conteudo, `financeos-backup-${new Date().toISOString().split('T')[0]}.json`, 'application/json');
        mostrarExportStatus(`${json.total} registros exportados como JSON.`);
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

function mostrarExportStatus(msg) {
    const el = document.getElementById('exportStatus');
    document.getElementById('exportMsg').textContent = msg;
    el.style.display = '';
    setTimeout(() => el.style.display = 'none', 5000);
}

// ═══════════════════════════════════════════════════════════
// ABA 4 — SISTEMA
// ═══════════════════════════════════════════════════════════
async function carregarStats() {
    try {
        const res  = await fetch('backend/api/configuracoes.php?acao=stats');
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);

        const labels = {
            transacoes:'Transações', categorias:'Categorias', contas:'Contas',
            cartoes:'Cartões', emprestimos:'Empréstimos', investimentos:'Investimentos',
            metas:'Metas', recorrencias:'Contas Fixas', alertas:'Alertas',
        };

        document.getElementById('statsGrid').innerHTML =
            Object.entries(json.counts).map(([k, v]) =>
                `<div class="stat-chip">
                    <div class="stat-chip-val">${v.toLocaleString('pt-BR')}</div>
                    <div class="stat-chip-lbl">${labels[k] || k}</div>
                </div>`
            ).join('') +
            `<div class="stat-chip" style="border-color:var(--emerald)">
                <div class="stat-chip-val" style="color:var(--emerald)">${brl(json.total_moviment)}</div>
                <div class="stat-chip-lbl">Total Movimentado</div>
            </div>`;

        if (json.desde) {
            const d = json.desde.split('-');
            document.getElementById('statsDesde').textContent = `${d[2]}/${d[1]}/${d[0]}`;
        }
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

// ═══════════════════════════════════════════════════════════
// ABA 3 — RESPONSÁVEIS
// ═══════════════════════════════════════════════════════════
function abrirModalResp() {
    document.getElementById('formResp').reset();
    document.getElementById('respId').value    = '';
    document.getElementById('respCor').value   = '#6366f1';
    document.getElementById('respIcone').value = 'user';
    document.getElementById('respModalTitulo').textContent = 'Novo Responsável';
    atualizarPreviewResp();
    document.getElementById('modalResp').classList.add('open');
    setTimeout(() => document.getElementById('respNome').focus(), 80);
}

function abrirEditarResp(id) {
    const r = _RESPS.find(x => +x.id === +id);
    if (!r) return;
    document.getElementById('respId').value    = r.id;
    document.getElementById('respNome').value  = r.nome  || '';
    document.getElementById('respCor').value   = r.cor   || '#6366f1';
    document.getElementById('respIcone').value = r.icone || 'user';
    document.getElementById('respModalTitulo').textContent = 'Editar Responsável';
    atualizarPreviewResp();
    document.getElementById('modalResp').classList.add('open');
}

function atualizarPreviewResp() {
    const cor   = document.getElementById('respCor').value;
    const icone = document.getElementById('respIcone').value.trim() || 'user';
    const prev  = document.getElementById('respIconePreview');
    prev.style.background = cor + '22';
    prev.style.color = cor;
    prev.innerHTML = `<i class="fa-solid fa-${esc(icone)}"></i>`;
}

async function salvarResp(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvarResp');
    btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    const id = document.getElementById('respId').value;
    const payload = {
        acao:  'salvar',
        id:    id ? parseInt(id) : 0,
        nome:  document.getElementById('respNome').value.trim(),
        icone: document.getElementById('respIcone').value.trim() || 'user',
        cor:   document.getElementById('respCor').value,
    };
    try {
        const res  = await fetch('backend/api/responsaveis.php', {
            method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        fecharModalResp();
        location.reload();
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
    finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar';
    }
}

async function toggleResp(id) {
    try {
        const res  = await fetch('backend/api/responsaveis.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ acao:'toggle', id })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success'); location.reload();
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

function excluirResp(id, nome) {
    confirmar('Remover responsável', `Remover "${nome}"? As transações vinculadas perderão o responsável.`, async () => {
        try {
            const res  = await fetch(`backend/api/responsaveis.php?id=${id}`, {method:'DELETE'});
            const json = await res.json();
            if (!json.success) throw new Error(json.erro);
            toast(json.msg, 'success'); location.reload();
        } catch (err) { toast('Erro: ' + err.message, 'error'); }
    });
}

function fecharModalResp() { document.getElementById('modalResp').classList.remove('open'); }

// ── Utilitários ───────────────────────────────────────────
function fecharModal() { document.getElementById('modalCat').classList.remove('open'); }
function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { fecharModal(); fecharModalResp(); }
});
</script>
