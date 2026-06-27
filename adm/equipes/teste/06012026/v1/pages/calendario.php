<?php
$nomesMeses = [
    1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',
    5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',
    9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'
];
$mesAtual = (int)date('m');
$anoAtual = (int)date('Y');
?>
<style>
/* ── Calendário ──────────────────────────────────────────── */
.cal-nav { display:flex; align-items:center; gap:.75rem; }
.cal-nav-title { font-size:1.1rem; font-weight:700; min-width:180px; text-align:center; }
.cal-grid-wrap { overflow-x:auto; }
.cal-grid {
    display:grid; grid-template-columns:repeat(7,1fr);
    gap:3px; min-width:560px;
}
.cal-dow {
    text-align:center; padding:.4rem .25rem;
    font-size:.7rem; font-weight:700; letter-spacing:.06em;
    text-transform:uppercase; color:var(--text-600);
}
.cal-cell {
    background:var(--bg-800); border:1px solid var(--border);
    border-radius:var(--radius-sm); padding:.4rem .45rem;
    min-height:90px; cursor:pointer; transition:var(--ease);
    position:relative; overflow:hidden;
}
.cal-cell:hover { border-color:var(--indigo); background:var(--bg-700); }
.cal-cell.hoje  { border-color:var(--indigo); box-shadow:0 0 0 1px var(--indigo); }
.cal-cell.outro-mes { opacity:.35; pointer-events:none; }
.cal-cell.tem-eventos:hover { z-index:1; }
.cal-dia {
    font-size:.8rem; font-weight:700; color:var(--text-400);
    margin-bottom:.3rem; line-height:1;
}
.cal-cell.hoje .cal-dia { color:var(--indigo); }
.cal-chip {
    font-size:.65rem; padding:.1rem .35rem; border-radius:3px;
    margin-bottom:2px; white-space:nowrap; overflow:hidden;
    text-overflow:ellipsis; display:block; font-weight:500;
}
.cal-chip.pago     { background:rgba(16,185,129,.18); color:var(--emerald); }
.cal-chip.atrasado { background:rgba(244,63,94,.18);  color:var(--rose); }
.cal-chip.pendente { background:rgba(245,158,11,.18); color:var(--amber); }
.cal-chip.receita  { background:rgba(99,102,241,.15); color:var(--indigo); }
.cal-mais { font-size:.6rem; color:var(--text-600); margin-top:1px; }

/* KPI sumário */
.cal-kpi { display:grid; grid-template-columns:repeat(4,1fr); gap:.75rem; margin-bottom:1.25rem; }
@media(max-width:768px){ .cal-kpi{ grid-template-columns:1fr 1fr; } }

/* Painel lateral do dia */
.cal-panel {
    position:fixed; top:0; right:0; width:340px; height:100vh;
    background:var(--bg-800); border-left:1px solid var(--border);
    display:flex; flex-direction:column; z-index:300;
    transform:translateX(100%); transition:.25s cubic-bezier(.4,0,.2,1);
    box-shadow:var(--shadow-lg);
}
.cal-panel.open { transform:none; }
.cal-panel-header {
    padding:1.1rem 1.25rem .875rem;
    border-bottom:1px solid var(--border);
    display:flex; justify-content:space-between; align-items:center;
}
.cal-panel-body { flex:1; overflow-y:auto; padding:.875rem 1.25rem; }
.cal-panel-event {
    display:flex; align-items:flex-start; gap:.6rem;
    padding:.6rem 0; border-bottom:1px solid var(--border);
}
.cal-panel-event:last-child { border:none; }
.cal-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; margin-top:.35rem; }
</style>

<!-- ── Cabeçalho + nav ──────────────────────────────────── -->
<div class="page-header">
    <div>
        <div class="page-title">Calendário de Vencimentos</div>
        <div class="page-sub">Visão mensal de receitas, despesas e parcelas</div>
    </div>
</div>

<!-- KPIs do mês -->
<div class="cal-kpi" id="calKpi">
    <?php foreach ([
        ['recebido', 'Recebido', 'emerald', 'check'],
        ['pago',     'Pago',     'rose',    'arrow-down'],
        ['a_receber','A Receber','indigo',  'clock'],
        ['a_pagar',  'A Pagar',  'amber',   'clock'],
    ] as [$key, $label, $cor, $icon]): ?>
    <div class="kpi-card <?= $cor ?>">
        <div class="kpi-header">
            <div class="kpi-label"><?= $label ?></div>
            <div class="kpi-icon <?= $cor ?>"><i class="fa-solid fa-<?= $icon ?>"></i></div>
        </div>
        <div class="kpi-value" id="kpi-<?= $key ?>">—</div>
    </div>
    <?php endforeach ?>
