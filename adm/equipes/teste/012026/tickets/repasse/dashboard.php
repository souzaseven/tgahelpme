<?php
require_once __DIR__ . '/includes/session.php';

if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$nomeUsuario = htmlspecialchars($_SESSION['nome']);
$isAdmin     = (int) $_SESSION['is_admin'];

require_once __DIR__ . '/../backend/conexao.php';
require_once __DIR__ . '/includes/config.php';

// Lideres para o select
$lideres = array_keys(ORDEM_EQUIPES);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Repasse de Tickets</title>
    <link rel="icon" href="https://tgameajuda.com/img/principal/bot-tga.webp" type="image/x-icon">
    <link rel="stylesheet" href="assets/style.css">
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-E7ZNTJSRYR"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-E7ZNTJSRYR');
    </script>
    <style>
        /* ── extras dashboard ── */
        .dash-header-bar {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 20px; flex-wrap: wrap; gap: 12px;
        }

        .dash-header-bar h2 { font-size: 18px; font-weight: 700; }

        .dash-filters {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 18px 20px; margin-bottom: 24px;
            display: flex; flex-wrap: wrap; align-items: flex-end; gap: 16px;
        }

        .dash-filters .form-group { margin-bottom: 0; min-width: 160px; }

        .dash-filters .form-group.form-group-lg { min-width: 220px; }

        .dash-stats {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 10px; margin-bottom: 28px;
        }

        .dash-stat-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 12px 14px;
            box-shadow: var(--shadow);
        }

        .dash-stat-card .stat-label {
            font-size: 10px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .5px; color: var(--muted); margin-bottom: 6px;
        }

        .dash-stat-card .stat-number {
            font-size: 24px; font-weight: 800; color: var(--primary); line-height: 1;
        }

        .dash-stat-card .stat-sub { font-size: 10px; color: var(--muted); margin-top: 4px; }

        .dash-stat-card.card-success .stat-number { color: var(--success); }
        .dash-stat-card.card-warning .stat-number { color: var(--warning); }
        .dash-stat-card.card-muted   .stat-number { color: var(--muted); }

        .section-title {
            font-size: 14px; font-weight: 700; color: var(--text);
            margin-bottom: 12px; padding-bottom: 8px;
            border-bottom: 2px solid var(--border);
        }

        .dash-sections {
            display: grid;
            grid-template-columns: minmax(300px, 0.72fr) minmax(760px, 1.68fr);
            gap: 24px;
            align-items: start;
            margin-bottom: 28px;
        }

        .dash-left-column {
            display: grid;
            gap: 24px;
            min-width: 0;
        }

        .dash-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 18px;
            display: flex;
            flex-direction: column;
            min-width: 0;
            width: 100%;
        }

        .table-section { margin-bottom: 0; }

        .dash-table-wrap { overflow-x: auto; border-radius: var(--radius); border: 1px solid var(--border); width: 100%; }

        .dash-table {
            width: 100%; border-collapse: collapse;
            background: var(--surface);
        }

        .dash-table th {
            background: #f8fafc; color: var(--muted); font-size: 11px;
            font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
            padding: 10px 14px; text-align: left; border-bottom: 2px solid var(--border);
            white-space: nowrap;
        }

        .dash-table th:not(:first-child):not(.th-nome) { text-align: right; }

        .dash-table td {
            padding: 9px 14px; border-bottom: 1px solid var(--border);
            font-size: 13px; vertical-align: middle;
        }

        .dash-table td:not(:first-child) { text-align: right; }
        .dash-table tbody tr:last-child td { border-bottom: none; }
        .dash-table tbody tr:hover { background: var(--bg); }

        #tbl-todos .col-operador,
        #tbl-todos .cell-operador { text-align: left !important; }

        #tbl-todos {
            table-layout: fixed;
            min-width: 0;
            width: 100%;
        }

        #tbl-todos .col-rank,
        #tbl-todos .cell-rank { width: 4%; }

        #tbl-todos .col-operador,
        #tbl-todos .cell-operador { width: 24%; }

        #tbl-todos .col-equipe,
        #tbl-todos .cell-equipe { width: 13%; }

        #tbl-todos .col-total,
        #tbl-todos .cell-total { width: 9%; }

        #tbl-todos .col-semanas,
        #tbl-todos .cell-semanas { width: 10%; }

        #tbl-todos .col-media,
        #tbl-todos .cell-media { width: 10%; }

        #tbl-todos .col-contratacao,
        #tbl-todos .cell-contratacao { width: 11%; }

        #tbl-todos .col-tempo,
        #tbl-todos .cell-tempo { width: 17%; }

        #tbl-todos th,
        #tbl-todos td {
            padding: 8px 10px;
            font-size: 12px;
        }

        #tbl-todos th {
            white-space: normal;
            line-height: 1.15;
        }

        #tbl-todos th.sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
            padding-right: 20px;
        }

        #tbl-todos th.sortable::after {
            content: '↕';
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 10px;
            color: var(--muted);
            opacity: 0.65;
        }

        #tbl-todos th.sortable.sort-asc::after {
            content: '▲';
            opacity: 0.95;
        }

        #tbl-todos th.sortable.sort-desc::after {
            content: '▼';
            opacity: 0.95;
        }

        #tbl-todos th + th,
        #tbl-todos td + td {
            border-left: 1px solid var(--border);
        }

        #tbl-todos .cell-operador {
            white-space: normal;
            word-break: break-word;
            min-width: 140px;
        }

        #tbl-todos .cell-tempo { white-space: normal; }

        .dash-table .total-col { font-weight: 700; color: var(--primary); }
        .dash-table .rank-col  { color: var(--muted); font-size: 12px; font-weight: 700; }
        .dash-table .media-col { color: var(--success); font-weight: 600; }

        .badge-lider {
            display: inline-block; padding: 1px 8px; border-radius: 999px;
            font-size: 10px; font-weight: 700; background: var(--tef-bg);
            color: var(--tef-color); white-space: nowrap;
        }

        .empty-dash {
            text-align: center; padding: 48px 24px; color: var(--muted); font-size: 14px;
        }

        .loading-dash {
            text-align: center; padding: 48px; color: var(--muted); font-size: 14px;
        }

        body.dark .dash-table th { background: #162032; }
        body.dark .dash-table tbody tr:hover { background: #1a2a3a; }
        body.dark .dash-stat-card { background: var(--surface); }

        .muted-inline { color: var(--muted); font-size: 12px; }

        @media (max-width: 1500px) {
            .dash-sections { grid-template-columns: minmax(280px, 0.74fr) minmax(560px, 1.66fr); }

            #tbl-todos .col-contratacao,
            #tbl-todos .cell-contratacao { display: none; }
        }

        @media (max-width: 1100px) {
            .dash-sections { grid-template-columns: 1fr; }

            #tbl-todos .col-semanas,
            #tbl-todos .cell-semanas { display: none; }

            #tbl-todos .col-media,
            #tbl-todos .cell-media { display: none; }
        }

        @media (max-width: 760px) {
            .dash-content { padding: 16px; }
            .dash-sections { grid-template-columns: 1fr; }

            #tbl-todos .col-tempo,
            #tbl-todos .cell-tempo,
            #tbl-todos .col-equipe,
            #tbl-todos .cell-equipe { display: none; }
        }
    </style>
</head>
<body>
<script src="assets/common.js?v=1"></script>

<!-- ── HEADER ── -->
<header class="topbar">
    <div class="topbar-left">
        <span class="topbar-icon">📊</span>
        <h1 class="topbar-title">Dashboard — Repasse</h1>
    </div>
    <div class="topbar-right">
        <a href="index.php"      class="btn btn-outline btn-sm">🎫 Repasse</a>
        <a href="operadores.php" class="btn btn-outline btn-sm">👥 Operadores</a>
        <?php if ($isAdmin): ?>
            <span class="badge-admin">Admin</span>
            <a href="usuarios.php"  class="btn btn-outline btn-sm">🔑 Usuarios</a>
            <a href="historico.php" class="btn btn-outline btn-sm">📋 Histórico</a>
        <?php endif; ?>
        <span class="topbar-user">👤 <?= $nomeUsuario ?></span>
        <button id="btn-tema" class="btn btn-outline btn-sm">🌙</button>
        <a href="logout.php" class="btn btn-outline btn-sm">Sair</a>
    </div>
</header>

<!-- ── CONTEUDO ── -->
<div class="dash-content">

    <!-- Filtros -->
    <div class="dash-filters">
        <div class="form-group">
            <label for="f-de">De (semana)</label>
            <input type="date" id="f-de" class="form-control" style="width:160px">
        </div>
        <div class="form-group">
            <label for="f-ate">Ate (semana)</label>
            <input type="date" id="f-ate" class="form-control" style="width:160px">
        </div>
        <div class="form-group">
            <label for="f-lider">Equipe</label>
            <select id="f-lider" class="form-control" style="width:200px">
                <option value="">Todas as equipes</option>
                <?php foreach ($lideres as $l): ?>
                    <option value="<?= htmlspecialchars($l) ?>"><?= htmlspecialchars($l) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group form-group-lg">
            <label for="f-tempo-empresa">Tempo de empresa</label>
            <select id="f-tempo-empresa" class="form-control" style="width:220px">
                <option value="">Todos</option>
                <option value="ate_6">Até 6 meses</option>
                <option value="6_12">6 a 12 meses</option>
                <option value="12_24">1 a 2 anos</option>
                <option value="24_plus">Mais de 2 anos</option>
                <option value="sem_data">Sem data de contratação</option>
            </select>
        </div>
        <div class="form-group form-group-lg">
            <label for="f-conhecimento">Conhecimento</label>
            <select id="f-conhecimento" class="form-control" style="width:220px">
                <option value="">Todos</option>
                <option value="tef">Apenas TEF</option>
                <option value="troca_regime">Apenas Troca de Regime</option>
            </select>
        </div>
        <div class="form-group form-group-lg">
            <label for="f-ordem-equipes">Ordenar equipes</label>
            <select id="f-ordem-equipes" class="form-control" style="width:220px">
                <option value="total_desc">Maior total de tickets</option>
                <option value="total_asc">Menor total de tickets</option>
                <option value="lider_asc">Equipe A-Z</option>
                <option value="lider_desc">Equipe Z-A</option>
                <option value="ferias_desc">Mais férias</option>
                <option value="ferias_asc">Menos férias</option>
            </select>
        </div>
        <div class="form-group form-group-lg">
            <label for="f-ordem-operadores">Ordenar operadores</label>
            <select id="f-ordem-operadores" class="form-control" style="width:220px">
                <option value="total_desc">Maior total de tickets</option>
                <option value="total_asc">Menor total de tickets</option>
                <option value="semanas_desc">Mais semanas ativas</option>
                <option value="semanas_asc">Menos semanas ativas</option>
                <option value="nome_asc">Nome A-Z</option>
                <option value="nome_desc">Nome Z-A</option>
                <option value="lider_asc">Equipe A-Z</option>
                <option value="lider_desc">Equipe Z-A</option>
                <option value="media_desc">Maior média/semana</option>
                <option value="media_asc">Menor média/semana</option>
                <option value="contratacao_desc">Contratação mais recente</option>
                <option value="contratacao_asc">Contratação mais antiga</option>
                <option value="tempo_desc">Mais tempo de empresa</option>
                <option value="tempo_asc">Menos tempo de empresa</option>
            </select>
        </div>
        
    </div>

    <!-- Stats -->
    <div class="dash-stats" id="dash-stats">
        <div class="loading-dash">Aplique os filtros para carregar os dados.</div>
    </div>

    <!-- Tabelas -->
    <div id="dash-corpo" hidden>

        <div class="dash-sections">
            <div class="dash-left-column">
                <section class="dash-panel">
                    <div class="section-title">Tickets por Equipe</div>
                    <div class="dash-table-wrap">
                        <table class="dash-table" id="tbl-equipes">
                            <thead>
                                <tr>
                                    <th>Equipe</th>
                                    <th>Total Tickets</th>
                                    <th>Em Ferias</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </section>
                <section class="dash-panel">
                    <div class="section-title">Top 10 Operadores</div>
                    <div class="dash-table-wrap">
                        <table class="dash-table" id="tbl-top">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th class="th-nome">Operador</th>
                                    <th>Total</th>
                                    <th>Media/sem</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </section>
            </div>
            <section class="dash-panel table-section">
                <div class="section-title">Todos os Operadores no Periodo</div>
                <div class="dash-table-wrap">
                    <table class="dash-table" id="tbl-todos">
                        <thead>
                            <tr>
                                <th class="col-rank">#</th>
                                <th class="th-nome col-operador sortable" data-sort="nome">Operador</th>
                                <th class="col-equipe sortable" data-sort="lider">Equipe</th>
                                <th class="col-total sortable" data-sort="total">Total</th>
                                <th class="col-semanas sortable" data-sort="semanas">Semanas<br>ativas</th>
                                <th class="col-media sortable" data-sort="media">Media<br>semanal</th>
                                <th class="col-contratacao sortable" data-sort="contratacao">Contratação</th>
                                <th class="col-tempo sortable" data-sort="tempo">Tempo de<br>empresa</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </section>
        </div>

    </div><!-- /dash-corpo -->


</div>

<div id="toast" class="toast" hidden></div>

<script>
// ─── Default: semana atual ───────────────────────────────────────────────────
(function () {
    const hoje  = new Date();
    const dow   = hoje.getDay();
    const diff  = dow === 0 ? -6 : 1 - dow;
    const seg   = new Date(hoje);
    seg.setDate(hoje.getDate() + diff);

    const fmt = d => {
        const y = d.getFullYear();
        const m = String(d.getMonth()+1).padStart(2,'0');
        const dd= String(d.getDate()).padStart(2,'0');
        return `${y}-${m}-${dd}`;
    };

    document.getElementById('f-de').value  = fmt(seg);
    document.getElementById('f-ate').value = fmt(seg);
})();

// ─── Estado global do dashboard ──────────────────────────────────────────────
let dadosAtuais = null;
const STORAGE_DASH_FILTROS = 'repasse:dashboard-filtros';
let autoBuscarTimer = null;

function restaurarFiltrosDashboard() {
    const raw = localStorage.getItem(STORAGE_DASH_FILTROS);
    if (!raw) return;

    try {
        const prefs = JSON.parse(raw);
        if (prefs.de) document.getElementById('f-de').value = prefs.de;
        if (prefs.ate) document.getElementById('f-ate').value = prefs.ate;
        if (prefs.lider !== undefined) document.getElementById('f-lider').value = prefs.lider;
        if (prefs.tempoEmpresa !== undefined) document.getElementById('f-tempo-empresa').value = prefs.tempoEmpresa;
        if (prefs.conhecimento !== undefined) document.getElementById('f-conhecimento').value = prefs.conhecimento;
        if (prefs.ordemEquipes) document.getElementById('f-ordem-equipes').value = prefs.ordemEquipes;
        if (prefs.ordemOperadores) document.getElementById('f-ordem-operadores').value = prefs.ordemOperadores;
    } catch (e) {
        // Ignora preferências inválidas.
    }
}

function salvarFiltrosDashboard() {
    const prefs = {
        de: document.getElementById('f-de').value,
        ate: document.getElementById('f-ate').value,
        lider: document.getElementById('f-lider').value,
        tempoEmpresa: document.getElementById('f-tempo-empresa').value,
        conhecimento: document.getElementById('f-conhecimento').value,
        ordemEquipes: document.getElementById('f-ordem-equipes').value,
        ordemOperadores: document.getElementById('f-ordem-operadores').value,
    };

    localStorage.setItem(STORAGE_DASH_FILTROS, JSON.stringify(prefs));
}

restaurarFiltrosDashboard();

document.getElementById('f-tempo-empresa').addEventListener('change', () => {
    salvarFiltrosDashboard();
    if (dadosAtuais) renderizarDashboard(dadosAtuais);
});
document.getElementById('f-conhecimento').addEventListener('change', () => {
    salvarFiltrosDashboard();
    if (dadosAtuais) renderizarDashboard(dadosAtuais);
});
document.getElementById('f-ordem-equipes').addEventListener('change', () => {
    salvarFiltrosDashboard();
    if (dadosAtuais) renderizarDashboard(dadosAtuais);
});
document.getElementById('f-ordem-operadores').addEventListener('change', () => {
    salvarFiltrosDashboard();
    if (dadosAtuais) renderizarDashboard(dadosAtuais);
});
document.getElementById('f-lider').addEventListener('change', () => {
    salvarFiltrosDashboard();
    agendarBuscaAutomatica();
});
document.getElementById('f-de').addEventListener('change', () => {
    salvarFiltrosDashboard();
    agendarBuscaAutomatica();
});
document.getElementById('f-ate').addEventListener('change', () => {
    salvarFiltrosDashboard();
    agendarBuscaAutomatica();
});

function agendarBuscaAutomatica() {
    if (autoBuscarTimer) clearTimeout(autoBuscarTimer);
    autoBuscarTimer = setTimeout(() => {
        autoBuscarTimer = null;
        buscarDados();
    }, 220);
}

configurarOrdenacaoTabelaTodos();

function configurarOrdenacaoTabelaTodos() {
    const cabecalhos = document.querySelectorAll('#tbl-todos thead th.sortable');
    const selectOrdenacao = document.getElementById('f-ordem-operadores');

    cabecalhos.forEach(th => {
        th.addEventListener('click', () => {
            const campo = th.dataset.sort;
            if (!campo) return;

            const [campoAtual, direcaoAtual] = String(selectOrdenacao.value || 'total_desc').split('_');
            const proximaDirecao = campoAtual === campo && direcaoAtual === 'desc' ? 'asc' : 'desc';
            const novoValor = `${campo}_${proximaDirecao}`;

            if ([...selectOrdenacao.options].some(opt => opt.value === novoValor)) {
                selectOrdenacao.value = novoValor;
            }

            salvarFiltrosDashboard();
            if (dadosAtuais) renderizarDashboard(dadosAtuais);
        });
    });
}

function atualizarIndicadoresOrdenacaoTabelaTodos() {
    const [campoAtual, direcaoAtual] = String(document.getElementById('f-ordem-operadores').value || '').split('_');
    const cabecalhos = document.querySelectorAll('#tbl-todos thead th.sortable');

    cabecalhos.forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
        if (th.dataset.sort === campoAtual) {
            th.classList.add(direcaoAtual === 'asc' ? 'sort-asc' : 'sort-desc');
        }
    });
}

