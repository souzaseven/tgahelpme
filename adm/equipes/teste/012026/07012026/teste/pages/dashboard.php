<?php
require_once __DIR__ . '/../src/Financeiro/Calculos.php';

use FinanceOS\Financeiro\Calculos;

// ── Dados do mês atual ────────────────────────────────────────
$mes  = (int) date('m');
$ano  = (int) date('Y');
$p    = TABLE_PREFIX;

// ── Filtro de pessoa (responsável ou terceiro) — URL: ?p=dashboard&resp=1,-1,tcr_3 ─────
$_respDash = [];
try {
    $_respDash = $pdo->query(
        "SELECT id, nome, cor, icone FROM `{$p}responsaveis` WHERE ativo=1 ORDER BY nome"
    )->fetchAll();
} catch (PDOException $_e) {}

$_terceirosDash = [];
try {
    $_terceirosDash = $pdo->query(
        "SELECT id, nome, cor, icone FROM `{$p}terceiros` WHERE ativo=1 ORDER BY nome"
    )->fetchAll();
} catch (PDOException $_e) {}

$_respRawD     = trim($_GET['resp'] ?? '');
$_allValsD     = $_respRawD !== '' ? array_map('trim', explode(',', $_respRawD)) : [];
$_respIdsD     = []; $_respNullD = false; $_terceiroIdsD = [];
foreach ($_allValsD as $v) {
    if ($v === '-1')                { $_respNullD = true; }
    elseif (str_starts_with($v, 'tcr_')) { $tid = (int)substr($v, 4); if ($tid > 0) $_terceiroIdsD[] = $tid; }
    else                            { $id = (int)$v; if ($id > 0) $_respIdsD[] = $id; }
}
// Reconstrói array para JS (strings como enviadas no URL)
$_allRespValsD = [];
foreach ($_respIdsD as $id) $_allRespValsD[] = (string)$id;
if ($_respNullD) $_allRespValsD[] = '-1';
foreach ($_terceiroIdsD as $id) $_allRespValsD[] = 'tcr_' . $id;
$_hasRespD = !empty($_allRespValsD);

// WHERE fragments — sem alias (queries simples) e com alias t. (queries com JOIN)
$_rWhere  = '';
$_rParams = [];
if ($_hasRespD) {
    $_rParts = [];
    if (!empty($_respIdsD) || $_respNullD) {
        $rP = [];
        if (!empty($_respIdsD)) {
            $ph = implode(',', array_fill(0, count($_respIdsD), '?'));
            $rP[] = "responsavel_id IN ($ph)";
            array_push($_rParams, ...$_respIdsD);
        }
        if ($_respNullD) $rP[] = 'responsavel_id IS NULL';
        $_rParts[] = '(terceiro_id IS NULL AND (' . implode(' OR ', $rP) . '))';
    }
    if (!empty($_terceiroIdsD)) {
        $ph = implode(',', array_fill(0, count($_terceiroIdsD), '?'));
        $_rParts[] = "terceiro_id IN ($ph)";
        array_push($_rParams, ...$_terceiroIdsD);
    }
    $_rWhere = ' AND (' . implode(' OR ', $_rParts) . ')';
}
$_rWhereT = str_replace(
    ['responsavel_id', 'terceiro_id'],
    ['t.responsavel_id', 't.terceiro_id'],
    $_rWhere
);

// ── Saldo total das contas ────────────────────────────────────
$saldoTotal  = (float) $pdo->query(
    "SELECT COALESCE(SUM(saldo_atual),0) FROM `{$p}contas` WHERE incluir_total=1 AND ativo=1"
)->fetchColumn();
$_qtdContas  = (int) $pdo->query("SELECT COUNT(*) FROM `{$p}contas` WHERE ativo=1")->fetchColumn();
$_qtdCats    = (int) $pdo->query("SELECT COUNT(*) FROM `{$p}categorias` WHERE ativo=1")->fetchColumn();
$_qtdResps   = (int) $pdo->query("SELECT COUNT(*) FROM `{$p}responsaveis` WHERE ativo=1")->fetchColumn();

// Receitas do mês
$_s = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM `{$p}transacoes` WHERE tipo='receita' AND status='pago' AND MONTH(data)=? AND YEAR(data)=?$_rWhere");
$_s->execute(array_merge([$mes, $ano], $_rParams));
$receitasMes = (float) $_s->fetchColumn();

// Despesas do mês — inclui pendentes (parcelas a vencer, contas não pagas)
$_s = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM `{$p}transacoes` WHERE tipo='despesa' AND status != 'cancelado' AND MONTH(data)=? AND YEAR(data)=?$_rWhere");
$_s->execute(array_merge([$mes, $ano], $_rParams));
$despesasMes = (float) $_s->fetchColumn();

// Taxa de poupança
$taxaPoupanca = $receitasMes > 0
    ? round((($receitasMes - $despesasMes) / $receitasMes) * 100, 1)
    : 0;

// Saldo do mês anterior (para variação)
$mesAnt = $mes === 1 ? 12 : $mes - 1;
$anoAnt = $mes === 1 ? $ano - 1 : $ano;
$_s = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM `{$p}transacoes` WHERE tipo='receita' AND status='pago' AND MONTH(data)=? AND YEAR(data)=?$_rWhere");
$_s->execute(array_merge([$mesAnt, $anoAnt], $_rParams));
$receitasAnt = (float) $_s->fetchColumn();

$_s = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM `{$p}transacoes` WHERE tipo='despesa' AND status='pago' AND MONTH(data)=? AND YEAR(data)=?$_rWhere");
$_s->execute(array_merge([$mesAnt, $anoAnt], $_rParams));
$despesasAnt = (float) $_s->fetchColumn();

$varReceita  = $receitasAnt  > 0 ? round((($receitasMes  - $receitasAnt)  / $receitasAnt)  * 100, 1) : 0;
$varDespesa  = $despesasAnt  > 0 ? round((($despesasMes  - $despesasAnt)  / $despesasAnt)  * 100, 1) : 0;

// Projeção do próximo mês via recorrências ativas
$mesProx  = $mes === 12 ? 1  : $mes + 1;
$anoProx  = $mes === 12 ? $ano + 1 : $ano;
$recsAtivas = $pdo->query("SELECT * FROM `{$p}recorrencias` WHERE ativo=1")->fetchAll();
$projRec = 0.0; $projDes = 0.0;
foreach ($recsAtivas as $r) {
    if (!Calculos::recorrenciaDeveGerarNoMes($r, $mesProx, $anoProx)) continue;
    if ($r['tipo'] === 'receita') $projRec += (float)$r['valor'];
    else                          $projDes += (float)$r['valor'];
}
// Saldo real do próximo mês (lançamentos já gerados)
$_s = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM `{$p}transacoes` WHERE tipo='receita' AND MONTH(data)=? AND YEAR(data)=?$_rWhere");
$_s->execute(array_merge([$mesProx, $anoProx], $_rParams));
$recProxReal = (float) $_s->fetchColumn();

