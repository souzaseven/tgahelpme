<?php
// ── Dados iniciais ────────────────────────────────────────────
$p   = TABLE_PREFIX;
$mes = (int) date('m');
$ano = (int) date('Y');

$mesProx = $mes === 12 ? 1  : $mes + 1;
$anoProx = $mes === 12 ? $ano + 1 : $ano;

$filtroTipo   = in_array($_GET['tipo']   ?? '', ['receita','despesa']) ? $_GET['tipo'] : '';
$filtroStatus = in_array($_GET['status'] ?? '', ['ativo','inativo','todos']) ? $_GET['status'] : 'ativo';

$where  = ['1=1'];
$params = [$mes, $ano, $mesProx, $anoProx];   // dois subselects: mês atual + próximo
if ($filtroTipo)                    { $where[] = 'r.tipo = ?';  $params[] = $filtroTipo; }
if ($filtroStatus === 'ativo')       { $where[] = 'r.ativo = 1'; }
elseif ($filtroStatus === 'inativo') { $where[] = 'r.ativo = 0'; }
$wSql = implode(' AND ', $where);

$stmt = $pdo->prepare(
    "SELECT r.*,
            c.nome cat_nome, c.cor cat_cor,
            ct.nome conta_nome,
            cc.nome cartao_nome,
            (SELECT COUNT(*) FROM `{$p}transacoes` t
             WHERE t.recorrencia_id = r.id
               AND MONTH(t.data) = ? AND YEAR(t.data) = ?) gerado_mes,
            (SELECT COUNT(*) FROM `{$p}transacoes` t
             WHERE t.recorrencia_id = r.id
               AND MONTH(t.data) = ? AND YEAR(t.data) = ?) gerado_prox
     FROM `{$p}recorrencias` r
     LEFT JOIN `{$p}categorias` c  ON c.id = r.categoria_id
     LEFT JOIN `{$p}contas`     ct ON ct.id = r.conta_id
     LEFT JOIN `{$p}cartoes`    cc ON cc.id = r.cartao_id
     WHERE $wSql
     ORDER BY r.tipo DESC, r.dia_vencimento, r.descricao"
);
$stmt->execute($params);
$recorrencias = $stmt->fetchAll();

// KPIs — apenas ativas, todas as frequências convertidas para equivalente mensal
$kpiStmt = $pdo->query(
    "SELECT tipo, valor, frequencia FROM `{$p}recorrencias` WHERE ativo=1"
);
$kpiRows = $kpiStmt->fetchAll();

function mensalEquiv(string $freq, float $valor): float {
    return match($freq) {
        'diaria'     => $valor * 30,
        'semanal'    => $valor * 4.33,
        'quinzenal'  => $valor * 2,
        'mensal'     => $valor,
        'bimestral'  => $valor / 2,
        'trimestral' => $valor / 3,
        'semestral'  => $valor / 6,
        'anual'      => $valor / 12,
        default      => $valor,
    };
}

$totalFixoDes = 0;
$totalFixoRec = 0;
foreach ($kpiRows as $k) {
    $eq = mensalEquiv($k['frequencia'], (float)$k['valor']);
    if ($k['tipo'] === 'despesa') $totalFixoDes += $eq;
    else                          $totalFixoRec += $eq;
}
$qtdAtivas = count($kpiRows);

$categoriasAll = $pdo->query(
    "SELECT MIN(id) id, nome, tipo FROM `{$p}categorias`
     WHERE ativo=1 GROUP BY nome, tipo ORDER BY tipo, nome"
)->fetchAll();
$contasAll  = $pdo->query("SELECT id, nome FROM `{$p}contas` WHERE ativo=1 ORDER BY nome")->fetchAll();
$cartoesAll = $pdo->query("SELECT id, nome FROM `{$p}cartoes` WHERE ativo=1 ORDER BY nome")->fetchAll();

$nomesMeses = [
    1=>'Janeiro', 2=>'Fevereiro', 3=>'Março',    4=>'Abril',
    5=>'Maio',    6=>'Junho',     7=>'Julho',     8=>'Agosto',
    9=>'Setembro',10=>'Outubro', 11=>'Novembro', 12=>'Dezembro'
];

