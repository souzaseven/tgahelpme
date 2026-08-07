<?php
// ── Auto-migração ─────────────────────────────────────────────
$p   = TABLE_PREFIX;
$col = $pdo->query("SHOW COLUMNS FROM `{$p}contas` LIKE 'titular'")->fetch();
if (!$col) {
    $pdo->exec(
        "ALTER TABLE `{$p}contas`
         ADD COLUMN `titular` VARCHAR(100) DEFAULT NULL AFTER `icone`"
    );
}

// ── Dados ─────────────────────────────────────────────────────
$contas = $pdo->query(
    "SELECT * FROM `{$p}contas` WHERE ativo=1 ORDER BY titular, nome"
)->fetchAll();

// Agrupa por titular
$grupos = [];
foreach ($contas as $c) {
    $t = trim($c['titular'] ?? '') ?: 'Sem titular';
    $grupos[$t][] = $c;
}
ksort($grupos);

// Totais por titular (só contas incluir_total=1)
$totaisTit = [];
foreach ($grupos as $tit => $arr) {
    $totaisTit[$tit] = array_sum(array_map(
        fn($c) => $c['incluir_total'] ? (float)$c['saldo_atual'] : 0, $arr
    ));
}
$saldoGeral = array_sum($totaisTit);

// Lista de titulares únicos para o select do formulário
$titularesExist = $pdo->query(
    "SELECT DISTINCT titular FROM `{$p}contas` WHERE titular IS NOT NULL AND titular != '' ORDER BY titular"
)->fetchAll(PDO::FETCH_COLUMN);

// brl() vem de backend/helpers/formatacao.php (incluído por index.php)

$tipoLabels = [
    'corrente'     => 'Conta Corrente',
    'poupanca'     => 'Poupança',
    'carteira'     => 'Carteira',
    'investimento' => 'Conta Investimento',
    'outro'        => 'Outro',
];
$tipoIcons = [
    'corrente'     => 'fa-university',
    'poupanca'     => 'fa-piggy-bank',
    'carteira'     => 'fa-wallet',
    'investimento' => 'fa-chart-line',
    'outro'        => 'fa-circle-dollar-to-slot',
];
?>

