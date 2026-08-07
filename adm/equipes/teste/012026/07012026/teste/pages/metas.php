<?php
// ── Dados iniciais ────────────────────────────────────────────
$p = TABLE_PREFIX;

$filtroStatus = in_array($_GET['status'] ?? '', ['ativa','pausada','concluida','cancelada','todos'])
                ? $_GET['status'] : '';

$where = $filtroStatus && $filtroStatus !== 'todos'
         ? "WHERE status = '$filtroStatus'"
         : "WHERE status NOT IN ('cancelada')";

$metas = $pdo->query(
    "SELECT * FROM `{$p}metas` $where ORDER BY prioridade, data_prazo, nome"
)->fetchAll();

// KPIs (apenas ativas)
$totAlvo    = 0; $totAtual = 0; $qtdAtivas = 0; $qtdConc = 0;
foreach ($metas as $m) {
    if ($m['status'] === 'ativa')     { $totAlvo += (float)$m['valor_alvo']; $totAtual += (float)$m['valor_atual']; $qtdAtivas++; }
    if ($m['status'] === 'concluida') { $qtdConc++; }
}
$pctGeral = $totAlvo > 0 ? min(100, round(($totAtual / $totAlvo) * 100, 1)) : 0;

// brl() vem de backend/helpers/formatacao.php (incluído por index.php)

$tipoIcons = [
    'emergencia'   => 'shield-halved',
    'carro'        => 'car',
    'imovel'       => 'house',
    'viagem'       => 'plane',
    'aposentadoria'=> 'umbrella-beach',
    'educacao'     => 'graduation-cap',
    'quitar_divida'=> 'hand-holding-dollar',
    'outro'        => 'bullseye',
];
$tipoLabels = [
    'emergencia'   => 'Reserva de emergência',
    'carro'        => 'Veículo',
    'imovel'       => 'Imóvel',
    'viagem'       => 'Viagem',
    'aposentadoria'=> 'Aposentadoria',
    'educacao'     => 'Educação',
    'quitar_divida'=> 'Quitar dívida',
    'outro'        => 'Outro',
];
$priorLabels = [1 => 'Alta', 2 => 'Média', 3 => 'Baixa'];
$priorCls    = [1 => 'rose', 2 => 'amber', 3 => 'emerald'];
?>

<style>
/* ── Cards de meta ───────────────────────────────────────── */
.meta-card {
    background: var(--bg-800);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: .875rem;
    transition: var(--ease);
    position: relative;
    overflow: hidden;
}
.meta-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
.meta-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 3px;
}
.meta-card.concluida { opacity: .7; }
.meta-card.pausada   { opacity: .8; border-style: dashed; }
.meta-card-header { display:flex; align-items:flex-start; gap:.875rem; }
.meta-card-icon {
    width: 44px; height: 44px;
    border-radius: var(--radius);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; flex-shrink: 0;
}
.meta-card-nome  { font-weight:700; font-size:.95rem; }
.meta-card-tipo  { font-size:.72rem; color:var(--text-400); margin-top:.1rem; }
.meta-valores    { display:flex; justify-content:space-between; align-items:flex-end; }
.meta-atual      { font-size:1.2rem; font-weight:700; }
.meta-alvo       { font-size:.78rem; color:var(--text-400); }
.meta-pct        { font-size:1.4rem; font-weight:700; }
.meta-dias       { font-size:.72rem; color:var(--text-400); margin-top:.15rem; text-align:right; }
.meta-actions    { display:flex; gap:.5rem; padding-top:.25rem; border-top:1px solid var(--border); }
/* ── Modal ───────────────────────────────────────────────── */
/* Modal base agora vive em assets/css/main.css (inclui .modal-box.sm). */
</style>

<!-- ── Cabeçalho ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <div class="page-title">Metas Financeiras</div>
        <div class="page-sub">Acompanhe seus objetivos com aportes e progresso visual</div>
    </div>
    <button class="btn btn-primary btn-sm" onclick="abrirModalNova()">
        <i class="fa-solid fa-plus"></i> Nova Meta
    </button>