$_s = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM `{$p}transacoes` WHERE tipo='despesa' AND MONTH(data)=? AND YEAR(data)=?$_rWhere");
$_s->execute(array_merge([$mesProx, $anoProx], $_rParams));
$desProxReal = (float) $_s->fetchColumn();
// Usa real+projeção para o próximo mês
$projRecFinal = max($recProxReal, $projRec);
$projDesFinal = max($desProxReal, $projDes);

// Patrimônio total (contas + investimentos)
$patrimonioInvest = (float) $pdo->query(
    "SELECT COALESCE(SUM(valor_atual),0) FROM `{$p}investimentos` WHERE ativo=1"
)->fetchColumn();
$patrimonioTotal  = $saldoTotal + $patrimonioInvest;

// Comprometimento da renda
$comprometimento = $receitasMes > 0
    ? round(($despesasMes / $receitasMes) * 100, 1)
    : 0;

// Fluxo de caixa 6 meses (para gráfico de linha) — 1 única query agregada
// (mesma ideia de relatorios.php, mas preservando a regra deste dashboard:
// no mês corrente as despesas somam também as pendentes, não só as pagas).
$fluxoIni = new DateTime('first day of -5 month');
$fluxoFim = new DateTime('last day of this month');

$fStmt = $pdo->prepare(
    "SELECT YEAR(data) ano, MONTH(data) mes,
            COALESCE(SUM(CASE WHEN tipo='receita' AND status='pago' THEN valor END),0) receitas,
            COALESCE(SUM(CASE
                          WHEN tipo='despesa' AND YEAR(data)=? AND MONTH(data)=? AND status != 'cancelado' THEN valor
                          WHEN tipo='despesa' AND NOT (YEAR(data)=? AND MONTH(data)=?) AND status='pago' THEN valor
                        END),0) despesas
     FROM `{$p}transacoes`
     WHERE data >= ? AND data <= ?$_rWhere
     GROUP BY YEAR(data), MONTH(data)"
);
$fStmt->execute(array_merge(
    [$ano, $mes, $ano, $mes, $fluxoIni->format('Y-m-d'), $fluxoFim->format('Y-m-d')],
    $_rParams
));
$fluxoIdx = [];
foreach ($fStmt->fetchAll() as $r) {
    $fluxoIdx["{$r['ano']}-{$r['mes']}"] = $r;
}

$fluxo = [];
for ($i = 5; $i >= 0; $i--) {
    $dt    = new DateTime("first day of -{$i} month");
    $mRef  = (int) $dt->format('m');
    $yRef  = (int) $dt->format('Y');
    $chave = "{$yRef}-{$mRef}";

    $fluxo[] = [
        'label' => $dt->format('M/y'),
        'rec'   => (float)($fluxoIdx[$chave]['receitas'] ?? 0),
        'des'   => (float)($fluxoIdx[$chave]['despesas'] ?? 0),
    ];
}

// Gastos por categoria — subcategorias agrupadas sob o pai (top 6)
$stmtCat = $pdo->prepare(
    "SELECT COALESCE(cp.nome, c.nome) AS nome,
            COALESCE(cp.cor,  c.cor)  AS cor,
            COALESCE(SUM(t.valor), 0) AS total
     FROM `{$p}categorias` c
     LEFT JOIN `{$p}categorias` cp ON cp.id = c.categoria_pai
     JOIN `{$p}transacoes` t ON t.categoria_id = c.id
     WHERE t.tipo='despesa' AND t.status != 'cancelado'
       AND MONTH(t.data)=? AND YEAR(t.data)=?
       AND c.tipo IN ('despesa','ambos')
       {$_rWhereT}
     GROUP BY COALESCE(cp.id, c.id), COALESCE(cp.nome, c.nome), COALESCE(cp.cor, c.cor)
     ORDER BY total DESC LIMIT 6"
);
$stmtCat->execute(array_merge([$mes, $ano], $_rParams));
$catGastos = $stmtCat->fetchAll();

// Últimas 8 transações
$stmtTx = $pdo->prepare(
    "SELECT t.*, c.nome AS cat_nome, c.cor AS cat_cor, c.icone AS cat_icone, ct.nome AS conta_nome
     FROM `{$p}transacoes` t
     LEFT JOIN `{$p}categorias` c  ON c.id = t.categoria_id
     LEFT JOIN `{$p}contas`     ct ON ct.id = t.conta_id
     ORDER BY t.data DESC, t.id DESC LIMIT 8"
);
$stmtTx->execute();
$transacoes = $stmtTx->fetchAll();

// Cartões (resumo)
$cartoes = $pdo->query(
    "SELECT * FROM `{$p}cartoes` WHERE ativo=1 ORDER BY limite_total DESC LIMIT 4"
)->fetchAll();

// Metas ativas
$metas = $pdo->query(
    "SELECT * FROM `{$p}metas` WHERE status='ativa' ORDER BY prioridade, data_prazo LIMIT 5"
)->fetchAll();

// Alertas ativos
$alertas = $pdo->query(
    "SELECT * FROM `{$p}alertas` WHERE lido=0 ORDER BY nivel DESC, criado_em DESC LIMIT 5"
)->fetchAll();
$totalAlertas = count($alertas);

// brl()/pct() vêm de backend/helpers/formatacao.php (incluído por index.php)
function varIcon(float $v): string { return $v >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; }
function varCls (float $v): string { return $v >= 0 ? 'up' : 'down'; }
?>

<!-- ── Saudação + visão mensal ───────────────────────────────── -->
<div class="page-header">
    <div>
        <div class="page-title">Olá! Aqui está seu resumo financeiro.</div>
        <div class="page-sub"><?= date('l, d \d\e F \d\e Y') ?> &nbsp;·&nbsp; Dados em tempo real</div>
    </div>
    <div class="d-flex gap-1">
        <a href="?p=receitas" class="btn btn-ghost btn-sm"><i class="fa-solid fa-plus"></i> Receita</a>
        <a href="?p=despesas" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Despesa</a>
    </div>
</div>

<?php if (!empty($_respDash) || !empty($_terceirosDash)): ?>
<style>
.resp-dropdown { position: relative; }
.resp-toggle {
    display: flex; align-items: center; gap: .5rem;
    cursor: pointer; min-width: 160px; text-align: left;
    background: var(--bg-700); color: var(--text-200);
    font-size: .82rem;
}
.resp-toggle:hover { border-color: var(--indigo); }
.resp-panel {
    display: none;
    position: absolute;
    top: calc(100% + 4px); left: 0;
    min-width: 210px;
    background: var(--bg-800);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    z-index: 500;
    padding: .35rem 0;
    max-height: 260px;
    overflow-y: auto;
}
.resp-panel.aberto { display: block; animation: fadeIn .12s ease; }
.resp-item {
    display: flex; align-items: center; gap: .6rem;
    padding: .45rem .875rem; cursor: pointer;
    font-size: .82rem; color: var(--text-200);
    transition: background .1s; user-select: none;
}
.resp-item:hover { background: var(--bg-700); }
.resp-item input[type=checkbox] {
    accent-color: var(--indigo); width: 14px; height: 14px; flex-shrink: 0; cursor: pointer;
}
.resp-avatar-sm {
    width: 20px; height: 20px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .6rem; flex-shrink: 0;
}
.resp-sep { border: none; border-top: 1px solid var(--border); margin: .25rem 0; }
</style>

