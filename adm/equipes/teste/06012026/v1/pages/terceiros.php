<?php
$p = TABLE_PREFIX;

// Dados para os selects do modal de lançamento
$catsTerceiro = $pdo->query(
    "SELECT id, nome, tipo, cor FROM `{$p}categorias` WHERE ativo=1 ORDER BY tipo, nome"
)->fetchAll();

$contasTerceiro = $pdo->query(
    "SELECT id, nome FROM `{$p}contas` WHERE ativo=1 ORDER BY nome"
)->fetchAll();
?>

<style>
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
    backdrop-filter: blur(2px);
}
.modal-overlay.open { display: flex; }
.modal-box {
    background: var(--bg-800);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    width: 100%;
    max-width: 640px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-lg);
    animation: modalIn .18s ease;
}
@keyframes modalIn {
    from { opacity:0; transform:translateY(-10px) scale(.97); }
    to   { opacity:1; transform:none; }
}
.modal-header {
    padding: 1.25rem 1.5rem 1rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.modal-title  { font-size: 1rem; font-weight: 700; }
.modal-body   { padding: 1.5rem; overflow-y: auto; flex: 1; }
.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: .75rem;
    flex-shrink: 0;
}

/* ── Cards de terceiros ───────────────────────────────────── */
.terc-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1rem; margin-bottom:1.5rem; }
.terc-card {
    background: var(--bg-800);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    cursor: pointer;
    transition: var(--ease);
    position: relative;
}
.terc-card:hover { border-color: var(--indigo); transform: translateY(-2px); box-shadow: var(--shadow-lg); }
.terc-card.active { border-color: var(--indigo); box-shadow: 0 0 0 2px rgba(99,102,241,.2); }
.terc-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; flex-shrink: 0;
}

/* ── Preview do ícone no modal ───────────────────────────── */
.icone-preview {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; flex-shrink: 0;
    border: 2px solid var(--border);
    transition: var(--ease);
}

/* ── Toggle tipo (Despesa/Receita) ───────────────────────── */
.tipo-toggle { display:flex; gap:.5rem; }
.tipo-toggle .btn { flex:1; justify-content:center; }

@media (max-width: 768px) {
    .terc-grid { grid-template-columns: 1fr; }
    .modal-overlay { align-items: flex-end; padding: 0; }
    .modal-box {
        max-width: 100%;
        border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        max-height: 92vh;
        animation: slideUp .22s ease;
    }
    @keyframes slideUp { from { transform:translateY(100%) } to { transform:none } }
}
</style>

<!-- Cabeçalho -->
<div class="page-header">
    <div>
        <div class="page-title">Controle de Terceiros</div>
        <div class="page-sub">Gerencie finanças de outras pessoas sem misturar com as suas</div>
    </div>
    <button class="btn btn-primary btn-sm" onclick="abrirModalTerceiro()">
        <i class="fa-solid fa-plus"></i> Novo Terceiro
    </button>
</div>

<!-- Cards dos terceiros -->
<div class="terc-grid" id="terceiroCards">
    <div style="text-align:center;padding:3rem;color:var(--text-600);grid-column:1/-1">
        <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
    </div>
</div>

