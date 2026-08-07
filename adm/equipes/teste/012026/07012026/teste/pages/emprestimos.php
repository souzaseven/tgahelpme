<?php
// ── Dados iniciais ────────────────────────────────────────────
$p = TABLE_PREFIX;

$filtroStatus = in_array($_GET['status'] ?? '', ['ativo','atrasado','negociado','quitado','todos'])
                ? $_GET['status'] : 'ativos';

$whereStatus = match($filtroStatus) {
    'quitado' => "e.status = 'quitado'",
    'todos'   => '1=1',
    default   => "e.status NOT IN ('quitado')",
};

$emprestimos = $pdo->query(
    "SELECT e.*, ct.nome conta_nome,
            (SELECT ep.data_vencimento FROM `{$p}emprestimos_parcelas` ep
             WHERE ep.emprestimo_id=e.id AND ep.status='pendente'
             ORDER BY ep.numero LIMIT 1) proxima_data,
            (SELECT ep.valor_parcela FROM `{$p}emprestimos_parcelas` ep
             WHERE ep.emprestimo_id=e.id AND ep.status='pendente'
             ORDER BY ep.numero LIMIT 1) proxima_valor,
            (SELECT ep.numero FROM `{$p}emprestimos_parcelas` ep
             WHERE ep.emprestimo_id=e.id AND ep.status='pendente'
             ORDER BY ep.numero LIMIT 1) proxima_numero,
            (SELECT COUNT(*) FROM `{$p}emprestimos_parcelas` ep
             WHERE ep.emprestimo_id=e.id AND ep.status='pendente'
               AND ep.data_vencimento < CURDATE()) parcelas_atraso
     FROM `{$p}emprestimos` e
     LEFT JOIN `{$p}contas` ct ON ct.id=e.conta_debito_id
     WHERE $whereStatus
     ORDER BY e.status DESC, e.data_fim"
)->fetchAll();

$contas = $pdo->query(
    "SELECT id, nome FROM `{$p}contas` WHERE ativo=1 ORDER BY nome"
)->fetchAll();

// KPIs
$totSaldo   = 0; $totParcela = 0; $qtdAtivos = 0; $qtdAtraso = 0;
foreach ($emprestimos as $e) {
    if (in_array($e['status'], ['ativo','atrasado','negociado'])) {
        $totSaldo   += (float)$e['saldo_devedor'];
        $totParcela += (float)$e['valor_parcela'];
        $qtdAtivos++;
        $qtdAtraso  += (int)$e['parcelas_atraso'];
    }
}

// brl()/pct() vêm de backend/helpers/formatacao.php (incluído por index.php)

$tipoLabels = [
    'pessoal'    => 'Pessoal',
    'veiculo'    => 'Veículo',
    'imovel'     => 'Imóvel',
    'consignado' => 'Consignado',
    'cartao'     => 'Cartão',
    'outro'      => 'Outro',
];
$tipoColors = [
    'pessoal'    => 'indigo',
    'veiculo'    => 'cyan',
    'imovel'     => 'violet',
    'consignado' => 'emerald',
    'cartao'     => 'rose',
    'outro'      => 'amber',
];
$statusColors = [
    'ativo'      => ['cls' => 'indigo', 'label' => 'Ativo'],
    'atrasado'   => ['cls' => 'rose',   'label' => 'Atrasado'],
    'quitado'    => ['cls' => 'emerald','label' => 'Quitado'],
    'negociado'  => ['cls' => 'amber',  'label' => 'Negociado'],
];
?>

<style>
/* ── Cards de empréstimo ─────────────────────────────────── */
.emp-card {
    background: var(--bg-800);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    cursor: pointer;
    transition: var(--ease);
    border-left: 4px solid var(--indigo);
}
.emp-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
.emp-card.selected { border-color: var(--indigo); box-shadow: 0 0 0 2px var(--indigo-soft); }
.emp-card.atrasado { border-left-color: var(--rose); }
.emp-card.quitado  { border-left-color: var(--emerald); opacity:.7; }
.emp-card.negociado{ border-left-color: var(--amber); }
.emp-nome { font-weight:700; font-size:.95rem; margin-bottom:.2rem; }
.emp-meta { font-size:.75rem; color:var(--text-400); }
.emp-saldo{ font-size:1.2rem; font-weight:700; color:var(--rose); margin:.6rem 0 .2rem; }
.emp-parc { font-size:.78rem; color:var(--text-400); }
/* ── Seção de detalhe ─────────────────────────────────────── */
#detalheSection { display:none; margin-top:1.25rem; animation:fadeIn .2s ease; }
@keyframes fadeIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:none} }
/* ── Linha da parcela paga ───────────────────────────────── */
tr.pago td { color: var(--text-600); }
tr.atrasado td { background: var(--rose-bg); }
tr.antecipado td { color: var(--text-400); }
.valor-antecipado { font-weight:700; color:var(--emerald); }
.valor-original-riscado { font-size:.72rem; color:var(--text-600); text-decoration:line-through; }
.economia-tag { font-size:.68rem; color:var(--emerald); font-weight:600; }
/* Modal base agora vive em assets/css/main.css (inclui .modal-box.sm). */
/* ── Simulador inline ─────────────────────────────────────── */
#simResult { background:var(--bg-700); border-radius:var(--radius); padding:.875rem 1rem; margin-top:.75rem; display:none; font-size:.825rem; }
#simResult .sim-row { display:flex; justify-content:space-between; padding:.2rem 0; }
#simResult .sim-val { font-weight:700; color:var(--text-100); }
</style>