async function buscarDados() {
    const de    = document.getElementById('f-de').value;
    const ate   = document.getElementById('f-ate').value;
    const lider = document.getElementById('f-lider').value;

    salvarFiltrosDashboard();

    if (!de || !ate) {
        mostrarToast('Selecione o periodo de datas.', 'error');
        return;
    }

    const statsEl = document.getElementById('dash-stats');
    statsEl.innerHTML = '<div class="loading-dash">⏳ Carregando...</div>';
    document.getElementById('dash-corpo').hidden = true;

    try {
        const url = `api/dashboard.php?de=${de}&ate=${ate}` + (lider ? `&lider=${encodeURIComponent(lider)}` : '');
        const res  = await fetch(url);

        if (res.status === 401) { window.location.href = 'login.php'; return; }

        const json = await res.json();
        if (json.erro) { mostrarToast(json.erro, 'error'); return; }

        dadosAtuais = json;
        renderizarDashboard(json);

    } catch (e) {
        console.error(e);
        mostrarToast('Erro ao carregar dados.', 'error');
    }
}

function renderizarDashboard(data) {
    const ordemEquipes = document.getElementById('f-ordem-equipes').value;
    const ordemOperadores = document.getElementById('f-ordem-operadores').value;
    const filtroTempoEmpresa = document.getElementById('f-tempo-empresa').value;
    const filtroConhecimento = document.getElementById('f-conhecimento').value;

    const operadoresFiltrados = data.por_operador.filter(op => {
        return aplicaFiltroTempoEmpresa(op, filtroTempoEmpresa)
            && aplicaFiltroConhecimento(op, filtroConhecimento);
    });
    const operadoresMap = new Map(operadoresFiltrados.map(op => [Number(op.id), op]));
    const linhasFiltradas = data.linhas.filter(linha => operadoresMap.has(Number(linha.operador_id)));
    const resumo = gerarResumo(linhasFiltradas, operadoresFiltrados.length);
    const porLider = ordenarEquipes(gerarPorLider(linhasFiltradas), ordemEquipes);
    const porOperador = ordenarOperadores([...operadoresFiltrados], ordemOperadores);

    const variacaoTemBase = resumo.variacao_percentual !== null;
    const variacaoClasse = !variacaoTemBase
        ? 'card-muted'
        : (resumo.variacao_abs >= 0 ? 'card-success' : 'card-warning');
    const variacaoPercentualFmt = variacaoTemBase
        ? `${resumo.variacao_percentual > 0 ? '+' : ''}${resumo.variacao_percentual.toLocaleString('pt-BR', {
            maximumFractionDigits: 1,
            minimumFractionDigits: Math.abs(resumo.variacao_percentual % 1) > 0 ? 1 : 0,
        })}%`
        : '—';
    const variacaoSub = variacaoTemBase
        ? `${resumo.variacao_abs > 0 ? '+' : ''}${resumo.variacao_abs.toLocaleString('pt-BR')} ticket(s) vs semana anterior`
        : 'sem base comparativa';

    // ── Stats cards ──────────────────────────────────────────────────────────
    const statsEl = document.getElementById('dash-stats');
    statsEl.innerHTML = `
        <div class="dash-stat-card">
            <div class="stat-label">Total de Tickets</div>
            <div class="stat-number">${resumo.total_tickets.toLocaleString('pt-BR')}</div>
            <div class="stat-sub">${resumo.total_semanas} semana(s) no periodo</div>
        </div>
        <div class="dash-stat-card card-success">
            <div class="stat-label">Media por Semana</div>
            <div class="stat-number">${resumo.total_semanas > 0 ? Math.round(resumo.total_tickets / resumo.total_semanas).toLocaleString('pt-BR') : '—'}</div>
            <div class="stat-sub">tickets/semana</div>
        </div>
        <div class="dash-stat-card">
            <div class="stat-label">Media por Dia</div>
            <div class="stat-number">${resumo.media_por_dia.toLocaleString('pt-BR', { maximumFractionDigits: 1, minimumFractionDigits: resumo.media_por_dia % 1 ? 1 : 0 })}</div>
            <div class="stat-sub">tickets/dia util no periodo</div>
        </div>
        <div class="dash-stat-card ${variacaoClasse}">
            <div class="stat-label">Variacao de Quantidade</div>
            <div class="stat-number">${variacaoPercentualFmt}</div>
            <div class="stat-sub">${variacaoSub}</div>
        </div>
        <div class="dash-stat-card card-warning">
            <div class="stat-label">Registros de Ferias</div>
            <div class="stat-number">${resumo.total_ferias}</div>
            <div class="stat-sub">no periodo filtrado</div>
        </div>
        <div class="dash-stat-card card-muted">
            <div class="stat-label">Operadores</div>
            <div class="stat-number">${porOperador.length}</div>
            <div class="stat-sub">com dados no periodo</div>
        </div>
        <div class="dash-stat-card">
            <div class="stat-label">Media por Equipe</div>
            <div class="stat-number">${resumo.media_por_equipe.toLocaleString('pt-BR', { maximumFractionDigits: 1, minimumFractionDigits: resumo.media_por_equipe % 1 ? 1 : 0 })}</div>
            <div class="stat-sub">tickets por equipe no periodo</div>
        </div>
        <div class="dash-stat-card">
            <div class="stat-label">Media por Operador</div>
            <div class="stat-number">${resumo.media_por_operador.toLocaleString('pt-BR', { maximumFractionDigits: 1, minimumFractionDigits: resumo.media_por_operador % 1 ? 1 : 0 })}</div>
            <div class="stat-sub">tickets por operador no periodo</div>
        </div>
    `;

    // ── Tabela por equipe ────────────────────────────────────────────────────
    const tbEquipes = document.querySelector('#tbl-equipes tbody');
    tbEquipes.innerHTML = porLider.length === 0
        ? '<tr><td colspan="3" class="empty-dash">Nenhum dado.</td></tr>'
        : porLider.map(l => `
            <tr>
                <td><strong>${esc(l.lider)}</strong></td>
                <td class="total-col">${l.total.toLocaleString('pt-BR')}</td>
                <td>${l.ferias}</td>
            </tr>
        `).join('');

    // ── Top 10 ───────────────────────────────────────────────────────────────
    const top10 = porOperador.slice(0, 10);
    const tbTop = document.querySelector('#tbl-top tbody');
    tbTop.innerHTML = top10.length === 0
        ? '<tr><td colspan="4" class="empty-dash">Nenhum dado.</td></tr>'
        : top10.map((op, i) => {
            const media = op.semanas_ativas > 0 ? (op.total / op.semanas_ativas).toFixed(1) : '—';
            return `
                <tr>
                    <td class="rank-col">#${i+1}</td>
                    <td>${esc(op.nome)}</td>
                    <td class="total-col">${op.total.toLocaleString('pt-BR')}</td>
                    <td class="media-col">${media}</td>
                </tr>
            `;
        }).join('');

    // ── Todos operadores ─────────────────────────────────────────────────────
    const tbTodos = document.querySelector('#tbl-todos tbody');
    tbTodos.innerHTML = porOperador.length === 0
        ? '<tr><td colspan="8" class="empty-dash">Nenhum dado encontrado para o periodo.</td></tr>'
        : porOperador.map((op, i) => {
            const media = op.semanas_ativas > 0 ? (op.total / op.semanas_ativas).toFixed(1) : '—';
            return `
                <tr>
                    <td class="rank-col cell-rank">#${i+1}</td>
                    <td class="cell-operador"><strong>${esc(op.nome)}</strong></td>
                    <td class="cell-equipe"><span class="badge-lider">${esc(op.lider ?? '—')}</span></td>
                    <td class="total-col cell-total">${op.total.toLocaleString('pt-BR')}</td>
                    <td class="cell-semanas">${op.semanas_ativas}</td>
                    <td class="media-col cell-media">${media}</td>
                    <td class="muted-inline cell-contratacao">${formatarDataBr(op.data_contratacao)}</td>
                    <td class="cell-tempo">${esc(op.tempo_empresa_texto ?? 'Sem data')}</td>
                </tr>
            `;
        }).join('');

    atualizarIndicadoresOrdenacaoTabelaTodos();

    document.getElementById('dash-corpo').hidden = false;
}