<!-- Painel de detalhes do terceiro selecionado -->
<div id="painelTerceiro" style="display:none">

    <!-- KPIs do terceiro -->
    <div class="kpi-grid" style="margin-bottom:1.25rem" id="terceiroKpis">
        <div class="kpi-card emerald">
            <div class="kpi-header"><div class="kpi-label">Receitas do Mês</div>
            <div class="kpi-icon emerald"><i class="fa-solid fa-arrow-trend-up"></i></div></div>
            <div class="kpi-value" id="tKpiRec">—</div>
        </div>
        <div class="kpi-card rose">
            <div class="kpi-header"><div class="kpi-label">Despesas do Mês</div>
            <div class="kpi-icon rose"><i class="fa-solid fa-arrow-trend-down"></i></div></div>
            <div class="kpi-value" id="tKpiDes">—</div>
        </div>
        <div class="kpi-card indigo">
            <div class="kpi-header"><div class="kpi-label">Saldo do Mês</div>
            <div class="kpi-icon indigo"><i class="fa-solid fa-scale-balanced"></i></div></div>
            <div class="kpi-value" id="tKpiSaldo">—</div>
        </div>
        <div class="kpi-card amber">
            <div class="kpi-header"><div class="kpi-label">Lançamentos</div>
            <div class="kpi-icon amber"><i class="fa-solid fa-receipt"></i></div></div>
            <div class="kpi-value" id="tKpiQtd">—</div>
        </div>
    </div>

    <!-- Tabela de transações do terceiro -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title" id="tabelaTercNome">Transações</div>
                <div class="card-subtitle" style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-top:.35rem">
                    <!-- Toggle modo filtro -->
                    <div style="display:flex;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;flex-shrink:0">
                        <button id="fBtnMes" onclick="setModoFiltro('mes')"
                                style="padding:.22rem .65rem;font-size:.72rem;font-weight:600;border:none;cursor:pointer;transition:var(--ease);background:var(--indigo);color:#fff">
                            Mês/Ano
                        </button>
                        <button id="fBtnPer" onclick="setModoFiltro('periodo')"
                                style="padding:.22rem .65rem;font-size:.72rem;font-weight:600;border:none;cursor:pointer;transition:var(--ease);background:transparent;color:var(--text-500)">
                            Período
                        </button>
                    </div>
                    <!-- Mês + Ano -->
                    <div id="filtroMes" style="display:flex;align-items:center;gap:.3rem">
                        <select id="tFilMes" class="form-control" style="width:110px" onchange="carregarTerceiro()">
                            <?php foreach ([1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',
                                            7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro']
                                           as $n => $nm): ?>
                            <option value="<?= $n ?>" <?= $n == (int)date('m') ? 'selected' : '' ?>><?= $nm ?></option>
                            <?php endforeach ?>
                        </select>
                        <select id="tFilAno" class="form-control" style="width:82px" onchange="carregarTerceiro()">
                            <?php for ($y = (int)date('Y')+1; $y >= (int)date('Y')-3; $y--): ?>
                            <option value="<?= $y ?>" <?= $y == (int)date('Y') ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor ?>
                        </select>
                    </div>
                    <!-- Período livre -->
                    <div id="filtroPeriodo" style="display:none;align-items:center;gap:.3rem">
                        <input type="date" id="tFilDe" class="form-control" style="width:140px" onchange="carregarTerceiro()">
                        <span style="color:var(--text-500);font-size:.8rem">até</span>
                        <input type="date" id="tFilAte" class="form-control" style="width:140px" onchange="carregarTerceiro()">
                    </div>
                </div>
            </div>
            <button class="btn btn-primary btn-sm" onclick="abrirModalLancar()">
                <i class="fa-solid fa-plus"></i> Lançar
            </button>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:36px;padding-right:0"><input type="checkbox" id="cbTodosTc" style="accent-color:var(--indigo);width:15px;height:15px;cursor:pointer" onclick="toggleTodosTc(this)" title="Selecionar todos"></th>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th>Conta</th>
                        <th>Data</th>
                        <th>Status</th>
                        <th class="text-right">Valor</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody id="tabelaTercBody">
                    <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text-600)">Selecione um terceiro</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Novo/Editar Terceiro -->
<div id="modalTerceiro" class="modal-overlay" onclick="if(event.target===this)fecharModalTerceiro()">
    <div class="modal-box" style="max-width:440px">
        <div class="modal-header">
            <div class="modal-title" id="modalTerceiroTitulo">Novo Terceiro</div>
            <button type="button" class="btn-icon" onclick="fecharModalTerceiro()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="formTerceiro" onsubmit="salvarTerceiro(event)">
            <div class="modal-body">
                <input type="hidden" id="tEditId" value="">
                <div class="form-group" style="margin-bottom:1rem">
                    <label class="form-label">Nome <span style="color:var(--rose)">*</span></label>
                    <input type="text" id="tNome" class="form-control" placeholder="Ex: João, Maria, Cliente ABC" required maxlength="100">
                </div>
                <div class="form-group" style="margin-bottom:1rem">
                    <label class="form-label">Descrição</label>
                    <input type="text" id="tDesc" class="form-control" placeholder="Opcional" maxlength="255">
                </div>
                <div class="form-grid form-grid-2" style="margin-bottom:1rem">
                    <div class="form-group">
                        <label class="form-label">Ícone (Font Awesome)</label>
                        <div style="display:flex;gap:.5rem;align-items:center">
                            <div class="icone-preview" id="tIconePreview" style="background:#6366f122;color:#6366f1">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <input type="text" id="tIcone" class="form-control" value="user"
                                   placeholder="user, briefcase, child..."
                                   oninput="atualizarPreviewIcone()">
                        </div>
                        <div class="text-xs text-muted" style="margin-top:.35rem">
                            Ex: user · briefcase · child · handshake · building
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cor do avatar</label>
                        <div style="display:flex;gap:.5rem;align-items:center">
                            <input type="color" id="tCor" class="form-control" value="#6366f1"
                                   style="height:38px;padding:.25rem;width:56px;flex-shrink:0;cursor:pointer"
                                   oninput="atualizarPreviewIcone()">
                            <div id="tCorHex" style="font-size:.8rem;color:var(--text-400);font-family:monospace">#6366f1</div>
                        </div>
                        <div class="text-xs text-muted" style="margin-top:.35rem">Clique para escolher a cor</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="fecharModalTerceiro()">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Lançar Transação para Terceiro -->