<div style="background:var(--bg-800);border:1px solid var(--border);border-radius:var(--radius-lg);
            padding:.75rem 1.25rem;margin-bottom:1.25rem;display:flex;align-items:flex-end;gap:.75rem;flex-wrap:wrap">
    <div style="display:flex;flex-direction:column;gap:.35rem">
        <span style="font-size:.7rem;font-weight:600;color:var(--text-600);text-transform:uppercase;letter-spacing:.05em">
            Pessoa
        </span>
        <div class="resp-dropdown" id="respDropdown">
            <button type="button" id="respToggleBtn" class="form-control resp-toggle"
                    onclick="_toggleRespDropdown(event)">
                <span id="respLabel"><?= $_hasRespD ? '' : 'Todos' ?></span>
                <i class="fa-solid fa-chevron-down fa-xs" style="margin-left:auto;opacity:.5"></i>
            </button>
            <div class="resp-panel" id="respPanel">
                <label class="resp-item">
                    <input type="checkbox" class="resp-cb" id="cbRespTodos" value="todos"
                           <?= !$_hasRespD ? 'checked' : '' ?> onchange="_onRespCbTodos(this)">
                    <i class="fa-solid fa-users fa-xs" style="color:var(--text-400)"></i>
                    <span>Todos</span>
                </label>
                <hr class="resp-sep">
                <label class="resp-item">
                    <input type="checkbox" class="resp-cb" value="-1"
                           <?= $_respNullD ? 'checked' : '' ?> onchange="_onRespCb(this)">
                    <div class="resp-avatar-sm" style="background:#64748b22;color:#64748b">
                        <i class="fa-solid fa-user-slash fa-xs"></i>
                    </div>
                    <span>Sem responsável</span>
                </label>
                <?php foreach ($_respDash as $r):
                    $cor = htmlspecialchars($r['cor']);
                    $sel = in_array((int)$r['id'], $_respIdsD) ? 'checked' : '';
                ?>
                <label class="resp-item">
                    <input type="checkbox" class="resp-cb" value="<?= $r['id'] ?>"
                           <?= $sel ?> onchange="_onRespCb(this)">
                    <div class="resp-avatar-sm" style="background:<?= $cor ?>22;color:<?= $cor ?>">
                        <i class="fa-solid fa-<?= htmlspecialchars($r['icone']) ?> fa-xs"></i>
                    </div>
                    <span><?= htmlspecialchars($r['nome']) ?></span>
                </label>
                <?php endforeach ?>
                <?php if (!empty($_terceirosDash)): ?>
                <hr class="resp-sep">
                <div style="padding:.3rem .875rem;font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--amber)">Terceiros</div>
                <?php foreach ($_terceirosDash as $r):
                    $cor  = htmlspecialchars($r['cor']);
                    $selT = in_array((int)$r['id'], $_terceiroIdsD) ? 'checked' : '';
                ?>
                <label class="resp-item">
                    <input type="checkbox" class="resp-cb" value="tcr_<?= $r['id'] ?>"
                           <?= $selT ?> onchange="_onRespCb(this)">
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
    <?php if ($_hasRespD): ?>
    <a href="?p=dashboard" class="btn btn-ghost btn-sm" style="align-self:flex-end">
        <i class="fa-solid fa-xmark fa-xs"></i> Limpar
    </a>
    <?php endif ?>
</div>

<script>
let _respSelecionados = new Set(<?= json_encode($_allRespValsD) ?>);
let _respIniciais     = '<?= htmlspecialchars($_respRawD, ENT_QUOTES) ?>';

function _toggleRespDropdown(e) {
    if (e) e.stopPropagation();
    const panel = document.getElementById('respPanel');
    if (!panel) return;
    const aberto = panel.classList.toggle('aberto');
    if (aberto) {
        setTimeout(() => document.addEventListener('click', _fecharRespFora, { once: true }), 0);
    }
}

function _fecharRespFora(e) {
    const dd = document.getElementById('respDropdown');
    if (dd && dd.contains(e.target)) {
        setTimeout(() => document.addEventListener('click', _fecharRespFora, { once: true }), 0);
        return;
    }
    document.getElementById('respPanel')?.classList.remove('aberto');
    const atual = _getRespIds();
    if (atual !== _respIniciais) _aplicarFiltro();
}

function _onRespCb(cb) {
    if (cb.checked) _respSelecionados.add(cb.value);
    else            _respSelecionados.delete(cb.value);
    const todoCb = document.getElementById('cbRespTodos');
    if (todoCb) todoCb.checked = false;
    _atualizarRespLabel();
}

function _onRespCbTodos(cb) {
    if (cb.checked) {
        _respSelecionados.clear();
        document.querySelectorAll('.resp-cb:not(#cbRespTodos)').forEach(c => c.checked = false);
    }
    _atualizarRespLabel();
}

function _atualizarRespLabel() {
    const el = document.getElementById('respLabel');
    if (!el) return;
    const n = _respSelecionados.size;
    if (n === 0) { el.textContent = 'Todos'; return; }
    if (n === 1) {
        const val  = [..._respSelecionados][0];
        const span = document.querySelector(`.resp-cb[value="${val}"]`)
                         ?.closest('.resp-item')?.querySelector('span');
        el.textContent = span ? span.textContent.trim() : `1 selecionado`;
    } else {
        el.textContent = `${n} selecionados`;
    }
}

function _getRespIds() {
    return [..._respSelecionados].join(',');
}

function _aplicarFiltro() {
    const ids = _getRespIds();
    const url = new URL(window.location.href);
    if (ids) url.searchParams.set('resp', ids);
    else     url.searchParams.delete('resp');
    window.location.href = url.href;
}

// Inicializa label com base nos valores vindos do PHP
_atualizarRespLabel();
</script>
<?php endif ?>

<!-- ── Visão Mensal Comparativa ──────────────────────────────── -->
<?php
$nomesMesesDash = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',
                   5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',
                   9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
$saldoAnt  = $receitasAnt  - $despesasAnt;
$saldoMes  = $receitasMes  - $despesasMes;
$saldoProx = $projRecFinal - $projDesFinal;
?>

<div id="dashWidgets">