function esc(str) {
    return String(str ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function formatarDataBr(data) {
    if (!data) return '—';
    const [ano, mes, dia] = String(data).split('-');
    return ano && mes && dia ? `${dia}/${mes}/${ano}` : '—';
}

function aplicaFiltroTempoEmpresa(op, filtro) {
    const meses = op.tempo_empresa_meses;
    if (!filtro) return true;
    if (filtro === 'sem_data') return meses === null || meses === undefined;
    if (meses === null || meses === undefined) return false;
    if (filtro === 'ate_6') return meses <= 6;
    if (filtro === '6_12') return meses > 6 && meses <= 12;
    if (filtro === '12_24') return meses > 12 && meses <= 24;
    if (filtro === '24_plus') return meses > 24;
    return true;
}

function aplicaFiltroConhecimento(op, filtro) {
    if (!filtro) return true;
    if (filtro === 'tef') return !!op.tef;
    if (filtro === 'troca_regime') return !!op.troca_regime;
    return true;
}

function gerarResumo(linhas, totalOperadores) {
    const semanas = new Set();
    const equipes = new Set();
    const totaisPorSemana = new Map();
    let totalTickets = 0;
    let totalFerias = 0;

    linhas.forEach(linha => {
        const semana = String(linha.semana || '');
        const totalLinha = Number(linha.total_semana || 0);

        semanas.add(semana);
        equipes.add(linha.lider || 'Sem equipe');
        totalTickets += totalLinha;
        totaisPorSemana.set(semana, (totaisPorSemana.get(semana) || 0) + totalLinha);

        if (Number(linha.em_ferias) === 1) totalFerias += 1;
    });

    const totalEquipes = equipes.size;
    const totalSemanas = semanas.size;
    const totalDiasUteis = totalSemanas * 6;
    const mediaPorDia = totalDiasUteis > 0 ? totalTickets / totalDiasUteis : 0;
    const mediaPorEquipe = totalEquipes > 0 ? totalTickets / totalEquipes : 0;
    const mediaPorOperador = totalOperadores > 0 ? totalTickets / totalOperadores : 0;

    const semanasOrdenadas = Array.from(totaisPorSemana.keys()).sort((a, b) => a.localeCompare(b, 'pt-BR'));
    let variacaoAbs = 0;
    let variacaoPercentual = null;

    if (semanasOrdenadas.length >= 2) {
        const ultimaSemana = totaisPorSemana.get(semanasOrdenadas[semanasOrdenadas.length - 1]) || 0;
        const penultimaSemana = totaisPorSemana.get(semanasOrdenadas[semanasOrdenadas.length - 2]) || 0;
        variacaoAbs = ultimaSemana - penultimaSemana;

        if (penultimaSemana === 0) {
            variacaoPercentual = ultimaSemana === 0 ? 0 : null;
        } else {
            variacaoPercentual = (variacaoAbs / penultimaSemana) * 100;
        }
    }

    return {
        total_tickets: totalTickets,
        total_semanas: totalSemanas,
        total_dias_uteis: totalDiasUteis,
        total_ferias: totalFerias,
        total_operadores: totalOperadores,
        total_equipes: totalEquipes,
        media_por_dia: mediaPorDia,
        media_por_equipe: mediaPorEquipe,
        media_por_operador: mediaPorOperador,
        variacao_abs: variacaoAbs,
        variacao_percentual: variacaoPercentual,
    };
}

function gerarPorLider(linhas) {
    const grupos = new Map();

    linhas.forEach(linha => {
        const lider = linha.lider || 'Sem equipe';
        const atual = grupos.get(lider) || { lider, total: 0, ferias: 0 };
        atual.total += Number(linha.total_semana || 0);
        if (Number(linha.em_ferias) === 1) atual.ferias += 1;
        grupos.set(lider, atual);
    });

    return Array.from(grupos.values());
}

function ordenarEquipes(lista, ordem) {
    const [campo, direcao] = String(ordem || 'total_desc').split('_');
    const multiplicador = direcao === 'asc' ? 1 : -1;

    return lista.sort((a, b) => {
        let comparacao = 0;
        if (campo === 'lider') comparacao = String(a.lider || '').localeCompare(String(b.lider || ''), 'pt-BR');
        if (campo === 'ferias') comparacao = Number(a.ferias || 0) - Number(b.ferias || 0);
        if (campo === 'total') comparacao = Number(a.total || 0) - Number(b.total || 0);
        if (comparacao === 0) comparacao = String(a.lider || '').localeCompare(String(b.lider || ''), 'pt-BR');
        return comparacao * multiplicador;
    });
}

function ordenarOperadores(lista, ordem) {
    const [campo, direcao] = String(ordem || 'total_desc').split('_');
    const multiplicador = direcao === 'asc' ? 1 : -1;

    return lista.sort((a, b) => {
        const mediaA = Number(a.semanas_ativas || 0) > 0 ? Number(a.total || 0) / Number(a.semanas_ativas || 0) : -1;
        const mediaB = Number(b.semanas_ativas || 0) > 0 ? Number(b.total || 0) / Number(b.semanas_ativas || 0) : -1;
        let comparacao = 0;

        if (campo === 'nome') comparacao = String(a.nome || '').localeCompare(String(b.nome || ''), 'pt-BR');
        if (campo === 'lider') comparacao = String(a.lider || '').localeCompare(String(b.lider || ''), 'pt-BR');
        if (campo === 'semanas') comparacao = Number(a.semanas_ativas || 0) - Number(b.semanas_ativas || 0);
        if (campo === 'tempo') comparacao = (Number(a.tempo_empresa_meses ?? -1) - Number(b.tempo_empresa_meses ?? -1));
        if (campo === 'media') comparacao = mediaA - mediaB;
        if (campo === 'contratacao') {
            const dataA = a.data_contratacao ? Date.parse(a.data_contratacao) : null;
            const dataB = b.data_contratacao ? Date.parse(b.data_contratacao) : null;
            if (dataA === null && dataB === null) comparacao = 0;
            else if (dataA === null) comparacao = 1;
            else if (dataB === null) comparacao = -1;
            else comparacao = dataA - dataB;
        }
        if (campo === 'total') comparacao = Number(a.total || 0) - Number(b.total || 0);
        if (comparacao === 0) comparacao = String(a.nome || '').localeCompare(String(b.nome || ''), 'pt-BR');
        return comparacao * multiplicador;
    });
}

buscarDados();
</script>


</body>
</html>
