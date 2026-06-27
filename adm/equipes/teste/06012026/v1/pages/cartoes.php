<?php
// ── Dados iniciais ────────────────────────────────────────────
$p   = TABLE_PREFIX;
$mes = (int) date('m');
$ano = (int) date('Y');

$stmt = $pdo->prepare(
    "SELECT c.*,
            f.id               fatura_id,
            COALESCE(f.valor_total,0)    fatura_total,
            COALESCE(f.valor_pago,0)     fatura_pago,
            f.status           fatura_status,
            f.data_vencimento  fatura_vencimento,
            COALESCE(SUM(t.valor),0)     gastos_mes
     FROM `{$p}cartoes` c
     LEFT JOIN `{$p}cartoes_faturas` f
            ON f.cartao_id = c.id
           AND f.mes_referencia = ? AND f.ano_referencia = ?
     LEFT JOIN `{$p}transacoes` t
            ON t.cartao_id = c.id AND t.tipo = 'despesa'
           AND MONTH(t.data) = ? AND YEAR(t.data) = ?
     WHERE c.ativo = 1
     GROUP BY c.id, f.id
     ORDER BY c.limite_total DESC"
);
$stmt->execute([$mes, $ano, $mes, $ano]);
$cartoes = $stmt->fetchAll();

$contas = $pdo->query(
    "SELECT id, nome FROM `{$p}contas` WHERE ativo=1 ORDER BY nome"
)->fetchAll();

$totLimite = array_sum(array_column($cartoes, 'limite_total'));
$totUsado  = array_sum(array_column($cartoes, 'limite_usado'));
$totDisp   = $totLimite - $totUsado;

$nomesMeses = [
    1=>'Janeiro', 2=>'Fevereiro', 3=>'Março',    4=>'Abril',
    5=>'Maio',    6=>'Junho',     7=>'Julho',     8=>'Agosto',
    9=>'Setembro',10=>'Outubro', 11=>'Novembro', 12=>'Dezembro'
];

function brlPHP(float $v): string {
    return 'R$ ' . number_format($v, 2, ',', '.');
}

$bandeiras = [
    'visa'       => ['label' => 'Visa',       'icon' => 'fa-cc-visa'],
    'mastercard' => ['label' => 'Mastercard', 'icon' => 'fa-cc-mastercard'],
    'elo'        => ['label' => 'Elo',        'icon' => 'fa-credit-card'],
    'amex'       => ['label' => 'Amex',       'icon' => 'fa-cc-amex'],
    'hipercard'  => ['label' => 'Hipercard',  'icon' => 'fa-credit-card'],
    'outro'      => ['label' => 'Outro',      'icon' => 'fa-credit-card'],
];
?>