<div data-widget-id="widget-1">
<div class="dash-grid-3" style="margin-bottom:1.25rem">

    <!-- Mês anterior -->
    <div class="card" style="border-top:3px solid var(--text-600)">
        <div class="card-body" style="padding:1rem 1.1rem">
            <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-600);margin-bottom:.55rem">
                ◀ <?= $nomesMesesDash[$mesAnt] ?> / <?= $anoAnt ?>
            </div>
            <div style="display:flex;flex-direction:column;gap:.4rem">
                <div style="display:flex;justify-content:space-between;font-size:.82rem">
                    <span style="color:var(--text-600)"><i class="fa-solid fa-arrow-up fa-xs" style="color:var(--emerald)"></i> Receitas</span>
                    <span class="fw-600 text-emerald"><?= brl($receitasAnt) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:.82rem">
                    <span style="color:var(--text-600)"><i class="fa-solid fa-arrow-down fa-xs" style="color:var(--rose)"></i> Despesas</span>
                    <span class="fw-600 text-rose"><?= brl($despesasAnt) ?></span>
                </div>
                <div style="border-top:1px solid var(--border);padding-top:.4rem;display:flex;justify-content:space-between;font-size:.85rem">
                    <span style="color:var(--text-600)">Saldo</span>
                    <span class="fw-700 <?= $saldoAnt >= 0 ? 'text-emerald' : 'text-rose' ?>">
                        <?= ($saldoAnt >= 0 ? '+' : '') . brl($saldoAnt) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Mês atual -->
    <div class="card" style="border-top:3px solid var(--indigo);box-shadow:0 0 0 1px var(--indigo) inset">
        <div class="card-body" style="padding:1rem 1.1rem">
            <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--indigo);margin-bottom:.55rem">
                ● <?= $nomesMesesDash[$mes] ?> / <?= $ano ?> — Atual
            </div>
            <div style="display:flex;flex-direction:column;gap:.4rem">
                <div style="display:flex;justify-content:space-between;font-size:.82rem">
                    <span style="color:var(--text-600)"><i class="fa-solid fa-arrow-up fa-xs" style="color:var(--emerald)"></i> Receitas</span>
                    <span class="fw-600 text-emerald"><?= brl($receitasMes) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:.82rem">
                    <span style="color:var(--text-600)"><i class="fa-solid fa-arrow-down fa-xs" style="color:var(--rose)"></i> Despesas</span>
                    <span class="fw-600 text-rose"><?= brl($despesasMes) ?></span>
                </div>
                <div style="border-top:1px solid var(--border);padding-top:.4rem;display:flex;justify-content:space-between;font-size:.85rem">
                    <span style="color:var(--text-600)">Saldo</span>
                    <span class="fw-700 <?= $saldoMes >= 0 ? 'text-emerald' : 'text-rose' ?>">
                        <?= ($saldoMes >= 0 ? '+' : '') . brl($saldoMes) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Próximo mês -->
    <div class="card" style="border-top:3px solid var(--amber)">
        <div class="card-body" style="padding:1rem 1.1rem">
            <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--amber);margin-bottom:.55rem">
                <?= $nomesMesesDash[$mesProx] ?> / <?= $anoProx ?> ▶ — Projeção
            </div>
            <div style="display:flex;flex-direction:column;gap:.4rem">
                <div style="display:flex;justify-content:space-between;font-size:.82rem">
                    <span style="color:var(--text-600)"><i class="fa-solid fa-arrow-up fa-xs" style="color:var(--emerald)"></i> Receitas</span>
                    <span class="fw-600 text-emerald"><?= brl($projRecFinal) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:.82rem">
                    <span style="color:var(--text-600)"><i class="fa-solid fa-arrow-down fa-xs" style="color:var(--rose)"></i> Despesas</span>
                    <span class="fw-600 text-rose"><?= brl($projDesFinal) ?></span>
                </div>
                <div style="border-top:1px solid var(--border);padding-top:.4rem;display:flex;justify-content:space-between;font-size:.85rem">
                    <span style="color:var(--text-600)">Saldo</span>
                    <span class="fw-700 <?= $saldoProx >= 0 ? 'text-emerald' : 'text-rose' ?>">
                        <?= ($saldoProx >= 0 ? '+' : '') . brl($saldoProx) ?>
                    </span>
                </div>
            </div>
            <?php if ($projRec == 0 && $projDes == 0): ?>
            <div style="margin-top:.5rem;font-size:.7rem;color:var(--text-600)">
                <i class="fa-solid fa-circle-info fa-xs"></i> Sem contas fixas para este mês
            </div>
            <?php endif ?>
        </div>
    </div>

</div>
</div><!-- /widget-1 -->

<!-- ── KPIs do Mês Atual ──────────────────────────────────────── -->
<div data-widget-id="widget-2">
<div class="kpi-grid">
    <div class="kpi-card indigo">
        <div class="kpi-header">
            <div class="kpi-label">Saldo Total (Contas)</div>
            <div class="kpi-icon indigo"><i class="fa-solid fa-wallet"></i></div>
        </div>
        <div class="kpi-value"><?= brl($saldoTotal) ?></div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Todas as contas ativas</div>
    </div>

    <div class="kpi-card emerald">
        <div class="kpi-header">
            <div class="kpi-label">Receitas do Mês</div>
            <div class="kpi-icon emerald"><i class="fa-solid fa-arrow-trend-up"></i></div>
        </div>
        <div class="kpi-value"><?= brl($receitasMes) ?></div>
        <div class="kpi-trend <?= varCls($varReceita) ?>">
            <i class="fa-solid <?= varIcon($varReceita) ?> fa-xs"></i>
            <?= pct(abs($varReceita)) ?> vs mês anterior
        </div>
    </div>

    <div class="kpi-card rose">
        <div class="kpi-header">
            <div class="kpi-label">Despesas do Mês</div>
            <div class="kpi-icon rose"><i class="fa-solid fa-arrow-trend-down"></i></div>
        </div>
        <div class="kpi-value"><?= brl($despesasMes) ?></div>
        <div class="kpi-trend <?= $varDespesa <= 0 ? 'up' : 'down' ?>">
            <i class="fa-solid <?= varIcon(-$varDespesa) ?> fa-xs"></i>
            <?= pct(abs($varDespesa)) ?> vs mês anterior
        </div>
    </div>

    <div class="kpi-card <?= $taxaPoupanca >= 20 ? 'emerald' : ($taxaPoupanca >= 10 ? 'amber' : 'rose') ?>">
        <div class="kpi-header">
            <div class="kpi-label">Taxa de Poupança</div>
            <div class="kpi-icon <?= $taxaPoupanca >= 20 ? 'emerald' : ($taxaPoupanca >= 10 ? 'amber' : 'rose') ?>">
                <i class="fa-solid fa-piggy-bank"></i>
            </div>
        </div>
        <div class="kpi-value"><?= pct($taxaPoupanca) ?></div>
        <div class="kpi-trend neutral">
            <i class="fa-solid fa-circle-info fa-xs"></i>
            <?php if ($taxaPoupanca >= 20): ?>Meta atingida — ótimo!
            <?php elseif ($taxaPoupanca >= 10): ?>Razoável — meta: 20%
            <?php else: ?>Abaixo do ideal — meta: 20%<?php endif ?>
        </div>
    </div>
</div>
</div><!-- /widget-2 -->

