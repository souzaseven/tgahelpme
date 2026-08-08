<?php
require_once __DIR__ . '/includes/session.php';

if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}


// Permite admin e líder acessar a página

$nomeUsuario = htmlspecialchars($_SESSION['nome']);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Ajustes — Repasse</title>
    <link rel="icon" href="https://tgameajuda.com/img/principal/bot-tga.webp" type="image/x-icon">
    <link rel="stylesheet" href="assets/style.css">
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-E7ZNTJSRYR"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', 'G-E7ZNTJSRYR');
    </script>
    <style>
        .hist-content {
            padding: 24px 32px;
        }

        .hist-filters {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px 20px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 16px;
            position: sticky;
            top: 58px;
            z-index: 90;
            box-shadow: var(--shadow);
        }

        .hist-filters .form-group {
            margin-bottom: 0;
        }

        .hist-filters-divider {
            width: 1px;
            height: 40px;
            background: var(--border);
            flex-shrink: 0;
            margin: 0 16px 0 auto;
        }

        .hist-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: var(--radius);
        }

        .hist-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--surface);
        }

        .hist-table th {
            background: #f8fafc;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding: 10px 14px;
            text-align: left;
            border-bottom: 2px solid var(--border);
            white-space: nowrap;
            transition: background .12s, color .12s;
        }

        .hist-table th[data-ordem] {
            cursor: pointer;
        }

        .hist-table th[data-ordem]:hover {
            background: var(--border);
            color: var(--text);
        }

        body.dark .hist-table th[data-ordem]:hover {
            background: #1e293b;
            color: var(--text);
        }

        .hist-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            vertical-align: middle;
        }

        .hist-table tbody tr:last-child td {
            border-bottom: none;
        }

        .hist-table tbody tr:nth-child(even) {
            background: rgba(148, 163, 184, .06);
        }

        .hist-table tbody tr:hover {
            background: var(--bg);
        }

        .empty-hist,
        .loading-hist {
            text-align: center;
            padding: 48px 24px;
            color: var(--muted);
            font-size: 14px;
        }

        .badge-lider {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            background: var(--tef-bg);
            color: var(--tef-color);
            white-space: nowrap;
        }

        .dias-detalhe {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            margin-top: 4px;
        }

        .dia-pill {
            display: inline-block;
            min-width: 40px;
            font-size: 10px;
            padding: 1px 6px;
            border-radius: 4px;
            background: var(--bg);
            border: 1px solid var(--border);
            color: var(--muted);
            text-align: center;
        }

        .var-pos {
            color: var(--success);
            font-size: 11px;
        }

        .var-neg {
            color: var(--warning);
            font-size: 11px;
        }

        .var-zero {
            color: var(--muted);
            font-size: 11px;
        }

        body.dark .hist-table th {
            background: #162032;
        }

        body.dark .hist-table tbody tr:hover {
            background: #1a2a3a;
        }

        @media (max-width: 900px) {
            .hist-content {
                padding: 16px;
            }

            .col-dias {
                display: none;
            }
        }
    </style>
</head>

