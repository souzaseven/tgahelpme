<?php
session_start();

if (!isset($_SESSION['empresa_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../backend/conexao.php';

$empresaId   = (int) $_SESSION['empresa_id'];
$empresaNome = $_SESSION['empresa_nome'] ?? 'Empresa';
$primeiroNome = explode(' ', $empresaNome)[0];

/* ── Buscar dados da empresa ────────────────────────────── */
$stmtEmp = $pdo->prepare("SELECT * FROM empresas_carreiras WHERE id = ? LIMIT 1");
$stmtEmp->execute([$empresaId]);
$empresaData = $stmtEmp->fetch(PDO::FETCH_ASSOC);

/* ── Buscar vagas da empresa ────────────────────────────── */
$stmtVagas = $pdo->prepare("
    SELECT v.*,
        (SELECT COUNT(*) FROM candidaturas c WHERE c.vaga_id = v.id) AS total_candidaturas
    FROM vagas v
    WHERE v.empresa_id = ?
    ORDER BY v.data_publicacao DESC
");
$stmtVagas->execute([$empresaId]);
$vagas = $stmtVagas->fetchAll(PDO::FETCH_ASSOC);

/* ── Buscar candidaturas recebidas (todas as vagas) ─────── */
$stmtCand = $pdo->prepare("
    SELECT 
        c.id AS candidatura_id,
        c.nome AS candidato_nome,
        c.email AS candidato_email,
        c.telefone,
        c.mensagem,
        c.curriculo,
        c.status,
        c.data_candidatura,
        v.id AS vaga_id,
        v.titulo AS vaga_titulo,
        v.localizacao,
        v.salario
    FROM candidaturas c
    INNER JOIN vagas v ON c.vaga_id = v.id
    WHERE v.empresa_id = ?
    ORDER BY c.data_candidatura DESC
");
$stmtCand->execute([$empresaId]);
$candidaturas = $stmtCand->fetchAll(PDO::FETCH_ASSOC);

/* ── Estatísticas ───────────────────────────────────────── */
$totalVagas      = count($vagas);
$vagasAtivas     = count(array_filter($vagas, fn($v) => ($v['status'] ?? 'ativa') === 'ativa'));
$vagasPausadas   = count(array_filter($vagas, fn($v) => ($v['status'] ?? '') === 'pausada'));
$vagasEncerradas = count(array_filter($vagas, fn($v) => ($v['status'] ?? '') === 'encerrada'));

$totalCandidaturas = count($candidaturas);
$candPendentes     = count(array_filter($candidaturas, fn($c) => $c['status'] === 'pendente'));
$candEmAnalise     = count(array_filter($candidaturas, fn($c) => $c['status'] === 'em_analise'));
$candAprovados     = count(array_filter($candidaturas, fn($c) => $c['status'] === 'aprovado'));
$candReprovados    = count(array_filter($candidaturas, fn($c) => $c['status'] === 'reprovado'));

/* ── Últimas 15 candidaturas ────────────────────────────── */
$ultimasCandidaturas = array_slice($candidaturas, 0, 15);

/* ── Status map ─────────────────────────────────────────── */
$statusLabels = [
    'pendente'   => ['label' => 'Pendente',   'icon' => '⏳', 'class' => 'pendente'],
    'em_analise' => ['label' => 'Em Análise',  'icon' => '🔍', 'class' => 'em_analise'],
    'aprovado'   => ['label' => 'Aprovado',    'icon' => '✅', 'class' => 'aprovado'],
    'reprovado'  => ['label' => 'Reprovado',   'icon' => '❌', 'class' => 'reprovado'],
];

$vagaStatusLabels = [
    'ativa'     => ['label' => 'Ativa',     'icon' => '🟢', 'class' => 'aprovado'],
    'pausada'   => ['label' => 'Pausada',   'icon' => '🟡', 'class' => 'em_analise'],
    'encerrada' => ['label' => 'Encerrada', 'icon' => '🔴', 'class' => 'reprovado'],
];
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Painel Empresa — TGA Carreiras</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="http://tgameajuda.com/Projetos/tgacarreiras/img/icone_logo.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Google AdSense -->
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044" crossorigin="anonymous"></script>
    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-S8EC5C2WTG"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-S8EC5C2WTG');
    </script>

    <style>
        /* ════════════════════════════════════════════════════
           VARIÁVEIS
        ════════════════════════════════════════════════════ */
        :root {
            --primary:       #0066FF;
            --primary-dark:  #0052CC;
            --primary-light: #3385FF;
            --bg:            #0F172A;
            --bg-card:       #1E293B;
            --surface:       #334155;
            --text:          #F1F5F9;
            --text-sec:      #CBD5E1;
            --text-muted:    #64748B;
            --border:        #334155;
            --success:       #10B981;
            --warning:       #F59E0B;
            --danger:        #EF4444;
            --info:          #3B82F6;
            --shadow:        0 4px 12px rgba(0,0,0,.3);
            --shadow-lg:     0 10px 30px rgba(0,0,0,.4);
            --shadow-xl:     0 20px 40px rgba(0,102,255,.25);
            --radius:        16px;
            --radius-sm:     10px;
            --transition:    all .3s ease;
            --sidebar-w:     270px;
        }
        [data-theme="light"] {
            --bg:        #F1F5F9;
            --bg-card:   #FFFFFF;
            --surface:   #EDF0F5;
            --text:      #1A202C;
            --text-sec:  #4A5568;
            --text-muted:#718096;
            --border:    #E2E8F0;
            --shadow:    0 4px 12px rgba(0,0,0,.07);
            --shadow-lg: 0 10px 30px rgba(0,0,0,.08);
            --shadow-xl: 0 20px 40px rgba(0,102,255,.12);
        }

        /* ════════════════════════════════════════════════════
           RESET
        ════════════════════════════════════════════════════ */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { overflow-x: hidden; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            transition: background .3s, color .3s;
        }

        /* ════════════════════════════════════════════════════
           LAYOUT
        ════════════════════════════════════════════════════ */
        .layout { display: flex; min-height: 100vh; }

        /* ════════════════════════════════════════════════════
           SIDEBAR
        ════════════════════════════════════════════════════ */
        .sidebar {
            width: var(--sidebar-w);
            background: var(--bg-card);
            border-right: 1px solid var(--border);
            position: fixed; top: 0; left: 0;
            height: 100vh; overflow-y: auto;
            z-index: 1000;
            display: flex; flex-direction: column;
            transition: transform .3s ease;
        }

        .sidebar-header {
            padding: 1.5rem 1.25rem;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-brand {
            display: flex; align-items: center; gap: .75rem;
            text-decoration: none; color: var(--text);
            margin-bottom: 1.25rem;
        }
        .sidebar-brand-icon {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.15rem; flex-shrink: 0;
        }
        .sidebar-brand-text { font-size: 1.15rem; font-weight: 800; letter-spacing: -.3px; }

        .user-profile {
            display: flex; align-items: center; gap: .75rem;
        }
        .user-avatar {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; flex-shrink: 0;
            box-shadow: var(--shadow);
        }
        .user-info { min-width: 0; }
        .user-info h2 {
            font-size: .92rem; font-weight: 700;
            color: var(--text);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .user-info p {
            font-size: .75rem; color: var(--text-muted);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .user-badge {
            display: inline-flex; align-items: center; gap: .25rem;
            background: rgba(0,102,255,.1); border: 1px solid rgba(0,102,255,.2);
            color: var(--primary); font-size: .6rem; font-weight: 700;
            padding: .15rem .5rem; border-radius: 12px;
            margin-top: .25rem; text-transform: uppercase; letter-spacing: .4px;
        }

        .sidebar-nav { padding: 1rem 0; flex: 1; }

        .nav-section { margin-bottom: 1.25rem; }
        .nav-section-title {
            padding: 0 1.25rem; font-size: .65rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .8px;
            color: var(--text-muted); margin-bottom: .5rem;
        }

        .nav-item {
            display: flex; align-items: center; gap: .75rem;
            padding: .7rem 1.25rem; color: var(--text-sec);
            text-decoration: none; font-weight: 500; font-size: .88rem;
            transition: var(--transition);
            border-left: 3px solid transparent;
        }
        .nav-item:hover {
            background: var(--surface); color: var(--text);
            border-left-color: var(--primary);
        }
        .nav-item.active {
            background: rgba(0,102,255,.08); color: var(--primary);
            border-left-color: var(--primary); font-weight: 600;
        }
        .nav-icon { font-size: 1.1rem; width: 22px; text-align: center; flex-shrink: 0; }
        .nav-count {
            margin-left: auto;
            background: var(--primary); color: #fff;
            font-size: .65rem; font-weight: 700;
            padding: .15rem .45rem; border-radius: 10px;
            min-width: 20px; text-align: center;
        }

        .nav-item.logout { color: var(--danger); }
        .nav-item.logout:hover { background: rgba(239,68,68,.08); border-left-color: var(--danger); }

        /* Overlay (mobile) */
        .sidebar-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,.6);
            backdrop-filter: blur(4px);
            z-index: 999;
        }
        .sidebar-overlay.open { display: block; }

        .sidebar-close {
            display: none;
            width: 32px; height: 32px;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 8px; cursor: pointer;
            color: var(--text-muted); font-size: .95rem;
            align-items: center; justify-content: center;
            transition: var(--transition);
        }
        .sidebar-close:hover { background: var(--danger); border-color: var(--danger); color: #fff; }

        /* ════════════════════════════════════════════════════
           MAIN CONTENT
        ════════════════════════════════════════════════════ */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-w);
            padding: 2rem;
            min-width: 0;
        }

        /* Top bar (mobile) */
        .mobile-topbar {
            display: none;
            align-items: center; justify-content: space-between;
            padding: .875rem 1rem;
            background: var(--bg-card);
            border-bottom: 2px solid var(--primary);
            position: sticky; top: 0; z-index: 500;
            box-shadow: var(--shadow);
        }
        .mobile-topbar-left { display: flex; align-items: center; gap: .75rem; }
        .hamburger {
            width: 38px; height: 38px;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 9px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; transition: var(--transition);
        }
        .hamburger:hover { background: var(--primary); border-color: var(--primary); }
        .mobile-topbar-brand {
            display: flex; align-items: center; gap: .5rem;
            font-size: 1.1rem; font-weight: 800; color: var(--text);
        }
        .mobile-topbar-brand-icon {
            width: 32px; height: 32px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: .95rem;
        }

        /* Page Header */
        .page-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 1.75rem;
            flex-wrap: wrap; gap: 1rem;
        }
        .page-title h1 {
            font-size: 1.65rem; font-weight: 800;
            color: var(--text); margin-bottom: .2rem;
            letter-spacing: -.3px;
        }
        .page-title p { color: var(--text-muted); font-size: .88rem; }
        .header-actions { display: flex; gap: .75rem; align-items: center; }

        .theme-toggle {
            width: 40px; height: 40px;
            border: 1px solid var(--border); border-radius: 9px;
            background: var(--surface); cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; transition: var(--transition);
        }
        .theme-toggle:hover { background: var(--primary); border-color: var(--primary); }

        /* ════════════════════════════════════════════════════
           STAT CARDS
        ════════════════════════════════════════════════════ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem; margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--bg-card); border-radius: var(--radius);
            padding: 1.25rem 1.35rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative; overflow: hidden;
        }
        .stat-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
        }
        .stat-card.blue::before   { background: var(--primary); }
        .stat-card.yellow::before { background: var(--warning); }
        .stat-card.cyan::before   { background: var(--info); }
        .stat-card.green::before  { background: var(--success); }
        .stat-card.red::before    { background: var(--danger); }
        .stat-card.purple::before { background: #8B5CF6; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
        .stat-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: .6rem;
        }
        .stat-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
        }
        .stat-icon.blue   { background: rgba(0,102,255,.1); }
        .stat-icon.yellow  { background: rgba(245,158,11,.1); }
        .stat-icon.cyan    { background: rgba(59,130,246,.1); }
        .stat-icon.green   { background: rgba(16,185,129,.1); }
        .stat-icon.red     { background: rgba(239,68,68,.1); }
        .stat-icon.purple  { background: rgba(139,92,246,.1); }
        .stat-label {
            font-size: .72rem; color: var(--text-muted);
            font-weight: 700; text-transform: uppercase; letter-spacing: .4px;
        }
        .stat-value { font-size: 2rem; font-weight: 800; color: var(--text); line-height: 1; }

        /* Animação stat cards */
        .stat-card { animation: fadeUp .4s ease both; }
        .stat-card:nth-child(2) { animation-delay: .05s; }
        .stat-card:nth-child(3) { animation-delay: .1s; }
        .stat-card:nth-child(4) { animation-delay: .15s; }
        .stat-card:nth-child(5) { animation-delay: .2s; }
        .stat-card:nth-child(6) { animation-delay: .25s; }

        /* ════════════════════════════════════════════════════
           CARDS
        ════════════════════════════════════════════════════ */
        .card {
            background: var(--bg-card); border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            margin-bottom: 1.75rem; overflow: hidden;
        }
        .card-header {
            padding: 1.15rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between;
            align-items: center; flex-wrap: wrap; gap: .5rem;
        }
        .card-title {
            font-size: 1.1rem; font-weight: 700; color: var(--text);
            display: flex; align-items: center; gap: .5rem;
        }
        .card-body { padding: 1.5rem; }

        /* ════════════════════════════════════════════════════
           VAGAS LIST
        ════════════════════════════════════════════════════ */
        .vagas-list { display: flex; flex-direction: column; gap: .75rem; }
        .vaga-row {
            display: flex; align-items: center; gap: 1rem;
            padding: 1rem 1.25rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }
        .vaga-row:hover { border-color: var(--primary); transform: translateX(4px); }
        .vaga-row-icon {
            width: 44px; height: 44px;
            background: rgba(0,102,255,.1); border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; flex-shrink: 0;
        }
        .vaga-row-info { flex: 1; min-width: 0; }
        .vaga-row-title {
            font-size: .95rem; font-weight: 700; color: var(--text);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .vaga-row-meta {
            font-size: .78rem; color: var(--text-muted);
            display: flex; gap: .75rem; flex-wrap: wrap; margin-top: .2rem;
        }
        .vaga-row-stats { display: flex; align-items: center; gap: 1rem; flex-shrink: 0; }
        .vaga-row-stat { text-align: center; }
        .vaga-row-stat-value { font-size: 1.15rem; font-weight: 800; color: var(--text); }
        .vaga-row-stat-label {
            font-size: .6rem; color: var(--text-muted);
            text-transform: uppercase; font-weight: 600; letter-spacing: .3px;
        }
        .vaga-row-actions { display: flex; gap: .4rem; flex-shrink: 0; align-items: center; }

        /* ════════════════════════════════════════════════════
           TABELA DESKTOP
        ════════════════════════════════════════════════════ */
        .table-container { overflow-x: auto; }
        .modern-table { width: 100%; border-collapse: collapse; }
        .modern-table thead { background: var(--surface); }
        .modern-table th {
            padding: .65rem 1rem; text-align: left;
            font-size: .68rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .4px;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        .modern-table td {
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--border);
            color: var(--text-sec); font-size: .85rem;
            vertical-align: middle;
        }
        .modern-table tbody tr { transition: background .15s; }
        .modern-table tbody tr:hover { background: var(--surface); }
        .modern-table tbody tr:last-child td { border-bottom: none; }
        .table-title { font-weight: 700; color: var(--text); }
        .table-meta { font-size: .75rem; color: var(--text-muted); }
        .table-vaga { font-size: .78rem; color: var(--primary); font-weight: 600; }

        /* ════════════════════════════════════════════════════
           MOBILE CANDIDATURAS — Cards empilhados
        ════════════════════════════════════════════════════ */
        .mobile-candidaturas { display: none; }
        .mc-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 1rem; margin-bottom: .65rem;
            transition: var(--transition);
        }
        .mc-card:last-child { margin-bottom: 0; }
        .mc-card:hover { border-color: var(--primary); }
        .mc-header {
            display: flex; align-items: center; gap: .75rem;
            margin-bottom: .65rem;
        }
        .mc-avatar {
            width: 40px; height: 40px;
            background: var(--bg-card); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; border: 1px solid var(--border);
            flex-shrink: 0;
        }
        .mc-title-area { flex: 1; min-width: 0; }
        .mc-titulo {
            font-size: .9rem; font-weight: 700; color: var(--text);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .mc-empresa { font-size: .75rem; font-weight: 600; color: var(--primary); }
        .mc-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: .3rem .65rem; margin-bottom: .65rem;
        }
        .mc-field { display: flex; flex-direction: column; }
        .mc-label {
            font-size: .58rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .3px;
            color: var(--text-muted);
        }
        .mc-value { font-size: .78rem; font-weight: 600; color: var(--text-sec); }
        .mc-footer {
            display: flex; align-items: center; justify-content: space-between;
            padding-top: .55rem; border-top: 1px solid var(--border);
            gap: .5rem; flex-wrap: wrap;
        }

        /* ════════════════════════════════════════════════════
           BADGES & FILTERS
        ════════════════════════════════════════════════════ */
        .status-badge {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .25rem .7rem; border-radius: 20px;
            font-size: .72rem; font-weight: 700;
        }
        .status-badge::before {
            content: ''; width: 6px; height: 6px;
            border-radius: 50%; background: currentColor;
        }
        .status-badge.pendente   { background: rgba(245,158,11,.1); color: var(--warning); }
        .status-badge.em_analise { background: rgba(59,130,246,.1);  color: var(--info); }
        .status-badge.aprovado   { background: rgba(16,185,129,.1);  color: var(--success); }
        .status-badge.reprovado  { background: rgba(239,68,68,.1);   color: var(--danger); }

        .filter-tab {
            padding: .35rem .75rem; border-radius: 20px;
            border: 1px solid var(--border);
            background: transparent; color: var(--text-muted);
            font-size: .72rem; font-weight: 600;
            cursor: pointer; white-space: nowrap;
            transition: var(--transition); font-family: inherit;
        }
        .filter-tab:hover { border-color: var(--primary); color: var(--primary); }
        .filter-tab.active {
            background: var(--primary); color: #fff;
            border-color: var(--primary);
        }

        /* ════════════════════════════════════════════════════
           BUTTONS
        ════════════════════════════════════════════════════ */
        .btn {
            padding: .6rem 1.15rem; border-radius: 8px;
            text-decoration: none; font-weight: 600; font-size: .85rem;
            border: none; cursor: pointer;
            display: inline-flex; align-items: center; gap: .4rem;
            transition: var(--transition); white-space: nowrap;
            font-family: inherit;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff; box-shadow: 0 4px 12px rgba(0,102,255,.2);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,102,255,.3); }
        .btn-ghost {
            background: var(--surface); color: var(--text-sec);
            border: 1px solid var(--border);
        }
        .btn-ghost:hover { border-color: var(--primary); color: var(--primary); }
        .btn-sm { padding: .4rem .85rem; font-size: .78rem; }

        /* ════════════════════════════════════════════════════
           EMPTY STATE
        ════════════════════════════════════════════════════ */
        .empty-state {
            text-align: center; padding: 3rem 1.5rem;
            color: var(--text-muted);
        }
        .empty-state-icon { font-size: 3.5rem; margin-bottom: .875rem; opacity: .45; }
        .empty-state h3 { font-size: 1.15rem; color: var(--text); margin-bottom: .4rem; font-weight: 700; }
        .empty-state p { font-size: .88rem; margin-bottom: 1.25rem; }

        /* ════════════════════════════════════════════════════
           ANIMAÇÕES
        ════════════════════════════════════════════════════ */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ════════════════════════════════════════════════════
           RESPONSIVO — TABLET (≤ 1024px)
        ════════════════════════════════════════════════════ */
        @media (max-width: 1024px) {
            :root { --sidebar-w: 240px; }
            .main-content { padding: 1.5rem; }
            .page-title h1 { font-size: 1.4rem; }
        }

        /* ════════════════════════════════════════════════════
           RESPONSIVO — MOBILE (≤ 768px)
        ════════════════════════════════════════════════════ */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
                box-shadow: 4px 0 20px rgba(0,0,0,.4);
            }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 0; }
            .mobile-topbar { display: flex; }
            .sidebar-close { display: flex; }
            .main-inner { padding: 1.25rem 1rem; }
            .page-header { margin-bottom: 1.25rem; }
            .page-title h1 { font-size: 1.25rem; }
            .page-title p  { font-size: .82rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: .65rem; }
            .stat-card { padding: 1rem; }
            .stat-value { font-size: 1.65rem; }
            .stat-icon { width: 36px; height: 36px; font-size: 1.05rem; }
            .table-container { display: none; }
            .mobile-candidaturas { display: block; }
            .card-header { padding: 1rem 1.15rem; }
            .card-body { padding: 1rem 1.15rem; }
            .vaga-row { flex-direction: column; align-items: flex-start; gap: .65rem; }
            .vaga-row-stats { width: 100%; justify-content: space-around; }
            .vaga-row-actions { width: 100%; flex-wrap: wrap; }
            .vaga-row-actions .btn { flex: 1; justify-content: center; }
            .header-actions .btn span:last-child { display: none; }
        }

        /* ════════════════════════════════════════════════════
           RESPONSIVO — MOBILE PEQUENO (≤ 480px)
        ════════════════════════════════════════════════════ */
        @media (max-width: 480px) {
            .mobile-topbar { padding: .75rem; }
            .main-inner { padding: 1rem .75rem; }
            .page-title h1 { font-size: 1.1rem; }
            .page-title p  { font-size: .78rem; }
            .stats-grid { gap: .5rem; }
            .stat-card { padding: .875rem; }
            .stat-value { font-size: 1.4rem; }
            .stat-label { font-size: .65rem; }
            .stat-icon { width: 32px; height: 32px; font-size: .95rem; }
            .card-header { padding: .875rem 1rem; }
            .card-title { font-size: .95rem; }
            .card-body { padding: .875rem 1rem; }
            .mc-card { padding: .875rem; }
            .mc-grid { grid-template-columns: 1fr; gap: .25rem; }
            .empty-state { padding: 2rem 1rem; }
            .empty-state-icon { font-size: 2.8rem; }
            .empty-state h3 { font-size: 1.05rem; }
        }

        /* ════════════════════════════════════════════════════
           RESPONSIVO — TELA MUITO PEQUENA (≤ 360px)
        ════════════════════════════════════════════════════ */
        @media (max-width: 360px) {
            .main-inner { padding: .75rem .5rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .stat-card { flex-direction: row; align-items: center; gap: .75rem; }
            .stat-icon { flex-shrink: 0; }
            .mc-titulo { font-size: .84rem; }
        }
    </style>
</head>
<body>

<!-- ═══ OVERLAY MOBILE ═══════════════════════════════════ -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="layout">

    <!-- ═══ SIDEBAR ══════════════════════════════════════ -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
                <a href="../public/index.php" class="sidebar-brand" style="margin-bottom:0">
                    <div class="sidebar-brand-icon">🚀</div>
                    <span class="sidebar-brand-text">TGA Carreiras</span>
                </a>
                <button class="sidebar-close" onclick="closeSidebar()" title="Fechar menu">✕</button>
            </div>
            <div class="user-profile">
                <div class="user-avatar">🏢</div>
                <div class="user-info">
                    <h2><?= htmlspecialchars($empresaNome) ?></h2>
                    <p><?= htmlspecialchars($empresaData['email'] ?? '') ?></p>
                    <div class="user-badge">🏷️ Empresa</div>
                </div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Menu Principal</div>
                <a href="dashboard.php" class="nav-item active" onclick="closeSidebar()">
                    <span class="nav-icon">📊</span><span>Dashboard</span>
                </a>
                <a href="vagas.php" class="nav-item" onclick="closeSidebar()">
                    <span class="nav-icon">📋</span><span>Minhas Vagas</span>
                    <?php if($totalVagas > 0): ?><span class="nav-count"><?= $totalVagas ?></span><?php endif; ?>
                </a>
                <a href="nova-vaga.php" class="nav-item" onclick="closeSidebar()">
                    <span class="nav-icon">➕</span><span>Publicar Vaga</span>
                </a>
                <a href="#secaoCandidaturas" class="nav-item" onclick="closeSidebar();document.getElementById('secaoCandidaturas')?.scrollIntoView({behavior:'smooth'})">
                    <span class="nav-icon">👥</span><span>Candidatos</span>
                    <?php if($candPendentes > 0): ?><span class="nav-count"><?= $candPendentes ?></span><?php endif; ?>
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Conta</div>
                <a href="perfil.php" class="nav-item" onclick="closeSidebar()">
                    <span class="nav-icon">🏢</span><span>Perfil da Empresa</span>
                </a>
                <a href="configuracoes.php" class="nav-item" onclick="closeSidebar()">
                    <span class="nav-icon">⚙️</span><span>Configurações</span>
                </a>
                <a href="logout.php" class="nav-item logout">
                    <span class="nav-icon">🚪</span><span>Sair da Conta</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- ═══ CONTEÚDO PRINCIPAL ═══════════════════════════ -->
    <main class="main-content">

        <!-- Top bar mobile -->
        <div class="mobile-topbar">
            <div class="mobile-topbar-left">
                <button class="hamburger" onclick="toggleSidebar()" title="Menu">☰</button>
                <div class="mobile-topbar-brand">
                    <div class="mobile-topbar-brand-icon">🚀</div>
                    <span>TGA Carreiras</span>
                </div>
            </div>
            <button class="theme-toggle" onclick="toggleTheme()">
                <span id="theme-icon-mobile">🌙</span>
            </button>
        </div>

        <div class="main-inner">

            <!-- Header -->
            <div class="page-header">
                <div class="page-title">
                    <h1>🏢 Olá, <?= htmlspecialchars($primeiroNome) ?>!</h1>
                    <p>Gerencie suas vagas e acompanhe as candidaturas recebidas</p>
                </div>
                <div class="header-actions">
                    <button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema">
                        <span id="theme-icon">🌙</span>
                    </button>
                    <a href="nova-vaga.php" class="btn btn-primary">
                        <span>➕</span><span>Nova Vaga</span>
                    </a>
                </div>
            </div>

            <!-- ═══ ESTATÍSTICAS ═════════════════════════ -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-header">
                        <div class="stat-icon blue">📋</div>
                        <div class="stat-label">Vagas Publicadas</div>
                    </div>
                    <div class="stat-value"><?= $totalVagas ?></div>
                </div>
                <div class="stat-card green">
                    <div class="stat-header">
                        <div class="stat-icon green">🟢</div>
                        <div class="stat-label">Vagas Ativas</div>
                    </div>
                    <div class="stat-value"><?= $vagasAtivas ?></div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-header">
                        <div class="stat-icon purple">👥</div>
                        <div class="stat-label">Candidaturas</div>
                    </div>
                    <div class="stat-value"><?= $totalCandidaturas ?></div>
                </div>
                <div class="stat-card yellow">
                    <div class="stat-header">
                        <div class="stat-icon yellow">⏳</div>
                        <div class="stat-label">Pendentes</div>
                    </div>
                    <div class="stat-value"><?= $candPendentes ?></div>
                </div>
                <div class="stat-card cyan">
                    <div class="stat-header">
                        <div class="stat-icon cyan">🔍</div>
                        <div class="stat-label">Em Análise</div>
                    </div>
                    <div class="stat-value"><?= $candEmAnalise ?></div>
                </div>
                <div class="stat-card red">
                    <div class="stat-header">
                        <div class="stat-icon red">🤝</div>
                        <div class="stat-label">Aprovados</div>
                    </div>
                    <div class="stat-value"><?= $candAprovados ?></div>
                </div>
            </div>

            <!-- ═══ MINHAS VAGAS ═════════════════════════ -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📋 Minhas Vagas</h3>
                    <div style="display:flex;gap:.5rem;align-items:center;">
                        <?php if($totalVagas > 0): ?>
                        <span class="btn btn-ghost btn-sm" style="cursor:default"><?= $totalVagas ?> vaga(s)</span>
                        <?php endif; ?>
                        <a href="nova-vaga.php" class="btn btn-primary btn-sm">➕ Nova</a>
                    </div>
                </div>

                <?php if ($totalVagas > 0): ?>
                <div class="card-body">
                    <div class="vagas-list">
                        <?php foreach ($vagas as $v):
                            $vst = $vagaStatusLabels[$v['status'] ?? 'ativa'] ?? $vagaStatusLabels['ativa'];
                        ?>
                        <div class="vaga-row">
                            <div class="vaga-row-icon">💼</div>
                            <div class="vaga-row-info">
                                <div class="vaga-row-title"><?= htmlspecialchars($v['titulo']) ?></div>
                                <div class="vaga-row-meta">
                                    <?php if(!empty($v['localizacao'])): ?>
                                    <span>📍 <?= htmlspecialchars($v['localizacao']) ?></span>
                                    <?php endif; ?>
                                    <?php if(!empty($v['salario'])): ?>
                                    <span>💰 <?= htmlspecialchars($v['salario']) ?></span>
                                    <?php endif; ?>
                                    <span>📅 <?= date('d/m/Y', strtotime($v['data_publicacao'])) ?></span>
                                </div>
                            </div>
                            <div class="vaga-row-stats">
                                <div class="vaga-row-stat">
                                    <div class="vaga-row-stat-value"><?= $v['total_candidaturas'] ?></div>
                                    <div class="vaga-row-stat-label">Candidatos</div>
                                </div>
                            </div>
                            <div class="vaga-row-actions">
                                <span class="status-badge <?= $vst['class'] ?>"><?= $vst['icon'] ?> <?= $vst['label'] ?></span>
                                <a href="candidatos.php?vaga_id=<?= $v['id'] ?>" class="btn btn-ghost btn-sm">👥 Ver</a>
                                <a href="editar-vaga.php?id=<?= $v['id'] ?>" class="btn btn-ghost btn-sm">✏️</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📋</div>
                    <h3>Nenhuma vaga publicada ainda</h3>
                    <p>Publique sua primeira vaga e comece a receber candidatos!</p>
                    <a href="nova-vaga.php" class="btn btn-primary">➕ Publicar Vaga</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- ═══ ÚLTIMAS CANDIDATURAS RECEBIDAS ═══════ -->
            <div class="card" id="secaoCandidaturas">
                <div class="card-header">
                    <h3 class="card-title">👥 Candidaturas Recebidas</h3>
                    <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                        <?php if($totalCandidaturas > 0): ?>
                        <span class="btn btn-ghost btn-sm" style="cursor:default"><?= $totalCandidaturas ?> total</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($totalCandidaturas > 0): ?>

                <!-- Filtro por status -->
                <div style="padding:.75rem 1.5rem;border-bottom:1px solid var(--border);display:flex;gap:.4rem;overflow-x:auto;">
                    <button class="filter-tab active" onclick="filterCandidaturas('all', this)">Todas (<?= $totalCandidaturas ?>)</button>
                    <?php if($candPendentes > 0): ?>
                    <button class="filter-tab" onclick="filterCandidaturas('pendente', this)">⏳ Pendentes (<?= $candPendentes ?>)</button>
                    <?php endif; ?>
                    <?php if($candEmAnalise > 0): ?>
                    <button class="filter-tab" onclick="filterCandidaturas('em_analise', this)">🔍 Em Análise (<?= $candEmAnalise ?>)</button>
                    <?php endif; ?>
                    <?php if($candAprovados > 0): ?>
                    <button class="filter-tab" onclick="filterCandidaturas('aprovado', this)">✅ Aprovados (<?= $candAprovados ?>)</button>
                    <?php endif; ?>
                    <?php if($candReprovados > 0): ?>
                    <button class="filter-tab" onclick="filterCandidaturas('reprovado', this)">❌ Reprovados (<?= $candReprovados ?>)</button>
                    <?php endif; ?>
                </div>

                <!-- TABELA DESKTOP -->
                <div class="table-container">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Candidato</th>
                                <th>Contato</th>
                                <th>Vaga</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimasCandidaturas as $c):
                                $st = $statusLabels[$c['status']] ?? $statusLabels['pendente'];
                            ?>
                            <tr data-status="<?= htmlspecialchars($c['status']) ?>">
                                <td>
                                    <div class="table-title"><?= htmlspecialchars($c['candidato_nome']) ?></div>
                                    <div class="table-meta"><?= htmlspecialchars($c['candidato_email']) ?></div>
                                </td>
                                <td>
                                    <div style="font-size:.82rem">📱 <?= htmlspecialchars($c['telefone'] ?? '—') ?></div>
                                </td>
                                <td>
                                    <div class="table-vaga">💼 <?= htmlspecialchars($c['vaga_titulo'] ?? '—') ?></div>
                                </td>
                                <td>
                                    <span class="status-badge <?= $st['class'] ?>"><?= $st['label'] ?></span>
                                </td>
                                <td>
                                    <div class="table-meta"><?= date('d/m/Y', strtotime($c['data_candidatura'])) ?></div>
                                    <div class="table-meta"><?= date('H:i', strtotime($c['data_candidatura'])) ?></div>
                                </td>
                                <td>
                                    <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                                        <?php if(!empty($c['curriculo'])): ?>
                                        <a href="../<?= htmlspecialchars($c['curriculo']) ?>" target="_blank" class="btn btn-ghost btn-sm" title="Ver currículo">📄</a>
                                        <?php endif; ?>
                                        <a href="candidatos.php?vaga_id=<?= $c['vaga_id'] ?>" class="btn btn-ghost btn-sm" title="Ver detalhes">👁️</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- MOBILE CANDIDATURAS -->
                <div class="mobile-candidaturas" style="padding: 1rem;">
                    <?php foreach ($ultimasCandidaturas as $c):
                        $st = $statusLabels[$c['status']] ?? $statusLabels['pendente'];
                    ?>
                    <div class="mc-card" data-status="<?= htmlspecialchars($c['status']) ?>">
                        <div class="mc-header">
                            <div class="mc-avatar">👤</div>
                            <div class="mc-title-area">
                                <div class="mc-titulo"><?= htmlspecialchars($c['candidato_nome']) ?></div>
                                <div class="mc-empresa">💼 <?= htmlspecialchars($c['vaga_titulo'] ?? '—') ?></div>
                            </div>
                        </div>
                        <div class="mc-grid">
                            <div class="mc-field">
                                <span class="mc-label">📧 E-mail</span>
                                <span class="mc-value"><?= htmlspecialchars($c['candidato_email']) ?></span>
                            </div>
                            <div class="mc-field">
                                <span class="mc-label">📱 Telefone</span>
                                <span class="mc-value"><?= htmlspecialchars($c['telefone'] ?? '—') ?></span>
                            </div>
                            <?php if(!empty($c['mensagem'])): ?>
                            <div class="mc-field" style="grid-column:1/-1">
                                <span class="mc-label">💬 Mensagem</span>
                                <span class="mc-value"><?= htmlspecialchars(mb_substr($c['mensagem'], 0, 100)) ?>...</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="mc-footer">
                            <span class="status-badge <?= $st['class'] ?>"><?= $st['label'] ?></span>
                            <span class="mc-value">📅 <?= date('d/m/Y H:i', strtotime($c['data_candidatura'])) ?></span>
                            <div style="display:flex;gap:.35rem;">
                                <?php if(!empty($c['curriculo'])): ?>
                                <a href="../<?= htmlspecialchars($c['curriculo']) ?>" target="_blank" class="btn btn-ghost btn-sm" title="Ver currículo">📄</a>
                                <?php endif; ?>
                                <a href="candidatos.php?vaga_id=<?= $c['vaga_id'] ?>" class="btn btn-ghost btn-sm">Ver →</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>Nenhuma candidatura recebida</h3>
                    <p>Publique vagas para começar a receber candidatos qualificados!</p>
                    <a href="nova-vaga.php" class="btn btn-primary">➕ Publicar Vaga</a>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- /main-inner -->
    </main>
