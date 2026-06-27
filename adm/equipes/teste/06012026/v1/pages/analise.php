<?php
$mesAtual = (int)date('m');
$anoAtual = (int)date('Y');
$nomesMeses = [
    1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',
    5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',
    9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'
];
?>
<style>
/* ── Tabs da análise ─────────────────────────────────────── */
.ana-tabs { display:flex; gap:.5rem; margin-bottom:1.25rem; flex-wrap:wrap; }
.ana-tab  { padding:.45rem 1rem; border-radius:var(--radius-sm); font-size:.85rem; font-weight:600;
             background:var(--bg-700); color:var(--text-400); cursor:pointer; transition:var(--ease); border:none; }
.ana-tab.active { background:var(--indigo-soft); color:var(--indigo); }
.ana-pane { display:none; }
.ana-pane.active { display:block; }

/* ── Barra de desvio ─────────────────────────────────────── */
.desvio-row {
    display:grid; grid-template-columns:140px 1fr 90px 70px;
    align-items:center; gap:.75rem; padding:.55rem 0;
    border-bottom:1px solid var(--border);
}
.desvio-row:last-child { border:none; }
.desvio-bar-bg { height:6px; background:var(--bg-600); border-radius:3px; overflow:hidden; }
.desvio-bar    { height:6px; border-radius:3px; transition:.4s; }
.semaforo { width:20px; height:20px; border-radius:50%; flex-shrink:0;
             display:flex; align-items:center; justify-content:center; font-size:.6rem; }

/* ── Comparativo: barras lado a lado ─────────────────────── */
.comp-row {
    display:grid; grid-template-columns:130px 1fr 1fr 80px 80px;
    align-items:center; gap:.5rem; padding:.5rem 0;
    border-bottom:1px solid var(--border); font-size:.82rem;
}
.comp-row:last-child { border:none; }
.comp-bar-wrap { display:flex; align-items:center; gap:.4rem; }
.comp-bar { height:10px; border-radius:3px; min-width:2px; }
</style>