<body>
    <script src="assets/common.js?v=1"></script>

    <!-- ── HEADER ── -->
    <header class="topbar">
        <div class="topbar-left">
            <span class="topbar-icon">📋</span>
            <h1 class="topbar-title">Histórico de Ajustes</h1>
        </div>
        <div class="topbar-right">
            <a href="index.php" class="btn btn-outline btn-sm">🎫 Repasse</a>
            <a href="dashboard.php" class="btn btn-outline btn-sm">📊 Dashboard</a>
            <a href="operadores.php" class="btn btn-outline btn-sm">👥 Operadores</a>
            <span class="badge-admin">Admin</span>
            <a href="usuarios.php" class="btn btn-outline btn-sm">🔑 Usuários</a>
            <span class="topbar-user">👤 <?= $nomeUsuario ?></span>
            <button id="btn-tema" class="btn btn-outline btn-sm" title="Alternar modo claro/escuro">🌙</button>
            <a href="logout.php" class="btn btn-outline btn-sm">Sair</a>
        </div>
    </header>

    <!-- ── CONTEÚDO ── -->
    <div class="hist-content">


        <div class="hist-filters">
            <div class="form-group">
                <label for="hist-de">De</label>
                <input type="date" id="hist-de" class="form-control" style="width:150px">
            </div>
            <div class="form-group">
                <label for="hist-ate">Até</label>
                <input type="date" id="hist-ate" class="form-control" style="width:150px">
            </div>
            <div class="form-group">
                <label for="hist-operador">Operador</label>
                <input type="text" id="hist-operador" class="form-control" style="width:160px"
                    placeholder="Buscar operador...">
            </div>
            <div class="form-group">
                <label for="hist-alterador">Alterado por</label>
                <select id="hist-alterador" class="form-control" style="width:160px">
                    <option value="">Todos</option>
                </select>
            </div>
            <div style="display:flex;align-items:flex-end;gap:8px">
                <button class="btn btn-primary" onclick="buscarHistorico()">🔍 Buscar</button>
            </div>

            <div class="hist-filters-divider"></div>

            <div class="mini-cards" style="display:flex;gap:12px;align-items:flex-end">
                <div class="mini-card" id="card-total-registros">
                    <div class="mini-card-label">Registros</div>
                    <div class="mini-card-value">0</div>
                </div>
                <div class="mini-card" id="card-total-operadores">
                    <div class="mini-card-label">Operadores únicos</div>
                    <div class="mini-card-value">0</div>
                </div>
                <div class="mini-card" id="card-periodo">
                    <div class="mini-card-label">Período exibido</div>
                    <div class="mini-card-value">—</div>
                </div>
            </div>
        </div>

        <div class="hist-table-wrap">
            <table class="hist-table" id="tbl-historico">
                <thead>
                    <tr>
                        <th data-ordem="alterado_em">Data / Hora <span class="ordem-seta"></span></th>
                        <th data-ordem="nome_operador">Operador <span class="ordem-seta"></span></th>
                        <th data-ordem="lider">Equipe <span class="ordem-seta"></span></th>
                        <th data-ordem="semana">Semana Ref. <span class="ordem-seta"></span></th>
                        <th data-ordem="total_anterior">Antes <span class="ordem-seta"></span></th>
                        <th data-ordem="total_novo">Depois <span class="ordem-seta"></span></th>
                        <th class="col-dias">Dias (SEG–SÁB)</th>
                        <th data-ordem="nome_alterador">Alterado por <span class="ordem-seta"></span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8" class="loading-hist"><div class="spinner" style="margin:0 auto 10px"></div>Carregando...</td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>

    <div id="toast" class="toast" hidden></div>

    <script>
        // ── Buscar ────────────────────────────────────────────────────────────────────
        let ordemCampo = 'alterado_em';
        let ordemDirecao = 'desc';


        // ── Datas padrão: semana atual (domingo a sábado) ─────────────────────────────
        (function () {
            const hoje = new Date();
            const diaSemana = hoje.getDay(); // 0=domingo, 6=sábado
            const inicioSemana = new Date(hoje);
            inicioSemana.setDate(hoje.getDate() - diaSemana);
            const fimSemana = new Date(inicioSemana);
            fimSemana.setDate(inicioSemana.getDate() + 6);
            const fmt = d => {
                const y = d.getFullYear();
                const m = String(d.getMonth() + 1).padStart(2, '0');
                const dd = String(d.getDate()).padStart(2, '0');
                return `${y}-${m}-${dd}`;
            };
            document.getElementById('hist-de').value = fmt(inicioSemana);
            document.getElementById('hist-ate').value = fmt(fimSemana);
            buscarHistorico();
            setInterval(buscarHistorico, 60 * 1000);
        })();

        async function buscarHistorico() {
            const de = document.getElementById('hist-de').value;
            const ate = document.getElementById('hist-ate').value;
            const alterador = document.getElementById('hist-alterador').value;
            if (!de || !ate) { mostrarToast('Selecione o período.', 'error'); return; }

            const tbody = document.querySelector('#tbl-historico tbody');
            tbody.innerHTML = '<tr><td colspan="8" class="loading-hist"><div class="spinner" style="margin:0 auto 10px"></div>Carregando...</td></tr>';


            const operador = document.getElementById('hist-operador').value.trim();
            let url = `api/historico.php?de=${de}&ate=${ate}&limit=300&ordem=${ordemCampo}&direcao=${ordemDirecao}`;
            if (alterador) url += `&alterador=${encodeURIComponent(alterador)}`;
            if (operador) url += `&operador=${encodeURIComponent(operador)}`;

            try {
                const res = await fetch(url);
                if (res.status === 401) { window.location.href = 'login.php'; return; }
                if (res.status === 403) { window.location.href = 'index.php'; return; }
                const json = await res.json();
                if (json.erro) { mostrarToast(json.erro, 'error'); return; }
                renderHistorico(json.registros);
                atualizarMiniCards(json.registros, de, ate);
                preencherSelectAlterador(json.registros);
            } catch (e) {
                mostrarToast('Erro ao carregar histórico.', 'error');
            }
        }

        function preencherSelectAlterador(registros) {
            const select = document.getElementById('hist-alterador');
            const nomes = Array.from(new Set(registros.map(r => r.nome_alterador).filter(Boolean)));
            const valorAtual = select.value;
            select.innerHTML = '<option value="">Todos</option>' + nomes.map(n => `<option value="${n}">${n}</option>`).join('');
            if (nomes.includes(valorAtual)) select.value = valorAtual;
        }

        function atualizarMiniCards(registros, de, ate) {
            document.querySelector('#card-total-registros .mini-card-value').textContent = registros.length;
            const operadores = new Set(registros.map(r => r.nome_operador));
            document.querySelector('#card-total-operadores .mini-card-value').textContent = operadores.size;
            document.querySelector('#card-periodo .mini-card-value').textContent = `${de} a ${ate}`;
        }

        // ── Renderizar ────────────────────────────────────────────────────────────────
        function renderHistorico(registros) {
            const tbody = document.querySelector('#tbl-historico tbody');
            const operadorBusca = document.getElementById('hist-operador').value.trim().toLowerCase();
            let filtrados = registros;
            if (operadorBusca) {
                filtrados = registros.filter(r => (r.nome_operador || '').toLowerCase().includes(operadorBusca));
            }
            if (!filtrados.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="empty-hist">Nenhum ajuste registrado no período.</td></tr>';
                return;
            }
            tbody.innerHTML = filtrados.map(r => {
                const variacao = Number(r.total_novo) - Number(r.total_anterior);
                const varStr = variacao > 0 ? `+${variacao}` : String(variacao);
                const varCls = variacao > 0 ? 'var-pos' : variacao < 0 ? 'var-neg' : 'var-zero';
                const dataHora = r.alterado_em
                    ? String(r.alterado_em).substring(0, 16).replace('T', ' ')
                    : '—';
                const dias = [
                    { d: 'SEG', v: Number(r.seg) },
                    { d: 'TER', v: Number(r.ter) },
                    { d: 'QUA', v: Number(r.qua) },
                    { d: 'QUI', v: Number(r.qui) },
                    { d: 'SEX', v: Number(r.sex) },
                    { d: 'SÁB', v: Number(r.sab) },
                ];
                const diasHtml = dias
                    .map(({ d, v }) => `<span class="dia-pill">${d}:${v}</span>`)
                    .join('');
                return `
            <tr>
                <td style="white-space:nowrap;font-size:12px">${esc(dataHora)}</td>
                <td><strong>${esc(r.nome_operador)}</strong></td>
                <td><span class="badge-lider">${esc(r.lider ?? '—')}</span></td>
                <td style="white-space:nowrap">${formatarDataBr(r.semana)}</td>
                <td class="muted-inline">${Number(r.total_anterior)}</td>
                <td>
                    <strong>${Number(r.total_novo)}</strong>
                    <span class="${varCls}">(${varStr})</span>
                </td>
                <td class="col-dias"><div class="dias-detalhe">${diasHtml}</div></td>
                <td class="muted-inline">${esc(r.nome_alterador ?? '—')}</td>
            </tr>
        `;
            }).join('');
        }

        // Ordenação ao clicar no cabeçalho
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.hist-table th[data-ordem]').forEach(th => {
                th.style.cursor = 'pointer';
                th.addEventListener('click', () => {
                    const campo = th.getAttribute('data-ordem');
                    if (ordemCampo === campo) {
                        ordemDirecao = ordemDirecao === 'asc' ? 'desc' : 'asc';
                    } else {
                        ordemCampo = campo;
                        ordemDirecao = 'asc';
                    }
                    buscarHistorico();
                    atualizarSetasOrdem();
                });
            });
            atualizarSetasOrdem();
        });

        function atualizarSetasOrdem() {
            document.querySelectorAll('.hist-table th[data-ordem]').forEach(th => {
                const campo = th.getAttribute('data-ordem');
                const seta = th.querySelector('.ordem-seta');
                if (campo === ordemCampo) {
                    seta.textContent = ordemDirecao === 'asc' ? '▲' : '▼';
                } else {
                    seta.textContent = '';
                }
            });
        }
    </script>
    <style>
        .mini-cards {}

        .mini-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 18px;
            min-width: 120px;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .mini-card-label {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 2px;
        }

        .mini-card-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-dk);
        }

        body.dark .mini-card {
            background: #162032;
            border-color: #334155;
        }

        body.dark .mini-card-value {
            color: #60a5fa;
        }
    </style>

    <script>
        // ── Helpers ───────────────────────────────────────────────────────────────────
        function esc(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function formatarDataBr(data) {
            if (!data) return '—';
            const [ano, mes, dia] = String(data).split('-');
            return ano && mes && dia ? `${dia}/${mes}/${ano}` : '—';
        }

    </script>
</body>

</html>