<!-- ── KPIs secundários ───────────────────────────────────────── -->
<div data-widget-id="widget-3">
<div class="kpi-grid" style="margin-top:.75rem">
    <div class="kpi-card violet">
        <div class="kpi-header">
            <div class="kpi-label">Patrimônio Total</div>
            <div class="kpi-icon violet"><i class="fa-solid fa-building-columns"></i></div>
        </div>
        <div class="kpi-value"><?= brl($patrimonioTotal) ?></div>
        <div class="kpi-trend neutral">
            <i class="fa-solid fa-circle-info fa-xs"></i>
            Contas <?= brl($saldoTotal) ?> + Invest. <?= brl($patrimonioInvest) ?>
        </div>
    </div>

    <div class="kpi-card <?= $comprometimento >= 80 ? 'rose' : ($comprometimento >= 60 ? 'amber' : 'emerald') ?>">
        <div class="kpi-header">
            <div class="kpi-label">Comprometimento</div>
            <div class="kpi-icon <?= $comprometimento >= 80 ? 'rose' : ($comprometimento >= 60 ? 'amber' : 'emerald') ?>">
                <i class="fa-solid fa-gauge-high"></i>
            </div>
        </div>
        <div class="kpi-value"><?= pct($comprometimento) ?></div>
        <div class="kpi-trend neutral">
            <i class="fa-solid fa-circle-info fa-xs"></i>
            <?php if ($comprometimento >= 80): ?>Renda muito comprometida
            <?php elseif ($comprometimento >= 60): ?>Atenção — compromet. alto
            <?php else: ?>Renda bem controlada<?php endif ?>
        </div>
    </div>

    <?php $saldoMesKpi = $receitasMes - $despesasMes; ?>
    <div class="kpi-card <?= $saldoMesKpi >= 0 ? 'emerald' : 'rose' ?>">
        <div class="kpi-header">
            <div class="kpi-label">Saldo do Mês</div>
            <div class="kpi-icon <?= $saldoMesKpi >= 0 ? 'emerald' : 'rose' ?>">
                <i class="fa-solid fa-scale-balanced"></i>
            </div>
        </div>
        <div class="kpi-value"><?= ($saldoMesKpi >= 0 ? '+' : '') . brl($saldoMesKpi) ?></div>
        <div class="kpi-trend neutral">
            <i class="fa-solid fa-circle-info fa-xs"></i>
            Receitas − Despesas do mês
        </div>
    </div>

    <div class="kpi-card <?= $totalAlertas > 0 ? ($totalAlertas >= 5 ? 'rose' : 'amber') : 'emerald' ?>">
        <div class="kpi-header">
            <div class="kpi-label">Alertas Ativos</div>
            <div class="kpi-icon <?= $totalAlertas > 0 ? ($totalAlertas >= 5 ? 'rose' : 'amber') : 'emerald' ?>">
                <i class="fa-solid fa-bell"></i>
            </div>
        </div>
        <div class="kpi-value"><?= $totalAlertas ?></div>
        <div class="kpi-trend neutral">
            <i class="fa-solid fa-circle-info fa-xs"></i>
            <a href="?p=alertas" style="color:inherit;text-decoration:underline">Ver alertas pendentes</a>
        </div>
    </div>
</div>
</div><!-- /widget-3 -->

<!-- ── Gráficos ──────────────────────────────────────────────── -->
<div data-widget-id="widget-4">
<div class="dash-grid">
    <!-- Fluxo de Caixa -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Fluxo de Caixa</div>
                <div class="card-subtitle">Receitas vs Despesas — últimos 6 meses</div>
            </div>
            <div class="d-flex gap-1 text-xs">
                <span style="color:var(--emerald)"><i class="fa-solid fa-circle fa-xs"></i> Receitas</span>
                <span style="color:var(--rose)"   ><i class="fa-solid fa-circle fa-xs"></i> Despesas</span>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-wrap" style="height:220px">
                <canvas id="chartFluxo"></canvas>
            </div>
        </div>
    </div>

    <!-- Gastos por Categoria -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Gastos por Categoria</div>
                <div class="card-subtitle"><?= date('F Y') ?></div>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-wrap" style="height:180px">
                <canvas id="chartCat"></canvas>
            </div>
            <?php if (!empty($catGastos)): ?>
            <div style="margin-top:.75rem; display:flex; flex-direction:column; gap:.35rem">
                <?php foreach ($catGastos as $cg): ?>
                <div class="d-flex align-center justify-between text-xs">
                    <div class="d-flex align-center gap-1">
                        <span style="width:8px;height:8px;border-radius:50%;background:<?= htmlspecialchars($cg['cor']) ?>;flex-shrink:0;display:inline-block"></span>
                        <span><?= htmlspecialchars($cg['nome']) ?></span>
                    </div>
                    <span class="fw-600"><?= brl((float)$cg['total']) ?></span>
                </div>
                <?php endforeach ?>
            </div>
            <?php else: ?>
                <p class="text-muted text-sm" style="text-align:center;margin-top:1rem">Sem despesas cadastradas neste mês</p>
            <?php endif ?>
        </div>
    </div>
</div>
</div><!-- /widget-4 -->