<!-- ── Cabeçalho ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <div class="page-title">Empréstimos</div>
        <div class="page-sub">Controle de financiamentos e dívidas com tabela Price</div>
    </div>
    <button class="btn btn-primary btn-sm" onclick="abrirModalNovo()">
        <i class="fa-solid fa-plus"></i> Novo Empréstimo
    </button>
</div>

<!-- ── KPIs ──────────────────────────────────────────────── -->
<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.25rem">
    <div class="kpi-card rose">
        <div class="kpi-header">
            <div class="kpi-label">Saldo Devedor</div>
            <div class="kpi-icon rose"><i class="fa-solid fa-landmark"></i></div>
        </div>
        <div class="kpi-value"><?= brl($totSaldo) ?></div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Total em aberto</div>
    </div>
    <div class="kpi-card amber">
        <div class="kpi-header">
            <div class="kpi-label">Parcela Mensal</div>
            <div class="kpi-icon amber"><i class="fa-solid fa-calendar-check"></i></div>
        </div>
        <div class="kpi-value"><?= brl($totParcela) ?></div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Compromisso mensal</div>
    </div>
    <div class="kpi-card slate">
        <div class="kpi-header">
            <div class="kpi-label">Empréstimos</div>
            <div class="kpi-icon slate"><i class="fa-solid fa-file-invoice-dollar"></i></div>
        </div>
        <div class="kpi-value"><?= $qtdAtivos ?></div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Ativos / em andamento</div>
    </div>
    <div class="kpi-card <?= $qtdAtraso > 0 ? 'rose' : 'emerald' ?>">
        <div class="kpi-header">
            <div class="kpi-label">Em Atraso</div>
            <div class="kpi-icon <?= $qtdAtraso > 0 ? 'rose' : 'emerald' ?>">
                <i class="fa-solid fa-<?= $qtdAtraso > 0 ? 'triangle-exclamation' : 'circle-check' ?>"></i>
            </div>
        </div>
        <div class="kpi-value"><?= $qtdAtraso ?></div>
        <div class="kpi-trend <?= $qtdAtraso > 0 ? 'down' : 'up' ?>">
            <i class="fa-solid fa-<?= $qtdAtraso > 0 ? 'arrow-up' : 'arrow-up' ?> fa-xs"></i>
            <?= $qtdAtraso > 0 ? 'Parcela(s) vencida(s)' : 'Tudo em dia' ?>
        </div>
    </div>
</div>

<!-- ── Filtro ─────────────────────────────────────────────── -->
<form method="get" action="" style="margin-bottom:1.25rem">
    <input type="hidden" name="p" value="emprestimos">
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <?php foreach (['ativos'=>'Ativos','quitado'=>'Quitados','todos'=>'Todos'] as $v => $l): ?>
        <button type="submit" name="status" value="<?= $v ?>"
                class="btn btn-sm <?= $filtroStatus === $v ? 'btn-primary' : 'btn-ghost' ?>">
            <?= $l ?>
        </button>
        <?php endforeach ?>
    </div>
</form>