</div>

<!-- ── KPIs ──────────────────────────────────────────────── -->
<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.25rem">
    <div class="kpi-card emerald">
        <div class="kpi-header">
            <div class="kpi-label">Metas Ativas</div>
            <div class="kpi-icon emerald"><i class="fa-solid fa-bullseye"></i></div>
        </div>
        <div class="kpi-value"><?= $qtdAtivas ?></div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Em andamento</div>
    </div>
    <div class="kpi-card violet">
        <div class="kpi-header">
            <div class="kpi-label">Total a Atingir</div>
            <div class="kpi-icon violet"><i class="fa-solid fa-flag"></i></div>
        </div>
        <div class="kpi-value"><?= brl($totAlvo) ?></div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Soma de todas as metas ativas</div>
    </div>
    <div class="kpi-card indigo">
        <div class="kpi-header">
            <div class="kpi-label">Acumulado</div>
            <div class="kpi-icon indigo"><i class="fa-solid fa-piggy-bank"></i></div>
        </div>
        <div class="kpi-value"><?= brl($totAtual) ?></div>
        <div class="kpi-trend <?= $pctGeral >= 50 ? 'up' : 'neutral' ?>">
            <i class="fa-solid fa-circle-info fa-xs"></i> <?= $pctGeral ?>% do objetivo
        </div>
    </div>
    <div class="kpi-card <?= $qtdConc > 0 ? 'emerald' : 'amber' ?>">
        <div class="kpi-header">
            <div class="kpi-label">Concluídas</div>
            <div class="kpi-icon <?= $qtdConc > 0 ? 'emerald' : 'amber' ?>">
                <i class="fa-solid fa-trophy"></i>
            </div>
        </div>
        <div class="kpi-value"><?= $qtdConc ?></div>
        <div class="kpi-trend <?= $qtdConc > 0 ? 'up' : 'neutral' ?>">
            <i class="fa-solid fa-<?= $qtdConc > 0 ? 'star' : 'circle-info' ?> fa-xs"></i>
            <?= $qtdConc > 0 ? 'Objetivos alcançados!' : 'Continue assim!' ?>
        </div>
    </div>
</div>

<!-- ── Filtros rápidos ────────────────────────────────────── -->
<form method="get" action="" style="margin-bottom:1.25rem">
    <input type="hidden" name="p" value="metas">
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <?php foreach (['' => 'Ativas + Pausadas', 'concluida' => 'Concluídas', 'todos' => 'Todas'] as $v => $l): ?>
        <button type="submit" name="status" value="<?= $v ?>"
                class="btn btn-sm <?= $filtroStatus === $v ? 'btn-primary' : 'btn-ghost' ?>">
            <?= $l ?>
        </button>
        <?php endforeach ?>
    </div>
</form>