</div>

<!-- Navegação do mês -->
<div class="card" style="margin-bottom:1.25rem">
    <div class="card-header">
        <div class="cal-nav">
            <button class="btn btn-ghost btn-sm" onclick="navMes(-1)">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <div class="cal-nav-title" id="calTitulo">—</div>
            <button class="btn btn-ghost btn-sm" onclick="navMes(1)">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
            <button class="btn btn-ghost btn-sm" onclick="irHoje()" style="margin-left:.5rem;font-size:.78rem">
                Hoje
            </button>
        </div>
        <div id="calCarregando" style="display:none;color:var(--text-600)">
            <i class="fa-solid fa-spinner fa-spin fa-xs"></i>
        </div>
    </div>
    <div class="card-body" style="padding:.75rem">
        <div class="cal-grid-wrap">
            <div class="cal-grid" id="calGrid">
                <?php foreach (['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'] as $d): ?>
                    <div class="cal-dow"><?= $d ?></div>
                <?php endforeach ?>
            </div>
        </div>
    </div>
</div>

<!-- Painel lateral do dia -->
<div class="cal-panel" id="calPanel">
    <div class="cal-panel-header">
        <div>
            <div class="fw-700" id="panelDia">—</div>
            <div class="text-xs text-muted" id="panelSubtitulo">—</div>
        </div>
        <button class="btn-icon" onclick="fecharPanel()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="cal-panel-body" id="panelBody">—</div>
</div>
<div id="calOverlay" onclick="fecharPanel()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:299"></div>

<script>
const _MESES_NOMES = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                       'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
let _calMes  = <?= $mesAtual ?>;
let _calAno  = <?= $anoAtual ?>;
let _calEvts = {};

function navMes(d) {
    _calMes += d;
    if (_calMes > 12) { _calMes = 1;  _calAno++; }
    if (_calMes < 1)  { _calMes = 12; _calAno--; }
    carregarCalendario();
}

function irHoje() {
    const n = new Date();
    _calMes = n.getMonth() + 1;
    _calAno = n.getFullYear();
    carregarCalendario();
}