</div><!-- /layout -->

<!-- ═══════════════════════════════════════════════════════════
     SCRIPTS
═══════════════════════════════════════════════════════════ -->
<script>
"use strict";

/* ── TEMA ────────────────────────────────────────────────── */
function toggleTheme() {
    const html = document.documentElement;
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    const emoji = next === 'dark' ? '🌙' : '☀️';
    document.querySelectorAll('[id^="theme-icon"]').forEach(el => el.textContent = emoji);
    localStorage.setItem('theme', next);
}

/* ── SIDEBAR MOBILE ──────────────────────────────────────── */
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
}

/* ── FILTRAR CANDIDATURAS POR STATUS ─────────────────────── */
function filterCandidaturas(status, btn) {
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');

    // Filtrar linhas da tabela desktop
    document.querySelectorAll('.modern-table tbody tr[data-status]').forEach(row => {
        row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
    });

    // Filtrar cards mobile
    document.querySelectorAll('.mc-card[data-status]').forEach(card => {
        card.style.display = (status === 'all' || card.dataset.status === status) ? '' : 'none';
    });
}

/* ── FECHAR SIDEBAR COM ESC ──────────────────────────────── */
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeSidebar();
});

/* ── INIT ────────────────────────────────────────────────── */
window.addEventListener('DOMContentLoaded', () => {
    const saved = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    const emoji = saved === 'dark' ? '🌙' : '☀️';
    document.querySelectorAll('[id^="theme-icon"]').forEach(el => el.textContent = emoji);
});
</script>

</body>
</html>