<!-- ── Linha 3: Cartões + Metas + Alertas ────────────────────── -->
<div data-widget-id="widget-5">
<div class="dash-grid-3">

    <!-- Cartões -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-credit-card text-indigo fa-sm"></i> Cartões</div>
            <a href="?p=cartoes" class="btn btn-ghost btn-sm">Ver todos</a>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:.75rem">
            <?php if (empty($cartoes)): ?>
                <p class="text-muted text-sm" style="text-align:center;padding:.5rem 0">Nenhum cartão cadastrado</p>
                <a href="?p=cartoes" class="btn btn-outline btn-sm w-full" style="justify-content:center">
                    <i class="fa-solid fa-plus"></i> Adicionar cartão
                </a>
            <?php else: ?>
                <?php foreach ($cartoes as $cc):
                    $uso = $cc['limite_total'] > 0 ? round(($cc['limite_usado'] / $cc['limite_total']) * 100) : 0;
                    $cor = $uso >= 70 ? 'rose' : ($uso >= 40 ? 'amber' : 'emerald');
                ?>
                <div class="cc-card" style="border-left: 3px solid <?= htmlspecialchars($cc['cor']) ?>">
                    <div class="cc-top">
                        <div>
                            <div class="cc-nome"><?= htmlspecialchars($cc['nome']) ?></div>
                            <div class="cc-digitos">
                                <?= $cc['ultimos_digitos'] ? '•••• ' . htmlspecialchars($cc['ultimos_digitos']) : '' ?>
                                &nbsp;·&nbsp; Vence dia <?= $cc['dia_vencimento'] ?>
                            </div>
                        </div>
                        <span class="cc-bandeira">
                            <?php if ($cc['bandeira'] === 'visa'): ?>💳
                            <?php elseif ($cc['bandeira'] === 'mastercard'): ?>🔴
                            <?php else: ?>💳<?php endif ?>
                        </span>
                    </div>
                    <div class="progress-wrap">
                        <div class="progress-header">
                            <span class="progress-label">Usado: <?= brl((float)$cc['limite_usado']) ?></span>
                            <span class="progress-pct <?= $uso >= 70 ? 'text-rose' : ($uso >= 40 ? 'text-amber' : 'text-emerald') ?>"><?= $uso ?>%</span>
                        </div>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill <?= $cor ?>" style="width:<?= min($uso, 100) ?>%"></div>
                        </div>
                        <div class="d-flex justify-between text-xs text-muted">
                            <span>Disponível: <?= brl((float)$cc['limite_total'] - (float)$cc['limite_usado']) ?></span>
                            <span>Limite: <?= brl((float)$cc['limite_total']) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach ?>
            <?php endif ?>
        </div>
    </div>

    <!-- Metas -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-bullseye text-emerald fa-sm"></i> Metas Financeiras</div>
            <a href="?p=metas" class="btn btn-ghost btn-sm">Ver todas</a>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:.75rem">
            <?php if (empty($metas)): ?>
                <p class="text-muted text-sm" style="text-align:center;padding:.5rem 0">Nenhuma meta cadastrada</p>
                <a href="?p=metas" class="btn btn-outline btn-sm w-full" style="justify-content:center">
                    <i class="fa-solid fa-plus"></i> Criar meta
                </a>
            <?php else: ?>
                <?php foreach ($metas as $meta):
                    $pctMeta = $meta['valor_alvo'] > 0 ? min(round(($meta['valor_atual'] / $meta['valor_alvo']) * 100), 100) : 0;
                    $diasFaltam = $meta['data_prazo'] ? (int)((new DateTime($meta['data_prazo']))->diff(new DateTime()))->format('%r%a') : null;
                ?>
                <div class="meta-item">
                    <div class="meta-top">
                        <div class="meta-icon" style="background:<?= htmlspecialchars($meta['cor']) ?>22;color:<?= htmlspecialchars($meta['cor']) ?>">
                            <i class="fa-solid fa-<?= htmlspecialchars($meta['icone']) ?>"></i>
                        </div>
                        <div style="flex:1">
                            <div class="meta-nome"><?= htmlspecialchars($meta['nome']) ?></div>
                            <div class="meta-vals">
                                <?= brl((float)$meta['valor_atual']) ?> de <?= brl((float)$meta['valor_alvo']) ?>
                                <?php if ($diasFaltam !== null): ?>
                                    &nbsp;·&nbsp; <?= $diasFaltam >= 0 ? $diasFaltam . ' dias' : '<span class="text-rose">Vencida</span>' ?>
                                <?php endif ?>
                            </div>
                        </div>
                        <span class="fw-600 text-sm" style="color:<?= htmlspecialchars($meta['cor']) ?>"><?= $pctMeta ?>%</span>
                    </div>
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" style="width:<?= $pctMeta ?>%;background:<?= htmlspecialchars($meta['cor']) ?>"></div>
                    </div>
                </div>
                <?php endforeach ?>
            <?php endif ?>
        </div>
    </div>

    <!-- Alertas -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-bell text-amber fa-sm"></i> Alertas
                <?php if (!empty($alertas)): ?>
                    <span class="badge" style="background:var(--rose-soft);color:var(--rose);margin-left:.5rem"><?= count($alertas) ?></span>
                <?php endif ?>
            </div>
        </div>
        <div class="card-body" style="padding:.75rem">
            <?php if (empty($alertas)): ?>
                <div style="text-align:center;padding:1rem;color:var(--text-600)">
                    <i class="fa-solid fa-circle-check fa-2x" style="color:var(--emerald);margin-bottom:.5rem"></i>
                    <div class="text-sm">Nenhum alerta ativo</div>
                </div>
            <?php else: ?>
                <?php foreach ($alertas as $al): ?>
                <div class="alert-item <?= htmlspecialchars($al['nivel']) ?>">
                    <div class="alert-icon">
                        <?php if ($al['nivel'] === 'urgente'): ?><i class="fa-solid fa-triangle-exclamation" style="color:var(--rose)"></i>
                        <?php elseif ($al['nivel'] === 'aviso'):  ?><i class="fa-solid fa-circle-exclamation" style="color:var(--amber)"></i>
                        <?php else: ?>                             <i class="fa-solid fa-circle-info"        style="color:var(--cyan)"></i>
                        <?php endif ?>
                    </div>
                    <div>
                        <div class="alert-title"><?= htmlspecialchars($al['titulo']) ?></div>
                        <div class="alert-msg"><?= htmlspecialchars($al['mensagem']) ?></div>
                    </div>
                </div>
                <?php endforeach ?>
            <?php endif ?>
        </div>
    </div>

</div>
</div><!-- /widget-5 -->

<!-- ── Últimas Transações ─────────────────────────────────────── -->
<div data-widget-id="widget-6">
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">Últimas Transações</div>
            <div class="card-subtitle">Movimentações recentes em todas as contas</div>
        </div>
        <div class="d-flex gap-1">
            <a href="?p=receitas" class="btn btn-ghost btn-sm"><i class="fa-solid fa-plus"></i> Receita</a>
            <a href="?p=despesas" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Despesa</a>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (empty($transacoes)): ?>
            <div style="text-align:center;padding:2.5rem;color:var(--text-600)">
                <i class="fa-solid fa-receipt fa-2x" style="margin-bottom:.75rem"></i>
                <div>Nenhuma transação cadastrada ainda.</div>
                <div class="text-sm" style="margin-top:.25rem">Adicione receitas ou despesas para começar.</div>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Descrição</th>
                    <th>Categoria</th>
                    <th>Conta</th>
                    <th>Data</th>
                    <th>Status</th>
                    <th class="text-right">Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transacoes as $tx): ?>
                <tr>
                    <td>
                        <div class="d-flex align-center gap-1">
                            <div class="tx-icon" style="background:<?= $tx['tipo']==='receita' ? 'var(--emerald-soft)' : 'var(--rose-soft)' ?>;color:<?= $tx['tipo']==='receita' ? 'var(--emerald)' : 'var(--rose)' ?>">
                                <i class="fa-solid <?= $tx['tipo']==='receita' ? 'fa-arrow-up' : 'fa-arrow-down' ?> fa-xs"></i>
                            </div>
                            <div>
                                <div class="fw-600 text-sm truncate" style="max-width:200px"><?= htmlspecialchars($tx['descricao']) ?></div>
                                <?php if ($tx['parcela_total']): ?>
                                    <div class="text-xs text-muted"><?= $tx['parcela_atual'] ?>/<?= $tx['parcela_total'] ?> parcelas</div>
                                <?php endif ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($tx['cat_nome']): ?>
                            <span class="badge" style="background:<?= htmlspecialchars($tx['cat_cor'] ?? '#334155') ?>22;color:<?= htmlspecialchars($tx['cat_cor'] ?? '#94a3b8') ?>">
                                <?= htmlspecialchars($tx['cat_nome']) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted text-xs">—</span>
                        <?php endif ?>
                    </td>
                    <td class="text-sm text-muted"><?= $tx['conta_nome'] ? htmlspecialchars($tx['conta_nome']) : '—' ?></td>
                    <td class="text-sm text-muted"><?= date('d/m/Y', strtotime($tx['data'])) ?></td>
                    <td><span class="badge <?= $tx['status'] ?>"><?= ucfirst($tx['status']) ?></span></td>
                    <td class="text-right fw-600 <?= $tx['tipo']==='receita' ? 'text-emerald' : 'text-rose' ?>">
                        <?= $tx['tipo']==='receita' ? '+' : '-' ?><?= brl((float)$tx['valor']) ?>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
        <?php endif ?>
    </div>
    <?php if (!empty($transacoes)): ?>
    <div class="card-footer d-flex justify-between align-center">
        <span>Exibindo as <?= count($transacoes) ?> transações mais recentes</span>
        <a href="?p=despesas" class="btn btn-ghost btn-sm">Ver todas <i class="fa-solid fa-arrow-right fa-xs"></i></a>
    </div>
    <?php endif ?>