<!-- ── Grade de empréstimos ──────────────────────────────── -->
<?php if (empty($emprestimos)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:3rem;color:var(--text-600)">
        <i class="fa-solid fa-landmark fa-3x" style="margin-bottom:1rem;color:var(--indigo)"></i>
        <div class="fw-700" style="margin-bottom:.5rem">Nenhum empréstimo cadastrado</div>
        <div class="text-sm" style="margin-bottom:1.25rem">Cadastre financiamentos, créditos pessoais, consignados, etc.</div>
        <button class="btn btn-primary" onclick="abrirModalNovo()">
            <i class="fa-solid fa-plus"></i> Cadastrar empréstimo
        </button>
    </div>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem">
    <?php foreach ($emprestimos as $e):
        $pct  = $e['total_parcelas'] > 0
                ? round(($e['parcelas_pagas'] / $e['total_parcelas']) * 100) : 0;
        $stCls= $statusColors[$e['status']] ?? ['cls'=>'indigo','label'=>ucfirst($e['status'])];
        $tCor = $tipoColors[$e['tipo']] ?? 'indigo';
    ?>
    <div class="emp-card <?= htmlspecialchars($e['status']) ?>"
         data-id="<?= $e['id'] ?>"
         role="button"
         tabindex="0"
         aria-label="Ver detalhes do empréstimo <?= htmlspecialchars($e['nome']) ?>"
         onclick="selecionarEmp(<?= $e['id'] ?>)"
         onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();selecionarEmp(<?= $e['id'] ?>)}"
         style="border-left-color:var(--<?= $tCor ?>)">

        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.6rem">
            <div>
                <div class="emp-nome"><?= htmlspecialchars($e['nome']) ?></div>
                <div class="emp-meta">
                    <span class="badge" style="background:var(--<?= $tCor ?>-soft,rgba(99,102,241,.15));color:var(--<?= $tCor ?>)">
                        <?= $tipoLabels[$e['tipo']] ?? $e['tipo'] ?>
                    </span>
                    <?= $e['credor'] ? ' &nbsp;·&nbsp; '.htmlspecialchars($e['credor']) : '' ?>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:.4rem">
                <button class="btn btn-ghost btn-sm"
                        style="font-size:.72rem;padding:.2rem .55rem"
                        onclick="event.stopPropagation();abrirEditarCard(<?= $e['id'] ?>)"
                        title="Editar empréstimo">
                    <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button class="btn btn-ghost btn-sm"
                        style="font-size:.72rem;padding:.2rem .55rem;color:var(--rose)"
                        onclick="event.stopPropagation();excluirEmpCard(<?= $e['id'] ?>, '<?= addslashes(htmlspecialchars($e['nome'])) ?>')"
                        title="Excluir empréstimo">
                    <i class="fa-solid fa-trash"></i>
                </button>
                <span class="badge <?= $stCls['cls'] ?>"><?= $stCls['label'] ?></span>
            </div>
        </div>

        <div class="emp-saldo"><?= brl((float)$e['saldo_devedor']) ?></div>
        <div class="emp-parc">
            Parcela: <?= brl((float)$e['valor_parcela']) ?> &nbsp;·&nbsp;
            <?= $e['taxa_juros_mensal'] > 0 ? pct((float)$e['taxa_juros_mensal'], 2).' a.m.' : 'Sem juros' ?>
        </div>

        <div class="progress-wrap" style="margin-top:.875rem">
            <div class="progress-header">
                <span class="progress-label text-xs"><?= $e['parcelas_pagas'] ?>/<?= $e['total_parcelas'] ?> parcelas pagas</span>
                <span class="progress-pct text-xs <?= $pct >= 70 ? 'text-emerald' : '' ?>"><?= $pct ?>%</span>
            </div>
            <div class="progress-bar-bg">
                <div class="progress-bar-fill <?= $tCor ?>" style="width:<?= $pct ?>%"></div>
            </div>
        </div>

        <?php if ($e['proxima_data']): ?>
        <div class="d-flex justify-between text-xs text-muted" style="margin-top:.5rem">
            <span>Próx. parcela <?= $e['proxima_numero'] ?>: <?= date('d/m/Y', strtotime($e['proxima_data'])) ?></span>
            <span class="fw-600"><?= brl((float)$e['proxima_valor']) ?></span>
        </div>
        <?php endif ?>

        <?php if ((int)$e['parcelas_atraso'] > 0): ?>
        <div class="text-xs" style="color:var(--rose);margin-top:.35rem">
            <i class="fa-solid fa-triangle-exclamation fa-xs"></i>
            <?= $e['parcelas_atraso'] ?> parcela(s) em atraso
        </div>
        <?php endif ?>
    </div>
    <?php endforeach ?>
</div>

<!-- ── Detalhe: tabela de amortização ────────────────────── -->
<div id="detalheSection">
    <!-- Aviso sobre despesas sincronizadas -->
    <div id="detalheAviso" style="display:none;background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.25);border-radius:var(--radius);padding:.75rem 1rem;margin-bottom:.75rem">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem">
            <div class="text-sm">
                <i class="fa-solid fa-receipt" style="color:var(--indigo);margin-right:.4rem"></i>
                <span id="detalheAvisoTxt">As parcelas foram criadas como <strong>despesas pendentes</strong> em cada mês de vencimento.</span>
            </div>
            <a id="detalheAvisoLink" href="?p=despesas" class="btn btn-sm btn-ghost" style="font-size:.75rem;color:var(--indigo)">
                <i class="fa-solid fa-arrow-right fa-xs"></i> Ver no mês da próxima parcela
            </a>
        </div>
        <div class="text-xs text-muted" style="margin-top:.35rem">
            <i class="fa-solid fa-forward fa-xs" style="color:var(--amber)"></i>
            Parcelas futuras: clique <strong style="color:var(--amber)">⚡ Antecipar</strong> para pagar antes do vencimento com desconto.
            &nbsp;|&nbsp;
            <i class="fa-solid fa-check fa-xs" style="color:var(--emerald)"></i>
            Parcelas vencidas: clique <strong style="color:var(--emerald)">✓ Pagar</strong> para registrar o pagamento.
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="flex-wrap:wrap;gap:.75rem">
            <div>
                <div class="card-title" id="detNome">—</div>
                <div class="card-subtitle" id="detMeta">—</div>
            </div>
            <div class="d-flex gap-1" style="flex-wrap:wrap">
                <button class="btn btn-ghost btn-sm" onclick="abrirModalEditar()">
                    <i class="fa-solid fa-pen-to-square"></i> Editar
                </button>
                <button class="btn btn-ghost btn-sm" style="color:var(--rose)" onclick="excluirEmp()">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Vencimento</th>
                        <th class="text-right">Parcela</th>
                        <th class="text-right">Juros</th>
                        <th class="text-right">Amortização</th>
                        <th class="text-right">Saldo Devedor</th>
                        <th>Status</th>
                        <th style="width:80px"></th>
                    </tr>
                </thead>
                <tbody id="detTbody">
                    <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text-600)">
                        Clique em um empréstimo para ver o cronograma.
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif ?>

