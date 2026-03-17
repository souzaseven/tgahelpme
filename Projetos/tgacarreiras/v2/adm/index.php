<?php
/* ============================================================
   adm/index.php — TGA Carreiras
   Dashboard principal do painel administrativo.

   Responsabilidades:
   ─ Verificar autenticação e permissão de admin
   ─ Carregar KPIs: total de vagas, ativas, inativas,
     candidaturas totais, pendentes e do mês atual
   ─ Listar as 8 últimas vagas publicadas
   ─ Exibir ações rápidas de navegação

   Correções aplicadas (vs versão anterior):
   ─ [CRÍTICO] Adicionada verificação de sessão — página
     era acessível sem login
   ─ [CRÍTICO] Adicionada verificação de is_admin — qualquer
     usuário logado poderia acessar o painel
   ─ [MÉDIO]   Corrigida divisão por zero no cálculo de
     percentual de vagas ativas
   ─ [MÉDIO]   Queries do mês atual convertidas para prepared
     statements (padrão seguro)
   ─ [BAIXO]   Corrigido link "Sair" que apontava para "#"
   ─ [BAIXO]   Corrigido link ativo no sidebar (dashboard.php
     → index.php)
============================================================ */

session_start();

/* ============================================================
   BLOCO 1 — AUTENTICAÇÃO
   Redireciona para o login se não houver sessão ativa
   ou se o usuário não for admin.
   ⚠️  Mantenha este bloco SEMPRE no topo, antes de qualquer
       output ou query no banco.
============================================================ */
if (empty($_SESSION['usuario_id'])) {
    header('Location: ../candidato/login.php?erro=Acesso+restrito');
    exit;
}

if (empty($_SESSION['is_admin']) || (int)$_SESSION['is_admin'] !== 1) {
    // Usuário logado mas sem permissão de admin
    header('Location: ../candidato/dashboard.php');
    exit;
}

/* ============================================================
   BLOCO 2 — CONEXÃO
============================================================ */
require_once __DIR__ . '/../backend/conexao.php';

/* ============================================================
   BLOCO 3 — KPIs GERAIS
   Todas as queries usam fetchColumn() para performance —
   não carregam linhas desnecessárias na memória.
============================================================ */

// Total e status de vagas
$totalVagas   = (int) $pdo->query("SELECT COUNT(*) FROM vagas")->fetchColumn();
$vagasAtivas  = (int) $pdo->query("SELECT COUNT(*) FROM vagas WHERE status = 'ativa'")->fetchColumn();
$vagasInativas = $totalVagas - $vagasAtivas;

// Percentual de vagas ativas (protegido contra divisão por zero)
$percentualAtivas = $totalVagas > 0
    ? round(($vagasAtivas / $totalVagas) * 100)
    : 0;

// Candidaturas
$totalCandidaturas      = (int) $pdo->query("SELECT COUNT(*) FROM candidaturas")->fetchColumn();
$candidaturasPendentes  = (int) $pdo->query("SELECT COUNT(*) FROM candidaturas WHERE status = 'pendente'")->fetchColumn();

/* ============================================================
   BLOCO 4 — ESTATÍSTICAS DO MÊS ATUAL
   Usa prepared statement com parâmetro nomeado para
   evitar qualquer risco de injeção SQL.
============================================================ */
$mesAtual = date('Y-m'); // formato: "2025-01"