<style>
/* ── Card visual (crédito) ───────────────────────────────── */
.cc-full {
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    cursor: pointer;
    transition: transform .2s ease, box-shadow .2s ease;
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;
}
.cc-full::after {
    content: '';
    position: absolute;
    width: 180px; height: 180px;
    border-radius: 50%;
    background: rgba(255,255,255,.04);
    top: -60px; right: -60px;
    pointer-events: none;
}
.cc-full:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,.45); }
.cc-full.selected { border-color: #fff2 !important; box-shadow: 0 0 0 3px rgba(255,255,255,.15), 0 12px 32px rgba(0,0,0,.5); }
.cc-full .cc-bandeira-icon { font-size: 1.5rem; }
.cc-full .cc-num { font-size: .9rem; letter-spacing: .12em; color: rgba(255,255,255,.7); margin: .75rem 0 .25rem; }
.cc-full .cc-nome { font-weight: 700; font-size: .95rem; color: #fff; }
.cc-full .cc-info { font-size: .72rem; color: rgba(255,255,255,.6); margin-top: .15rem; }
.cc-full .cc-bar-bg { height: 5px; background: rgba(0,0,0,.25); border-radius: 99px; margin-top: .9rem; }
.cc-full .cc-bar-fill { height: 100%; border-radius: 99px; background: rgba(255,255,255,.75); transition: width .5s ease; }
.cc-full .cc-limit-row { display:flex; justify-content:space-between; margin-top:.35rem; font-size:.72rem; color:rgba(255,255,255,.65); }
.cc-full .cc-usado { font-weight:700; font-size:.85rem; color:#fff; }
/* ── Status da fatura ─────────────────────────────────────── */
.fatura-badge { display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .6rem;border-radius:999px;font-size:.68rem;font-weight:700; }
.fatura-badge.aberta   { background:rgba(245,158,11,.2); color:var(--amber); }
.fatura-badge.fechada  { background:rgba(99,102,241,.2); color:var(--indigo); }
.fatura-badge.paga     { background:rgba(16,185,129,.2); color:var(--emerald); }
.fatura-badge.parcial  { background:rgba(6,182,212,.2);  color:var(--cyan); }
/* ── Seção de detalhe ─────────────────────────────────────── */
#detalheSection {
    display: none;
    margin-top: 1.25rem;
    animation: fadeIn .2s ease;
}
@keyframes fadeIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:none} }
/* ── Modal ───────────────────────────────────────────────── */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.65);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.modal-overlay.open { display: flex; }
.modal-box {
    background: var(--bg-800);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    width: 100%;
    max-width: 560px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-lg);
    animation: modalIn .18s ease;
}
.modal-box.sm { max-width: 420px; }
@keyframes modalIn { from{opacity:0;transform:translateY(-10px) scale(.97)} to{opacity:1;transform:none} }
.modal-header {
    padding: 1.25rem 1.5rem 1rem;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
}
.modal-title  { font-size: 1rem; font-weight: 700; }
.modal-body   { padding: 1.5rem; overflow-y: auto; flex: 1; }
.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border);
    display: flex; justify-content: flex-end; gap: .75rem;
    flex-shrink: 0;
}
/* ── Navegação de mês ─────────────────────────────────────── */
.mes-nav { display:flex; align-items:center; gap:.5rem; }
.mes-nav button { background:var(--bg-700); border:1px solid var(--border); color:var(--text-200); padding:.3rem .6rem; border-radius:var(--radius-sm); cursor:pointer; font-size:.8rem; transition:var(--ease); }
.mes-nav button:hover { background:var(--bg-600); }
.mes-nav span { min-width:120px; text-align:center; font-size:.85rem; font-weight:600; }
</style>

<!-- ── Cabeçalho ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <div class="page-title">Cartões de Crédito</div>
        <div class="page-sub"><?= count($cartoes) ?> cartão<?= count($cartoes) !== 1 ? 'ões' : '' ?> ativo<?= count($cartoes) !== 1 ? 's' : '' ?></div>
    </div>
    <button class="btn btn-primary btn-sm" onclick="abrirModalCartao()">
        <i class="fa-solid fa-plus"></i> Novo Cartão
    </button>
</div>

<!-- ── KPIs ──────────────────────────────────────────────── -->
<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.25rem">
    <div class="kpi-card violet">
        <div class="kpi-header">
            <div class="kpi-label">Limite Total</div>
            <div class="kpi-icon violet"><i class="fa-solid fa-credit-card"></i></div>
        </div>
        <div class="kpi-value"><?= brlPHP($totLimite) ?></div>
        <div class="kpi-trend neutral">
            <i class="fa-solid fa-circle-info fa-xs"></i> Soma de todos os cartões
        </div>
    </div>
    <div class="kpi-card rose">
        <div class="kpi-header">
            <div class="kpi-label">Limite Usado</div>
            <div class="kpi-icon rose"><i class="fa-solid fa-arrow-down"></i></div>
        </div>
        <div class="kpi-value"><?= brlPHP($totUsado) ?></div>
        <div class="kpi-trend <?= $totLimite > 0 && ($totUsado / $totLimite) >= .7 ? 'down' : 'neutral' ?>">
            <i class="fa-solid fa-circle-info fa-xs"></i>
            <?= $totLimite > 0 ? number_format(($totUsado / $totLimite) * 100, 1, ',', '.') . '% do limite' : 'Sem limite cadastrado' ?>
        </div>
    </div>
    <div class="kpi-card emerald">
        <div class="kpi-header">
            <div class="kpi-label">Disponível</div>
            <div class="kpi-icon emerald"><i class="fa-solid fa-circle-check"></i></div>
        </div>
        <div class="kpi-value"><?= brlPHP($totDisp) ?></div>
        <div class="kpi-trend neutral">
            <i class="fa-solid fa-circle-info fa-xs"></i> Saldo disponível para uso
        </div>
    </div>