<!-- ── Modal: Novo Empréstimo ─────────────────────────────── -->
<div id="modalNovo" class="modal-overlay" onclick="if(event.target===this)fecharModal('modalNovo')">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Novo Empréstimo</div>
            <button type="button" class="btn-icon" onclick="fecharModal('modalNovo')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formNovo" onsubmit="criarEmp(event)">
            <div class="modal-body">

                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Nome / Identificação <span style="color:var(--rose)">*</span></label>
                    <input type="text" id="nNome" class="form-control"
                           placeholder="Ex: Financiamento do carro, Crédito pessoal..." required>
                </div>

                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Tipo</label>
                        <select id="nTipo" class="form-control">
                            <option value="pessoal">Pessoal</option>
                            <option value="veiculo">Veículo</option>
                            <option value="imovel">Imóvel</option>
                            <option value="consignado">Consignado</option>
                            <option value="cartao">Cartão</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Credor / Banco</label>
                        <input type="text" id="nCreder" class="form-control" placeholder="Ex: Banco do Brasil, Caixa...">
                    </div>
                </div>

                <div class="form-grid form-grid-3" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Valor Contratado (R$) <span style="color:var(--rose)">*</span></label>
                        <input type="text" id="nValor" class="form-control"
                               placeholder="0,00" inputmode="numeric" data-currency required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Taxa Mensal (%)</label>
                        <input type="number" id="nTaxa" class="form-control" step="0.01" min="0"
                               placeholder="0,00" oninput="simular()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nº de Parcelas <span style="color:var(--rose)">*</span></label>
                        <input type="number" id="nParcelas" class="form-control" min="1" max="480" required
                               oninput="simular()">
                    </div>
                </div>

                <!-- Simulador inline -->
                <div id="simResult">
                    <div class="sim-row">
                        <span>Parcela mensal (Price)</span>
                        <span class="sim-val" id="simPMT">—</span>
                    </div>
                    <div class="sim-row">
                        <span>Total a pagar</span>
                        <span class="sim-val" id="simTotal">—</span>
                    </div>
                    <div class="sim-row">
                        <span>Total de juros</span>
                        <span class="sim-val text-rose" id="simJuros">—</span>
                    </div>
                    <div class="sim-row">
                        <span>CET estimado (a.m.)</span>
                        <span class="sim-val" id="simCET">—</span>
                    </div>
                </div>

                <div class="form-grid form-grid-2" style="margin-top:1.1rem;margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Data de início <span style="color:var(--rose)">*</span></label>
                        <input type="date" id="nDataIni" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dia de vencimento</label>
                        <input type="number" id="nDiaVenc" class="form-control" value="10" min="1" max="31">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Conta para débito automático</label>
                    <select id="nConta" class="form-control" onchange="atualizarCheckboxEntrada()">
                        <option value="">— Sem conta vinculada —</option>
                        <?php foreach ($contas as $ct): ?>
                        <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['nome']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Observação</label>
                    <textarea id="nObs" class="form-control" rows="2" placeholder="(opcional)"></textarea>
                </div>

                <!-- Registrar entrada -->
                <div style="background:var(--bg-700);border-radius:var(--radius);padding:.75rem 1rem">
                    <label style="display:flex;align-items:flex-start;gap:.6rem;cursor:pointer">
                        <input type="checkbox" id="nRegistrarEntrada" style="margin-top:.15rem;flex-shrink:0">
                        <div>
                            <div class="text-sm fw-600">Registrar recebimento do valor na conta</div>
                            <div class="text-xs text-muted" style="margin-top:.15rem">
                                Cria um lançamento de <strong>receita</strong> pelo valor contratado e credita na conta selecionada
                            </div>
                        </div>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="fecharModal('modalNovo')">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnCriar">
                    <i class="fa-solid fa-floppy-disk"></i> Cadastrar e Gerar Tabela
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Editar Empréstimo (completo) ───────────────── -->
<div id="modalEditar" class="modal-overlay" onclick="if(event.target===this)fecharModal('modalEditar')">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Editar Empréstimo</div>
            <button type="button" class="btn-icon" onclick="fecharModal('modalEditar')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formEditar" onsubmit="editarCompleto(event)">
            <div class="modal-body">
                <input type="hidden" id="eId">

                <!-- Aviso: parcelas já pagas serão removidas -->
                <div id="eAvisoRecalculo" style="display:none;background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);border-radius:var(--radius);padding:.75rem 1rem;margin-bottom:1.25rem">
                    <div style="font-weight:700;color:var(--amber);font-size:.85rem">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Atenção: <span id="eQtdPagas"></span> parcela(s) já paga(s)
                    </div>
                    <div class="text-xs text-muted" style="margin-top:.3rem">
                        Ao salvar, toda a tabela de amortização será regenerada do zero.
                        As parcelas pagas serão removidas. Lançamentos em conta já realizados
                        <strong>não</strong> serão revertidos automaticamente.
                    </div>
                </div>

                <!-- Identificação -->
                <div class="text-xs fw-600 text-muted" style="margin-bottom:.65rem;text-transform:uppercase;letter-spacing:.05em">Identificação</div>

                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Nome / Identificação <span style="color:var(--rose)">*</span></label>
                    <input type="text" id="eNome" class="form-control" required>
                </div>

                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Tipo</label>
                        <select id="eTipo" class="form-control">
                            <option value="pessoal">Pessoal</option>
                            <option value="veiculo">Veículo</option>
                            <option value="imovel">Imóvel</option>
                            <option value="consignado">Consignado</option>
                            <option value="cartao">Cartão</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Credor / Banco</label>
                        <input type="text" id="eCreder" class="form-control">
                    </div>
                </div>

                <!-- Dados financeiros -->
                <div class="text-xs fw-600 text-muted" style="margin-bottom:.65rem;margin-top:1.25rem;text-transform:uppercase;letter-spacing:.05em">Dados Financeiros</div>

                <div class="form-grid form-grid-3" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Valor Contratado (R$) <span style="color:var(--rose)">*</span></label>
                        <input type="text" id="eValor" class="form-control" data-currency inputmode="numeric" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Taxa Mensal (%)</label>
                        <input type="number" id="eTaxa" class="form-control" step="0.0001" min="0" placeholder="0,00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nº de Parcelas <span style="color:var(--rose)">*</span></label>
                        <input type="number" id="eParcelas" class="form-control" min="1" max="480" required>
                    </div>
                </div>

                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Data de início <span style="color:var(--rose)">*</span></label>
                        <input type="date" id="eDataIni" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dia de vencimento</label>
                        <input type="number" id="eDiaVenc" class="form-control" min="1" max="31">
                    </div>
                </div>

                <!-- Configurações -->
                <div class="text-xs fw-600 text-muted" style="margin-bottom:.65rem;margin-top:1.25rem;text-transform:uppercase;letter-spacing:.05em">Configurações</div>

                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select id="eStatus" class="form-control">
                            <option value="ativo">Ativo</option>
                            <option value="atrasado">Atrasado</option>
                            <option value="negociado">Negociado</option>
                            <option value="quitado">Quitado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Conta para débito</label>
                        <select id="eConta" class="form-control">
                            <option value="">— Sem conta —</option>
                            <?php foreach ($contas as $ct): ?>
                            <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['nome']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Observação</label>
                    <textarea id="eObs" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="fecharModal('modalEditar')">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnEditarSalvar">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar e Recalcular
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Pagar Parcela ──────────────────────────────── -->
<div id="modalPagar" class="modal-overlay" onclick="if(event.target===this)fecharModal('modalPagar')">
    <div class="modal-box sm">
        <div class="modal-header">
            <div class="modal-title">Registrar Pagamento</div>
            <button type="button" class="btn-icon" onclick="fecharModal('modalPagar')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formPagar" onsubmit="pagarParcela(event)">
            <div class="modal-body">
                <input type="hidden" id="pgParcelaId">

                <!-- Aviso de antecipação -->
                <div id="pgAntecipadoAviso" style="display:none;background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);border-radius:var(--radius);padding:.75rem 1rem;margin-bottom:1.1rem">
                    <div style="font-weight:700;color:var(--amber);font-size:.85rem">⚡ Pagamento antecipado</div>
                    <div class="text-xs text-muted" style="margin-top:.2rem">
                        Esta parcela vence em <strong id="pgVencStr"></strong>. Você está pagando antes do prazo.
                    </div>
                </div>

                <div class="card" style="margin-bottom:1.1rem;background:var(--bg-700)">
                    <div class="card-body" style="padding:.875rem">
                        <div class="text-xs text-muted">Parcela</div>
                        <div class="fw-700" id="pgInfo" style="margin-top:.2rem">—</div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Data do pagamento <span style="color:var(--rose)">*</span></label>
                    <input type="date" id="pgData" class="form-control" required oninput="atualizarAvisoAntecip()">
                </div>

                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">
                        Valor pago (R$)
                        <span id="pgValorPagoLabel" style="display:none;color:var(--amber);font-size:.75rem;margin-left:.4rem">— antecipação, pode ter desconto</span>
                    </label>
                    <input type="text" id="pgValorPago" class="form-control" data-currency inputmode="numeric">
                    <div class="text-xs text-muted" style="margin-top:.3rem">Valor original: <strong id="pgValorOriginal">—</strong></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Conta de débito</label>
                    <select id="pgConta" class="form-control">
                        <option value="">— Não débitar —</option>
                        <?php foreach ($contas as $ct): ?>
                        <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['nome']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="fecharModal('modalPagar')">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnPagar">
                    <i class="fa-solid fa-check"></i> Confirmar Pagamento
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const _EMPS = <?= json_encode(array_values($emprestimos), JSON_UNESCAPED_UNICODE) ?>;
let _empAtivo = null;
let _parcelas = [];