async function carregarCalendario() {
    document.getElementById('calCarregando').style.display = '';
    document.getElementById('calTitulo').textContent = `${_MESES_NOMES[_calMes]} ${_calAno}`;

    try {
        const res  = await fetch(`backend/api/calendario.php?mes=${_calMes}&ano=${_calAno}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);

        // KPIs
        const s = json.sumario;
        document.getElementById('kpi-recebido').textContent  = brl(s.recebido);
        document.getElementById('kpi-pago').textContent      = brl(s.pago);
        document.getElementById('kpi-a_receber').textContent = brl(s.a_receber);
        document.getElementById('kpi-a_pagar').textContent   = brl(s.a_pagar);

        // Indexa eventos por dia
        _calEvts = {};
        json.events.forEach(e => {
            const dia = e.data.split('T')[0];
            if (!_calEvts[dia]) _calEvts[dia] = [];
            _calEvts[dia].push(e);
        });

        renderGrade();
    } catch (err) {
        toast('Erro ao carregar calendário: ' + err.message, 'error');
    } finally {
        document.getElementById('calCarregando').style.display = 'none';
    }
}

function renderGrade() {
    const hoje     = new Date().toISOString().split('T')[0];
    const grid     = document.getElementById('calGrid');
    const diasSem  = ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'];

    // Remove todas as células (mantém os headers)
    while (grid.children.length > 7) grid.removeChild(grid.lastChild);

    // Primeiro dia do mês (ajusta para segunda=0)
    const primeiroDia = new Date(_calAno, _calMes - 1, 1);
    let dow = primeiroDia.getDay(); // 0=Dom
    dow = dow === 0 ? 6 : dow - 1; // converte para Seg=0

    const diasNoMes = new Date(_calAno, _calMes, 0).getDate();

    // Dias do mês anterior (preenchimento)
    const diasAnt = new Date(_calAno, _calMes - 1, 0).getDate();
    for (let i = dow - 1; i >= 0; i--) {
        const cell = document.createElement('div');
        cell.className = 'cal-cell outro-mes';
        cell.innerHTML = `<div class="cal-dia">${diasAnt - i}</div>`;
        grid.appendChild(cell);
    }

    // Dias do mês atual
    for (let d = 1; d <= diasNoMes; d++) {
        const dateStr  = `${_calAno}-${String(_calMes).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const evts     = _calEvts[dateStr] || [];
        const isHoje   = dateStr === hoje;

        const cell = document.createElement('div');
        cell.className = 'cal-cell' + (isHoje ? ' hoje' : '') + (evts.length ? ' tem-eventos' : '');
        if (evts.length) cell.onclick = () => abrirPanel(dateStr, d);

        let html = `<div class="cal-dia">${d}</div>`;
        const max = 3;
        evts.slice(0, max).forEach(e => {
            const cls = e.tipo === 'receita' ? 'receita'
                      : e.status === 'pago'    ? 'pago'
                      : e.data < hoje          ? 'atrasado' : 'pendente';
            const sinal = e.tipo === 'receita' ? '+' : '-';
            html += `<span class="cal-chip ${cls}">${sinal} ${brl(e.valor)}</span>`;
        });
        if (evts.length > max) {
            html += `<div class="cal-mais">+${evts.length - max} mais</div>`;
        }
        cell.innerHTML = html;
        grid.appendChild(cell);
    }

    // Preenchimento dos dias seguintes
    const total = dow + diasNoMes;
    const resto = (7 - (total % 7)) % 7;
    for (let i = 1; i <= resto; i++) {
        const cell = document.createElement('div');
        cell.className = 'cal-cell outro-mes';
        cell.innerHTML = `<div class="cal-dia">${i}</div>`;
        grid.appendChild(cell);
    }
}

function abrirPanel(dateStr, dia) {
    const evts  = _calEvts[dateStr] || [];
    const hoje  = new Date().toISOString().split('T')[0];
    const parts = dateStr.split('-');
    const semana= ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
    const dow   = semana[new Date(dateStr + 'T12:00:00').getDay()];

    document.getElementById('panelDia').textContent =
        `${dow}, ${dia} de ${_MESES_NOMES[_calMes]}`;

    const receber = evts.filter(e => e.tipo === 'receita' && e.status !== 'pago').reduce((s,e) => s + +e.valor, 0);
    const pagar   = evts.filter(e => e.tipo !== 'receita' && e.status !== 'pago').reduce((s,e) => s + +e.valor, 0);
    document.getElementById('panelSubtitulo').textContent =
        evts.length === 0 ? 'Nenhum lançamento'
        : `${evts.length} lançamento${evts.length>1?'s':''}`
          + (receber > 0 ? ` · +${brl(receber)}` : '')
          + (pagar   > 0 ? ` · -${brl(pagar)}`   : '');

    const body = document.getElementById('panelBody');
    if (!evts.length) {
        body.innerHTML = `<div class="text-sm text-muted" style="padding:1rem 0">Nenhum lançamento neste dia.</div>`;
    } else {
        body.innerHTML = evts.map(e => {
            const isR  = e.tipo === 'receita';
            const cls  = isR ? 'indigo'
                       : e.status === 'pago'   ? 'emerald'
                       : dateStr < hoje        ? 'rose' : 'amber';
            const cor  = `var(--${cls})`;
            const stat = isR
                ? (e.status === 'pago' ? 'Recebido' : 'A receber')
                : (e.status === 'pago' ? 'Pago' : dateStr < hoje ? 'Atrasado' : 'Pendente');
            return `
            <div class="cal-panel-event">
                <div class="cal-dot" style="background:${cor}"></div>
                <div style="flex:1;min-width:0">
                    <div class="fw-600 text-sm" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                        ${e.descricao || '—'}
                    </div>
                    <div class="text-xs text-muted">${e.cat_nome||'Sem categoria'} ${e.conta_nome?'· '+e.conta_nome:''}</div>
                    <div class="text-xs" style="color:${cor};margin-top:.15rem">${stat}</div>
                </div>
                <div class="fw-700 text-sm" style="color:${cor};white-space:nowrap;margin-left:.5rem">
                    ${isR?'+':'-'}${brl(e.valor)}
                </div>
            </div>`;
        }).join('');
    }

    document.getElementById('calPanel').classList.add('open');
    document.getElementById('calOverlay').style.display = '';
}

function fecharPanel() {
    document.getElementById('calPanel').classList.remove('open');
    document.getElementById('calOverlay').style.display = 'none';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharPanel(); });

// Inicializa
carregarCalendario();
</script>