</div>
</div><!-- /widget-6 -->

<!-- Projeção de Fluxo de Caixa -->
<div data-widget-id="widget-7">
<div class="card" style="margin-top:1.25rem" id="cardProjecao">
    <div class="card-header">
        <div>
            <div class="card-title">Projeção de Fluxo de Caixa</div>
            <div class="card-subtitle">Estimativa dos próximos 6 meses baseada em recorrências e pendentes</div>
        </div>
        <div id="projecaoSaldo" style="font-size:.85rem;font-weight:700;color:var(--indigo)"></div>
    </div>
    <div class="card-body">
        <div style="position:relative;height:220px">
            <canvas id="chartProjecao"></canvas>
        </div>
    </div>
</div>
</div><!-- /widget-7 -->

</div><!-- /dashWidgets -->

<!-- ── Scripts dos gráficos ──────────────────────────────────── -->
<script>
const fluxoData = <?= json_encode($fluxo) ?>;
const catData   = <?= json_encode($catGastos) ?>;

document.addEventListener('DOMContentLoaded', () => {
    const defaults = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } };
    const gridColor = 'rgba(255,255,255,.06)';
    const fontColor = '#64748b';

    // Gráfico Fluxo de Caixa
    const ctxF = document.getElementById('chartFluxo');
    if (ctxF) {
        new Chart(ctxF, {
            type: 'bar',
            data: {
                labels: fluxoData.map(d => d.label),
                datasets: [
                    {
                        label: 'Receitas',
                        data: fluxoData.map(d => d.rec),
                        backgroundColor: 'rgba(16,185,129,.75)',
                        borderRadius: 6,
                        borderSkipped: false,
                    },
                    {
                        label: 'Despesas',
                        data: fluxoData.map(d => d.des),
                        backgroundColor: 'rgba(244,63,94,.75)',
                        borderRadius: 6,
                        borderSkipped: false,
                    }
                ]
            },
            options: {
                ...defaults,
                plugins: {
                    legend: { display: true, labels: { color: fontColor, boxWidth: 10, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' R$ ' + ctx.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits:2})
                        }
                    }
                },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: fontColor, font: { size: 11 } } },
                    y: {
                        grid: { color: gridColor },
                        ticks: { color: fontColor, font: { size: 11 },
                            callback: v => 'R$ ' + (v/1000).toFixed(0) + 'k'
                        }
                    }
                }
            }
        });
    }

    // Gráfico Categorias
    const ctxC = document.getElementById('chartCat');
    if (ctxC && catData.length > 0) {
        new Chart(ctxC, {
            type: 'doughnut',
            data: {
                labels: catData.map(d => d.nome),
                datasets: [{
                    data: catData.map(d => parseFloat(d.total)),
                    backgroundColor: catData.map(d => d.cor || '#6366f1'),
                    borderWidth: 0,
                    hoverOffset: 4,
                }]
            },
            options: {
                ...defaults,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' R$ ' + ctx.parsed.toLocaleString('pt-BR', {minimumFractionDigits:2})
                        }
                    }
                }
            }
        });
    }
});
</script>