<!-- Cabeçalho -->
<div class="page-header">
    <div>
        <div class="page-title">Análise de Gastos</div>
        <div class="page-sub">Comparativo anual e inteligência de consumo</div>
    </div>
    <!-- Filtro de período -->
    <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
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
        <div id="filtroMes" style="display:flex;align-items:center;gap:.5rem">
            <select id="anaFilMes" class="form-control" style="width:120px" onchange="recarregarAna()">
                <?php foreach ($nomesMeses as $n => $nm): ?>
                <option value="<?= $n ?>" <?= $n === $mesAtual ? 'selected' : '' ?>><?= $nm ?></option>
                <?php endforeach ?>
            </select>
            <select id="anaFilAno" class="form-control" style="width:90px" onchange="recarregarAna()">
                <?php for ($y = $anoAtual + 1; $y >= $anoAtual - 4; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $anoAtual ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor ?>
            </select>
        </div>
        <div id="filtroPeriodo" style="display:none;align-items:center;gap:.3rem">
            <input type="date" id="filDe" class="form-control" style="width:140px" onchange="recarregarAna()">
            <span style="color:var(--text-500);font-size:.8rem">até</span>
            <input type="date" id="filAte" class="form-control" style="width:140px" onchange="recarregarAna()">
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="ana-tabs">
    <button class="ana-tab active" onclick="anaTab('inteligente')">
        <i class="fa-solid fa-brain fa-xs"></i> Análise Inteligente
    </button>
    <button class="ana-tab" onclick="anaTab('comparativo')">
        <i class="fa-solid fa-scale-balanced fa-xs"></i> Comparativo Ano a Ano
    </button>
</div>

<!-- ═══════ ABA 1 — INTELIGENTE ════════════════════════════ -->
<div id="pane-inteligente" class="ana-pane active">

    <!-- KPIs globais -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem;margin-bottom:1.25rem" id="intKpis">
        <div class="kpi-card rose">
            <div class="kpi-label">Total do Mês</div>
            <div class="kpi-value" id="intTotalAtual">—</div>
        </div>
        <div class="kpi-card indigo">
            <div class="kpi-label">Média 3 Meses</div>
            <div class="kpi-value" id="intMedia3m">—</div>
        </div>
        <div class="kpi-card" id="intDesvioCard">
            <div class="kpi-label">Variação</div>
            <div class="kpi-value" id="intDesvioGlobal">—</div>
            <div class="kpi-trend" id="intDesvioTrend"></div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Proporção por Categoria</div>
                <div class="card-subtitle" id="intDonutSub">—</div>
            </div>
            <div class="card-body">
                <div style="position:relative;height:200px">
                    <canvas id="chartIntDonut"></canvas>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">Evolução 3 Meses</div>
                <div class="card-subtitle">Atual vs média anterior</div>
            </div>
            <div class="card-body">
                <div style="position:relative;height:200px">
                    <canvas id="chartIntEvolucao"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Gasto por Categoria vs. Média dos 3 Meses Anteriores</div>
        </div>
        <div class="card-body" id="intBody">
            <div style="text-align:center;padding:2rem;color:var(--text-600)">
                <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
            </div>
        </div>
    </div>
</div>

<!-- ═══════ ABA 2 — COMPARATIVO ════════════════════════════ -->
<div id="pane-comparativo" class="ana-pane">

    <!-- KPIs comparativos -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem;margin-bottom:1.25rem">
        <div class="kpi-card rose">
            <div class="kpi-label" id="compLabelAtual">Mês Atual</div>
            <div class="kpi-value" id="compTotAtual">—</div>
        </div>
        <div class="kpi-card indigo">
            <div class="kpi-label" id="compLabelAnt">Mesmo Mês Ano Passado</div>
            <div class="kpi-value" id="compTotAnt">—</div>
        </div>
        <div class="kpi-card" id="compVarCard">
            <div class="kpi-label">Variação</div>
            <div class="kpi-value" id="compVar">—</div>
            <div class="kpi-trend" id="compVarTrend"></div>
        </div>
    </div>

    <!-- Por categoria -->
    <div class="card" style="margin-bottom:1.25rem">
        <div class="card-header">
            <div class="card-title">Por Categoria</div>
        </div>
        <div class="card-body" id="compCatBody">
            <div style="text-align:center;padding:2rem;color:var(--text-600)">
                <i class="fa-solid fa-spinner fa-spin"></i>
            </div>
        </div>
    </div>

    <!-- Gráfico de tendência 12 meses -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Tendência — últimos 12 meses</div>
        </div>
        <div class="card-body">
            <canvas id="chartTendencia" height="80"></canvas>
        </div>
    </div>
</div>

<script>
let _anaTabAtiva = 'inteligente';
let _anaCarregado = {};

// ── Modo de filtro: Mês/Ano ou Período ───────────────────
let _modoFiltro = 'mes';

function setModoFiltro(modo, carregar = true) {
    _modoFiltro = modo;
    document.getElementById('filtroMes').style.display     = modo === 'mes'     ? 'flex' : 'none';
    document.getElementById('filtroPeriodo').style.display = modo === 'periodo' ? 'flex' : 'none';
    const bm = document.getElementById('fBtnMes');
    const bp = document.getElementById('fBtnPer');
    bm.style.background = modo === 'mes'     ? 'var(--indigo)' : 'transparent';
    bm.style.color      = modo === 'mes'     ? '#fff'          : 'var(--text-500)';
    bp.style.background = modo === 'periodo' ? 'var(--indigo)' : 'transparent';
    bp.style.color      = modo === 'periodo' ? '#fff'          : 'var(--text-500)';
    if (carregar) recarregarAna();
}

function _getDateRange() {
    if (_modoFiltro === 'periodo') {
        return { de: document.getElementById('filDe').value, ate: document.getElementById('filAte').value };
    }
    const mes = parseInt(document.getElementById('anaFilMes').value);
    const ano = parseInt(document.getElementById('anaFilAno').value);
    const ult = new Date(ano, mes, 0).getDate();
    const m   = String(mes).padStart(2,'0');
    return { de: `${ano}-${m}-01`, ate: `${ano}-${m}-${String(ult).padStart(2,'0')}` };
}

function _initPeriodoDatas() {
    const hoje = new Date();
    const y = hoje.getFullYear();
    const m = String(hoje.getMonth() + 1).padStart(2, '0');
    const ult = new Date(y, hoje.getMonth() + 1, 0).getDate();
    document.getElementById('filDe').value  = `${y}-${m}-01`;
    document.getElementById('filAte').value = `${y}-${m}-${String(ult).padStart(2, '0')}`;
}

function anaTab(id) {
    document.querySelectorAll('.ana-tab').forEach((b, i) =>
        b.classList.toggle('active', ['inteligente','comparativo'][i] === id));
    document.querySelectorAll('.ana-pane').forEach(p => p.classList.remove('active'));
    document.getElementById(`pane-${id}`).classList.add('active');
    _anaTabAtiva = id;
    if (!_anaCarregado[id]) carregarAna(id);
}

function recarregarAna() {
    _anaCarregado = {};
    carregarAna(_anaTabAtiva);
}

async function carregarAna(tipo) {
    let mes, ano;
    if (_modoFiltro === 'periodo') {
        const de = document.getElementById('filDe').value;
        const partes = de.split('-');
        ano = partes[0];
        mes = parseInt(partes[1]).toString();
    } else {
        mes = document.getElementById('anaFilMes').value;
        ano = document.getElementById('anaFilAno').value;
    }

    try {
        const res  = await fetch(`backend/api/analise.php?tipo=${tipo}&mes=${mes}&ano=${ano}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);

        if (tipo === 'inteligente') renderInteligente(json);
        else                        renderComparativo(json);
        _anaCarregado[tipo] = true;
    } catch (err) {
        toast('Erro na análise: ' + err.message, 'error');
    }
}

// ── Análise Inteligente ───────────────────────────────────
function renderInteligente(json) {
    const tot = json.totalAtual, med = json.media3mTotal;
    document.getElementById('intTotalAtual').textContent = brl(tot);
    document.getElementById('intMedia3m').textContent    = brl(med);

    const dPct = med > 0 ? ((tot - med) / med * 100).toFixed(1) : null;
    const card  = document.getElementById('intDesvioCard');
    const dEl   = document.getElementById('intDesvioGlobal');
    const tEl   = document.getElementById('intDesvioTrend');

    if (dPct !== null) {
        const acima = +dPct > 0;
        dEl.textContent  = (acima ? '+' : '') + dPct + '%';
        tEl.textContent  = acima ? '▲ Acima da média' : '▼ Abaixo da média';
        tEl.className    = 'kpi-trend ' + (acima ? 'down' : 'up');
        card.className   = 'kpi-card ' + (acima ? 'rose' : 'emerald');
    } else {
        dEl.textContent = '—'; tEl.textContent = 'Sem histórico';
    }

    // Donut de proporção
    const donutCtx = document.getElementById('chartIntDonut');
    const evolCtx  = document.getElementById('chartIntEvolucao');
    const subEl    = document.getElementById('intDonutSub');
    const nomeMesAna = document.getElementById('anaFilMes')?.options[document.getElementById('anaFilMes').selectedIndex]?.text || '';
    if (subEl) subEl.textContent = nomeMesAna + ' ' + (document.getElementById('anaFilAno')?.value || '');

    if (donutCtx && json.dados.length > 0) {
        if (window._chartIntDonut) window._chartIntDonut.destroy();
        window._chartIntDonut = new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: json.dados.map(d => d.cat_nome),
                datasets: [{
                    data: json.dados.map(d => parseFloat(d.atual)),
                    backgroundColor: json.dados.map(d => d.cor || '#6366f1'),
                    borderWidth: 0,
                    hoverOffset: 4,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => ' ' + brl(ctx.parsed) } }
                }
            }
        });
    }

    if (evolCtx && json.dados.length > 0) {
        if (window._chartIntEv) window._chartIntEv.destroy();
        window._chartIntEv = new Chart(evolCtx, {
            type: 'bar',
            data: {
                labels: json.dados.map(d => d.cat_nome.length > 10 ? d.cat_nome.slice(0,10)+'…' : d.cat_nome),
                datasets: [
                    { label: 'Atual',    data: json.dados.map(d => parseFloat(d.atual)),    backgroundColor: json.dados.map(d => d.cor || '#6366f1'), borderRadius: 4, borderSkipped: false },
                    { label: 'Média 3m', data: json.dados.map(d => parseFloat(d.media3m)), backgroundColor: json.dados.map(d => (d.cor || '#6366f1') + '44'), borderRadius: 4, borderSkipped: false },
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, labels: { color:'#64748b', boxWidth:10, font:{size:10} } },
                    tooltip: { callbacks: { label: ctx => ' ' + brl(ctx.parsed.y) } }
                },
                scales: {
                    x: { grid:{color:'rgba(255,255,255,.05)'}, ticks:{color:'#64748b',font:{size:9},maxRotation:30} },
                    y: { grid:{color:'rgba(255,255,255,.05)'}, ticks:{color:'#64748b',font:{size:10}, callback: v => 'R$'+(v>=1000?(v/1000).toFixed(0)+'k':v)} }
                }
            }
        });
    }

    // Barras por categoria
    const body    = document.getElementById('intBody');
    const maxVal  = Math.max(...json.dados.map(d => Math.max(d.atual, d.media3m)), 1);

    if (!json.dados.length) {
        body.innerHTML = `<div class="text-muted text-sm" style="padding:1rem">Sem dados para análise.</div>`;
        return;
    }

    // Header
    let html = `<div class="desvio-row" style="border-bottom:2px solid var(--border);padding-bottom:.4rem">
        <div class="text-xs fw-600 text-muted">CATEGORIA</div>
        <div class="text-xs fw-600 text-muted">ATUAL vs MÉDIA</div>
        <div class="text-xs fw-600 text-muted text-right">ATUAL</div>
        <div class="text-xs fw-600 text-muted text-right">MÉDIA 3M</div>
    </div>`;

    json.dados.forEach(d => {
        const desvio  = d.desvio_pct;
        const semCor  = desvio === null ? 'var(--text-600)'
                      : Math.abs(desvio) <= 10 ? 'var(--emerald)'
                      : Math.abs(desvio) <= 30 ? 'var(--amber)' : 'var(--rose)';
        const semIcon = desvio === null ? '—'
                      : Math.abs(desvio) <= 10 ? '✓'
                      : desvio > 0 ? '▲' : '▼';
        const pctAtual = (d.atual  / maxVal * 100).toFixed(1);
        const pctMedia = (d.media3m / maxVal * 100).toFixed(1);
        const desvStr  = desvio !== null ? (desvio > 0 ? '+' : '') + desvio + '%' : '—';
        const corBarra = d.cor || '#6366f1';

        html += `<div class="desvio-row">
            <div>
                <div class="text-sm fw-600 truncate" style="max-width:135px">${d.cat_nome}</div>
                <div class="text-xs" style="color:${semCor}">${semIcon} ${desvStr}</div>
            </div>
            <div>
                <div style="margin-bottom:3px">
                    <div class="desvio-bar-bg">
                        <div class="desvio-bar" style="width:${pctAtual}%;background:${corBarra}"></div>
                    </div>
                </div>
                <div class="desvio-bar-bg">
                    <div class="desvio-bar" style="width:${pctMedia}%;background:${corBarra}55"></div>
                </div>
            </div>
            <div class="text-right fw-600 text-sm text-rose">${brl(d.atual)}</div>
            <div class="text-right text-sm text-muted">${brl(d.media3m)}</div>
        </div>`;
    });
    body.innerHTML = html;
}

// ── Comparativo Ano a Ano ─────────────────────────────────
let _chartTend = null;
function renderComparativo(json) {
    const mes   = document.getElementById('anaFilMes');
    const nomeMes = mes.options[mes.selectedIndex].text;

    document.getElementById('compLabelAtual').textContent = `${nomeMes} ${json.ano}`;
    document.getElementById('compLabelAnt').textContent   = `${nomeMes} ${json.anoAnt}`;
    document.getElementById('compTotAtual').textContent   = brl(json.totAtual);
    document.getElementById('compTotAnt').textContent     = brl(json.totAnterior);

    const dif  = json.totAtual - json.totAnterior;
    const dPct = json.totAnterior > 0 ? (dif / json.totAnterior * 100).toFixed(1) : null;
    document.getElementById('compVar').textContent = dPct !== null ? (dif > 0 ? '+' : '') + dPct + '%' : '—';
    document.getElementById('compVarTrend').className = 'kpi-trend ' + (dif > 0 ? 'down' : 'up');
    document.getElementById('compVarTrend').textContent = dif > 0 ? '▲ Acima do ano passado' : '▼ Abaixo do ano passado';
    document.getElementById('compVarCard').className = 'kpi-card ' + (dif > 0 ? 'rose' : 'emerald');

    // Por categoria
    const body   = document.getElementById('compCatBody');
    const maxVal = Math.max(...json.categorias.map(c => Math.max(c.atual, c.anterior)), 1);
    if (!json.categorias.length) {
        body.innerHTML = `<div class="text-muted text-sm" style="padding:1rem">Sem dados para comparar.</div>`;
    } else {
        let html = `<div class="comp-row" style="border-bottom:2px solid var(--border);padding-bottom:.35rem">
            <div class="text-xs fw-600 text-muted">CATEGORIA</div>
            <div class="text-xs fw-600" style="color:var(--rose)">${json.ano}</div>
            <div class="text-xs fw-600" style="color:var(--indigo)">${json.anoAnt}</div>
            <div class="text-xs fw-600 text-muted text-right">${json.ano}</div>
            <div class="text-xs fw-600 text-muted text-right">${json.anoAnt}</div>
        </div>`;
        json.categorias.forEach(c => {
            const pA = (c.atual    / maxVal * 100).toFixed(1);
            const pB = (c.anterior / maxVal * 100).toFixed(1);
            const dif = c.atual - c.anterior;
            const cor = c.cat_cor || '#6366f1';
            html += `<div class="comp-row">
                <div class="text-sm fw-600 truncate" style="max-width:125px">${c.cat_nome}</div>
                <div class="comp-bar-wrap">
                    <div class="comp-bar" style="width:${pA}%;background:${cor};flex:none;max-width:120px;width:${pA}%"></div>
                </div>
                <div class="comp-bar-wrap">
                    <div class="comp-bar" style="width:${pB}%;background:${cor}55;flex:none;max-width:120px;width:${pB}%"></div>
                </div>
                <div class="text-right fw-600 text-sm text-rose">${brl(c.atual)}</div>
                <div class="text-right text-sm text-muted">${brl(c.anterior)}</div>
            </div>`;
        });
        body.innerHTML = html;
    }

    // Gráfico de tendência
    const ctx = document.getElementById('chartTendencia').getContext('2d');
    if (_chartTend) _chartTend.destroy();
    _chartTend = new Chart(ctx, {
        type: 'line',
        data: {
            labels: json.tendencia.map(t => t.label),
            datasets: [{
                label: 'Despesas',
                data:  json.tendencia.map(t => t.total),
                borderColor: '#f43f5e',
                backgroundColor: 'rgba(244,63,94,.08)',
                fill: true, tension: .35, pointRadius: 3,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,.05)' }, ticks: { color: '#64748b', font: { size: 10 } } },
                y: { grid: { color: 'rgba(255,255,255,.05)' }, ticks: { color: '#64748b', font: { size: 10 },
                     callback: v => 'R$' + (v >= 1000 ? (v/1000).toFixed(1) + 'k' : v) } }
            }
        }
    });
}

// Inicia ao carregar
document.addEventListener('DOMContentLoaded', () => {
    _initPeriodoDatas();
    carregarAna('inteligente');
});
</script>