$labFreq = [
    'diaria'     => 'Diária',
    'semanal'    => 'Semanal',
    'quinzenal'  => 'Quinzenal',
    'mensal'     => 'Mensal',
    'bimestral'  => 'Bimestral',
    'trimestral' => 'Trimestral',
    'semestral'  => 'Semestral',
    'anual'      => 'Anual',
];

// brl() vem de backend/helpers/formatacao.php (incluído por index.php)
?>

<style>
/* Modal base agora vive em assets/css/main.css (inclui .modal-box.sm). */
/* ── Filtros ──────────────────────────────────────────────── */
/* .filters-bar/.filter-group/.filter-label vêm de assets/css/main.css */
/* ── Badges de frequência ─────────────────────────────────── */
.freq-badge { display:inline-flex; align-items:center; padding:.15rem .5rem; border-radius:999px; font-size:.68rem; font-weight:600; background:var(--indigo-soft); color:var(--indigo); }
.freq-badge.anual      { background:var(--violet-soft); color:var(--violet); }
.freq-badge.semestral  { background:var(--cyan-soft); color:var(--cyan); }
.freq-badge.trimestral { background:var(--amber-soft); color:var(--amber); }
/* ── Status gerado ────────────────────────────────────────── */
.status-gerado { display:inline-flex; align-items:center; gap:.3rem; font-size:.75rem; font-weight:600; }
.status-gerado.sim  { color:var(--emerald); }
.status-gerado.nao  { color:var(--text-600); }
/* ── Linha inativa ────────────────────────────────────────── */
tr.inativa td { opacity:.45; }
</style>

<!-- ── Cabeçalho ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <div class="page-title">Contas Fixas</div>
        <div class="page-sub">Receitas e despesas que se repetem regularmente</div>
    </div>
    <div class="d-flex gap-1">
        <button class="btn btn-ghost btn-sm" onclick="gerarTodosRapido(<?= $mes ?>, <?= $ano ?>)">
            <i class="fa-solid fa-rotate"></i> Gerar <?= $nomesMeses[$mes] ?>
        </button>
        <button class="btn btn-ghost btn-sm" style="color:var(--amber)" onclick="gerarTodosRapido(<?= $mesProx ?>, <?= $anoProx ?>)">
            <i class="fa-solid fa-rotate fa-xs"></i> Gerar <?= $nomesMeses[$mesProx] ?>
        </button>
        <button class="btn btn-primary btn-sm" onclick="abrirModal()">
            <i class="fa-solid fa-plus"></i> Nova Conta Fixa
        </button>
    </div>
</div>

<!-- ── KPIs ──────────────────────────────────────────────── -->
<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.25rem">
    <div class="kpi-card rose">
        <div class="kpi-header">
            <div class="kpi-label">Despesas Fixas / mês</div>
            <div class="kpi-icon rose"><i class="fa-solid fa-arrow-trend-down"></i></div>
        </div>
        <div class="kpi-value"><?= brl($totalFixoDes) ?></div>
        <div class="kpi-trend neutral">
            <i class="fa-solid fa-circle-info fa-xs"></i> Equivalente mensal
        </div>
    </div>
    <div class="kpi-card emerald">
        <div class="kpi-header">
            <div class="kpi-label">Receitas Fixas / mês</div>
            <div class="kpi-icon emerald"><i class="fa-solid fa-arrow-trend-up"></i></div>
        </div>
        <div class="kpi-value"><?= brl($totalFixoRec) ?></div>
        <div class="kpi-trend neutral">
            <i class="fa-solid fa-circle-info fa-xs"></i> Equivalente mensal
        </div>
    </div>
    <div class="kpi-card <?= ($totalFixoRec - $totalFixoDes) >= 0 ? 'indigo' : 'amber' ?>">
        <div class="kpi-header">
            <div class="kpi-label">Saldo Fixo / mês</div>
            <div class="kpi-icon <?= ($totalFixoRec - $totalFixoDes) >= 0 ? 'indigo' : 'amber' ?>">
                <i class="fa-solid fa-scale-balanced"></i>
            </div>
        </div>
        <div class="kpi-value"><?= brl(abs($totalFixoRec - $totalFixoDes)) ?></div>
        <div class="kpi-trend <?= ($totalFixoRec - $totalFixoDes) >= 0 ? 'up' : 'down' ?>">
            <i class="fa-solid fa-<?= ($totalFixoRec - $totalFixoDes) >= 0 ? 'arrow-up' : 'arrow-down' ?> fa-xs"></i>
            <?= ($totalFixoRec - $totalFixoDes) >= 0 ? 'Saldo positivo' : 'Comprometimento maior que receitas' ?>
            &nbsp;·&nbsp; <?= $qtdAtivas ?> ativa<?= $qtdAtivas !== 1 ? 's' : '' ?>
        </div>
    </div>