<!-- ── Grade de metas ─────────────────────────────────────── -->
<?php if (empty($metas)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:3rem;color:var(--text-600)">
        <i class="fa-solid fa-bullseye fa-3x" style="margin-bottom:1rem;color:var(--emerald)"></i>
        <div class="fw-700" style="margin-bottom:.5rem">Nenhuma meta cadastrada</div>
        <div class="text-sm" style="margin-bottom:1.25rem">
            Defina seus objetivos financeiros: reserva de emergência, viagem dos sonhos, imóvel próprio…
        </div>
        <button class="btn btn-primary" onclick="abrirModalNova()">
            <i class="fa-solid fa-plus"></i> Criar primeira meta
        </button>
    </div>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem">
    <?php foreach ($metas as $m):
        $pct    = (float)$m['valor_alvo'] > 0 ? min(100, round(((float)$m['valor_atual'] / (float)$m['valor_alvo']) * 100, 1)) : 0;
        $cor    = htmlspecialchars($m['cor']);
        $icone  = $tipoIcons[$m['tipo']] ?? 'bullseye';
        $pCls   = $priorCls[$m['prioridade']] ?? 'emerald';
        $falta  = (float)$m['valor_alvo'] - (float)$m['valor_atual'];
        $hoje   = new DateTime();

        $diasInfo = '';
        if ($m['data_prazo'] && $m['status'] === 'ativa') {
            $prazo = new DateTime($m['data_prazo']);
            $diff  = (int) $hoje->diff($prazo)->format('%r%a');
            if ($diff < 0)      $diasInfo = '<span style="color:var(--rose)">Prazo vencido há '.abs($diff).' dias</span>';
            elseif ($diff === 0) $diasInfo = '<span style="color:var(--amber)">Vence hoje!</span>';
            else                 $diasInfo = "Faltam $diff dias";
        }
    ?>
    <div class="meta-card <?= htmlspecialchars($m['status']) ?>"
         data-id="<?= $m['id'] ?>"
         style="--mc:<?= $cor ?>; border-left:3px solid <?= $cor ?>">
        <style>.meta-card[data-id="<?= $m['id'] ?>"]:before{background:<?= $cor ?>}</style>

        <div class="meta-card-header">
            <div class="meta-card-icon" style="background:<?= $cor ?>22;color:<?= $cor ?>">
                <i class="fa-solid fa-<?= $icone ?>"></i>
            </div>
            <div style="flex:1">
                <div class="meta-card-nome"><?= htmlspecialchars($m['nome']) ?></div>
                <div class="meta-card-tipo">
                    <?= $tipoLabels[$m['tipo']] ?? $m['tipo'] ?>
                    &nbsp;·&nbsp;
                    <span class="badge <?= $pCls ?>" style="font-size:.62rem">
                        Prioridade <?= $priorLabels[$m['prioridade']] ?>
                    </span>
                    <?php if ($m['status'] !== 'ativa'): ?>
                    <span class="badge amber" style="font-size:.62rem;margin-left:.25rem">
                        <?= ucfirst($m['status']) ?>
                    </span>
                    <?php endif ?>
                </div>
            </div>
        </div>

        <div class="meta-valores">
            <div>
                <div class="meta-atual" style="color:<?= $cor ?>"><?= brl((float)$m['valor_atual']) ?></div>
                <div class="meta-alvo">de <?= brl((float)$m['valor_alvo']) ?></div>
            </div>
            <div style="text-align:right">
                <div class="meta-pct" style="color:<?= $cor ?>"><?= $pct ?>%</div>
                <?php if ($diasInfo): ?>
                <div class="meta-dias"><?= $diasInfo ?></div>
                <?php endif ?>
            </div>
        </div>

        <div class="progress-wrap" style="gap:.3rem">
            <div class="progress-bar-bg">
                <div class="progress-bar-fill" style="width:<?= $pct ?>%;background:<?= $cor ?>;transition:width .6s ease"></div>
            </div>
        </div>

        <?php if ($m['aporte_mensal'] && $m['status'] === 'ativa'): ?>
        <div class="text-xs text-muted">
            <i class="fa-solid fa-calendar-check fa-xs"></i>
            Aporte sugerido: <strong><?= brl((float)$m['aporte_mensal']) ?>/mês</strong>
            <?php if ($m['aporte_mensal'] > 0 && $falta > 0):
                $mesesNecessarios = ceil($falta / $m['aporte_mensal']); ?>
            &nbsp;·&nbsp; ~<?= $mesesNecessarios ?> mes<?= $mesesNecessarios !== 1 ? 'es' : '' ?>
            <?php endif ?>
        </div>
        <?php endif ?>

        <div class="meta-actions">
            <?php if ($m['status'] === 'ativa'): ?>
            <button class="btn btn-success btn-sm" onclick="abrirAporte(<?= $m['id'] ?>)" style="flex:1;justify-content:center">
                <i class="fa-solid fa-plus"></i> Aportar
            </button>
            <?php endif ?>
            <button class="btn btn-ghost btn-sm" onclick="abrirEditar(<?= $m['id'] ?>)" style="flex:1;justify-content:center">
                <i class="fa-solid fa-pen-to-square"></i> Editar
            </button>
            <button class="btn-icon" onclick="verAportes(<?= $m['id'] ?>)" title="Ver aportes"
                    style="width:32px;height:32px;font-size:.8rem;color:var(--text-400)">
                <i class="fa-solid fa-list"></i>
            </button>
            <button class="btn-icon" onclick="excluirMeta(<?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['nome'])) ?>')"
                    title="Excluir" style="width:32px;height:32px;font-size:.8rem;color:var(--rose)">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    </div>
    <?php endforeach ?>
</div>
<?php endif ?>

<!-- ── Modal: Nova / Editar Meta ─────────────────────────── -->
<div id="modalForm" class="modal-overlay" onclick="if(event.target===this)fecharModal('modalForm')">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title" id="modalTitulo">Nova Meta</div>
            <button type="button" class="btn-icon" onclick="fecharModal('modalForm')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formMeta" onsubmit="salvarMeta(event)">
            <div class="modal-body">
                <input type="hidden" id="mId" value="">

                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Nome <span style="color:var(--rose)">*</span></label>
                    <input type="text" id="mNome" class="form-control" required
                           placeholder="Ex: Reserva de emergência, Viagem para Europa...">
                </div>

                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Tipo</label>
                        <select id="mTipo" class="form-control" onchange="atualizarIcone()">
                            <?php foreach ($tipoLabels as $v => $l): ?>
                            <option value="<?= $v ?>"><?= $l ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Prioridade</label>
                        <select id="mPrior" class="form-control">
                            <option value="1">Alta</option>
                            <option value="2">Média</option>
                            <option value="3" selected>Baixa</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid form-grid-3" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Valor alvo (R$) <span style="color:var(--rose)">*</span></label>
                        <input type="text" id="mAlvo" class="form-control" placeholder="0,00" inputmode="numeric" data-currency required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Aporte mensal (R$)</label>
                        <input type="text" id="mAporte" class="form-control" placeholder="0,00" inputmode="numeric" data-currency>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Prazo</label>
                        <input type="date" id="mPrazo" class="form-control">
                    </div>
                </div>

                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Data início</label>
                        <input type="date" id="mInicio" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select id="mStatus" class="form-control">
                            <option value="ativa">Ativa</option>
                            <option value="pausada">Pausada</option>
                            <option value="concluida">Concluída</option>
                            <option value="cancelada">Cancelada</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Cor</label>
                        <input type="color" id="mCor" class="form-control" value="#10b981"
                               style="height:40px;padding:.3rem;cursor:pointer">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ícone FontAwesome</label>
                        <input type="text" id="mIcone" class="form-control" value="bullseye"
                               placeholder="bullseye, car, house...">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <textarea id="mDesc" class="form-control" rows="2" placeholder="(opcional)"></textarea>
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

<!-- ── Modal: Aportar ─────────────────────────────────────── -->
<div id="modalAporte" class="modal-overlay" onclick="if(event.target===this)fecharModal('modalAporte')">
    <div class="modal-box sm">
        <div class="modal-header">
            <div class="modal-title">Registrar Aporte</div>
            <button type="button" class="btn-icon" onclick="fecharModal('modalAporte')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formAporte" onsubmit="salvarAporte(event)">
            <div class="modal-body">
                <input type="hidden" id="aMetaId">
                <div class="card" style="margin-bottom:1.1rem;background:var(--bg-700)">
                    <div class="card-body" style="padding:.875rem">
                        <div class="text-xs text-muted">Meta</div>
                        <div class="fw-700" id="aMetaNome" style="margin-top:.2rem">—</div>
                        <div class="text-sm text-muted" id="aMetaInfo" style="margin-top:.15rem">—</div>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Valor do aporte (R$) <span style="color:var(--rose)">*</span></label>
                    <input type="text" id="aValor" class="form-control" placeholder="0,00" inputmode="numeric" data-currency required>
                </div>
                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Data</label>
                    <input type="date" id="aData" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <input type="text" id="aDesc" class="form-control" placeholder="(opcional)">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="fecharModal('modalAporte')">Cancelar</button>
                <button type="submit" class="btn btn-success" id="btnAporte">
                    <i class="fa-solid fa-plus"></i> Confirmar Aporte
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Histórico de aportes ───────────────────────── -->
<div id="modalHistorico" class="modal-overlay" onclick="if(event.target===this)fecharModal('modalHistorico')">
    <div class="modal-box sm">
        <div class="modal-header">
            <div class="modal-title" id="hTitulo">Aportes</div>
            <button type="button" class="btn-icon" onclick="fecharModal('modalHistorico')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <div id="hLista" style="display:flex;flex-direction:column;gap:.5rem">
                <i class="fa-solid fa-spinner fa-spin"></i>
            </div>
        </div>
    </div>
</div>

<script>
const _METAS = <?= json_encode(array_values($metas), JSON_UNESCAPED_UNICODE) ?>;
const _TIPO_ICONES = <?= json_encode($tipoIcons) ?>;

function abrirModalNova() {
    document.getElementById('formMeta').reset();
    document.getElementById('mId').value     = '';
    document.getElementById('mInicio').value = new Date().toISOString().split('T')[0];
    document.getElementById('mCor').value    = '#10b981';
    document.getElementById('mIcone').value  = 'bullseye';
    document.getElementById('mPrior').value  = '3';
    document.getElementById('modalTitulo').textContent = 'Nova Meta';
    document.getElementById('modalForm').classList.add('open');
    setTimeout(() => document.getElementById('mNome').focus(), 80);
}

function abrirEditar(id) {
    const m = _METAS.find(x => +x.id === +id);
    if (!m) return;
    document.getElementById('mId').value     = m.id;
    document.getElementById('mNome').value   = m.nome || '';
    document.getElementById('mTipo').value   = m.tipo || 'outro';
    document.getElementById('mPrior').value  = m.prioridade || '3';
    document.getElementById('mAlvo').value   = m.valor_alvo   ? brlMask(m.valor_alvo)   : '';
    document.getElementById('mAporte').value = m.aporte_mensal ? brlMask(m.aporte_mensal) : '';
    document.getElementById('mPrazo').value  = (m.data_prazo   ||'').split('T')[0];
    document.getElementById('mInicio').value = (m.data_inicio  ||'').split('T')[0];
    document.getElementById('mStatus').value = m.status || 'ativa';
    document.getElementById('mCor').value    = m.cor    || '#10b981';
    document.getElementById('mIcone').value  = m.icone  || 'bullseye';
    document.getElementById('mDesc').value   = m.descricao || '';
    document.getElementById('modalTitulo').textContent = 'Editar Meta';
    document.getElementById('modalForm').classList.add('open');
}

function atualizarIcone() {
    const tipo = document.getElementById('mTipo').value;
    if (_TIPO_ICONES[tipo]) document.getElementById('mIcone').value = _TIPO_ICONES[tipo];
}

async function salvarMeta(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvar');
    btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    const id = document.getElementById('mId').value;
    const payload = {
        acao:          'salvar',
        id:            id ? parseInt(id) : 0,
        nome:          document.getElementById('mNome').value.trim(),
        tipo:          document.getElementById('mTipo').value,
        prioridade:    parseInt(document.getElementById('mPrior').value),
        valor_alvo:    parseCurrency(document.getElementById('mAlvo').value),
        aporte_mensal: parseCurrency(document.getElementById('mAporte').value) || null,
        data_prazo:    document.getElementById('mPrazo').value  || null,
        data_inicio:   document.getElementById('mInicio').value || null,
        status:        document.getElementById('mStatus').value,
        cor:           document.getElementById('mCor').value,
        icone:         document.getElementById('mIcone').value.trim(),
        descricao:     document.getElementById('mDesc').value.trim(),
    };

    try {
        const res  = await fetch('backend/api/metas.php', {
            method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        fecharModal('modalForm');
        location.reload();
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
    finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar';
    }
}

function abrirAporte(id) {
    const m = _METAS.find(x => +x.id === +id);
    if (!m) return;
    const falta = Math.max(0, parseFloat(m.valor_alvo) - parseFloat(m.valor_atual));
    document.getElementById('aMetaId').value        = id;
    document.getElementById('aMetaNome').textContent = m.nome;
    document.getElementById('aMetaInfo').textContent =
        `${brl(m.valor_atual)} / ${brl(m.valor_alvo)} · Faltam ${brl(falta)}`;
    document.getElementById('aValor').value = m.aporte_mensal ? brlMask(m.aporte_mensal) : '';
    document.getElementById('aData').value  = new Date().toISOString().split('T')[0];
    document.getElementById('aDesc').value  = '';
    document.getElementById('modalAporte').classList.add('open');
    setTimeout(() => document.getElementById('aValor').focus(), 80);
}

async function salvarAporte(e) {
    e.preventDefault();
    const btn = document.getElementById('btnAporte');
    btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    const payload = {
        acao:      'aporte',
        meta_id:   parseInt(document.getElementById('aMetaId').value),
        valor:     parseCurrency(document.getElementById('aValor').value),
        data:      document.getElementById('aData').value,
        descricao: document.getElementById('aDesc').value.trim(),
    };

    try {
        const res  = await fetch('backend/api/metas.php', {
            method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        fecharModal('modalAporte');
        location.reload();
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
    finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-plus"></i> Confirmar Aporte';
    }
}

async function verAportes(id) {
    const m = _METAS.find(x => +x.id === +id);
    document.getElementById('hTitulo').textContent = m ? `Aportes — ${m.nome}` : 'Aportes';
    document.getElementById('hLista').innerHTML = '<div style="text-align:center;padding:1rem"><i class="fa-solid fa-spinner fa-spin"></i></div>';
    document.getElementById('modalHistorico').classList.add('open');
    try {
        const res  = await fetch(`backend/api/metas.php?acao=aportes&id=${id}`);
        const json = await res.json();
        const lista = document.getElementById('hLista');
        if (!json.aportes.length) {
            lista.innerHTML = '<p class="text-muted text-sm" style="text-align:center;padding:1rem">Nenhum aporte registrado.</p>';
            return;
        }
        lista.innerHTML = json.aportes.map(a => `
            <div class="d-flex justify-between align-center" style="padding:.6rem .875rem;background:var(--bg-700);border-radius:var(--radius-sm)">
                <div>
                    <div class="fw-600 text-sm text-emerald">+${brl(a.valor)}</div>
                    <div class="text-xs text-muted">${a.descricao || '—'}</div>
                </div>
                <div class="text-xs text-muted">${fmtData(a.data)}</div>
            </div>`).join('');
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

function excluirMeta(id, nome) {
    confirmar('Excluir meta', `Excluir a meta "${nome}"? Todo o histórico de aportes será removido.`, async () => {
        try {
            const res  = await fetch(`backend/api/metas.php?id=${id}`, {method:'DELETE'});
            const json = await res.json();
            if (!json.success) throw new Error(json.erro);
            toast(json.msg, 'success');
            location.reload();
        } catch (err) { toast('Erro: ' + err.message, 'error'); }
    });
}

function fecharModal(id) { document.getElementById(id).classList.remove('open'); }
// fmtData() vem de assets/js/app.js (carregado globalmente por index.php)

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') ['modalForm','modalAporte','modalHistorico'].forEach(fecharModal);
});
</script>