</div>

<!-- ── Grade de cartões ───────────────────────────────────── -->
<?php if (empty($cartoes)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:3rem;color:var(--text-600)">
        <i class="fa-solid fa-credit-card fa-3x" style="margin-bottom:1rem;color:var(--violet)"></i>
        <div class="page-title" style="font-size:1rem;margin-bottom:.5rem">Nenhum cartão cadastrado</div>
        <div class="text-sm" style="margin-bottom:1.25rem">Adicione seus cartões de crédito para acompanhar limites e faturas.</div>
        <button class="btn btn-primary" onclick="abrirModalCartao()">
            <i class="fa-solid fa-plus"></i> Adicionar primeiro cartão
        </button>
    </div>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem" id="cartoesGrid">
    <?php foreach ($cartoes as $cc):
        $uso   = $cc['limite_total'] > 0 ? min(100, round(($cc['limite_usado'] / $cc['limite_total']) * 100)) : 0;
        $fatSt = $cc['fatura_status'] ?: 'aberta';
        $gastos = (float) $cc['gastos_mes'];

        // Gradiente baseado na cor do cartão
        $cor = htmlspecialchars($cc['cor']);
    ?>
    <div class="cc-full"
         data-id="<?= $cc['id'] ?>"
         data-cor="<?= $cor ?>"
         style="background:linear-gradient(135deg,<?= $cor ?>cc,<?= $cor ?>88)"
         onclick="selecionarCartao(<?= $cc['id'] ?>)">

        <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
                <div class="cc-nome"><?= htmlspecialchars($cc['nome']) ?></div>
                <div class="cc-info">
                    <?= $cc['banco'] ? htmlspecialchars($cc['banco']) . ' &nbsp;·&nbsp; ' : '' ?>
                    <?= ucfirst($cc['bandeira']) ?>
                </div>
            </div>
            <i class="fa-brands <?= htmlspecialchars($bandeiras[$cc['bandeira']]['icon'] ?? 'fa-credit-card') ?> cc-bandeira-icon" style="color:rgba(255,255,255,.7)"></i>
        </div>

        <div class="cc-num">
            •••• •••• •••• <?= $cc['ultimos_digitos'] ?: '????'  ?>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center">
            <div>
                <div class="cc-usado"><?= brlPHP((float)$cc['limite_usado']) ?></div>
                <div class="cc-info">de <?= brlPHP((float)$cc['limite_total']) ?> &nbsp;·&nbsp; <?= $uso ?>% usado</div>
            </div>
            <div style="text-align:right">
                <span class="fatura-badge <?= $fatSt ?>">
                    <i class="fa-solid <?= $fatSt === 'paga' ? 'fa-circle-check' : ($fatSt === 'fechada' ? 'fa-lock' : 'fa-clock') ?> fa-xs"></i>
                    <?= ucfirst($fatSt) ?>
                </span>
                <?php if ($gastos > 0): ?>
                <div class="cc-info" style="margin-top:.25rem"><?= brlPHP($gastos) ?> este mês</div>
                <?php endif ?>
            </div>
        </div>

        <div class="cc-bar-bg">
            <div class="cc-bar-fill" style="width:<?= $uso ?>%"></div>
        </div>
        <div class="cc-limit-row">
            <span>Fecha dia <?= $cc['dia_fechamento'] ?> &nbsp;·&nbsp; Vence dia <?= $cc['dia_vencimento'] ?></span>
            <span><?= brlPHP((float)$cc['limite_total'] - (float)$cc['limite_usado']) ?> disponível</span>
        </div>

        <!-- Botões de ação rápida no card -->
        <div style="display:flex;justify-content:flex-end;gap:.4rem;margin-top:.5rem"
             onclick="event.stopPropagation()">
            <button class="btn btn-sm"
                    style="background:rgba(255,255,255,.15);color:#fff;font-size:.72rem;padding:.25rem .6rem"
                    onclick="abrirModalEditarById(<?= $cc['id'] ?>)"
                    title="Editar cartão">
                <i class="fa-solid fa-pen-to-square fa-xs"></i> Editar
            </button>
        </div>
    </div>
    <?php endforeach ?>
</div>

<!-- ── Seção de detalhe do cartão selecionado ────────────── -->
<div id="detalheSection">
    <div class="card">
        <div class="card-header" style="flex-wrap:wrap;gap:.75rem">
            <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
                <div>
                    <div class="card-title" id="detTitulo">—</div>
                    <div class="card-subtitle" id="detSubtitulo">—</div>
                </div>
                <div class="mes-nav">
                    <button onclick="mudarMes(-1)"><i class="fa-solid fa-chevron-left fa-xs"></i></button>
                    <span id="detMesLabel">—</span>
                    <button onclick="mudarMes(1)"><i class="fa-solid fa-chevron-right fa-xs"></i></button>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
                <div style="text-align:right">
                    <div class="text-xs text-muted">Total da fatura</div>
                    <div class="fw-700" id="detTotal" style="font-size:1.1rem">—</div>
                </div>
                <span id="detFatStatus" class="fatura-badge aberta">Aberta</span>
                <button class="btn btn-primary btn-sm" id="btnPagarFatura" onclick="abrirModalPagamento()">
                    <i class="fa-solid fa-money-bill-wave"></i> Pagar Fatura
                </button>
                <button class="btn btn-ghost btn-sm" onclick="abrirModalEditar()">
                    <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button class="btn btn-ghost btn-sm" style="color:var(--rose)" onclick="desativarCartao()" title="Desativar cartão">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th>Data</th>
                        <th>Status</th>
                        <th class="text-right">Valor</th>
                    </tr>
                </thead>
                <tbody id="detTbody">
                    <tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--text-600)">
                        Clique em um cartão para ver as transações.
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif ?>