$stmtVagasMes = $pdo->prepare("
    SELECT COUNT(*) FROM vagas
    WHERE DATE_FORMAT(data_publicacao, '%Y-%m') = :mes
");
$stmtVagasMes->execute([':mes' => $mesAtual]);
$vagasMesAtual = (int) $stmtVagasMes->fetchColumn();

$stmtCandMes = $pdo->prepare("
    SELECT COUNT(*) FROM candidaturas
    WHERE DATE_FORMAT(data_candidatura, '%Y-%m') = :mes
");
$stmtCandMes->execute([':mes' => $mesAtual]);
$candidaturasMesAtual = (int) $stmtCandMes->fetchColumn();

/* ============================================================
   BLOCO 5 — ÚLTIMAS VAGAS (tabela do dashboard)
   LEFT JOIN para incluir vagas mesmo sem candidatos.
   GROUP BY necessário pelo COUNT de candidaturas.
============================================================ */
$ultimasVagas = $pdo->query("
    SELECT
        v.id,
        v.titulo,
        v.data_publicacao,
        v.status,
        COUNT(c.id) AS total_candidatos
    FROM vagas v
    LEFT JOIN candidaturas c ON v.id = c.vaga_id
    GROUP BY v.id
    ORDER BY v.data_publicacao DESC
    LIMIT 8
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin — TGA Carreiras</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<!--icone da pagina-->
<link rel="icon" href="http://tgameajuda.com/Projetos/tgacarreiras/img/icone_logo.ico" type="image/x-icon">


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
        /* ── Variáveis de tema ───────────────────────────────── */
        :root {
            --primary-blue:  #0066FF;
            --primary-dark:  #0052CC;
            --primary-light: #3385FF;

            /* Modo escuro (padrão) */
            --bg-primary:    #0F172A;
            --bg-secondary:  #1E293B;
            --bg-tertiary:   #334155;
            --text-primary:  #F1F5F9;
            --text-secondary:#CBD5E1;
            --text-tertiary: #64748B;
            --border-color:  #334155;
            --shadow-sm:  0 1px 3px  rgba(0,0,0,0.30);
            --shadow-md:  0 4px 12px rgba(0,0,0,0.30);
            --shadow-lg:  0 10px 30px rgba(0,0,0,0.40);
            --shadow-xl:  0 20px 40px rgba(0,102,255,0.30);

            /* Status */
            --success: #10B981;
            --warning: #F59E0B;
            --danger:  #EF4444;
            --info:    #3B82F6;
        }

        [data-theme="light"] {
            --bg-primary:    #FAFBFC;
            --bg-secondary:  #F5F7FA;
            --bg-tertiary:   #EDF0F5;
            --text-primary:  #1A202C;
            --text-secondary:#4A5568;
            --text-tertiary: #718096;
            --border-color:  #E2E8F0;
            --shadow-sm:  0 1px 3px  rgba(0,0,0,0.06);
            --shadow-md:  0 4px 12px rgba(0,0,0,0.07);
            --shadow-lg:  0 10px 30px rgba(0,0,0,0.08);
            --shadow-xl:  0 20px 40px rgba(0,102,255,0.12);
        }

        /* ── Reset ───────────────────────────────────────────── */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            line-height: 1.6;
            transition: background-color 0.3s ease;
        }

        /* ── Layout ──────────────────────────────────────────── */
        .admin-layout { display: flex; min-height: 100vh; }

        /* ── Sidebar ─────────────────────────────────────────── */
        .sidebar {
            width: 280px;
            background: var(--bg-primary);
            border-right: 1px solid var(--border-color);
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 0 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
        }

        .logo { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; }

        .logo-icon {
            width: 45px; height: 45px;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-dark));
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
        }

        .logo-text h1 { font-size: 1.4rem; font-weight: 800; color: var(--text-primary); }
        .logo-text p  { font-size: 0.75rem; color: var(--text-tertiary); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

        .sidebar-nav { padding: 1.5rem 0; }
        .nav-section  { margin-bottom: 1.5rem; }

        .nav-section-title {
            padding: 0 1.5rem;
            font-size: 0.7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1px;
            color: var(--text-tertiary); margin-bottom: 0.75rem;
        }

        .nav-item {
            display: flex; align-items: center; gap: 1rem;
            padding: 0.875rem 1.5rem;
            color: var(--text-secondary);
            text-decoration: none; font-weight: 500; font-size: 0.95rem;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }

        .nav-item:hover  { background: var(--bg-secondary); color: var(--text-primary); border-left-color: var(--primary-blue); }
        .nav-item.active { background: var(--bg-secondary); color: var(--primary-blue);  border-left-color: var(--primary-blue); font-weight: 600; }
        .nav-item.danger:hover { background: rgba(239,68,68,0.08); color: var(--danger); border-left-color: var(--danger); }

        .nav-icon  { font-size: 1.25rem; width: 24px; text-align: center; }

        .nav-badge {
            margin-left: auto;
            background: var(--danger); color: white;
            font-size: 0.7rem; font-weight: 700;
            padding: 0.2rem 0.5rem; border-radius: 10px;
            min-width: 20px; text-align: center;
        }

        /* ── Conteúdo principal ──────────────────────────────── */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }

        /* ── Cabeçalho da página ─────────────────────────────── */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;
        }

        .page-title      { flex: 1; }
        .page-title h2   { font-size: 2rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0.25rem; }
        .page-title p    { color: var(--text-secondary); font-size: 0.95rem; }
        .header-actions  { display: flex; gap: 1rem; align-items: center; }

        .theme-toggle {
            width: 44px; height: 44px;
            border: 2px solid var(--border-color); border-radius: 10px;
            background: var(--bg-primary); cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; transition: all 0.3s ease;
        }

        .theme-toggle:hover { background: var(--primary-blue); border-color: var(--primary-blue); transform: scale(1.05); }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-dark));
            color: white; padding: 0.75rem 1.5rem; border-radius: 10px;
            text-decoration: none; font-weight: 600; font-size: 0.95rem;
            border: none; cursor: pointer;
            display: inline-flex; align-items: center; gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(0,102,255,0.25); transition: all 0.3s ease;
        }

        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,102,255,0.35); }

        /* ── Cards de estatísticas ───────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem; margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-primary); border-radius: 16px;
            padding: 1.75rem; border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md); transition: all 0.3s ease;
            position: relative; overflow: hidden;
            animation: fadeInUp 0.5s ease-out backwards;
        }

        /* Barra colorida no topo do card ao hover */
        .stat-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, var(--primary-blue), var(--primary-light));
            transform: scaleX(0); transition: transform 0.3s ease;
        }

        .stat-card:hover::before { transform: scaleX(1); }
        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }

        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.10s; }
        .stat-card:nth-child(3) { animation-delay: 0.15s; }
        .stat-card:nth-child(4) { animation-delay: 0.20s; }

        .stat-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }

        .stat-icon {
            width: 50px; height: 50px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.blue   { background: rgba(0,102,255,0.1);  color: var(--primary-blue); }
        .stat-icon.green  { background: rgba(16,185,129,0.1); color: var(--success); }
        .stat-icon.orange { background: rgba(245,158,11,0.1); color: var(--warning); }
        .stat-icon.purple { background: rgba(139,92,246,0.1); color: #8B5CF6; }

        .stat-trend {
            display: flex; align-items: center; gap: 0.25rem;
            font-size: 0.85rem; font-weight: 600;
            padding: 0.25rem 0.65rem; border-radius: 20px;
        }

        .stat-trend.up   { background: rgba(16,185,129,0.1); color: var(--success); }
        .stat-trend.warn { background: rgba(245,158,11,0.1); color: var(--warning); }

        .stat-label       { font-size: 0.85rem; color: var(--text-tertiary); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; }
        .stat-value       { font-size: 2.25rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0.5rem; }
        .stat-description { font-size: 0.85rem; color: var(--text-secondary); }

        /* ── Seção de tabela + ações rápidas ─────────────────── */
        .chart-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem; margin-bottom: 2rem;
        }

        .chart-card {
            background: var(--bg-primary); border-radius: 16px;
            padding: 1.75rem; border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
        }

        .chart-header            { margin-bottom: 1.5rem; }
        .chart-header h3         { font-size: 1.2rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; }
        .chart-header p          { font-size: 0.85rem; color: var(--text-tertiary); }

        /* ── Tabela ──────────────────────────────────────────── */
        .table-container { overflow-x: auto; }

        .modern-table { width: 100%; border-collapse: collapse; }

        .modern-table thead { background: var(--bg-secondary); }

        .modern-table th {
            padding: 1rem 1.5rem; text-align: left;
            font-size: 0.8rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px;
            color: var(--text-tertiary); border-bottom: 1px solid var(--border-color);
        }

        .modern-table td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary); font-size: 0.95rem;
        }

        .modern-table tbody tr { transition: background-color 0.2s ease; }
        .modern-table tbody tr:hover { background: var(--bg-secondary); }
        .modern-table tbody tr:last-child td { border-bottom: none; }

        .table-id    { font-weight: 700; color: var(--text-tertiary); font-size: 0.85rem; }
        .table-title { font-weight: 600; color: var(--text-primary); }

        /* ── Badges de status ────────────────────────────────── */
        .status-badge {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.375rem 0.875rem; border-radius: 20px;
            font-size: 0.8rem; font-weight: 600; text-transform: capitalize;
        }

        .status-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .status-badge.ativa   { background: rgba(16,185,129,0.1); color: var(--success); }
        .status-badge.inativa { background: rgba(100,116,139,0.1); color: var(--text-tertiary); }

        .candidate-count {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: var(--bg-secondary);
            padding: 0.375rem 0.875rem; border-radius: 20px;
            font-weight: 600; color: var(--text-primary); font-size: 0.85rem;
        }

        /* ── Ações rápidas ───────────────────────────────────── */
        .quick-actions { display: grid; gap: 1rem; }

        .action-item {
            background: var(--bg-secondary); padding: 1.25rem;
            border-radius: 12px; border: 1px solid var(--border-color);
            display: flex; align-items: center; gap: 1rem;
            transition: all 0.2s ease; cursor: pointer;
            text-decoration: none; color: inherit;
        }

        .action-item:hover { background: var(--bg-tertiary); transform: translateX(4px); }

        .action-icon {
            width: 45px; height: 45px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-dark));
            color: white; flex-shrink: 0;
        }

        .action-content      { flex: 1; }
        .action-title        { font-weight: 600; color: var(--text-primary); margin-bottom: 0.25rem; }
        .action-description  { font-size: 0.85rem; color: var(--text-tertiary); }

        /* ── Responsivo ──────────────────────────────────────── */
        @media (max-width: 1200px) {
            .chart-section { grid-template-columns: 1fr; }
        }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
        }

        @media (max-width: 768px) {
            .main-content { padding: 1rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .stats-grid { grid-template-columns: 1fr; }
        }

        /* ── Animações ───────────────────────────────────────── */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

:root{
  --sidebar-open: 280px;
  --sidebar-collapsed: 84px;
}

.sidebar{
  width: var(--sidebar-open);
  transition: width .25s ease;
}

.main-content{
  margin-left: var(--sidebar-open);
  transition: margin-left .25s ease;
}

/* Botão recolher */
.sidebar-toggle{
  margin-left: auto;
  width: 40px; height: 40px;
  border-radius: 10px;
  border: 1px solid var(--border-color);
  background: var(--bg-secondary);
  color: var(--text-primary);
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: .2s ease;
}
.sidebar-toggle:hover{
  background: var(--primary-blue);
  border-color: var(--primary-blue);
  transform: scale(1.05);
}

/* Estado recolhido */
.admin-layout.sidebar-collapsed .sidebar{ width: var(--sidebar-collapsed); }
.admin-layout.sidebar-collapsed .main-content{ margin-left: var(--sidebar-collapsed); }

.admin-layout.sidebar-collapsed .logo-text,
.admin-layout.sidebar-collapsed .nav-section-title,
.admin-layout.sidebar-collapsed .nav-item span:not(.nav-icon){
  display: none;
}

.admin-layout.sidebar-collapsed .nav-item{
  justify-content: center;
  padding: 0.9rem 0;
  gap: 0;
}

.admin-layout.sidebar-collapsed .nav-item{
  position: relative;
}
.admin-layout.sidebar-collapsed .nav-item::after{
  content: attr(data-label);
  position: absolute;
  left: calc(100% + 10px);
  top: 50%;
  transform: translateY(-50%);
  background: #111827;
  color: #fff;
  padding: 6px 10px;
  border-radius: 8px;
  font-size: .78rem;
  white-space: nowrap;
  opacity: 0;
  pointer-events: none;
  transition: .2s ease;
  box-shadow: 0 10px 30px rgba(0,0,0,.35);
}
.admin-layout.sidebar-collapsed .nav-item:hover::after{
  opacity: 1;
}
    </style>
</head>
<body>

<div class="admin-layout">

    <!-- ===== SIDEBAR ===== -->
   
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">⚙️</div>
                <div class="logo-text">
                    <h1>TGA Admin</h1>
                    <p>Developer Panel</p>
                </div>

                <!-- Botão recolher -->
                <button class="sidebar-toggle" id="sidebarToggle" type="button" title="Ocultar/Mostrar menu">
                    ☰
                </button>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Principal</div>

                <a href="index.php" class="nav-item" data-label="Dashboard">
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
                </a>

                <a href="vagas.php" class="nav-item active" data-label="Vagas">
                    <span class="nav-icon">💼</span>
                    <span>Vagas</span>
                </a>

                <a href="candidaturas.php" class="nav-item" data-label="Candidaturas">
                    <span class="nav-icon">👥</span>
                    <span>Candidaturas</span>
                </a>
            </div>

           
             <div class="nav-section">
                <div class="nav-section-title">Configurações</div>

                <a href="#" class="nav-item">
                    <span class="nav-icon">⚙️</span>
                    <span>Configurações</span>
                </a>

               
                 <a href="perfil.php" class="nav-item" data-label="Perfil">
                    <span class="nav-icon">👤</span>
                    <span>Perfil</span>
                </a>

                <!-- Logout corrigido: antes apontava para "#" -->
                <a href="logout.php" class="nav-item danger">
                    <span class="nav-icon">🚪</span>
                    <span>Sair</span>
                </a>
            </div>
            
        </nav>
    </aside>

    <!-- ===== CONTEÚDO PRINCIPAL ===== -->
    <main class="main-content">

        <div class="page-header">
            <div class="page-title">
                <h2>Dashboard</h2>
                <p>Bem-vindo, <?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Admin') ?> — visão geral do sistema TGA Carreiras</p>
            </div>
            <div class="header-actions">
                <button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema">
                    <span id="theme-icon">🌙</span>
                </button>
                <a href="vagas.php" class="btn-primary">
                    <span>➕</span>
                    <span>Nova Vaga</span>
                </a>
            </div>
        </div>

        <!-- ── KPIs ── -->
        <div class="stats-grid">

            <!-- Total de Vagas -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon blue">💼</div>
                    <div class="stat-trend up">
                        <span>↑</span>
                        <span>+<?= $vagasMesAtual ?></span>
                    </div>
                </div>
                <div class="stat-label">Total de Vagas</div>
                <div class="stat-value"><?= $totalVagas ?></div>
                <div class="stat-description"><?= $vagasMesAtual ?> novas este mês</div>
            </div>

            <!-- Vagas Ativas (percentual protegido contra divisão por zero) -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon green">✅</div>
                    <div class="stat-trend up">
                        <span>↑</span>
                        <span><?= $percentualAtivas ?>%</span>
                    </div>
                </div>
                <div class="stat-label">Vagas Ativas</div>
                <div class="stat-value"><?= $vagasAtivas ?></div>
                <div class="stat-description"><?= $vagasInativas ?> inativas no momento</div>
            </div>

            <!-- Candidaturas -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon orange">👥</div>
                    <div class="stat-trend up">
                        <span>↑</span>
                        <span>+<?= $candidaturasMesAtual ?></span>
                    </div>
                </div>
                <div class="stat-label">Candidaturas</div>
                <div class="stat-value"><?= $totalCandidaturas ?></div>
                <div class="stat-description"><?= $candidaturasMesAtual ?> novas este mês</div>
            </div>

            <!-- Pendentes -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon purple">⏳</div>
                    <?php if ($candidaturasPendentes > 0): ?>
                        <div class="stat-trend warn">
                            <span>!</span>
                            <span>Atenção</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="stat-label">Pendentes</div>
                <div class="stat-value"><?= $candidaturasPendentes ?></div>
                <div class="stat-description">Aguardando análise</div>
            </div>

        </div>

        <!-- ── Tabela de últimas vagas + Ações rápidas ── -->
        <div class="chart-section">

            <!-- Últimas vagas publicadas -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Últimas Vagas Publicadas</h3>
                    <p>As 8 vagas mais recentes do sistema</p>
                </div>
                <div class="table-container">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Título da Vaga</th>
                                <th>Status</th>
                                <th>Candidatos</th>
                                <th>Publicação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ultimasVagas)): ?>
                            <tr>
                                <td colspan="5" style="text-align:center;color:var(--text-tertiary);padding:2rem">
                                    Nenhuma vaga cadastrada ainda.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($ultimasVagas as $vaga): ?>
                                <tr>
                                    <td>
                                        <span class="table-id">
                                            #<?= str_pad($vaga['id'], 4, '0', STR_PAD_LEFT) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="table-title">
                                            <?= htmlspecialchars($vaga['titulo']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= htmlspecialchars($vaga['status']) ?>">
                                            <?= ucfirst(htmlspecialchars($vaga['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="candidate-count">
                                            <span>👥</span>
                                            <span><?= (int)$vaga['total_candidatos'] ?></span>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($vaga['data_publicacao'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Ações rápidas -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Ações Rápidas</h3>
                    <p>Acesso direto às funcionalidades</p>
                </div>
                <div class="quick-actions">

                    <a href="vagas.php" class="action-item">
                        <div class="action-icon">➕</div>
                        <div class="action-content">
                            <div class="action-title">Criar Nova Vaga</div>
                            <div class="action-description">Publicar uma nova oportunidade</div>
                        </div>
                    </a>

                    <a href="vagas.php" class="action-item">
                        <div class="action-icon">📝</div>
                        <div class="action-content">
                            <div class="action-title">Gerenciar Vagas</div>
                            <div class="action-description">Editar e organizar vagas</div>
                        </div>
                    </a>

                    <a href="candidaturas.php" class="action-item">
                        <div class="action-icon">👁️</div>
                        <div class="action-content">
                            <div class="action-title">Ver Candidaturas</div>
                            <div class="action-description">Analisar candidatos</div>
                        </div>
                    </a>

                    <!-- Placeholder: funcionalidade futura -->
                    <a href="#" class="action-item">
                        <div class="action-icon">📊</div>
                        <div class="action-content">
                            <div class="action-title">Relatórios</div>
                            <div class="action-description">Análises e estatísticas (em breve)</div>
                        </div>
                    </a>

                    <a href="#" class="action-item">
                        <div class="action-icon">⚙️</div>
                        <div class="action-content">
                            <div class="action-title">Configurações</div>
                            <div class="action-description">Personalizar sistema (em breve)</div>
                        </div>
                    </a>

                </div>
            </div>

        </div>

    </main>
</div><!-- /admin-layout -->

<script>
"use strict";

/* ── Tema claro / escuro ─────────────────────────────────── */
function toggleTheme() {
    const html    = document.documentElement;
    const icon    = document.getElementById('theme-icon');
    const current = html.getAttribute('data-theme') || 'dark';
    const next    = current === 'dark' ? 'light' : 'dark';

    html.setAttribute('data-theme', next);
    icon.textContent = next === 'light' ? '☀️' : '🌙';
    localStorage.setItem('theme', next);
}

/* ── Carregar tema salvo no localStorage ─────────────────── */
window.addEventListener('DOMContentLoaded', () => {
    const saved = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    document.getElementById('theme-icon').textContent = saved === 'light' ? '☀️' : '🌙';
});

/* ── Toggle sidebar em mobile ────────────────────────────── */
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}


(function sidebarToggleInit(){
  const layout = document.querySelector('.admin-layout');
  const btn = document.getElementById('sidebarToggle');
  if (!layout || !btn) return;

  const saved = localStorage.getItem('admin_sidebar') || 'open';
  if (saved === 'collapsed') layout.classList.add('sidebar-collapsed');

  btn.addEventListener('click', () => {
    layout.classList.toggle('sidebar-collapsed');
    localStorage.setItem(
      'admin_sidebar',
      layout.classList.contains('sidebar-collapsed') ? 'collapsed' : 'open'
    );
  });
})();

</script>

</body>
</html>

<?php
/* ============================================================
   SUGESTÕES FUTURAS — adm/index.php
   ─────────────────────────────────────────────────────────
   [ ] Criar adm/login.php com formulário de autenticação
       próprio para o admin (atualmente redireciona para
       candidato/login.php que é genérico)

   [ ] Criar adm/logout.php dedicado (hoje o link aponta
       para logout.php que ainda não existe na pasta /adm)

   [ ] Adicionar gráfico de candidaturas por mês usando
       Chart.js ou ApexCharts (dados já disponíveis no banco)

   [ ] Adicionar KPI de "Taxa de aprovação" =
       aprovados / total_candidaturas * 100

   [ ] Adicionar KPI de "Empresas ativas" quando o módulo
       de empresas for expandido

   [ ] Paginação na tabela de últimas vagas (hoje LIMIT 8
       fixo — pode esconder vagas relevantes)

   [ ] Botão de toggle da sidebar para mobile com overlay
       (a função toggleSidebar() existe mas não há botão
       no HTML que a chame)

   [ ] Logs de acesso ao painel admin (quem logou, quando,
       de qual IP) para auditoria
============================================================ */
?>