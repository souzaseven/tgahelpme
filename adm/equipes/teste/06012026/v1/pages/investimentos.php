<?php
// ── Dados iniciais ────────────────────────────────────────────
$p = TABLE_PREFIX;

$invs = $pdo->query(
    "SELECT i.*, ct.nome conta_nome
     FROM `{$p}investimentos` i
     LEFT JOIN `{$p}contas` ct ON ct.id = i.conta_id
     WHERE i.ativo = 1
     ORDER BY i.valor_atual DESC"
)->fetchAll();

$contas = $pdo->query(
    "SELECT id, nome FROM `{$p}contas` WHERE ativo=1 ORDER BY nome"
)->fetchAll();

// KPIs
$totAplicado = array_sum(array_column($invs, 'valor_aplicado'));
$totAtual    = array_sum(array_column($invs, 'valor_atual'));
$rendimento  = $totAtual - $totAplicado;
$rentab      = $totAplicado > 0 ? round(($rendimento / $totAplicado) * 100, 2) : 0;

// Dados para o gráfico de alocação por tipo
$porTipo = [];
foreach ($invs as $inv) {
    $t = $inv['tipo'];
    $porTipo[$t] = ($porTipo[$t] ?? 0) + (float)$inv['valor_atual'];
}
arsort($porTipo);

function brlI(float $v): string { return 'R$ '.number_format($v,2,',','.'); }
function pctI(float $v): string { return number_format($v,2,',','.').'%'; }

$tipoLabels = [
    'tesouro'   => 'Tesouro Direto',
    'cdb'       => 'CDB',
    'lci'       => 'LCI',
    'lca'       => 'LCA',
    'lc'        => 'LC',
    'debenture' => 'Debênture',
    'fundo'     => 'Fundo',
    'acao'      => 'Ações',
    'fii'       => 'FII',
    'etf'       => 'ETF',
    'cripto'    => 'Cripto',
    'poupanca'  => 'Poupança',
    'outro'     => 'Outro',
];
$tipoCores = [
    'tesouro'   => '#10b981',
    'cdb'       => '#06b6d4',
    'lci'       => '#0ea5e9',
    'lca'       => '#3b82f6',
    'lc'        => '#6366f1',
    'debenture' => '#8b5cf6',
    'fundo'     => '#a855f7',
    'acao'      => '#f97316',
    'fii'       => '#f59e0b',
    'etf'       => '#eab308',
    'cripto'    => '#ef4444',
    'poupanca'  => '#84cc16',
    'outro'     => '#64748b',
];

$rentTipoLabels = [
    'prefixado' => 'Prefixado',
    'pos_cdi'   => '% CDI',
    'pos_ipca'  => 'IPCA+',
    'pos_selic' => 'Selic',
    'variavel'  => 'Variável',
];
$liquidezLabels = [
    'diaria'    => 'D+0',
    '30d'       => 'D+30',
    '60d'       => 'D+60',
    '90d'       => 'D+90',
    '180d'      => 'D+180',
    '365d'      => 'D+365',
    'vencimento'=> 'No vencimento',
];

// Grupo para o formulário (renda fixa vs variável)
$rendaFixa = ['tesouro','cdb','lci','lca','lc','debenture','poupanca'];
?>