<!-- ── Modal: Novo / Editar Cartão ───────────────────────── -->
<div id="modalCartao" class="modal-overlay" onclick="if(event.target===this)fecharModais()">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title" id="modalCartaoTitulo">Novo Cartão</div>
            <button type="button" class="btn-icon" onclick="fecharModais()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formCartao" onsubmit="salvarCartao(event)">
            <div class="modal-body">
                <input type="hidden" id="cId" value="">

                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Nome <span style="color:var(--rose)">*</span></label>
                    <input type="text" id="cNome" class="form-control"
                           placeholder="Ex: Nubank, Itaú Platinum..." required maxlength="100">
                </div>

                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Bandeira</label>
                        <select id="cBandeira" class="form-control">
                            <option value="visa">Visa</option>
                            <option value="mastercard">Mastercard</option>
                            <option value="elo">Elo</option>
                            <option value="amex">Amex</option>
                            <option value="hipercard">Hipercard</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Banco / Emissor</label>
                        <input type="text" id="cBanco" class="form-control"
                               placeholder="Ex: Nubank, Itaú..." maxlength="100">
                    </div>
                </div>

                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Últimos 4 dígitos</label>
                        <input type="text" id="cDigitos" class="form-control"
                               placeholder="0000" maxlength="4" pattern="\d{0,4}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Limite Total (R$) <span style="color:var(--rose)">*</span></label>
                        <input type="text" id="cLimite" class="form-control"
                               placeholder="0,00" inputmode="numeric" data-currency required>
                    </div>
                </div>

                <div class="form-grid form-grid-3" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Dia fechamento</label>
                        <input type="number" id="cFech" class="form-control"
                               value="1" min="1" max="31">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dia vencimento</label>
                        <input type="number" id="cVenc" class="form-control"
                               value="10" min="1" max="31">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cor</label>
                        <input type="color" id="cCor" class="form-control" value="#8b5cf6"
                               style="padding:.3rem;height:40px;cursor:pointer">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="fecharModais()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnSalvarCartao">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Pagar Fatura ───────────────────────────────── -->