<div id="modalLancar" class="modal-overlay" onclick="if(event.target===this)fecharModalLancar()">
    <div class="modal-box" style="max-width:520px">
        <div class="modal-header">
            <div class="modal-title" id="modalLancarTitulo">Lançar para Terceiro</div>
            <button type="button" class="btn-icon" onclick="fecharModalLancar()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="formLancar" onsubmit="salvarLancamento(event)">
            <div class="modal-body">
                <div style="margin-bottom:1.25rem">
                    <label class="form-label" style="margin-bottom:.5rem">Tipo</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem">
                        <button type="button" id="lTipoDes" onclick="setTipoLancar('despesa')"
                                style="display:flex;align-items:center;justify-content:center;gap:.5rem;
                                       padding:.625rem;border-radius:var(--radius);font-size:.85rem;font-weight:600;
                                       border:2px solid var(--rose);background:rgba(239,68,68,.12);color:var(--rose);cursor:pointer;transition:var(--ease)">
                            <i class="fa-solid fa-arrow-trend-down"></i> Despesa
                        </button>
                        <button type="button" id="lTipoRec" onclick="setTipoLancar('receita')"
                                style="display:flex;align-items:center;justify-content:center;gap:.5rem;
                                       padding:.625rem;border-radius:var(--radius);font-size:.85rem;font-weight:600;
                                       border:2px solid var(--border);background:transparent;color:var(--text-400);cursor:pointer;transition:var(--ease)">
                            <i class="fa-solid fa-arrow-trend-up"></i> Receita
                        </button>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:1rem">
                    <label class="form-label">Status</label>
                    <select id="lStatus" class="form-control">
                        <option value="pago">✓ Pago / Recebido</option>
                        <option value="pendente">⏳ Pendente</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:1rem">
                    <label class="form-label">Descrição <span style="color:var(--rose)">*</span></label>
                    <input type="text" id="lDesc" class="form-control" placeholder="Ex: Mercado, Aluguel..." required maxlength="200">
                </div>
                <div class="form-grid form-grid-2" style="margin-bottom:1rem">
                    <div class="form-group">
                        <label class="form-label">Valor (R$) <span style="color:var(--rose)">*</span></label>
                        <input type="text" id="lValor" class="form-control" placeholder="0,00" inputmode="numeric" data-currency required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data <span style="color:var(--rose)">*</span></label>
                        <input type="date" id="lData" class="form-control" required>
                    </div>
                </div>
                <div class="form-grid form-grid-2" style="margin-bottom:1rem">
                    <div class="form-group">
                        <label class="form-label">Categoria</label>
                        <select id="lCat" class="form-control">
                            <option value="">— Sem categoria —</option>
                            <?php foreach ($catsTerceiro as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Conta</label>
                        <select id="lConta" class="form-control">
                            <option value="">— Sem conta —</option>
                            <?php foreach ($contasTerceiro as $ct): ?>
                            <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['nome']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Observação</label>
                    <textarea id="lObs" class="form-control" rows="2" placeholder="Opcional"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="fecharModalLancar()">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
let _terceiros       = [];
let _terceiroAtivo   = null;
let _tipoLancar      = 'despesa';
let _modoFiltro      = 'mes';
let _saldoMes        = null;
let _selecionadosTc  = new Set();

// ── Toggle entre modos de filtro ──────────────────────────────
function setModoFiltro(modo) {
    _modoFiltro = modo;
    document.getElementById('filtroMes').style.display      = modo === 'mes'     ? 'flex' : 'none';
    document.getElementById('filtroPeriodo').style.display  = modo === 'periodo' ? 'flex' : 'none';
    const bm = document.getElementById('fBtnMes');
    const bp = document.getElementById('fBtnPer');
    bm.style.background = modo === 'mes'     ? 'var(--indigo)' : 'transparent';
    bm.style.color      = modo === 'mes'     ? '#fff'          : 'var(--text-500)';
    bp.style.background = modo === 'periodo' ? 'var(--indigo)' : 'transparent';
    bp.style.color      = modo === 'periodo' ? '#fff'          : 'var(--text-500)';
    carregarTerceiro();
}

// ── Inicializa datas do filtro "Período" com o mês corrente ──
function initFiltrosDatas() {
    const hoje = new Date();
    const y = hoje.getFullYear();
    const m = String(hoje.getMonth() + 1).padStart(2, '0');
    const ult = new Date(y, hoje.getMonth() + 1, 0).getDate();
    document.getElementById('tFilDe').value  = `${y}-${m}-01`;
    document.getElementById('tFilAte').value = `${y}-${m}-${String(ult).padStart(2, '0')}`;
}

// ── Carregar lista de terceiros ───────────────────────────────
async function carregarTerceiros() {
    try {
        const res  = await fetch('backend/api/terceiros.php?acao=lista');
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        _terceiros = json.dados;
        renderTerceiroCards(json.dados);
        // Exibe o primeiro automaticamente ao carregar a página
        if (!_terceiroAtivo && json.dados.length > 0) {
            await selecionarTerceiro(json.dados[0].id);
        }
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

function renderTerceiroCards(lista) {
    const el = document.getElementById('terceiroCards');
    if (!lista.length) {
        el.innerHTML = `<div style="text-align:center;padding:3rem;color:var(--text-600);grid-column:1/-1">
            <i class="fa-solid fa-users fa-2x" style="margin-bottom:.75rem;display:block"></i>
            <div class="text-sm">Nenhum terceiro cadastrado</div>
            <div class="text-xs" style="margin-top:.3rem">Clique em "Novo Terceiro" para começar</div>
        </div>`;
        return;
    }
    el.innerHTML = lista.map(t => {
        const isAtivo  = _terceiroAtivo?.id == t.id;
        const sMes     = isAtivo && _saldoMes !== null ? _saldoMes : null;
        const sBg      = sMes !== null ? (sMes >= 0 ? 'rgba(16,185,129,.08)' : 'rgba(239,68,68,.08)') : 'rgba(100,116,139,.06)';
        const sClass   = sMes !== null ? (sMes >= 0 ? 'text-emerald' : 'text-rose') : 'text-muted';
        const sTxt     = sMes !== null ? `${sMes >= 0 ? '+' : ''}${brl(sMes)}` : '—';
        return `<div class="terc-card ${isAtivo ? 'active' : ''}" data-id="${+t.id}" onclick="selecionarTerceiro(${+t.id})">
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem">
                <div class="terc-avatar" style="background:${esc(t.cor)}22;color:${esc(t.cor)}">
                    <i class="fa-solid fa-${esc(t.icone)}"></i>
                </div>
                <div style="flex:1">
                    <div class="fw-700">${esc(t.nome)}</div>
                    ${t.descricao ? `<div class="text-xs text-muted">${esc(t.descricao)}</div>` : ''}
                </div>
                <button class="btn-icon" onclick="event.stopPropagation();editarTerceiro(${+t.id})" title="Editar"
                        style="width:26px;height:26px;font-size:.75rem">
                    <i class="fa-solid fa-pen-to-square"></i>
                </button>
            </div>
            <div id="tCardSaldoBox_${+t.id}" style="background:${sBg};border-radius:var(--radius);padding:.45rem .75rem;margin-bottom:.6rem;display:flex;align-items:center;justify-content:space-between">
                <span class="text-xs text-muted">Saldo do Mês</span>
                <span class="fw-700 ${sClass}" style="font-size:.95rem">${sTxt}</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.4rem;font-size:.78rem">
                <div>
                    <div class="text-muted">Total Rec.</div>
                    <div class="fw-600 text-emerald">${brl(parseFloat(t.total_receitas))}</div>
                </div>
                <div>
                    <div class="text-muted">Total Des.</div>
                    <div class="fw-600 text-rose">${brl(parseFloat(t.total_despesas))}</div>
                </div>
            </div>
            <div style="margin-top:.5rem;padding-top:.5rem;border-top:1px solid var(--border)">
                <span class="text-xs text-muted">${t.total_transacoes} lançamentos</span>
            </div>
        </div>`;
    }).join('');
}

// ── Selecionar terceiro ───────────────────────────────────────
async function selecionarTerceiro(id) {
    const painel = document.getElementById('painelTerceiro');

    if (_terceiroAtivo?.id == id) {
        // mesmo card — alterna visibilidade do painel
        const oculto = painel.style.display === 'none';
        painel.style.display = oculto ? '' : 'none';
        document.querySelector(`.terc-card[data-id="${id}"]`)?.classList.toggle('active', oculto);
        return;
    }

    _saldoMes = null;
    _selecionadosTc.clear();
    atualizarBulkBarTc();
    const cbAllTc = document.getElementById('cbTodosTc');
    if (cbAllTc) cbAllTc.checked = false;
    _terceiroAtivo = _terceiros.find(t => +t.id === +id) || null;
    document.querySelectorAll('.terc-card').forEach(c => c.classList.remove('active'));
    document.querySelector(`.terc-card[data-id="${id}"]`)?.classList.add('active');
    painel.style.display = '';
    document.getElementById('tabelaTercNome').textContent = `Transações de ${_terceiroAtivo?.nome || ''}`;
    await carregarTerceiro();
}

async function carregarTerceiro() {
    if (!_terceiroAtivo) return;
    let de, ate;
    if (_modoFiltro === 'mes') {
        const mes = parseInt(document.getElementById('tFilMes').value);
        const ano = parseInt(document.getElementById('tFilAno').value);
        const ult = new Date(ano, mes, 0).getDate();
        de  = `${ano}-${String(mes).padStart(2,'0')}-01`;
        ate = `${ano}-${String(mes).padStart(2,'0')}-${String(ult).padStart(2,'0')}`;
    } else {
        de  = document.getElementById('tFilDe').value;
        ate = document.getElementById('tFilAte').value;
    }

    try {
        const res  = await fetch(`backend/api/terceiros.php?acao=transacoes&id=${_terceiroAtivo.id}&de=${de}&ate=${ate}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        renderTabelaTerceiro(json.transacoes, json.kpi);
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

function renderTabelaTerceiro(rows, kpi) {
    document.getElementById('tKpiRec').textContent   = brl(parseFloat(kpi.receitas||0));
    document.getElementById('tKpiDes').textContent   = brl(parseFloat(kpi.despesas||0));
    document.getElementById('tKpiQtd').textContent   = kpi.qtd || 0;
    const saldo = parseFloat(kpi.receitas||0) - parseFloat(kpi.despesas||0);
    document.getElementById('tKpiSaldo').textContent = (saldo >= 0 ? '+' : '') + brl(saldo);

    // Atualiza saldo do mês no card do terceiro ativo
    _saldoMes = saldo;
    if (_terceiroAtivo) {
        const box = document.getElementById(`tCardSaldoBox_${_terceiroAtivo.id}`);
        if (box) {
            box.style.background = saldo >= 0 ? 'rgba(16,185,129,.08)' : 'rgba(239,68,68,.08)';
            box.innerHTML = `<span class="text-xs text-muted">Saldo do Mês</span><span class="fw-700 ${saldo >= 0 ? 'text-emerald' : 'text-rose'}" style="font-size:.95rem">${saldo >= 0 ? '+' : ''}${brl(saldo)}</span>`;
        }
    }

    const tbody = document.getElementById('tabelaTercBody');

    _selecionadosTc.clear();
    atualizarBulkBarTc();
    const cbAllTc = document.getElementById('cbTodosTc');
    if (cbAllTc) cbAllTc.checked = false;

    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text-600)">
            Nenhum lançamento neste período.</td></tr>`;
        return;
    }
    tbody.innerHTML = rows.map(tx => {
        const isRec  = tx.tipo === 'receita';
        const catBdg = tx.cat_nome
            ? `<span class="badge" style="background:${tx.cat_cor||'#334155'}22;color:${tx.cat_cor||'#94a3b8'}">${esc(tx.cat_nome)}</span>`
            : '<span class="text-muted text-xs">—</span>';
        const contaTxt = tx.cartao_nome
            ? `<i class="fa-solid fa-credit-card fa-xs" style="color:var(--indigo);margin-right:.2rem"></i>${esc(tx.cartao_nome)}`
            : (tx.conta_nome ? esc(tx.conta_nome) : '—');

        return `<tr>
            <td style="padding-right:0;width:36px">
                <input type="checkbox" class="row-cb-tc" data-id="${+tx.id}"
                       style="accent-color:var(--indigo);width:15px;height:15px;cursor:pointer"
                       onchange="onChangeCbTc(this)">
            </td>
            <td>
                <div class="d-flex align-center gap-1">
                    <div class="tx-icon" style="background:${isRec?'var(--emerald-soft)':'var(--rose-soft)'};color:${isRec?'var(--emerald)':'var(--rose)'}">
                        <i class="fa-solid ${isRec?'fa-arrow-up':'fa-arrow-down'} fa-xs"></i>
                    </div>
                    <div class="fw-600 text-sm truncate" style="max-width:200px">${esc(tx.descricao)}</div>
                </div>
            </td>
            <td>${catBdg}</td>
            <td class="text-sm text-muted">${contaTxt}</td>
            <td class="text-sm text-muted">${fmtData(tx.data)}</td>
            <td><span class="badge ${esc(tx.status)}">${tx.status==='pago'?'Pago':'Pendente'}</span></td>
            <td class="text-right fw-600 ${isRec?'text-emerald':'text-rose'}">${isRec?'+':'-'}${brl(parseFloat(tx.valor))}</td>
            <td>
                <button class="btn-icon" onclick="excluirTx(${+tx.id})" title="Excluir"
                        style="width:26px;height:26px;font-size:.73rem;color:var(--rose)">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        </tr>`;
    }).join('');
}

// ── Modal Terceiro ────────────────────────────────────────────
function abrirModalTerceiro(id = null) {
    document.getElementById('formTerceiro').reset();
    document.getElementById('tEditId').value = id || '';
    document.getElementById('tCor').value    = '#6366f1';
    document.getElementById('tIcone').value  = 'user';

    if (id) {
        const t = _terceiros.find(x => +x.id === +id);
        if (t) {
            document.getElementById('tNome').value  = t.nome;
            document.getElementById('tDesc').value  = t.descricao || '';
            document.getElementById('tCor').value   = t.cor || '#6366f1';
            document.getElementById('tIcone').value = t.icone || 'user';
        }
        document.getElementById('modalTerceiroTitulo').textContent = 'Editar Terceiro';
    } else {
        document.getElementById('modalTerceiroTitulo').textContent = 'Novo Terceiro';
    }

    atualizarPreviewIcone();
    document.getElementById('modalTerceiro').classList.add('open');
    setTimeout(() => document.getElementById('tNome').focus(), 80);
}

function editarTerceiro(id) { abrirModalTerceiro(id); }
function fecharModalTerceiro() { document.getElementById('modalTerceiro').classList.remove('open'); }

async function salvarTerceiro(e) {
    e.preventDefault();
    try {
        const res  = await fetch('backend/api/terceiros.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                acao:      'salvar',
                id:        document.getElementById('tEditId').value || null,
                nome:      document.getElementById('tNome').value.trim(),
                descricao: document.getElementById('tDesc').value.trim(),
                cor:       document.getElementById('tCor').value,
                icone:     document.getElementById('tIcone').value.trim() || 'user',
            })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        fecharModalTerceiro();
        carregarTerceiros();
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

// ── Modal Lançamento ──────────────────────────────────────────
function abrirModalLancar() {
    if (!_terceiroAtivo) { toast('Selecione um terceiro primeiro.', 'warning'); return; }
    document.getElementById('formLancar').reset();
    document.getElementById('lData').value = new Date().toISOString().split('T')[0];
    setTipoLancar('despesa');
    document.getElementById('modalLancarTitulo').textContent = `Lançar para ${_terceiroAtivo.nome}`;
    document.getElementById('modalLancar').classList.add('open');
    setTimeout(() => document.getElementById('lDesc').focus(), 80);
}

function fecharModalLancar() { document.getElementById('modalLancar').classList.remove('open'); }

function setTipoLancar(tipo) {
    _tipoLancar = tipo;
    const des = document.getElementById('lTipoDes');
    const rec = document.getElementById('lTipoRec');
    if (tipo === 'despesa') {
        des.style.background   = 'rgba(239,68,68,.18)';
        des.style.borderColor  = 'var(--rose)';
        des.style.color        = 'var(--rose)';
        rec.style.background   = 'transparent';
        rec.style.borderColor  = 'var(--border)';
        rec.style.color        = 'var(--text-400)';
    } else {
        rec.style.background   = 'rgba(16,185,129,.18)';
        rec.style.borderColor  = 'var(--emerald)';
        rec.style.color        = 'var(--emerald)';
        des.style.background   = 'transparent';
        des.style.borderColor  = 'var(--border)';
        des.style.color        = 'var(--text-400)';
    }
}

function atualizarPreviewIcone() {
    const icone = (document.getElementById('tIcone')?.value || 'user').trim();
    const cor   = document.getElementById('tCor')?.value || '#6366f1';
    const prev  = document.getElementById('tIconePreview');
    if (prev) {
        prev.style.background = cor + '22';
        prev.style.color      = cor;
        prev.style.borderColor = cor + '55';
        prev.innerHTML = `<i class="fa-solid fa-${icone.replace(/[^a-z0-9-]/g,'')}"></i>`;
    }
    const hex = document.getElementById('tCorHex');
    if (hex) hex.textContent = cor;
}

async function salvarLancamento(e) {
    e.preventDefault();
    if (!_terceiroAtivo) return;
    try {
        const res  = await fetch('backend/api/terceiros.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                acao:         'lancar',
                terceiro_id:  _terceiroAtivo.id,
                tipo:         _tipoLancar,
                descricao:    document.getElementById('lDesc').value.trim(),
                valor:        parseCurrency(document.getElementById('lValor').value),
                data:         document.getElementById('lData').value,
                categoria_id: document.getElementById('lCat').value || null,
                conta_id:     document.getElementById('lConta').value || null,
                status:       document.getElementById('lStatus').value,
                observacao:   document.getElementById('lObs').value.trim(),
            })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        fecharModalLancar();
        carregarTerceiro();
        carregarTerceiros();
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

async function excluirTx(txId) {
    if (!_terceiroAtivo) return;
    confirmar('Excluir lançamento', 'Esta ação não pode ser desfeita.', async () => {
        try {
            const res  = await fetch('backend/api/terceiros.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ acao:'excluir_tx', id:txId, terceiro_id:_terceiroAtivo.id })
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.erro);
            toast(json.msg, 'success');
            carregarTerceiro();
            carregarTerceiros();
        } catch (err) { toast('Erro: ' + err.message, 'error'); }
    });
}

// Helpers locais (complemento ao app.js)
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmtData(s) { if(!s) return '—'; const d=s.split('T')[0].split('-'); return `${d[2]}/${d[1]}/${d[0]}`; }

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { fecharModalTerceiro(); fecharModalLancar(); }
});

// ── Seleção em lote (terceiros) ───────────────────────────
function onChangeCbTc(cb) {
    const id = +cb.dataset.id;
    if (cb.checked) _selecionadosTc.add(id);
    else            _selecionadosTc.delete(id);
    atualizarBulkBarTc();
    const total = document.querySelectorAll('.row-cb-tc').length;
    const cbAll = document.getElementById('cbTodosTc');
    if (cbAll) cbAll.checked = _selecionadosTc.size === total && total > 0;
}

function toggleTodosTc(el) {
    document.querySelectorAll('.row-cb-tc').forEach(cb => {
        cb.checked = el.checked;
        const id = +cb.dataset.id;
        if (el.checked) _selecionadosTc.add(id);
        else            _selecionadosTc.delete(id);
    });
    atualizarBulkBarTc();
}

function atualizarBulkBarTc() {
    const bar   = document.getElementById('bulkBarTc');
    const count = document.getElementById('bulkCountTc');
    if (!bar) return;
    if (_selecionadosTc.size > 0) {
        bar.style.display = 'flex';
        count.textContent = `${_selecionadosTc.size} selecionado${_selecionadosTc.size > 1 ? 's' : ''}`;
    } else {
        bar.style.display = 'none';
    }
}

function limparSelecaoTc() {
    _selecionadosTc.clear();
    document.querySelectorAll('.row-cb-tc').forEach(cb => cb.checked = false);
    const cbAll = document.getElementById('cbTodosTc');
    if (cbAll) cbAll.checked = false;
    atualizarBulkBarTc();
}

async function excluirSelecionadosTc() {
    if (!_selecionadosTc.size || !_terceiroAtivo) return;
    const n = _selecionadosTc.size;
    confirmar(
        `Excluir ${n} lançamento${n > 1 ? 's' : ''}`,
        'Esta ação não pode ser desfeita.',
        async () => {
            try {
                const res  = await fetch('backend/api/terceiros.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        acao: 'excluir_tx_bulk',
                        ids: [..._selecionadosTc],
                        terceiro_id: _terceiroAtivo.id,
                    }),
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.erro || 'Erro ao excluir');
                toast(json.msg, 'success');
                _selecionadosTc.clear();
                carregarTerceiro();
                carregarTerceiros();
            } catch (err) {
                toast('Erro: ' + err.message, 'error');
            }
        }
    );
}

function abrirModalBulkStatusTc() {
    if (!_selecionadosTc.size) return;
    document.getElementById('modalBulkStatusTc').style.display = 'flex';
}

function fecharModalBulkStatusTc() {
    document.getElementById('modalBulkStatusTc').style.display = 'none';
}

async function confirmarAlterarStatusTc() {
    const novoStatus = document.getElementById('bulkNovoStatusTc').value;
    fecharModalBulkStatusTc();
    try {
        const res  = await fetch('backend/api/terceiros.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                acao: 'alterar_status_tx_bulk',
                ids: [..._selecionadosTc],
                terceiro_id: _terceiroAtivo.id,
                status: novoStatus,
            }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro || 'Erro ao alterar');
        toast(json.msg, 'success');
        _selecionadosTc.clear();
        carregarTerceiro();
    } catch (err) {
        toast('Erro: ' + err.message, 'error');
    }
}

document.addEventListener('DOMContentLoaded', () => { initFiltrosDatas(); carregarTerceiros(); });
</script>

<!-- ── Bulk action bar (terceiros) ──────────────────────── -->
<div id="bulkBarTc" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:400;
     background:var(--bg-900,#0f172a);border-top:2px solid var(--indigo);
     padding:.65rem 1.25rem;align-items:center;gap:.75rem;flex-wrap:wrap;box-shadow:0 -4px 24px rgba(0,0,0,.35)">
    <i class="fa-solid fa-square-check" style="color:var(--indigo);font-size:1rem"></i>
    <span id="bulkCountTc" style="font-size:.85rem;font-weight:700;color:var(--text-100)">0 selecionados</span>
    <div style="flex:1"></div>
    <button onclick="abrirModalBulkStatusTc()"
            style="display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .85rem;font-size:.8rem;font-weight:600;
                   border-radius:var(--radius);border:1px solid var(--border);background:transparent;
                   color:var(--text-200);cursor:pointer;transition:var(--ease)">
        <i class="fa-solid fa-tag"></i> Alterar status
    </button>
    <button onclick="excluirSelecionadosTc()"
            style="display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .85rem;font-size:.8rem;font-weight:600;
                   border-radius:var(--radius);border:none;background:var(--rose);
                   color:#fff;cursor:pointer;transition:var(--ease)">
        <i class="fa-solid fa-trash"></i> Excluir selecionados
    </button>
    <button onclick="limparSelecaoTc()"
            style="display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .7rem;font-size:.8rem;font-weight:600;
                   border-radius:var(--radius);border:1px solid var(--border);background:transparent;
                   color:var(--text-500);cursor:pointer;transition:var(--ease)">
        <i class="fa-solid fa-xmark"></i> Cancelar
    </button>
</div>

<!-- ── Modal alterar status em lote (terceiros) ─────────── -->
<div id="modalBulkStatusTc" onclick="if(event.target===this)fecharModalBulkStatusTc()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:500;
            align-items:center;justify-content:center">
    <div style="background:var(--bg-800);border:1px solid var(--border);border-radius:var(--radius-lg);
                padding:1.5rem;width:300px;max-width:90vw">
        <div style="font-size:1rem;font-weight:700;color:var(--text-100);margin-bottom:1rem">
            <i class="fa-solid fa-tag" style="color:var(--indigo);margin-right:.4rem"></i> Alterar status
        </div>
        <div style="font-size:.82rem;color:var(--text-500);margin-bottom:.75rem">
            Novo status para os lançamentos selecionados:
        </div>
        <select id="bulkNovoStatusTc" class="form-control" style="margin-bottom:1.25rem">
            <option value="pago">Pago</option>
            <option value="pendente">Pendente</option>
            <option value="cancelado">Cancelado</option>
        </select>
        <div style="display:flex;gap:.5rem;justify-content:flex-end">
            <button onclick="fecharModalBulkStatusTc()"
                    style="padding:.35rem .85rem;font-size:.82rem;font-weight:600;border-radius:var(--radius);
                           border:1px solid var(--border);background:transparent;color:var(--text-400);cursor:pointer">
                Cancelar
            </button>
            <button onclick="confirmarAlterarStatusTc()"
                    style="padding:.35rem .85rem;font-size:.82rem;font-weight:600;border-radius:var(--radius);
                           border:none;background:var(--indigo);color:#fff;cursor:pointer">
                Aplicar
            </button>
        </div>
    </div>
</div>