</div>

<!-- ── Filtros ────────────────────────────────────────────── -->
<form method="get" action="">
    <input type="hidden" name="p" value="recorrencias">
    <div class="filters-bar">
        <div class="filter-group">
            <span class="filter-label">Tipo</span>
            <select name="tipo" class="form-control" style="min-width:130px" onchange="this.form.submit()">
                <option value=""       <?= $filtroTipo===''        ? 'selected':'' ?>>Todos</option>
                <option value="despesa"<?= $filtroTipo==='despesa'  ? 'selected':'' ?>>Despesas</option>
                <option value="receita"<?= $filtroTipo==='receita'  ? 'selected':'' ?>>Receitas</option>
            </select>
        </div>
        <div class="filter-group">
            <span class="filter-label">Status</span>
            <select name="status" class="form-control" style="min-width:130px" onchange="this.form.submit()">
                <option value="ativo"  <?= $filtroStatus==='ativo'   ? 'selected':'' ?>>Ativas</option>
                <option value="inativo"<?= $filtroStatus==='inativo' ? 'selected':'' ?>>Inativas</option>
                <option value="todos"  <?= $filtroStatus==='todos'   ? 'selected':'' ?>>Todas</option>
            </select>
        </div>
    </div>
</form>

<!-- ── Tabela ─────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">Recorrências Cadastradas</div>
            <div class="card-subtitle"><?= count($recorrencias) ?> registro<?= count($recorrencias)!==1?'s':'' ?></div>
        </div>
        <div class="text-xs text-muted d-flex align-center gap-1">
            <i class="fa-solid fa-circle-check" style="color:var(--emerald)"></i> = lançamento gerado em
            <strong><?= $nomesMeses[$mes] ?>/<?= $ano ?></strong>
        </div>
    </div>

    <?php if (empty($recorrencias)): ?>
    <div class="card-body" style="text-align:center;padding:3rem;color:var(--text-600)">
        <i class="fa-solid fa-rotate fa-3x" style="margin-bottom:1rem;color:var(--indigo)"></i>
        <div class="fw-700" style="margin-bottom:.5rem">Nenhuma conta fixa cadastrada</div>
        <div class="text-sm" style="margin-bottom:1.25rem">
            Cadastre aqui suas despesas e receitas que se repetem todo mês:<br>
            aluguel, salário, Netflix, internet, academia, etc.
        </div>
        <button class="btn btn-primary" onclick="abrirModal()">
            <i class="fa-solid fa-plus"></i> Cadastrar primeira conta fixa
        </button>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Descrição</th>
                    <th>Frequência</th>
                    <th>Dia Venc.</th>
                    <th>Categoria / Conta</th>
                    <th style="text-align:center"><?= $nomesMeses[$mes] ?></th>
                    <th style="text-align:center;color:var(--amber)"><?= $nomesMeses[$mesProx] ?></th>
                    <th class="text-right">Valor</th>
                    <th style="width:100px"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recorrencias as $r):
                $gerado     = (int)$r['gerado_mes']  > 0;
                $geradoProx = (int)$r['gerado_prox'] > 0;
            ?>
            <tr class="<?= !$r['ativo'] ? 'inativa' : '' ?>" data-id="<?= $r['id'] ?>">
                <td>
                    <div class="d-flex align-center gap-1">
                        <div class="tx-icon"
                             style="background:<?= $r['tipo']==='receita' ? 'var(--emerald-soft)' : 'var(--rose-soft)' ?>;
                                    color:<?= $r['tipo']==='receita' ? 'var(--emerald)' : 'var(--rose)' ?>">
                            <i class="fa-solid fa-<?= $r['tipo']==='receita' ? 'arrow-up' : 'arrow-down' ?> fa-xs"></i>
                        </div>
                        <div>
                            <div class="fw-600 text-sm"><?= htmlspecialchars($r['descricao']) ?></div>
                            <?php if ($r['data_fim']): ?>
                            <div class="text-xs text-muted">até <?= date('m/Y', strtotime($r['data_fim'])) ?></div>
                            <?php endif ?>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="freq-badge <?= htmlspecialchars($r['frequencia']) ?>">
                        <?= $labFreq[$r['frequencia']] ?? $r['frequencia'] ?>
                    </span>
                </td>
                <td class="text-sm text-muted">Dia <?= $r['dia_vencimento'] ?></td>
                <td>
                    <?php if ($r['cat_nome']): ?>
                    <span class="badge" style="background:<?= htmlspecialchars($r['cat_cor']??'#334155') ?>22;color:<?= htmlspecialchars($r['cat_cor']??'#94a3b8') ?>">
                        <?= htmlspecialchars($r['cat_nome']) ?>
                    </span>
                    <?php endif ?>
                    <?php if ($r['cartao_nome']): ?>
                    <div class="text-xs text-muted"><i class="fa-solid fa-credit-card fa-xs"></i> <?= htmlspecialchars($r['cartao_nome']) ?></div>
                    <?php elseif ($r['conta_nome']): ?>
                    <div class="text-xs text-muted"><?= htmlspecialchars($r['conta_nome']) ?></div>
                    <?php endif ?>
                </td>
                <td style="text-align:center">
                    <?php if ($gerado): ?>
                        <span class="status-gerado sim"><i class="fa-solid fa-circle-check"></i> Gerado</span>
                    <?php elseif ($r['ativo']): ?>
                        <button class="btn btn-ghost btn-sm" onclick="gerarUm(<?= $r['id'] ?>)"
                                style="font-size:.72rem;padding:.25rem .6rem">
                            <i class="fa-solid fa-rotate"></i> Gerar
                        </button>
                    <?php else: ?>
                        <span class="status-gerado nao">—</span>
                    <?php endif ?>
                </td>
                <td style="text-align:center">
                    <?php if ($geradoProx): ?>
                        <span class="status-gerado sim"><i class="fa-solid fa-circle-check"></i> Gerado</span>
                    <?php elseif ($r['ativo']): ?>
                        <button class="btn btn-ghost btn-sm" onclick="gerarUmProx(<?= $r['id'] ?>)"
                                style="font-size:.72rem;padding:.25rem .6rem;color:var(--amber)">
                            <i class="fa-solid fa-rotate"></i> Gerar
                        </button>
                    <?php else: ?>
                        <span class="status-gerado nao">—</span>
                    <?php endif ?>
                </td>
                <td class="text-right fw-600 <?= $r['tipo']==='receita' ? 'text-emerald' : 'text-rose' ?>">
                    <?= $r['tipo']==='receita' ? '+' : '-' ?><?= brl((float)$r['valor']) ?>
                </td>
                <td>
                    <div class="d-flex gap-1 row-actions" style="justify-content:flex-end">
                        <button class="btn-icon" onclick="abrirEditar(<?= $r['id'] ?>)" title="Editar"
                                style="width:28px;height:28px;font-size:.75rem">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button class="btn-icon" onclick="toggleAtivo(<?= $r['id'] ?>)"
                                title="<?= $r['ativo'] ? 'Desativar' : 'Ativar' ?>"
                                style="width:28px;height:28px;font-size:.75rem;color:<?= $r['ativo'] ? 'var(--amber)' : 'var(--emerald)' ?>">
                            <i class="fa-solid fa-<?= $r['ativo'] ? 'pause' : 'play' ?>"></i>
                        </button>
                        <button class="btn-icon" onclick="excluir(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['descricao'])) ?>')"
                                title="Excluir"
                                style="width:28px;height:28px;font-size:.75rem;color:var(--rose)">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?php endif ?>