<style>
/* ── Linha de rentabilidade ───────────────────────────────── */
.rent-pos { color: var(--emerald); font-weight:700; }
.rent-neg { color: var(--rose);    font-weight:700; }
.rent-zer { color: var(--text-400);}
/* ── Detalhe ──────────────────────────────────────────────── */
#detalheSection { display:none; margin-top:1.25rem; animation:fadeIn .2s ease; }
@keyframes fadeIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:none} }
/* ── Modal ───────────────────────────────────────────────── */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.65); z-index:1000; align-items:center; justify-content:center; padding:1rem; }
.modal-overlay.open { display:flex; }
.modal-box { background:var(--bg-800); border:1px solid var(--border); border-radius:var(--radius-lg); width:100%; max-width:580px; max-height:92vh; display:flex; flex-direction:column; box-shadow:var(--shadow-lg); animation:modalIn .18s ease; }
.modal-box.sm { max-width:400px; }
@keyframes modalIn { from{opacity:0;transform:translateY(-10px) scale(.97)} to{opacity:1;transform:none} }
.modal-header { padding:1.25rem 1.5rem 1rem; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
.modal-title  { font-size:1rem; font-weight:700; }
.modal-body   { padding:1.5rem; overflow-y:auto; flex:1; }
.modal-footer { padding:1rem 1.5rem; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:.75rem; flex-shrink:0; }
/* ── Tipo toggle ─────────────────────────────────────────── */
.tipo-group { display:flex; gap:.4rem; flex-wrap:wrap; margin-bottom:1.1rem; }
.tipo-btn { padding:.3rem .7rem; border-radius:999px; font-size:.75rem; font-weight:600; cursor:pointer; border:1px solid var(--border); background:var(--bg-700); color:var(--text-400); transition:var(--ease); }
.tipo-btn.active { border-color:transparent; color:#fff; }
/* ── Row clicável ────────────────────────────────────────── */
tbody tr.inv-row { cursor:pointer; }
tbody tr.inv-row.selected td { background:var(--indigo-soft); }
</style>

<!-- ── Cabeçalho ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <div class="page-title">Investimentos</div>
        <div class="page-sub">Carteira consolidada — <?= count($invs) ?> ativo<?= count($invs)!==1?'s':'' ?></div>
    </div>
    <button class="btn btn-primary btn-sm" onclick="abrirModalNovo()">
        <i class="fa-solid fa-plus"></i> Novo Investimento
    </button>
</div>

<!-- ── KPIs ──────────────────────────────────────────────── -->
<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.25rem">
    <div class="kpi-card violet">
        <div class="kpi-header">
            <div class="kpi-label">Total Investido</div>
            <div class="kpi-icon violet"><i class="fa-solid fa-vault"></i></div>
        </div>
        <div class="kpi-value"><?= brlI($totAplicado) ?></div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Valor aportado</div>
    </div>
    <div class="kpi-card <?= $totAtual >= $totAplicado ? 'emerald' : 'rose' ?>">
        <div class="kpi-header">
            <div class="kpi-label">Valor Atual</div>
            <div class="kpi-icon <?= $totAtual >= $totAplicado ? 'emerald' : 'rose' ?>">
                <i class="fa-solid fa-chart-line"></i>
            </div>
        </div>
        <div class="kpi-value"><?= brlI($totAtual) ?></div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Cotação atual</div>
    </div>
    <div class="kpi-card <?= $rendimento >= 0 ? 'emerald' : 'rose' ?>">
        <div class="kpi-header">
            <div class="kpi-label">Rendimento</div>
            <div class="kpi-icon <?= $rendimento >= 0 ? 'emerald' : 'rose' ?>">
                <i class="fa-solid fa-<?= $rendimento >= 0 ? 'arrow-trend-up' : 'arrow-trend-down' ?>"></i>
            </div>
        </div>
        <div class="kpi-value"><?= ($rendimento >= 0 ? '+' : '') . brlI($rendimento) ?></div>
        <div class="kpi-trend <?= $rendimento >= 0 ? 'up' : 'down' ?>">
            <i class="fa-solid fa-<?= $rendimento >= 0 ? 'arrow-up' : 'arrow-down' ?> fa-xs"></i>
            <?= ($rendimento >= 0 ? '+' : '') . pctI($rentab) ?> total
        </div>
    </div>
    <div class="kpi-card indigo">
        <div class="kpi-header">
            <div class="kpi-label">Rentabilidade</div>
            <div class="kpi-icon indigo"><i class="fa-solid fa-percent"></i></div>
        </div>
        <div class="kpi-value"><?= ($rentab >= 0 ? '+' : '') . pctI($rentab) ?></div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Sobre o capital</div>
    </div>
</div>

<!-- ── Gráfico + Tabela ───────────────────────────────────── -->
<?php if (empty($invs)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:3rem;color:var(--text-600)">
        <i class="fa-solid fa-chart-line fa-3x" style="margin-bottom:1rem;color:var(--violet)"></i>
        <div class="fw-700" style="margin-bottom:.5rem">Nenhum investimento cadastrado</div>
        <div class="text-sm" style="margin-bottom:1.25rem">
            Registre seus investimentos: Tesouro Direto, CDB, Ações, FIIs, Cripto e mais.
        </div>
        <button class="btn btn-primary" onclick="abrirModalNovo()">
            <i class="fa-solid fa-plus"></i> Cadastrar investimento
        </button>
    </div>
</div>
<?php else: ?>

<div class="dash-grid" style="margin-bottom:1.25rem">
    <!-- Gráfico de alocação -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Alocação por Classe</div>
                <div class="card-subtitle">Distribuição do valor atual</div>
            </div>
        </div>
        <div class="card-body" style="display:flex;gap:1.5rem;align-items:center;flex-wrap:wrap">
            <div class="chart-wrap" style="height:200px;width:200px;flex-shrink:0">
                <canvas id="chartAlocacao"></canvas>
            </div>
            <div style="flex:1;min-width:150px;display:flex;flex-direction:column;gap:.4rem">
                <?php foreach ($porTipo as $tipo => $val):
                    $pctTipo = $totAtual > 0 ? round(($val / $totAtual) * 100, 1) : 0;
                    $cor = $tipoCores[$tipo] ?? '#64748b';
                ?>
                <div class="d-flex align-center justify-between text-sm">
                    <div class="d-flex align-center gap-1">
                        <span style="width:10px;height:10px;border-radius:50%;background:<?= $cor ?>;flex-shrink:0;display:inline-block"></span>
                        <span><?= $tipoLabels[$tipo] ?? $tipo ?></span>
                    </div>
                    <div class="d-flex align-center gap-1">
                        <span class="text-muted text-xs"><?= $pctTipo ?>%</span>
                        <span class="fw-600"><?= brlI($val) ?></span>
                    </div>
                </div>
                <?php endforeach ?>
            </div>
        </div>
    </div>

    <!-- Resumo por classe -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Resumo da Carteira</div>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:.875rem">
            <?php
            $rendaFixaTot  = 0; $rendaVarTot = 0;
            $rfTipos = ['tesouro','cdb','lci','lca','lc','debenture','poupanca'];
            foreach ($invs as $inv) {
                if (in_array($inv['tipo'], $rfTipos)) $rendaFixaTot  += (float)$inv['valor_atual'];
                else                                   $rendaVarTot   += (float)$inv['valor_atual'];
            }
            $pctRF = $totAtual > 0 ? round(($rendaFixaTot / $totAtual) * 100) : 0;
            $pctRV = 100 - $pctRF;
            ?>
            <div>
                <div class="d-flex justify-between text-sm" style="margin-bottom:.3rem">
                    <span class="fw-600">Renda Fixa</span>
                    <span><?= brlI($rendaFixaTot) ?> &nbsp;<span class="text-muted">(<?= $pctRF ?>%)</span></span>
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill emerald" style="width:<?= $pctRF ?>%"></div>
                </div>
            </div>
            <div>
                <div class="d-flex justify-between text-sm" style="margin-bottom:.3rem">
                    <span class="fw-600">Renda Variável</span>
                    <span><?= brlI($rendaVarTot) ?> &nbsp;<span class="text-muted">(<?= $pctRV ?>%)</span></span>
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill violet" style="width:<?= $pctRV ?>%"></div>
                </div>
            </div>

            <hr style="border:none;border-top:1px solid var(--border)">
            <?php foreach ($invs as $inv):
                $rent = (float)$inv['valor_aplicado'] > 0
                    ? round((((float)$inv['valor_atual'] - (float)$inv['valor_aplicado']) / (float)$inv['valor_aplicado']) * 100, 2)
                    : 0;
                $cor = $tipoCores[$inv['tipo']] ?? '#64748b';
            ?>
            <div class="d-flex align-center justify-between text-sm">
                <div class="d-flex align-center gap-1">
                    <span style="width:8px;height:8px;border-radius:50%;background:<?= $cor ?>;flex-shrink:0;display:inline-block"></span>
                    <span class="truncate" style="max-width:130px"><?= htmlspecialchars($inv['nome']) ?></span>
                    <?php if ($inv['ticker']): ?>
                    <span class="text-xs text-muted"><?= htmlspecialchars($inv['ticker']) ?></span>
                    <?php endif ?>
                </div>
                <div class="text-right">
                    <div class="fw-600"><?= brlI((float)$inv['valor_atual']) ?></div>
                    <div class="text-xs <?= $rent >= 0 ? 'text-emerald' : 'text-rose' ?>">
                        <?= ($rent >= 0 ? '+' : '') . pctI($rent) ?>
                    </div>
                </div>
            </div>
            <?php endforeach ?>
        </div>
    </div>
</div>

<!-- ── Tabela de ativos ───────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Ativos</div>
        <div class="card-subtitle text-xs text-muted">Clique em um ativo para ver o histórico de movimentações</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Ativo</th>
                    <th>Rentabilidade</th>
                    <th class="text-right">Aplicado</th>
                    <th class="text-right">Atual</th>
                    <th class="text-right">Rendimento</th>
                    <th>Liquidez</th>
                    <th>Vencimento</th>
                    <th style="width:80px"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($invs as $inv):
                $rend = (float)$inv['valor_atual'] - (float)$inv['valor_aplicado'];
                $rentRow = (float)$inv['valor_aplicado'] > 0
                    ? round(($rend / (float)$inv['valor_aplicado']) * 100, 2) : 0;
                $cor = $tipoCores[$inv['tipo']] ?? '#64748b';
                $rentCls = $rentRow > 0 ? 'rent-pos' : ($rentRow < 0 ? 'rent-neg' : 'rent-zer');
            ?>
            <tr class="inv-row" data-id="<?= $inv['id'] ?>" onclick="selecionarInv(<?= $inv['id'] ?>)">
                <td>
                    <div class="d-flex align-center gap-1">
                        <span class="badge" style="background:<?= $cor ?>22;color:<?= $cor ?>">
                            <?= $tipoLabels[$inv['tipo']] ?? $inv['tipo'] ?>
                        </span>
                        <div>
                            <div class="fw-600 text-sm"><?= htmlspecialchars($inv['nome']) ?></div>
                            <?php if ($inv['ticker'] || $inv['corretora']): ?>
                            <div class="text-xs text-muted">
                                <?= $inv['ticker'] ? htmlspecialchars($inv['ticker']).' &nbsp;·&nbsp; ':'' ?>
                                <?= $inv['corretora'] ? htmlspecialchars($inv['corretora']) : '' ?>
                            </div>
                            <?php endif ?>
                        </div>
                    </div>
                </td>
                <td class="text-sm">
                    <?php if ($inv['rentabilidade_taxa']): ?>
                    <span class="fw-600"><?= pctI((float)$inv['rentabilidade_taxa']) ?></span>
                    <span class="text-muted text-xs"> <?= $rentTipoLabels[$inv['rentabilidade_tipo']] ?? '' ?></span>
                    <?php else: ?>
                    <span class="text-muted text-xs"><?= $rentTipoLabels[$inv['rentabilidade_tipo']] ?? '—' ?></span>
                    <?php endif ?>
                </td>
                <td class="text-right text-sm text-muted"><?= brlI((float)$inv['valor_aplicado']) ?></td>
                <td class="text-right fw-600"><?= brlI((float)$inv['valor_atual']) ?></td>
                <td class="text-right <?= $rentCls ?>">
                    <?= ($rend >= 0 ? '+' : '') . brlI($rend) ?><br>
                    <span class="text-xs"><?= ($rentRow >= 0 ? '+' : '') . pctI($rentRow) ?></span>
                </td>
                <td class="text-sm text-muted"><?= $liquidezLabels[$inv['liquidez']] ?? $inv['liquidez'] ?></td>
                <td class="text-sm text-muted">
                    <?= $inv['data_vencimento'] ? date('d/m/Y', strtotime($inv['data_vencimento'])) : '—' ?>
                </td>
                <td onclick="event.stopPropagation()">
                    <div class="d-flex gap-1" style="justify-content:flex-end">
                        <button class="btn-icon" onclick="abrirCotacao(<?= $inv['id'] ?>)" title="Atualizar cotação"
                                style="width:28px;height:28px;font-size:.72rem;color:var(--emerald)">
                            <i class="fa-solid fa-rotate"></i>
                        </button>
                        <button class="btn-icon" onclick="abrirEditar(<?= $inv['id'] ?>)" title="Editar"
                                style="width:28px;height:28px;font-size:.72rem">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Detalhe: histórico de movimentações ───────────────── -->
<div id="detalheSection">
    <div class="card">
        <div class="card-header" style="flex-wrap:wrap;gap:.75rem">
            <div>
                <div class="card-title" id="detNome">—</div>
                <div class="card-subtitle" id="detMeta">—</div>
            </div>
            <div class="d-flex gap-1">
                <button class="btn btn-success btn-sm" onclick="abrirModalTx()">
                    <i class="fa-solid fa-plus"></i> Movimentação
                </button>
                <button class="btn btn-ghost btn-sm" style="color:var(--rose)" onclick="arquivarInv()">
                    <i class="fa-solid fa-box-archive"></i> Arquivar
                </button>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th>Data</th>
                        <th class="text-right">Quantidade</th>
                        <th class="text-right">Preço Unit.</th>
                        <th class="text-right">Valor</th>
                    </tr>
                </thead>
                <tbody id="detTbody">
                    <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-600)">
                        Clique em um ativo para ver o histórico.
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif ?>

<!-- ── Modal: Novo Investimento ──────────────────────────── -->
<div id="modalNovo" class="modal-overlay" onclick="if(event.target===this)fecharModal('modalNovo')">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title" id="modalNovoTitulo">Novo Investimento</div>
            <button type="button" class="btn-icon" onclick="fecharModal('modalNovo')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formNovo" onsubmit="salvarInv(event)">
            <div class="modal-body">
                <input type="hidden" id="nId" value="">

                <!-- Seletor de tipo -->
                <div style="margin-bottom:1.1rem">
                    <div class="form-label" style="margin-bottom:.5rem">Classe do ativo</div>
                    <div class="tipo-group" id="tipoGroup">
                        <?php foreach ($tipoLabels as $v => $l):
                            $cor = $tipoCores[$v] ?? '#64748b';
                        ?>
                        <button type="button" class="tipo-btn" data-v="<?= $v ?>"
                                data-cor="<?= $cor ?>"
                                onclick="setTipoInv('<?= $v ?>', '<?= $cor ?>')">
                            <?= $l ?>
                        </button>
                        <?php endforeach ?>
                    </div>
                    <input type="hidden" id="nTipo" value="cdb">
                </div>

                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Nome <span style="color:var(--rose)">*</span></label>
                    <input type="text" id="nNome" class="form-control" required
                           placeholder="Ex: Tesouro Selic 2028, VALE3, Nubank CDB...">
                </div>

                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Ticker / Código</label>
                        <input type="text" id="nTicker" class="form-control" placeholder="Ex: VALE3, KNRI11">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Corretora</label>
                        <input type="text" id="nCorretora" class="form-control" placeholder="Ex: XP, Clear, Nubank">
                    </div>
                </div>

                <div class="form-grid form-grid-3" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Valor inicial (R$) <span style="color:var(--rose)" id="nValorReq">*</span></label>
                        <input type="text" id="nValor" class="form-control" placeholder="0,00" inputmode="numeric" data-currency required>
                    </div>
                    <div class="form-group" id="nQtdGroup">
                        <label class="form-label">Qtd. de cotas/ações</label>
                        <input type="number" id="nQtd" class="form-control" step="0.00000001" min="0" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data do aporte <span style="color:var(--rose)">*</span></label>
                        <input type="date" id="nDataApl" class="form-control" required>
                    </div>
                </div>

                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem" id="rentGroup">
                    <div class="form-group">
                        <label class="form-label">Indexador</label>
                        <select id="nRentTipo" class="form-control">
                            <option value="prefixado">Prefixado</option>
                            <option value="pos_cdi" selected>% CDI</option>
                            <option value="pos_ipca">IPCA+</option>
                            <option value="pos_selic">Selic</option>
                            <option value="variavel">Variável</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Taxa (%)</label>
                        <input type="number" id="nRentTaxa" class="form-control" step="0.01" min="0" placeholder="Ex: 110,50">
                    </div>
                </div>

                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Vencimento</label>
                        <input type="date" id="nVenc" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Liquidez</label>
                        <select id="nLiquidez" class="form-control">
                            <?php foreach ($liquidezLabels as $v => $l): ?>
                            <option value="<?= $v ?>"><?= $l ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Conta de origem</label>
                    <select id="nConta" class="form-control">
                        <option value="">— Não registrar saída —</option>
                        <?php foreach ($contas as $ct): ?>
                        <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['nome']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="fecharModal('modalNovo')">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnSalvarInv">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Movimentação ───────────────────────────────── -->
<div id="modalTx" class="modal-overlay" onclick="if(event.target===this)fecharModal('modalTx')">
    <div class="modal-box sm">
        <div class="modal-header">
            <div class="modal-title">Registrar Movimentação</div>
            <button type="button" class="btn-icon" onclick="fecharModal('modalTx')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formTx" onsubmit="salvarTx(event)">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Tipo</label>
                    <select id="txTipo" class="form-control" onchange="toggleTxFields()">
                        <option value="aporte">Aporte (aplicação adicional)</option>
                        <option value="resgate">Resgate (retirada)</option>
                        <option value="dividendo">Dividendo</option>
                        <option value="juros">Juros sobre Capital</option>
                        <option value="rendimento">Rendimento</option>
                        <option value="taxa">Taxa / IOF / IR</option>
                        <option value="outro">Outro</option>
                    </select>
                </div>
                <div class="form-grid form-grid-2" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Valor (R$) <span style="color:var(--rose)">*</span></label>
                        <input type="text" id="txValor" class="form-control" placeholder="0,00" inputmode="numeric" data-currency required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data <span style="color:var(--rose)">*</span></label>
                        <input type="date" id="txData" class="form-control" required>
                    </div>
                </div>
                <div class="form-grid form-grid-2" id="txQtdGroup" style="margin-bottom:1.1rem">
                    <div class="form-group">
                        <label class="form-label">Quantidade</label>
                        <input type="number" id="txQtd" class="form-control" step="0.00000001" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Preço unitário</label>
                        <input type="number" id="txPreco" class="form-control" step="0.00000001" min="0">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:1.1rem">
                    <label class="form-label">Conta</label>
                    <select id="txConta" class="form-control">
                        <option value="">— Não movimentar conta —</option>
                        <?php foreach ($contas as $ct): ?>
                        <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['nome']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <input type="text" id="txDesc" class="form-control" placeholder="(opcional)">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="fecharModal('modalTx')">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnSalvarTx">
                    <i class="fa-solid fa-floppy-disk"></i> Registrar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Atualizar Cotação ──────────────────────────── -->
<div id="modalCotacao" class="modal-overlay" onclick="if(event.target===this)fecharModal('modalCotacao')">
    <div class="modal-box sm">
        <div class="modal-header">
            <div class="modal-title">Atualizar Cotação</div>
            <button type="button" class="btn-icon" onclick="fecharModal('modalCotacao')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formCotacao" onsubmit="salvarCotacao(event)">
            <div class="modal-body">
                <input type="hidden" id="ctId">
                <div class="card" style="margin-bottom:1.1rem;background:var(--bg-700)">
                    <div class="card-body" style="padding:.875rem">
                        <div class="text-xs text-muted">Ativo</div>
                        <div class="fw-700" id="ctNome" style="margin-top:.2rem">—</div>
                        <div class="text-sm text-muted" id="ctAtual" style="margin-top:.15rem">—</div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Novo Valor Atual (R$) <span style="color:var(--rose)">*</span></label>
                    <input type="number" id="ctValor" class="form-control" step="0.01" min="0.01" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="fecharModal('modalCotacao')">Cancelar</button>
                <button type="submit" class="btn btn-success" id="btnCotacao">
                    <i class="fa-solid fa-rotate"></i> Atualizar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const _INVS = <?= json_encode(array_values($invs), JSON_UNESCAPED_UNICODE) ?>;
let _invAtivo = null;

// ── Gráfico de alocação ───────────────────────────────────
const _porTipo = <?= json_encode($porTipo) ?>;
const _tipoCores = <?= json_encode($tipoCores) ?>;
const _tipoLabels = <?= json_encode($tipoLabels) ?>;

document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('chartAlocacao');
    if (!ctx || Object.keys(_porTipo).length === 0) return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(_porTipo).map(t => _tipoLabels[t] || t),
            datasets: [{
                data: Object.values(_porTipo),
                backgroundColor: Object.keys(_porTipo).map(t => _tipoCores[t] || '#64748b'),
                borderWidth: 0, hoverOffset: 4,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: {
                    label: ctx => ' ' + brl(ctx.parsed)
                }}
            }
        }
    });
});