<div id="modalPagamento" class="modal-overlay" onclick="if(event.target===this)fecharModais()">
    <div class="modal-box sm">
        <div class="modal-header">
            <div class="modal-title">Registrar Pagamento</div>
            <button type="button" class="btn-icon" onclick="fecharModais()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formPagamento" onsubmit="salvarPagamento(event)">
            <div class="modal-body">
                <div class="card" style="margin-bottom:1.1rem;background:var(--bg-700)">
                    <div class="card-body" style="padding:.875rem">
                        <div class="text-xs text-muted">Fatura a pagar</div>
                        <div class="fw-700" id="pagFaturaInfo" style="font-size:1rem;margin-top:.2rem">—</div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Valor a pagar (R$) <span style="color:var(--rose)">*</span></label>
                    <input type="text" id="pValor" class="form-control"
                           placeholder="0,00" inputmode="numeric" data-currency required>
                </div>
                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Data do pagamento <span style="color:var(--rose)">*</span></label>
                    <input type="date" id="pData" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Conta de débito</label>
                    <select id="pConta" class="form-control">
                        <option value="">— Não débitar conta —</option>
                        <?php foreach ($contas as $ct): ?>
                        <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['nome']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="fecharModais()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnSalvarPag">
                    <i class="fa-solid fa-money-bill-wave"></i> Confirmar Pagamento
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Dados dos cartões (para edição direta) ────────────────
const _CARTOES = <?= json_encode(array_values($cartoes), JSON_UNESCAPED_UNICODE) ?>;

// ── Estado ────────────────────────────────────────────────
let _cartaoAtivo  = null;
let _detalhe      = null;
let _detMes       = <?= $mes ?>;
let _detAno       = <?= $ano ?>;

const MESES = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho',
               'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

// ── Selecionar cartão ─────────────────────────────────────
function selecionarCartao(id) {
    document.querySelectorAll('.cc-full').forEach(c => c.classList.remove('selected'));
    const el = document.querySelector(`.cc-full[data-id="${id}"]`);
    if (el) el.classList.add('selected');
    _cartaoAtivo = id;
    carregarDetalhe();
}

// ── Navegar mês ────────────────────────────────────────────
function mudarMes(delta) {
    _detMes += delta;
    if (_detMes > 12) { _detMes = 1;  _detAno++; }
    if (_detMes < 1)  { _detMes = 12; _detAno--; }
    carregarDetalhe();
}

// ── Carregar detalhe via API ──────────────────────────────
async function carregarDetalhe() {
    if (!_cartaoAtivo) return;

    document.getElementById('detalheSection').style.display = '';
    document.getElementById('detMesLabel').textContent = `${MESES[_detMes]}/${_detAno}`;
    document.getElementById('detTbody').innerHTML =
        `<tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--text-600)">
            <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
        </td></tr>`;

    const params = new URLSearchParams({ acao:'detalhe', id:_cartaoAtivo, mes:_detMes, ano:_detAno });

    try {
        const res  = await fetch('backend/api/cartoes.php?' + params);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        _detalhe = json;
        renderDetalhe(json);
    } catch (err) {
        toast('Erro: ' + err.message, 'error');
    }
}