<style>
/* ── Card de conta ───────────────────────────────────────── */
.conta-card {
    background: var(--bg-700);
    border-radius: var(--radius);
    padding: 1rem 1.1rem;
    display: flex;
    align-items: center;
    gap:.875rem;
    transition: var(--ease);
    border-left: 3px solid transparent;
}
.conta-card:hover { background: var(--bg-600); }
.conta-icon {
    width:38px; height:38px; border-radius:var(--radius-sm);
    display:flex; align-items:center; justify-content:center;
    font-size:.9rem; flex-shrink:0;
}
.conta-nome  { font-weight:600; font-size:.875rem; }
.conta-tipo  { font-size:.72rem; color:var(--text-400); }
.conta-saldo { font-weight:700; font-size:1rem; }
/* ── Grupo de titular ─────────────────────────────────────── */
.titular-section { margin-bottom:1.5rem; }
.titular-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:.625rem 1rem;
    background: var(--bg-800);
    border: 1px solid var(--border);
    border-radius: var(--radius) var(--radius) 0 0;
}
.titular-nome  { font-weight:700; font-size:.95rem; display:flex; align-items:center; gap:.6rem; }
.titular-total { font-size:1.05rem; font-weight:700; }
.titular-body {
    border: 1px solid var(--border);
    border-top: none;
    border-radius: 0 0 var(--radius) var(--radius);
    overflow: hidden;
    display: flex; flex-direction: column; gap: 1px;
    background: var(--border);
}
.titular-body .conta-card { border-radius:0; }
/* ── Painel de comparação ─────────────────────────────────── */
.comparacao-grid {
    display: grid;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
/* Modal base agora vive em assets/css/main.css (inclui .modal-box.sm). */
/* ── Datalist suggestion ─────────────────────────────────── */
input[list]::-webkit-calendar-picker-indicator { opacity:.5; }
</style>

<!-- ── Cabeçalho ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <div class="page-title">Contas Bancárias</div>
        <div class="page-sub">Visão por titular — individual e consolidada</div>
    </div>
    <button class="btn btn-primary btn-sm" onclick="abrirModalNova()">
        <i class="fa-solid fa-plus"></i> Nova Conta
    </button>
</div>

<!-- ── Painel de comparação por titular ──────────────────── -->
<?php if (!empty($grupos)): ?>
<div class="comparacao-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr))">
    <?php foreach ($totaisTit as $tit => $total):
        $pct = $saldoGeral > 0 ? round(($total / $saldoGeral) * 100) : 0;
        $cores = ['indigo','emerald','violet','cyan','amber','rose'];
        $ci = array_keys($totaisTit);
        $idx = array_search($tit, $ci) % count($cores);
        $cor = $cores[$idx];
    ?>
    <div class="kpi-card <?= $cor ?>">
        <div class="kpi-header">
            <div class="kpi-label"><?= htmlspecialchars($tit) ?></div>
            <div class="kpi-icon <?= $cor ?>"><i class="fa-solid fa-user-circle"></i></div>
        </div>
        <div class="kpi-value"><?= brl($total) ?></div>
        <div class="kpi-trend neutral">
            <i class="fa-solid fa-circle-info fa-xs"></i>
            <?= $pct ?>% do total &nbsp;·&nbsp; <?= count($grupos[$tit]) ?> conta<?= count($grupos[$tit])!==1?'s':'' ?>
        </div>
    </div>
    <?php endforeach ?>

    <!-- Total geral -->
    <div class="kpi-card indigo" style="border:2px solid var(--indigo)">
        <div class="kpi-header">
            <div class="kpi-label">PATRIMÔNIO TOTAL</div>
            <div class="kpi-icon indigo"><i class="fa-solid fa-coins"></i></div>
        </div>
        <div class="kpi-value"><?= brl($saldoGeral) ?></div>
        <div class="kpi-trend up">
            <i class="fa-solid fa-arrow-up fa-xs"></i>
            <?= count($contas) ?> conta<?= count($contas)!==1?'s':'' ?> ativa<?= count($contas)!==1?'s':'' ?>
        </div>
    </div>
</div>

<!-- ── Seções por titular ─────────────────────────────────── -->
<?php foreach ($grupos as $titular => $contasGrupo):
    $totalGrupo = array_sum(array_map(fn($c) => $c['incluir_total'] ? (float)$c['saldo_atual'] : 0, $contasGrupo));
?>
<div class="titular-section">
    <div class="titular-header">
        <div class="titular-nome">
            <i class="fa-solid fa-user-circle" style="color:var(--indigo)"></i>
            <?= htmlspecialchars($titular) ?>
            <span class="badge indigo"><?= count($contasGrupo) ?> conta<?= count($contasGrupo)!==1?'s':'' ?></span>
        </div>
        <div class="titular-total <?= $totalGrupo >= 0 ? 'text-emerald' : 'text-rose' ?>">
            <?= brl($totalGrupo) ?>
        </div>
    </div>

    <div class="titular-body">
        <?php foreach ($contasGrupo as $c):
            $cor   = htmlspecialchars($c['cor'] ?? '#6366f1');
            $icone = $tipoIcons[$c['tipo']] ?? 'fa-university';
        ?>
        <div class="conta-card" style="border-left-color:<?= $cor ?>">
            <div class="conta-icon" style="background:<?= $cor ?>22;color:<?= $cor ?>">
                <i class="fa-solid <?= $icone ?>"></i>
            </div>
            <div style="flex:1">
                <div class="conta-nome"><?= htmlspecialchars($c['nome']) ?></div>
                <div class="conta-tipo">
                    <?= $tipoLabels[$c['tipo']] ?? $c['tipo'] ?>
                    <?= $c['banco'] ? ' &nbsp;·&nbsp; '.htmlspecialchars($c['banco']) : '' ?>
                    <?php if (!$c['incluir_total']): ?>
                    <span class="badge amber" style="font-size:.6rem;margin-left:.3rem">Não computa total</span>
                    <?php endif ?>
                </div>
            </div>
            <div style="text-align:right">
                <div class="conta-saldo <?= (float)$c['saldo_atual'] >= 0 ? 'text-emerald' : 'text-rose' ?>">
                    <?= brl((float)$c['saldo_atual']) ?>
                </div>
                <div class="text-xs text-muted">saldo atual</div>
            </div>
            <div class="d-flex gap-1">
                <button class="btn-icon" onclick="abrirEditar(<?= $c['id'] ?>)" title="Editar"
                        style="width:28px;height:28px;font-size:.72rem">
                    <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button class="btn-icon" onclick="abrirAjuste(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['nome'])) ?>', <?= $c['saldo_atual'] ?>)"
                        title="Ajustar saldo" style="width:28px;height:28px;font-size:.72rem;color:var(--amber)">
                    <i class="fa-solid fa-sliders"></i>
                </button>
                <button class="btn-icon" onclick="desativarConta(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['nome'])) ?>')"
                        title="Desativar" style="width:28px;height:28px;font-size:.72rem;color:var(--rose)">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>
        <?php endforeach ?>
    </div>
</div>
<?php endforeach ?>

<?php else: ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:3rem;color:var(--text-600)">
        <i class="fa-solid fa-university fa-3x" style="margin-bottom:1rem;color:var(--indigo)"></i>
        <div class="fw-700" style="margin-bottom:.5rem">Nenhuma conta cadastrada</div>
        <div class="text-sm" style="margin-bottom:1.25rem">Adicione suas contas bancárias, poupança e carteiras.</div>
        <button class="btn btn-primary" onclick="abrirModalNova()">
            <i class="fa-solid fa-plus"></i> Adicionar conta
        </button>
    </div>
</div>
<?php endif ?>

<!-- ── Datalist de titulares existentes ──────────────────── -->
<datalist id="titularesList">
    <?php foreach ($titularesExist as $t): ?>
    <option value="<?= htmlspecialchars($t) ?>">
    <?php endforeach ?>
</datalist>

<!-- ── Modal: Nova / Editar Conta ────────────────────────── -->
<div id="modalForm" class="modal-overlay" onclick="if(event.target===this)fecharModal('modalForm')">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title" id="modalTitulo">Nova Conta</div>
            <button type="button" class="btn-icon" onclick="fecharModal('modalForm')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formConta" onsubmit="salvarConta(event)">
            <div class="modal-body">
                <input type="hidden" id="cId" value="">

                <!-- TITULAR — campo em destaque -->
                <div class="card" style="margin-bottom:1.25rem;border:2px solid var(--indigo);background:var(--indigo-bg)">
                    <div class="card-body" style="padding:1rem">
                        <div class="form-group">
                            <label class="form-label" style="color:var(--indigo);font-size:.8rem">
                                <i class="fa-solid fa-user-circle"></i>
                                Titular da conta
                            </label>
                            <input type="text" id="cTitular" class="form-control"
                                   list="titularesList"
                                   placeholder="Ex: Anderson, Maria, Conjunto, Empresa..."
                                   autocomplete="off">
                            <div class="text-xs text-muted" style="margin-top:.4rem">
                                Digite um nome ou escolha um já existente. Usado para agrupar e comparar saldos.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Nome da conta <span style="color:var(--rose)">*</span></label>
                    <input type="text" id="cNome" class="form-control" required
                           placeholder="Ex: Nubank, Itaú Poupança, Carteira...">
                </div>

                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Tipo</label>
                        <select id="cTipo" class="form-control">
                            <option value="corrente">Conta Corrente</option>
                            <option value="poupanca">Poupança</option>
                            <option value="carteira">Carteira (dinheiro físico)</option>
                            <option value="investimento">Conta Investimento</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Banco / Instituição</label>
                        <input type="text" id="cBanco" class="form-control" placeholder="Ex: Nubank, Bradesco...">
                    </div>
                </div>

                <div class="form-grid form-grid-3" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Saldo inicial (R$)</label>
                        <input type="text" id="cSaldo" class="form-control" placeholder="0,00" inputmode="numeric" data-currency>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cor</label>
                        <input type="color" id="cCor" class="form-control" value="#6366f1"
                               style="height:40px;padding:.3rem;cursor:pointer">
                    </div>
                    <div class="form-group" style="justify-content:flex-end;padding-top:1.35rem">
                        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.85rem">
                            <input type="checkbox" id="cIncTotal" checked>
                            Incluir no total
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="fecharModal('modalForm')">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnSalvar">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Ajustar Saldo ──────────────────────────────── -->
<div id="modalAjuste" class="modal-overlay" onclick="if(event.target===this)fecharModal('modalAjuste')">
    <div class="modal-box sm">
        <div class="modal-header">
            <div class="modal-title">Ajustar Saldo</div>
            <button type="button" class="btn-icon" onclick="fecharModal('modalAjuste')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formAjuste" onsubmit="salvarAjuste(event)">
            <div class="modal-body">
                <input type="hidden" id="ajId">
                <div class="card" style="margin-bottom:1.1rem;background:var(--bg-700)">
                    <div class="card-body" style="padding:.875rem">
                        <div class="text-xs text-muted">Conta</div>
                        <div class="fw-700" id="ajNome" style="margin-top:.2rem">—</div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Novo saldo atual (R$) <span style="color:var(--rose)">*</span></label>
                    <input type="text" id="ajSaldo" class="form-control" placeholder="0,00" inputmode="numeric" data-currency required>
                </div>
                <div class="text-xs text-muted" style="margin-top:.5rem">
                    <i class="fa-solid fa-circle-info"></i>
                    Use para corrigir o saldo sem criar uma transação. Ideal para ajuste inicial ou conciliação.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="fecharModal('modalAjuste')">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnAjuste">
                    <i class="fa-solid fa-sliders"></i> Ajustar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const _CONTAS = <?= json_encode(array_values($contas), JSON_UNESCAPED_UNICODE) ?>;

function abrirModalNova() {
    document.getElementById('formConta').reset();
    document.getElementById('cId').value      = '';
    document.getElementById('cCor').value     = '#6366f1';
    document.getElementById('cIncTotal').checked = true;
    const cSaldo = document.getElementById('cSaldo');
    cSaldo.value       = '';
    cSaldo.disabled    = false;
    cSaldo.placeholder = '0,00';
    document.getElementById('modalTitulo').textContent = 'Nova Conta';
    document.getElementById('modalForm').classList.add('open');
    setTimeout(() => document.getElementById('cTitular').focus(), 80);
}

function abrirEditar(id) {
    const c = _CONTAS.find(x => +x.id === +id);
    if (!c) return;
    document.getElementById('formConta').reset();
    document.getElementById('cId').value         = c.id;
    document.getElementById('cTitular').value    = c.titular   || '';
    document.getElementById('cNome').value       = c.nome      || '';
    document.getElementById('cTipo').value       = c.tipo      || 'corrente';
    document.getElementById('cBanco').value      = c.banco     || '';
    document.getElementById('cCor').value        = c.cor       || '#6366f1';
    document.getElementById('cIncTotal').checked = !!+c.incluir_total;
    // Saldo não pode ser editado aqui — use "Ajustar Saldo" para isso
    document.getElementById('cSaldo').value      = '';
    document.getElementById('cSaldo').disabled   = true;
    document.getElementById('cSaldo').placeholder = 'Use "Ajustar Saldo" para alterar';
    document.getElementById('modalTitulo').textContent = 'Editar Conta';
    document.getElementById('modalForm').classList.add('open');
    setTimeout(() => document.getElementById('cNome').focus(), 80);
}

async function salvarConta(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvar');
    btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    const id = document.getElementById('cId').value;
    const payload = {
        acao:          'salvar',
        id:            id ? parseInt(id) : 0,
        titular:       document.getElementById('cTitular').value.trim(),
        nome:          document.getElementById('cNome').value.trim(),
        tipo:          document.getElementById('cTipo').value,
        banco:         document.getElementById('cBanco').value.trim(),
        saldo_inicial: parseCurrency(document.getElementById('cSaldo').value) || 0,
        cor:           document.getElementById('cCor').value,
        incluir_total: document.getElementById('cIncTotal').checked ? 1 : 0,
    };

    try {
        const res  = await fetch('backend/api/contas.php', {
            method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        fecharModal('modalForm');
        location.reload();
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
    finally {
        const sl = document.getElementById('cSaldo');
        sl.disabled    = false;
        sl.placeholder = '0,00';
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar';
    }
}

function abrirAjuste(id, nome, saldo) {
    document.getElementById('ajId').value    = id;
    document.getElementById('ajNome').textContent = nome;
    document.getElementById('ajSaldo').value = brlMask(saldo);
    document.getElementById('modalAjuste').classList.add('open');
    setTimeout(() => document.getElementById('ajSaldo').select(), 80);
}

async function salvarAjuste(e) {
    e.preventDefault();
    const btn = document.getElementById('btnAjuste');
    btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    try {
        const res  = await fetch('backend/api/contas.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({
                acao:   'ajustar_saldo',
                id:     parseInt(document.getElementById('ajId').value),
                saldo:  parseCurrency(document.getElementById('ajSaldo').value),
            })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        fecharModal('modalAjuste');
        location.reload();
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
    finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-sliders"></i> Ajustar';
    }
}

async function desativarConta(id, nome) {
    let qtdTx = 0;
    try {
        const ri = await fetch(`backend/api/contas.php?impacto=1&id=${id}`);
        const ji = await ri.json();
        qtdTx = ji.qtd_transacoes || 0;
    } catch (_) {}

    const aviso = qtdTx > 0
        ? ` Esta conta possui ${qtdTx} lançamento${qtdTx !== 1 ? 's' : ''} vinculado${qtdTx !== 1 ? 's' : ''} — eles não serão excluídos.`
        : '';

    confirmar(
        'Desativar conta',
        `A conta "${nome}" será ocultada e não aparecerá mais no sistema.${aviso}`,
        async () => {
            try {
                const res  = await fetch(`backend/api/contas.php?id=${id}`, {method:'DELETE'});
                const json = await res.json();
                if (!json.success) throw new Error(json.erro);
                toast(json.msg, 'success');
                location.reload();
            } catch (err) { toast('Erro: ' + err.message, 'error'); }
        },
        { btnLabel: 'Desativar', icone: 'ban' }
    );
}

function fecharModal(id) { document.getElementById(id).classList.remove('open'); }

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') ['modalForm','modalAjuste'].forEach(fecharModal);
});
</script>