// ── Selecionar ativo ──────────────────────────────────────
function selecionarInv(id) {
    document.querySelectorAll('.inv-row').forEach(r => r.classList.remove('selected'));
    const el = document.querySelector(`.inv-row[data-id="${id}"]`);
    if (el) el.classList.add('selected');
    _invAtivo = _INVS.find(i => +i.id === +id);
    carregarTx(id);
}

async function carregarTx(id) {
    document.getElementById('detalheSection').style.display = '';
    if (_invAtivo) {
        const rend = (parseFloat(_invAtivo.valor_atual) - parseFloat(_invAtivo.valor_aplicado));
        const rentPct = parseFloat(_invAtivo.valor_aplicado) > 0
            ? (rend / parseFloat(_invAtivo.valor_aplicado) * 100).toFixed(2) : 0;
        document.getElementById('detNome').textContent = _invAtivo.nome;
        document.getElementById('detMeta').textContent =
            `Aplicado: ${brl(_invAtivo.valor_aplicado)} · Atual: ${brl(_invAtivo.valor_atual)} · Rendimento: ${brl(rend)} (${rentPct >= 0 ? '+' : ''}${rentPct}%)`;
    }
    document.getElementById('detTbody').innerHTML =
        `<tr><td colspan="6" style="text-align:center;padding:1.5rem;color:var(--text-600)">
            <i class="fa-solid fa-spinner fa-spin"></i>
        </td></tr>`;
    try {
        const res  = await fetch(`backend/api/investimentos.php?acao=transacoes&id=${id}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        renderTx(json.transacoes);
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

function renderTx(txs) {
    const tbody = document.getElementById('detTbody');
    if (!txs.length) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-600)">
            Sem movimentações registradas.
        </td></tr>`;
        return;
    }
    const tipoColors = {
        aporte: 'emerald', resgate: 'rose', dividendo: 'indigo',
        juros: 'cyan', rendimento: 'emerald', taxa: 'amber', outro: 'violet'
    };
    tbody.innerHTML = txs.map(tx => {
        const cls = tipoColors[tx.tipo] || 'indigo';
        const sinal = ['aporte','taxa'].includes(tx.tipo) ? (tx.tipo === 'taxa' ? '-' : '+') :
                      tx.tipo === 'resgate' ? '-' : '+';
        return `<tr>
            <td><span class="badge ${cls}">${tx.tipo}</span></td>
            <td class="text-sm text-muted">${esc(tx.descricao || '—')}</td>
            <td class="text-sm text-muted">${fmtData(tx.data)}</td>
            <td class="text-right text-sm text-muted">${tx.quantidade ? parseFloat(tx.quantidade).toLocaleString('pt-BR',{maximumFractionDigits:8}) : '—'}</td>
            <td class="text-right text-sm text-muted">${tx.preco_unitario ? brl(tx.preco_unitario) : '—'}</td>
            <td class="text-right fw-600 ${tx.tipo === 'resgate' || tx.tipo === 'taxa' ? 'text-rose' : 'text-emerald'}">
                ${sinal}${brl(tx.valor)}
            </td>
        </tr>`;
    }).join('');
}

// ── Tipo de ativo ─────────────────────────────────────────
function setTipoInv(v, cor) {
    document.getElementById('nTipo').value = v;
    document.querySelectorAll('.tipo-btn').forEach(b => {
        if (b.dataset.v === v) {
            b.classList.add('active');
            b.style.background = cor;
        } else {
            b.classList.remove('active');
            b.style.background = '';
        }
    });
    // Variável → mostrar campo de quantidade
    const variaveis = ['acao','fii','etf','cripto','fundo'];
    document.getElementById('nQtdGroup').style.display =
        variaveis.includes(v) ? '' : 'none';
}

// ── Salvar novo / editar ──────────────────────────────────
function abrirModalNovo() {
    document.getElementById('formNovo').reset();
    document.getElementById('nId').value     = '';
    document.getElementById('nDataApl').value = new Date().toISOString().split('T')[0];
    document.getElementById('modalNovoTitulo').textContent = 'Novo Investimento';
    document.getElementById('nQtdGroup').style.display = 'none';
    setTipoInv('cdb', _tipoCores['cdb']);
    document.getElementById('modalNovo').classList.add('open');
    setTimeout(() => document.getElementById('nNome').focus(), 80);
}

function abrirEditar(id) {
    const inv = _INVS.find(i => +i.id === +id);
    if (!inv) return;
    document.getElementById('nId').value        = inv.id;
    document.getElementById('nNome').value      = inv.nome || '';
    document.getElementById('nTicker').value    = inv.ticker || '';
    document.getElementById('nCorretora').value = inv.corretora || '';
    document.getElementById('nRentTipo').value  = inv.rentabilidade_tipo || 'pos_cdi';
    document.getElementById('nRentTaxa').value  = inv.rentabilidade_taxa || '';
    document.getElementById('nVenc').value      = (inv.data_vencimento||'').split('T')[0];
    document.getElementById('nLiquidez').value  = inv.liquidez || 'vencimento';
    document.getElementById('nConta').value     = inv.conta_id || '';
    document.getElementById('nValor').value      = inv.valor_aplicado ? brlMask(inv.valor_aplicado) : '';
    document.getElementById('nValor').required   = false;
    document.getElementById('nDataApl').value    = (inv.data_aplicacao||'').split('T')[0];
    document.getElementById('nDataApl').required = false;
    document.getElementById('modalNovoTitulo').textContent = 'Editar Investimento';
    setTipoInv(inv.tipo || 'cdb', _tipoCores[inv.tipo] || '#64748b');
    document.getElementById('modalNovo').classList.add('open');
}

async function salvarInv(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvarInv');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    const id = document.getElementById('nId').value;
    const invExistente = id ? _INVS.find(i => +i.id === +parseInt(id)) : null;
    const valorDigitado = parseCurrency(document.getElementById('nValor').value);
    const payload = {
        acao:              'salvar',
        id:                id ? parseInt(id) : 0,
        nome:              document.getElementById('nNome').value.trim(),
        tipo:              document.getElementById('nTipo').value,
        ticker:            document.getElementById('nTicker').value.trim(),
        corretora:         document.getElementById('nCorretora').value.trim(),
        valor_aplicado:    valorDigitado || (invExistente ? parseFloat(invExistente.valor_aplicado || 0) : 0),
        quantidade:        parseFloat(document.getElementById('nQtd').value) || null,
        rentabilidade_tipo:document.getElementById('nRentTipo').value,
        rentabilidade_taxa:parseFloat(document.getElementById('nRentTaxa').value) || null,
        data_aplicacao:    document.getElementById('nDataApl').value,
        data_vencimento:   document.getElementById('nVenc').value || null,
        liquidez:          document.getElementById('nLiquidez').value,
        conta_id:          document.getElementById('nConta').value || null,
    };

    try {
        const res  = await fetch('backend/api/investimentos.php', {
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
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar';
    }
}

// ── Movimentação ──────────────────────────────────────────
function abrirModalTx() {
    if (!_invAtivo) return;
    document.getElementById('formTx').reset();
    document.getElementById('txData').value = new Date().toISOString().split('T')[0];
    document.getElementById('txConta').value = _invAtivo.conta_id || '';
    toggleTxFields();
    document.getElementById('modalTx').classList.add('open');
}

function toggleTxFields() {
    const tipo = document.getElementById('txTipo').value;
    const mostraQtd = ['aporte','resgate'].includes(tipo);
    document.getElementById('txQtdGroup').style.display = mostraQtd ? '' : 'none';
}

async function salvarTx(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvarTx');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    const payload = {
        acao:           'transacao',
        investimento_id: _invAtivo.id,
        tipo:            document.getElementById('txTipo').value,
        valor:           parseCurrency(document.getElementById('txValor').value),
        quantidade:      parseFloat(document.getElementById('txQtd').value) || null,
        preco_unitario:  parseFloat(document.getElementById('txPreco').value) || null,
        data:            document.getElementById('txData').value,
        descricao:       document.getElementById('txDesc').value.trim(),
        conta_id:        document.getElementById('txConta').value || null,
    };

    try {
        const res  = await fetch('backend/api/investimentos.php', {
            method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        fecharModal('modalTx');
        location.reload();
    } catch (err) {
        toast('Erro: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Registrar';
    }
}

// ── Cotação ───────────────────────────────────────────────
function abrirCotacao(id) {
    const inv = _INVS.find(i => +i.id === +id);
    if (!inv) return;
    document.getElementById('ctId').value    = id;
    document.getElementById('ctNome').textContent  = inv.nome;
    document.getElementById('ctAtual').textContent = `Valor atual: ${brl(inv.valor_atual)}`;
    document.getElementById('ctValor').value = parseFloat(inv.valor_atual).toFixed(2);
    document.getElementById('modalCotacao').classList.add('open');
    setTimeout(() => document.getElementById('ctValor').select(), 80);
}

async function salvarCotacao(e) {
    e.preventDefault();
    const btn = document.getElementById('btnCotacao');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    try {
        const res  = await fetch('backend/api/investimentos.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                acao: 'cotacao',
                id:   parseInt(document.getElementById('ctId').value),
                valor_atual: parseFloat(document.getElementById('ctValor').value),
            })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        fecharModal('modalCotacao');
        location.reload();
    } catch (err) {
        toast('Erro: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-rotate"></i> Atualizar';
    }
}

// ── Arquivar ──────────────────────────────────────────────
function arquivarInv() {
    if (!_invAtivo) return;
    confirmar('Arquivar investimento', `Arquivar "${_invAtivo.nome}"? Ele não aparecerá mais na carteira ativa.`, async () => {
        try {
            const res  = await fetch(`backend/api/investimentos.php?id=${_invAtivo.id}`, {method:'DELETE'});
            const json = await res.json();
            if (!json.success) throw new Error(json.erro);
            toast(json.msg, 'success');
            location.reload();
        } catch (err) { toast('Erro: ' + err.message, 'error'); }
    }, { btnLabel: 'Arquivar', icone: 'box-archive' });
}

function fecharModal(id) { document.getElementById(id).classList.remove('open'); }

function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtData(s) {
    if (!s) return '—';
    const d = s.split('T')[0].split('-');
    return `${d[2]}/${d[1]}/${d[0]}`;
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') ['modalNovo','modalTx','modalCotacao'].forEach(fecharModal);
});

// Inicializa tipo selecionado
document.addEventListener('DOMContentLoaded', () => setTipoInv('cdb', _tipoCores['cdb'] || '#06b6d4'));
</script>