function renderDetalhe(data) {
    const { cartao, transacoes, total_mes } = data;

    document.getElementById('detTitulo').textContent    = cartao.nome;
    document.getElementById('detSubtitulo').textContent =
        `${MESES[_detMes]}/${_detAno} &nbsp;·&nbsp; ${ucFirst(cartao.bandeira)}`.replace('&nbsp;','·');
    document.getElementById('detSubtitulo').innerHTML   =
        `${MESES[_detMes]}/${_detAno} &nbsp;·&nbsp; ${ucFirst(cartao.bandeira)}`;
    document.getElementById('detTotal').textContent = brl(total_mes);

    const fatSt = cartao.fatura_status || 'aberta';
    const badge = document.getElementById('detFatStatus');
    badge.className = `fatura-badge ${fatSt}`;
    badge.innerHTML = `<i class="fa-solid ${fatSt === 'paga' ? 'fa-circle-check' : fatSt === 'fechada' ? 'fa-lock' : 'fa-clock'} fa-xs"></i> ${ucFirst(fatSt)}`;

    document.getElementById('btnPagarFatura').style.display = fatSt === 'paga' ? 'none' : '';

    const tbody = document.getElementById('detTbody');
    if (!transacoes.length) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--text-600)">
            <i class="fa-solid fa-receipt fa-2x" style="margin-bottom:.75rem;display:block"></i>
            Nenhuma transação neste mês para este cartão.
        </td></tr>`;
        return;
    }

    tbody.innerHTML = transacoes.map(tx => {
        const catBadge = tx.cat_nome
            ? `<span class="badge" style="background:${tx.cat_cor||'#334155'}22;color:${tx.cat_cor||'#94a3b8'}">${esc(tx.cat_nome)}</span>`
            : '<span class="text-muted text-xs">—</span>';
        const parcelas = tx.parcela_total
            ? `<div class="text-xs text-muted">${tx.parcela_atual}/${tx.parcela_total} parcelas</div>` : '';
        return `<tr>
            <td>
                <div class="fw-600 text-sm">${esc(tx.descricao)}</div>
                ${parcelas}
            </td>
            <td>${catBadge}</td>
            <td class="text-sm text-muted">${fmtData(tx.data)}</td>
            <td><span class="badge ${esc(tx.status)}">${ucFirst(tx.status)}</span></td>
            <td class="text-right fw-600 text-rose">${brl(tx.valor)}</td>
        </tr>`;
    }).join('');
}

// ── Modal: Novo/Editar Cartão ─────────────────────────────
function abrirModalCartao() {
    document.getElementById('formCartao').reset();
    document.getElementById('cId').value   = '';
    document.getElementById('cCor').value  = '#8b5cf6';
    document.getElementById('cFech').value = '1';
    document.getElementById('cVenc').value = '10';
    document.getElementById('modalCartaoTitulo').textContent = 'Novo Cartão';
    document.getElementById('modalCartao').classList.add('open');
    setTimeout(() => document.getElementById('cNome').focus(), 80);
}

function _preencherModalCartao(cc) {
    document.getElementById('formCartao').reset();
    document.getElementById('cId').value       = cc.id;
    document.getElementById('cNome').value     = cc.nome     || '';
    document.getElementById('cBandeira').value = cc.bandeira || 'visa';
    document.getElementById('cBanco').value    = cc.banco    || '';
    document.getElementById('cDigitos').value  = cc.ultimos_digitos || '';
    document.getElementById('cLimite').value   = cc.limite_total ? brlMask(cc.limite_total) : '';
    document.getElementById('cFech').value     = cc.dia_fechamento || '1';
    document.getElementById('cVenc').value     = cc.dia_vencimento || '10';
    document.getElementById('cCor').value      = cc.cor || '#8b5cf6';
    document.getElementById('modalCartaoTitulo').textContent = 'Editar Cartão';
    document.getElementById('modalCartao').classList.add('open');
    setTimeout(() => document.getElementById('cNome').focus(), 80);
}

function abrirModalEditar() {
    if (!_detalhe) return;
    _preencherModalCartao(_detalhe.cartao);
}

function abrirModalEditarById(id) {
    const cc = _CARTOES.find(c => +c.id === +id);
    if (cc) _preencherModalCartao(cc);
}

async function salvarCartao(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvarCartao');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Salvando...';

    const id = document.getElementById('cId').value;
    const payload = {
        acao:             'salvar',
        id:               id ? parseInt(id) : 0,
        nome:             document.getElementById('cNome').value.trim(),
        bandeira:         document.getElementById('cBandeira').value,
        banco:            document.getElementById('cBanco').value.trim(),
        ultimos_digitos:  document.getElementById('cDigitos').value.trim(),
        limite_total:     parseCurrency(document.getElementById('cLimite').value),
        dia_fechamento:   parseInt(document.getElementById('cFech').value),
        dia_vencimento:   parseInt(document.getElementById('cVenc').value),
        cor:              document.getElementById('cCor').value,
    };

    try {
        const res  = await fetch('backend/api/cartoes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg || 'Salvo!', 'success');
        fecharModais();
        location.reload();
    } catch (err) {
        toast('Erro: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar';
    }
}

// ── Modal: Pagar Fatura ───────────────────────────────────
function abrirModalPagamento() {
    if (!_detalhe) return;
    const totalMes = _detalhe.total_mes || 0;
    const fatPago  = parseFloat(_detalhe.cartao.fatura_pago || 0);
    const restante = Math.max(0, totalMes - fatPago);

    document.getElementById('formPagamento').reset();
    document.getElementById('pValor').value = brlMask(restante);
    document.getElementById('pData').value  = new Date().toISOString().split('T')[0];
    document.getElementById('pagFaturaInfo').textContent =
        `${_detalhe.cartao.nome} — ${MESES[_detMes]}/${_detAno} — ${brl(totalMes)}`;
    document.getElementById('modalPagamento').classList.add('open');
    setTimeout(() => document.getElementById('pValor').focus(), 80);
}

async function salvarPagamento(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvarPag');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Confirmando...';

    const payload = {
        acao:            'pagar_fatura',
        cartao_id:       _cartaoAtivo,
        mes:             _detMes,
        ano:             _detAno,
        valor_pago:      parseCurrency(document.getElementById('pValor').value),
        data_pagamento:  document.getElementById('pData').value,
        conta_id:        document.getElementById('pConta').value || null,
    };

    try {
        const res  = await fetch('backend/api/cartoes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg || 'Pagamento registrado!', 'success');
        fecharModais();
        carregarDetalhe();
        location.reload();
    } catch (err) {
        toast('Erro: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-money-bill-wave"></i> Confirmar Pagamento';
    }
}

// ── Desativar cartão ──────────────────────────────────────
function desativarCartao() {
    if (!_cartaoAtivo || !_detalhe) return;
    const nome = _detalhe.cartao.nome;
    confirmar('Desativar cartão', `Desativar o cartão "${nome}"? Ele não aparecerá mais no sistema.`, async () => {
        try {
            const res  = await fetch(`backend/api/cartoes.php?id=${_cartaoAtivo}`, { method: 'DELETE' });
            const json = await res.json();
            if (!json.success) throw new Error(json.erro);
            toast(json.msg || 'Cartão desativado.', 'success');
            location.reload();
        } catch (err) {
            toast('Erro: ' + err.message, 'error');
        }
    }, { btnLabel: 'Desativar', icone: 'ban' });
}

function fecharModais() {
    document.getElementById('modalCartao').classList.remove('open');
    document.getElementById('modalPagamento').classList.remove('open');
}

// ── Helpers ───────────────────────────────────────────────
function esc(s) {
    return String(s || '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtData(s) {
    if (!s) return '—';
    const d = s.split('T')[0].split('-');
    return `${d[2]}/${d[1]}/${d[0]}`;
}
function ucFirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }

document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharModais(); });
</script>