</div>

<!-- ── Modal: Nova / Editar Conta Fixa ───────────────────── -->
<div id="modalForm" class="modal-overlay" onclick="if(event.target===this)fecharModal('modalForm')">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title" id="modalTitulo">Nova Conta Fixa</div>
            <button type="button" class="btn-icon" onclick="fecharModal('modalForm')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formRec" onsubmit="salvar(event)">
            <div class="modal-body">
                <input type="hidden" id="rId" value="">

                <!-- Tipo: toggle visual -->
                <div style="display:flex;gap:.5rem;margin-bottom:1.1rem">
                    <button type="button" id="btnTipoDes"
                            onclick="setTipo('despesa')"
                            class="btn btn-sm" style="flex:1;justify-content:center;transition:var(--ease)">
                        <i class="fa-solid fa-arrow-down"></i> Despesa
                    </button>
                    <button type="button" id="btnTipoRec"
                            onclick="setTipo('receita')"
                            class="btn btn-sm" style="flex:1;justify-content:center;transition:var(--ease)">
                        <i class="fa-solid fa-arrow-up"></i> Receita
                    </button>
                </div>
                <input type="hidden" id="rTipo" value="despesa">

                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Descrição <span style="color:var(--rose)">*</span></label>
                    <input type="text" id="rDesc" class="form-control"
                           placeholder="Ex: Aluguel, Netflix, Salário..." required maxlength="255">
                </div>

                <div class="form-grid form-grid-3" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Valor (R$) <span style="color:var(--rose)">*</span></label>
                        <input type="text" id="rValor" class="form-control"
                               placeholder="0,00" inputmode="numeric" data-currency required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Frequência</label>
                        <select id="rFreq" class="form-control">
                            <option value="mensal">Mensal</option>
                            <option value="semanal">Semanal</option>
                            <option value="quinzenal">Quinzenal</option>
                            <option value="bimestral">Bimestral</option>
                            <option value="trimestral">Trimestral</option>
                            <option value="semestral">Semestral</option>
                            <option value="anual">Anual</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dia vencimento</label>
                        <input type="number" id="rDia" class="form-control"
                               value="1" min="1" max="31">
                    </div>
                </div>

                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Início <span style="color:var(--rose)">*</span></label>
                        <input type="date" id="rIni" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fim (opcional)</label>
                        <input type="date" id="rFim" class="form-control">
                    </div>
                </div>

                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Categoria</label>
                        <select id="rCat" class="form-control">
                            <option value="">— Sem categoria —</option>
                            <?php
                            $tipoAtual = '';
                            foreach ($categoriasAll as $cat):
                                if ($cat['tipo'] !== $tipoAtual):
                                    if ($tipoAtual !== '') echo '</optgroup>';
                                    $tipoAtual = $cat['tipo'];
                                    $labGrp = $tipoAtual === 'receita' ? 'Receitas' : ($tipoAtual === 'despesa' ? 'Despesas' : 'Ambos');
                                    echo "<optgroup label=\"$labGrp\">";
                                endif;
                            ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option>
                            <?php endforeach ?>
                            <?php if ($tipoAtual !== '') echo '</optgroup>'; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Conta</label>
                        <select id="rConta" class="form-control">
                            <option value="">— Sem conta —</option>
                            <?php foreach ($contasAll as $ct): ?>
                            <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['nome']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="cartaoWrap">
                    <label class="form-label">Cartão (se for despesa no cartão)</label>
                    <select id="rCartao" class="form-control">
                        <option value="">— Sem cartão —</option>
                        <?php foreach ($cartoesAll as $cc): ?>
                        <option value="<?= $cc['id'] ?>"><?= htmlspecialchars($cc['nome']) ?></option>
                        <?php endforeach ?>
                    </select>
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