<script>
(async function () {
    try {
        const res  = await fetch('backend/api/projecao.php');
        const json = await res.json();
        if (!json.success) return;

        const el = document.getElementById('projecaoSaldo');
        if (el) {
            const ultimo = json.meses[json.meses.length - 1];
            const delta  = ultimo.saldo - json.saldo_atual;
            el.textContent = 'Saldo em 6 meses: R$ ' + ultimo.saldo.toLocaleString('pt-BR',{minimumFractionDigits:2});
            el.style.color = delta >= 0 ? 'var(--emerald)' : 'var(--rose)';
        }

        const ctx = document.getElementById('chartProjecao');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: json.meses.map(m => m.label),
                datasets: [
                    {
                        label: 'Saldo Projetado',
                        data: json.meses.map(m => m.saldo),
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,.08)',
                        fill: true,
                        tension: .4,
                        pointBackgroundColor: '#6366f1',
                        pointRadius: 5,
                    },
                    {
                        label: 'Entradas',
                        data: json.meses.map(m => m.entradas),
                        borderColor: '#10b981',
                        borderDash: [4,3],
                        tension: .3,
                        pointRadius: 3,
                        fill: false,
                    },
                    {
                        label: 'Saídas',
                        data: json.meses.map(m => m.saidas),
                        borderColor: '#ef4444',
                        borderDash: [4,3],
                        tension: .3,
                        pointRadius: 3,
                        fill: false,
                    },
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { labels: { color:'#94a3b8', boxWidth:12, font:{size:11} } },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.dataset.label}: R$ ${ctx.parsed.y.toLocaleString('pt-BR',{minimumFractionDigits:2})}`
                        }
                    }
                },
                scales: {
                    x: { grid:{color:'rgba(255,255,255,.04)'}, ticks:{color:'#64748b'} },
                    y: {
                        grid: {color:'rgba(255,255,255,.04)'},
                        ticks: {
                            color: '#64748b',
                            callback: v => 'R$' + (Math.abs(v) >= 1000
                                ? (v/1000).toFixed(1)+'k'
                                : v.toFixed(0))
                        }
                    }
                }
            }
        });
    } catch {}
})();
</script>

<script>
// ── Wizard de onboarding ───────────────────────────────────────
(function () {
    const _ob = {
        temContas: <?= $_qtdContas > 0 ? 'true' : 'false' ?>,
        temCats:   <?= $_qtdCats   > 0 ? 'true' : 'false' ?>,
        temResps:  <?= $_qtdResps  > 0 ? 'true' : 'false' ?>,
    };

    if (localStorage.getItem('ob_done')) return;

    const PASSOS = [
        {
            icone: 'fa-wand-magic-sparkles', cor: 'var(--indigo)',
            titulo: 'Bem-vindo ao Controle Financeiro!',
            desc: 'Configure seu sistema em 3 passos rápidos e comece a ter controle total das suas finanças.',
            btn: 'Começar', navegar: null, pular: false,
        },
        {
            icone: 'fa-building-columns', cor: 'var(--emerald)',
            titulo: 'Adicione sua conta bancária',
            desc: 'Cadastre sua conta corrente, poupança ou carteira para acompanhar saldo em tempo real.',
            btn: _ob.temContas ? 'Já tenho conta ✓' : 'Ir para Contas',
            navegar: _ob.temContas ? null : '?p=contas',
            concluido: _ob.temContas, pular: true,
        },
        {
            icone: 'fa-tags', cor: 'var(--amber)',
            titulo: 'Organize por categorias',
            desc: 'Categorias agrupam seus lançamentos e revelam onde você mais gasta. Crie as que fazem sentido para você.',
            btn: _ob.temCats ? 'Já tenho categorias ✓' : 'Ir para Categorias',
            navegar: _ob.temCats ? null : '?p=configuracoes',
            concluido: _ob.temCats, pular: true,
        },
        {
            icone: 'fa-user-group', cor: 'var(--violet)',
            titulo: 'Adicione um responsável',
            desc: 'Responsáveis permitem rastrear gastos por pessoa: você, cônjuge, filhos ou qualquer membro da família.',
            btn: _ob.temResps ? 'Já tenho responsável ✓' : 'Ir para Responsáveis',
            navegar: _ob.temResps ? null : '?p=responsaveis',
            concluido: _ob.temResps, pular: true,
        },
        {
            icone: 'fa-circle-check', cor: 'var(--emerald)',
            titulo: 'Tudo pronto!',
            desc: 'Seu sistema está configurado. Registre seu primeiro lançamento e comece a entender para onde vai seu dinheiro.',
            btn: 'Registrar primeira despesa',
            navegar: '?p=despesas', pular: false,
        },
    ];

    let _passo = 0;

    function fechar() {
        localStorage.setItem('ob_done', '1');
        document.getElementById('_obModal')?.remove();
    }

    function render() {
        const p   = PASSOS[_passo];
        const tot = PASSOS.length;
        const dots = PASSOS.map((_, i) => {
            const ativo = i === _passo;
            const feito = i < _passo;
            return `<span style="width:${ativo ? '20px' : '8px'};height:8px;border-radius:100px;
                         transition:all .3s ease;
                         background:${feito ? 'var(--emerald)' : ativo ? 'var(--indigo)' : 'var(--bg-600)'}"></span>`;
        }).join('');

        const concBadge = p.concluido
            ? `<span style="display:inline-flex;align-items:center;gap:.3rem;font-size:.75rem;
                            background:rgba(16,185,129,.15);color:var(--emerald);
                            padding:.2rem .6rem;border-radius:100px;margin-bottom:.75rem">
                   <i class="fa-solid fa-check fa-xs"></i> Já configurado
               </span>`
            : '';

        document.getElementById('_obContent').innerHTML = `
            <div style="text-align:center;padding:2rem 1.75rem 1.5rem">
                <div style="width:76px;height:76px;border-radius:50%;
                            background:${p.cor}1a;color:${p.cor};
                            display:flex;align-items:center;justify-content:center;
                            margin:0 auto 1.1rem;font-size:1.75rem">
                    <i class="fa-solid ${p.icone}"></i>
                </div>
                ${concBadge}
                <div style="font-size:1.1rem;font-weight:700;color:var(--text-100);margin-bottom:.5rem">${p.titulo}</div>
                <p style="font-size:.875rem;color:var(--text-400);line-height:1.65;margin-bottom:1.75rem">${p.desc}</p>
                <div style="display:flex;gap:.5rem;justify-content:center;margin-bottom:1.75rem">${dots}</div>
                <div style="display:flex;gap:.6rem;justify-content:center;flex-wrap:wrap">
                    ${p.pular ? `<button id="_obPular" class="btn btn-ghost btn-sm">Pular</button>` : ''}
                    <button id="_obAcao" class="btn btn-primary btn-sm" style="${p.concluido ? 'background:var(--emerald);border-color:var(--emerald)' : ''}">
                        ${p.btn}${p.navegar && !p.concluido ? ' <i class="fa-solid fa-arrow-right fa-xs"></i>' : ''}
                    </button>
                </div>
                <div style="font-size:.75rem;color:var(--text-600);margin-top:1rem">
                    Passo ${_passo + 1} de ${tot}
                </div>
            </div>`;

        document.getElementById('_obAcao').addEventListener('click', () => {
            if (p.navegar && !p.concluido) {
                localStorage.setItem('ob_done', '1');
                window.location.href = p.navegar;
            } else {
                _passo++;
                if (_passo >= tot) fechar();
                else render();
            }
        });

        const pularBtn = document.getElementById('_obPular');
        if (pularBtn) pularBtn.addEventListener('click', () => {
            _passo++;
            if (_passo >= tot) fechar();
            else render();
        });
    }

    function iniciar() {
        const overlay = document.createElement('div');
        overlay.id = '_obModal';
        overlay.style.cssText =
            'display:flex;position:fixed;inset:0;background:rgba(0,0,0,.72);' +
            'z-index:9200;align-items:center;justify-content:center;padding:1rem;' +
            'animation:fadeIn .25s ease';
        overlay.innerHTML = `
            <div style="background:var(--bg-800);border:1px solid var(--border);
                        border-radius:var(--radius-lg);width:100%;max-width:460px;
                        box-shadow:var(--shadow-lg);animation:modalIn .22s ease;position:relative">
                <button id="_obFechar" style="position:absolute;top:.65rem;right:.65rem;
                        background:none;border:none;color:var(--text-600);cursor:pointer;
                        font-size:1rem;padding:.3rem .45rem;border-radius:.4rem;
                        transition:background .15s" onmouseenter="this.style.background='var(--bg-700)'"
                        onmouseleave="this.style.background='none'">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <div id="_obContent"></div>
            </div>`;
        document.body.appendChild(overlay);
        document.getElementById('_obFechar').addEventListener('click', fechar);
        overlay.addEventListener('click', e => { if (e.target === overlay) fechar(); });
        render();
    }

    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(iniciar, 700);
    });
})();
</script>

<script>
// Dashboard personalizável
(function () {
    const STORAGE_KEY = 'financeOS_dashOrder';
    const container   = document.getElementById('dashWidgets');
    if (!container || typeof Sortable === 'undefined') return;

    // Restaura ordem salva
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
        try {
            const order = JSON.parse(saved);
            const children = Array.from(container.children);
            order.forEach(wid => {
                const el = children.find(c => c.dataset.widgetId === wid);
                if (el) container.appendChild(el);
            });
        } catch {}
    }

    // Inicializa drag-and-drop
    Sortable.create(container, {
        animation: 180,
        handle: '.card-header',
        ghostClass: 'skel',
        chosenClass: 'sortable-chosen',
        onEnd: () => {
            const order = Array.from(container.children)
                .map(c => c.dataset.widgetId)
                .filter(Boolean);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(order));
        }
    });

    // Estilo para drag handle visual
    const s = document.createElement('style');
    s.textContent = `
        #dashWidgets [data-widget-id] .card-header { cursor: grab; }
        #dashWidgets [data-widget-id] .card-header:active { cursor: grabbing; }
        .sortable-chosen { opacity: .85; box-shadow: 0 8px 32px rgba(99,102,241,.25); }
    `;
    document.head.appendChild(s);
})();
</script>