// ── Selecionar empréstimo ─────────────────────────────────
function selecionarEmp(id) {
    document.querySelectorAll('.emp-card').forEach(c => c.classList.remove('selected'));
    const el = document.querySelector(`.emp-card[data-id="${id}"]`);
    if (el) el.classList.add('selected');
    _empAtivo = _EMPS.find(e => +e.id === +id);
    carregarParcelas(id);
}

async function carregarParcelas(id) {
    document.getElementById('detalheSection').style.display = '';
    document.getElementById('detTbody').innerHTML =
        `<tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text-600)">
            <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
        </td></tr>`;

    if (_empAtivo) {
        document.getElementById('detNome').textContent = _empAtivo.nome;
        document.getElementById('detMeta').textContent =
            `${_empAtivo.parcelas_pagas}/${_empAtivo.total_parcelas} parcelas · Saldo: ${brl(_empAtivo.saldo_devedor)} · Taxa: ${_empAtivo.taxa_juros_mensal > 0 ? _empAtivo.taxa_juros_mensal + '% a.m.' : 'Sem juros'}`;
    }

    try {
        const res  = await fetch(`backend/api/emprestimos.php?acao=parcelas&id=${id}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        _parcelas = json.parcelas;
        renderParcelas(json.parcelas);
        mostrarAvisoDetalhe(json.parcelas);
    } catch (err) {
        toast('Erro: ' + err.message, 'error');
    }
}

function mostrarAvisoDetalhe(parcelas) {
    const avisoEl = document.getElementById('detalheAviso');
    const linkEl  = document.getElementById('detalheAvisoLink');
    if (!avisoEl) return;

    // Encontra a próxima parcela pendente
    const hoje   = new Date().toISOString().split('T')[0];
    const proxima = parcelas.find(ep => ep.status === 'pendente' && ep.data_vencimento >= hoje)
                 || parcelas.find(ep => ep.status === 'pendente');

    avisoEl.style.display = '';

    if (proxima && proxima.data_vencimento) {
        const d    = proxima.data_vencimento.split('-');
        const mes  = parseInt(d[1]);
        const ano  = parseInt(d[0]);
        const mNome = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'][mes-1];
        linkEl.href        = `?p=despesas&mes=${mes}&ano=${ano}`;
        linkEl.textContent = `Ver despesas de ${mNome}/${ano}`;
        linkEl.innerHTML   = `<i class="fa-solid fa-arrow-right fa-xs"></i> Ver despesas de ${mNome}/${ano}`;
    } else {
        linkEl.href = '?p=despesas';
        linkEl.innerHTML = '<i class="fa-solid fa-arrow-right fa-xs"></i> Ver no extrato de despesas';
    }
}

function renderParcelas(parcelas) {
    const tbody = document.getElementById('detTbody');
    if (!parcelas.length) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text-600)">
            Nenhuma parcela encontrada.
        </td></tr>`;
        return;
    }

    const hoje = new Date().toISOString().split('T')[0];
    tbody.innerHTML = parcelas.map(ep => {
        const atrasada    = ep.status === 'pendente' && ep.data_vencimento < hoje;
        const isPago      = ep.status === 'pago';
        const isAntecip   = ep.status === 'antecipado';
        const trCls       = isPago ? 'pago' : (isAntecip ? 'antecipado' : (atrasada ? 'atrasado' : ''));

        const stBadge = isPago
            ? `<span class="badge pago">Pago</span>`
            : isAntecip
                ? `<span class="badge" style="background:var(--amber-soft,rgba(245,158,11,.15));color:var(--amber)">⚡ Antecipado</span>`
                : atrasada
                    ? `<span class="badge despesa">Atrasado</span>`
                    : `<span class="badge pendente">Pendente</span>`;

        // Célula de valor: mostra valor pago + original riscado quando antecipado com desconto
        let valorCell;
        if (isAntecip && ep.valor_pago !== null && parseFloat(ep.valor_pago) !== parseFloat(ep.valor_parcela)) {
            const economia = parseFloat(ep.valor_parcela) - parseFloat(ep.valor_pago);
            valorCell = `<div class="valor-antecipado">${brl(ep.valor_pago)}</div>
                         <div class="valor-original-riscado">${brl(ep.valor_parcela)}</div>
                         <div class="economia-tag">-${brl(economia)}</div>`;
        } else if (isAntecip && ep.valor_pago !== null) {
            valorCell = `<div class="valor-antecipado">${brl(ep.valor_pago)}</div>
                         <div class="text-xs text-muted">(original: ${brl(ep.valor_parcela)})</div>`;
        } else {
            valorCell = `<span class="fw-600">${brl(ep.valor_parcela)}</span>`;
        }

        // Data: mostra data de pagamento quando pago/antecipado
        const dataCel = (isPago || isAntecip) && ep.data_pagamento
            ? `<div class="text-sm text-muted">${fmtData(ep.data_vencimento)}</div>
               <div class="text-xs" style="color:var(--${isAntecip?'amber':'text-500'})">pago ${fmtData(ep.data_pagamento)}</div>`
            : `<span class="text-sm text-muted">${fmtData(ep.data_vencimento)}</span>`;

        // Futuro = antecipar (âmbar) | vencido/hoje = pagar (verde)
        const isFuturo = ep.data_vencimento > hoje;
        const btn = !isPago && !isAntecip
            ? isFuturo
                ? `<button class="btn btn-sm" onclick="abrirPagar(${ep.id})"
                           style="font-size:.72rem;padding:.25rem .6rem;background:rgba(245,158,11,.15);color:var(--amber);border:1px solid rgba(245,158,11,.3)">
                       <i class="fa-solid fa-forward"></i> Antecipar
                   </button>`
                : `<button class="btn btn-ghost btn-sm" onclick="abrirPagar(${ep.id})"
                           style="font-size:.72rem;padding:.25rem .6rem;color:var(--emerald)">
                       <i class="fa-solid fa-check"></i> Pagar
                   </button>`
            : '';

        return `<tr class="${trCls}">
            <td class="text-sm fw-600">${ep.numero}</td>
            <td>${dataCel}</td>
            <td class="text-right">${valorCell}</td>
            <td class="text-right text-rose text-sm">${brl(ep.valor_juros)}</td>
            <td class="text-right text-emerald text-sm">${brl(ep.valor_amortizacao)}</td>
            <td class="text-right text-muted text-sm">${brl(ep.saldo_devedor)}</td>
            <td>${stBadge}</td>
            <td>${btn}</td>
        </tr>`;
    }).join('');
}

// ── Simulador de parcela ──────────────────────────────────
function simular() {
    const pv = parseCurrency(document.getElementById('nValor').value) || 0;
    const i  = parseFloat(document.getElementById('nTaxa').value)    || 0;
    const n  = parseInt(document.getElementById('nParcelas').value)  || 0;
    const div = document.getElementById('simResult');

    if (!pv || !n) { div.style.display = 'none'; return; }

    let pmt;
    if (i <= 0) {
        pmt = pv / n;
    } else {
        const r  = i / 100;
        const f  = Math.pow(1 + r, n);
        pmt = pv * (r * f) / (f - 1);
    }

    const total  = pmt * n;
    const juros  = total - pv;

    div.style.display = '';
    document.getElementById('simPMT').textContent   = brl(pmt);
    document.getElementById('simTotal').textContent  = brl(total);
    document.getElementById('simJuros').textContent  = brl(juros);
    document.getElementById('simCET').textContent    = i > 0 ? (i).toFixed(2).replace('.',',') + '%' : '0%';
}

// ── Criar empréstimo ──────────────────────────────────────
async function criarEmp(e) {
    e.preventDefault();
    const btn = document.getElementById('btnCriar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Gerando tabela...';

    const payload = {
        acao:             'criar',
        nome:             document.getElementById('nNome').value.trim(),
        tipo:             document.getElementById('nTipo').value,
        credor:           document.getElementById('nCreder').value.trim(),
        valor_contratado: parseCurrency(document.getElementById('nValor').value),
        taxa_juros_mensal:parseFloat(document.getElementById('nTaxa').value) || 0,
        total_parcelas:   parseInt(document.getElementById('nParcelas').value),
        data_inicio:      document.getElementById('nDataIni').value,
        dia_vencimento:   parseInt(document.getElementById('nDiaVenc').value),
        conta_debito_id:  document.getElementById('nConta').value || null,
        observacao:       document.getElementById('nObs').value.trim(),
        registrar_entrada:document.getElementById('nRegistrarEntrada').checked,
    };

    try {
        const res  = await fetch('backend/api/emprestimos.php', {
            method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        fecharModal('modalNovo');
        location.reload();
    } catch (err) {
        toast('Erro: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Cadastrar e Gerar Tabela';
    }
}

// ── Editar ────────────────────────────────────────────────
function abrirEditarCard(id) {
    _empAtivo = _EMPS.find(e => +e.id === +id) || null;
    if (_empAtivo) abrirModalEditar();
}

function excluirEmpCard(id, nome) {
    confirmar(
        'Excluir empréstimo',
        `Excluir "${nome}"? Todas as parcelas e despesas vinculadas serão removidas.`,
        async () => {
            try {
                const res  = await fetch(`backend/api/emprestimos.php?id=${id}`, { method: 'DELETE' });
                const json = await res.json();
                if (!json.success) throw new Error(json.erro);
                toast(json.msg, 'success');
                location.reload();
            } catch (err) {
                toast('Erro: ' + err.message, 'error');
            }
        },
        { btnLabel: 'Excluir', btnCor: 'var(--rose)' }
    );
}

function abrirModalEditar() {
    if (!_empAtivo) return;
    const e = _empAtivo;

    document.getElementById('eId').value       = e.id;
    document.getElementById('eNome').value     = e.nome              || '';
    document.getElementById('eTipo').value     = e.tipo              || 'pessoal';
    document.getElementById('eCreder').value   = e.credor            || '';
    document.getElementById('eValor').value    = brlMask(parseFloat(e.valor_contratado || 0));
    document.getElementById('eTaxa').value     = e.taxa_juros_mensal || 0;
    document.getElementById('eParcelas').value = e.total_parcelas    || '';
    document.getElementById('eDataIni').value  = e.data_inicio       || '';
    document.getElementById('eDiaVenc').value  = e.dia_vencimento    || 10;
    document.getElementById('eStatus').value   = e.status            || 'ativo';
    document.getElementById('eConta').value    = e.conta_debito_id   || '';
    document.getElementById('eObs').value      = e.observacao        || '';

    const pagas = parseInt(e.parcelas_pagas || 0);
    document.getElementById('eAvisoRecalculo').style.display = pagas > 0 ? '' : 'none';
    document.getElementById('eQtdPagas').textContent         = pagas;

    document.getElementById('modalEditar').classList.add('open');
}

function editarCompleto(e) {
    e.preventDefault();

    const executar = async () => {
        const btn = document.getElementById('btnEditarSalvar');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Salvando...';

        const payload = {
            acao:             'editar_completo',
            id:               parseInt(document.getElementById('eId').value),
            nome:             document.getElementById('eNome').value.trim(),
            tipo:             document.getElementById('eTipo').value,
            credor:           document.getElementById('eCreder').value.trim(),
            valor_contratado: parseCurrency(document.getElementById('eValor').value),
            taxa_juros_mensal:parseFloat(document.getElementById('eTaxa').value) || 0,
            total_parcelas:   parseInt(document.getElementById('eParcelas').value),
            data_inicio:      document.getElementById('eDataIni').value,
            dia_vencimento:   parseInt(document.getElementById('eDiaVenc').value) || 10,
            status:           document.getElementById('eStatus').value,
            conta_debito_id:  document.getElementById('eConta').value || null,
            observacao:       document.getElementById('eObs').value.trim(),
        };

        try {
            const res  = await fetch('backend/api/emprestimos.php', {
                method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.erro);
            toast(json.msg, 'success');
            fecharModal('modalEditar');
            location.reload();
        } catch (err) {
            toast('Erro: ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar e Recalcular';
        }
    };

    const pagas = parseInt(_empAtivo?.parcelas_pagas || 0);
    if (pagas > 0) {
        confirmar(
            'Recalcular empréstimo',
            `Este empréstimo tem ${pagas} parcela(s) já paga(s). Ao confirmar, toda a tabela será gerada do zero e as parcelas pagas serão removidas. Lançamentos em conta já realizados não serão revertidos. Continuar?`,
            executar,
            { btnLabel: 'Sim, recalcular', btnCor: 'var(--amber)' }
        );
    } else {
        executar();
    }
}

// ── Pagar parcela ─────────────────────────────────────────
let _pgEpAtivo = null;

function abrirPagar(parcelaId) {
    const ep = _parcelas.find(p => +p.id === +parcelaId);
    _pgEpAtivo = ep || null;

    document.getElementById('pgParcelaId').value = parcelaId;

    const hoje = new Date().toISOString().split('T')[0];
    document.getElementById('pgData').value  = hoje;
    document.getElementById('pgConta').value = _empAtivo?.conta_debito_id || '';

    document.getElementById('pgInfo').textContent =
        ep ? `Parcela ${ep.numero} — Vence ${fmtData(ep.data_vencimento)} — ${brl(ep.valor_parcela)}` : '';

    // Preenche campo valor pago com valor original
    if (ep) {
        document.getElementById('pgValorPago').value          = brlMask(parseFloat(ep.valor_parcela));
        document.getElementById('pgValorOriginal').textContent = brl(ep.valor_parcela);
    }

    atualizarAvisoAntecip();
    document.getElementById('modalPagar').classList.add('open');
}

function atualizarAvisoAntecip() {
    if (!_pgEpAtivo) return;
    const dataPag  = document.getElementById('pgData').value;
    const antecip  = dataPag && dataPag < _pgEpAtivo.data_vencimento;
    document.getElementById('pgAntecipadoAviso').style.display    = antecip ? '' : 'none';
    document.getElementById('pgVencStr').textContent               = fmtData(_pgEpAtivo.data_vencimento);
    document.getElementById('pgValorPagoLabel').style.display      = antecip ? '' : 'none';
}

async function pagarParcela(e) {
    e.preventDefault();
    const btn = document.getElementById('btnPagar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    const valorPago = parseCurrency(document.getElementById('pgValorPago').value);

    const payload = {
        acao:           'pagar_parcela',
        parcela_id:     parseInt(document.getElementById('pgParcelaId').value),
        data_pagamento: document.getElementById('pgData').value,
        conta_id:       document.getElementById('pgConta').value || null,
        valor_pago:     valorPago > 0 ? valorPago : null,
    };

    try {
        const res  = await fetch('backend/api/emprestimos.php', {
            method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        fecharModal('modalPagar');
        if (json.quitado) { location.reload(); } else { carregarParcelas(_empAtivo.id); }
    } catch (err) {
        toast('Erro: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Confirmar Pagamento';
    }
}

// ── Excluir ───────────────────────────────────────────────
function excluirEmp() {
    if (!_empAtivo) return;
    confirmar('Excluir empréstimo', `Excluir "${_empAtivo.nome}"? Todas as parcelas serão removidas.`, async () => {
        try {
            const res  = await fetch(`backend/api/emprestimos.php?id=${_empAtivo.id}`, {method:'DELETE'});
            const json = await res.json();
            if (!json.success) throw new Error(json.erro);
            toast(json.msg, 'success');
            location.reload();
        } catch (err) {
            toast('Erro: ' + err.message, 'error');
        }
    });
}

function atualizarCheckboxEntrada() {
    const temConta = !!document.getElementById('nConta').value;
    const cb       = document.getElementById('nRegistrarEntrada');
    cb.disabled    = !temConta;
    if (!temConta) cb.checked = false;
}

function abrirModalNovo() {
    document.getElementById('formNovo').reset();
    document.getElementById('nDataIni').value = new Date().toISOString().split('T')[0];
    document.getElementById('simResult').style.display = 'none';
    document.getElementById('nRegistrarEntrada').checked  = false;
    document.getElementById('nRegistrarEntrada').disabled = true;
    document.getElementById('modalNovo').classList.add('open');
    setTimeout(() => document.getElementById('nNome').focus(), 80);
}

function fecharModal(id) {
    document.getElementById(id).classList.remove('open');
}

// fmtData() vem de assets/js/app.js (carregado globalmente por index.php)

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') ['modalNovo','modalEditar','modalPagar'].forEach(fecharModal);
});

// Recalcula simulador após atualização da máscara de valor
document.addEventListener('DOMContentLoaded', () => {
    const nV = document.getElementById('nValor');
    if (nV) {
        const ts = () => setTimeout(simular, 0);
        nV.addEventListener('keydown', ts);
        nV.addEventListener('paste', ts);
    }
});
</script>