<!-- ── Modal: Gerar Lançamentos ──────────────────────────── -->
<div id="modalGerar" class="modal-overlay" onclick="if(event.target===this)fecharModal('modalGerar')">
    <div class="modal-box sm">
        <div class="modal-header">
            <div class="modal-title">Gerar Lançamentos</div>
            <button type="button" class="btn-icon" onclick="fecharModal('modalGerar')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <p class="text-sm text-muted" style="margin-bottom:1.1rem">
                Gera automaticamente as transações de todas as contas fixas ativas para o mês selecionado.
                Já existentes serão ignoradas.
            </p>
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label class="form-label">Mês</label>
                    <select id="gMes" class="form-control">
                        <?php foreach ($nomesMeses as $n => $nome): ?>
                        <option value="<?= $n ?>" <?= $n === $mes ? 'selected':'' ?>><?= $nome ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Ano</label>
                    <select id="gAno" class="form-control">
                        <?php for ($y = $ano - 1; $y <= $ano + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= $y === $ano ? 'selected':'' ?>><?= $y ?></option>
                        <?php endfor ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="fecharModal('modalGerar')">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btnGerarTudo" onclick="gerarTodos()">
                <i class="fa-solid fa-rotate"></i> Gerar Todos
            </button>
        </div>
    </div>
</div>

<!-- ── Dados para edição inline ──────────────────────────── -->
<script>
const _RECS = <?= json_encode(array_values($recorrencias), JSON_UNESCAPED_UNICODE) ?>;

// ── Tipo toggle ───────────────────────────────────────────
function setTipo(tipo) {
    document.getElementById('rTipo').value = tipo;
    const btnD = document.getElementById('btnTipoDes');
    const btnR = document.getElementById('btnTipoRec');
    if (tipo === 'despesa') {
        btnD.className = 'btn btn-danger btn-sm';
        btnR.className = 'btn btn-ghost btn-sm';
        document.getElementById('cartaoWrap').style.display = '';
    } else {
        btnR.className = 'btn btn-success btn-sm';
        btnD.className = 'btn btn-ghost btn-sm';
        document.getElementById('cartaoWrap').style.display = 'none';
    }
    // Filtra categorias pelo tipo... já estão em optgroup, OK
}

// ── Abrir modal novo ──────────────────────────────────────
function abrirModal() {
    document.getElementById('formRec').reset();
    document.getElementById('rId').value  = '';
    document.getElementById('rIni').value = new Date().toISOString().split('T')[0];
    document.getElementById('modalTitulo').textContent = 'Nova Conta Fixa';
    setTipo('despesa');
    document.getElementById('modalForm').classList.add('open');
    setTimeout(() => document.getElementById('rDesc').focus(), 80);
}

// ── Abrir modal editar ────────────────────────────────────
function abrirEditar(id) {
    const r = _RECS.find(x => +x.id === +id);
    if (!r) return;

    document.getElementById('rId').value    = r.id;
    document.getElementById('rDesc').value  = r.descricao  || '';
    document.getElementById('rValor').value = r.valor ? brlMask(r.valor) : '';
    document.getElementById('rFreq').value  = r.frequencia || 'mensal';
    document.getElementById('rDia').value   = r.dia_vencimento || 1;
    document.getElementById('rIni').value   = (r.data_inicio||'').split('T')[0];
    document.getElementById('rFim').value   = (r.data_fim  ||'').split('T')[0];
    document.getElementById('rCat').value   = r.categoria_id || '';
    document.getElementById('rConta').value = r.conta_id     || '';
    document.getElementById('rCartao').value= r.cartao_id    || '';
    document.getElementById('modalTitulo').textContent = 'Editar Conta Fixa';
    setTipo(r.tipo || 'despesa');
    document.getElementById('modalForm').classList.add('open');
}

// ── Salvar ────────────────────────────────────────────────
async function salvar(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    const id = document.getElementById('rId').value;
    const payload = {
        acao:          'salvar',
        id:            id ? parseInt(id) : 0,
        descricao:     document.getElementById('rDesc').value.trim(),
        tipo:          document.getElementById('rTipo').value,
        valor:         parseCurrency(document.getElementById('rValor').value),
        frequencia:    document.getElementById('rFreq').value,
        dia_vencimento:parseInt(document.getElementById('rDia').value),
        data_inicio:   document.getElementById('rIni').value,
        data_fim:      document.getElementById('rFim').value || null,
        categoria_id:  document.getElementById('rCat').value    || null,
        conta_id:      document.getElementById('rConta').value  || null,
        cartao_id:     document.getElementById('rCartao').value || null,
    };

    try {
        const res  = await fetch('backend/api/recorrencias.php', {
            method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg || 'Salvo!', 'success');
        fecharModal('modalForm');
        location.reload();
    } catch (err) {
        toast('Erro: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar';
    }
}

// ── Gerar um (mês atual) ──────────────────────────────────
async function gerarUm(id) {
    try {
        const res  = await fetch('backend/api/recorrencias.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ acao:'gerar', id, mes:<?= $mes ?>, ano:<?= $ano ?> })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, json.gerados > 0 ? 'success' : 'info');
        if (json.gerados > 0) location.reload();
    } catch (err) {
        toast('Erro: ' + err.message, 'error');
    }
}

// ── Gerar um (próximo mês) ────────────────────────────────
async function gerarUmProx(id) {
    try {
        const res  = await fetch('backend/api/recorrencias.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ acao:'gerar', id, mes:<?= $mesProx ?>, ano:<?= $anoProx ?> })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, json.gerados > 0 ? 'success' : 'info');
        if (json.gerados > 0) location.reload();
    } catch (err) {
        toast('Erro: ' + err.message, 'error');
    }
}

// ── Gerar todos (mês específico, sem modal) ───────────────
async function gerarTodosRapido(mes, ano) {
    const btn = event.currentTarget;
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    try {
        const res  = await fetch('backend/api/recorrencias.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ acao:'gerar', mes, ano })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, json.gerados > 0 ? 'success' : 'info');
        if (json.gerados > 0) location.reload();
    } catch (err) {
        toast('Erro: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

// ── Gerar todos ───────────────────────────────────────────
function abrirModalGerar() {
    document.getElementById('modalGerar').classList.add('open');
}

async function gerarTodos() {
    const btn = document.getElementById('btnGerarTudo');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Gerando...';

    const mes = parseInt(document.getElementById('gMes').value);
    const ano = parseInt(document.getElementById('gAno').value);

    try {
        const res  = await fetch('backend/api/recorrencias.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ acao:'gerar', mes, ano })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, json.gerados > 0 ? 'success' : 'info');
        fecharModal('modalGerar');
        if (json.gerados > 0) location.reload();
    } catch (err) {
        toast('Erro: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-rotate"></i> Gerar Todos';
    }
}

// ── Toggle ativo ──────────────────────────────────────────
async function toggleAtivo(id) {
    try {
        const res  = await fetch('backend/api/recorrencias.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ acao:'toggle', id })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        location.reload();
    } catch (err) {
        toast('Erro: ' + err.message, 'error');
    }
}

// ── Excluir ───────────────────────────────────────────────
function excluir(id, desc) {
    confirmar('Excluir conta fixa', `Excluir "${desc}"? Os lançamentos já gerados serão mantidos.`, async () => {
        try {
            const res  = await fetch(`backend/api/recorrencias.php?id=${id}`, { method:'DELETE' });
            const json = await res.json();
            if (!json.success) throw new Error(json.erro);
            toast(json.msg, 'success');
            location.reload();
        } catch (err) {
            toast('Erro: ' + err.message, 'error');
        }
    });
}

function fecharModal(id) {
    document.getElementById(id).classList.remove('open');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        fecharModal('modalForm');
        fecharModal('modalGerar');
    }
});
</script>
