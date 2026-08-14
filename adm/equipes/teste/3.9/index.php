<?php
/**
 * Painel Unificado - Clientes Web
 * Versão: v3 (base unificada estável)
 * Autor: Anderson de Souza
 */


require_once __DIR__ . '/backend/verifica_acesso.php';
require_once __DIR__ . '/backend/conexao.php';

/* CSRF */
$csrf = $_SESSION['csrf_token'] ?? '';

$timeout        = 7200;
$loginTime      = $_SESSION['login_time'] ?? time();
$tokenRemaining = max(0, $timeout - time() + $loginTime);

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>Painel Unificado - Clientes Web (v3)</title>

  <!-- Fonte -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

  <!-- Estilo principal (versão automática pelo mtime — força recarregamento ao subir arquivos) -->
  <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>" />
  <link rel="stylesheet" href="assets/css/modal-relat.css?v=<?= filemtime(__DIR__.'/assets/css/modal-relat.css') ?>" />

  <!-- Favicon -->
  <link rel="shortcut icon" href="https://tgameajuda.com/img/principal/bot-tga.webp" type="image/x-icon">

  <!-- ✅ Google Tag Manager -->
  <!---->
  <script>
    (function (w, d, s, l, i) {
      w[l] = w[l] || [];
      w[l].push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
      var f = d.getElementsByTagName(s)[0],
        j = d.createElement(s),
        dl = l != 'dataLayer' ? '&l=' + l : '';
      j.async = true;
      j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
      f.parentNode.insertBefore(j, f);
    })(window, document, 'script', 'dataLayer', 'GTM-K2XFNTVZ');
  </script>

  <!-- ✅ Google Analytics -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-E7ZNTJSRYR"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('js', new Date());
    gtag('config', 'G-E7ZNTJSRYR');
    gtag('config', 'G-S8EC5C2WTG');
  </script>

  <!--  Ajustes rápidos para: scroll em qualquer view + collapse do sidebar -->
  <style>
    /* Scroll automático dentro de qualquer tela (view) */
    .content {
      height: calc(100vh - 72px);
    }

    .view {
      height: 100%;
      overflow: auto;
      padding-right: 6px;
    }

    /* Sidebar collapsed: só ícones */
    .sidebar.collapsed {
      width: 72px;
    }

    .sidebar.collapsed .brand-text,
    .sidebar.collapsed .mi-label,
    .sidebar.collapsed .hint span:nth-child(2),
    .sidebar.collapsed #btnToggleTheme {
      display: none !important;
    }

    .sidebar.collapsed .brand {
      justify-content: center;
    }

    .sidebar.collapsed .menu-item {
      justify-content: center;
      gap: 0;
      padding: 12px 10px;
    }

    .sidebar.collapsed .mi-ico {
      font-size: 18px;
    }

    /* ══════════════════════════════════════
       RELATÓRIOS — Layout
    ══════════════════════════════════════ */
    #view-relatorios:not(.hidden) {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    /* Painel de filtros */
    .relat-filter-panel {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 16px 20px;
    }

    .relat-filter-row {
      display: flex;
      align-items: flex-end;
      flex-wrap: wrap;
      gap: 12px;
    }

    .relat-filter-group {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .relat-filter-group label {
      font-size: .7rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: var(--text-muted);
    }

    .relat-filter-group select,
    .relat-filter-group input[type="date"] {
      padding: 8px 12px;
      background: var(--bg-hover);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      color: var(--text-main);
      font-size: .83rem;
      font-family: inherit;
      transition: border-color .15s;
    }

    .relat-filter-group select:focus,
    .relat-filter-group input:focus {
      outline: none;
      border-color: var(--color-primary);
    }

    /* Busca */
    .relat-search-group {
      flex: 1;
      min-width: 200px;
    }

    .relat-search-wrap {
      position: relative;
    }

    .relat-search-wrap i {
      position: absolute;
      left: 11px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
      font-size: .82rem;
      pointer-events: none;
    }

    .relat-search-wrap input {
      width: 100%;
      padding: 8px 12px 8px 34px;
      background: var(--bg-hover);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      color: var(--text-main);
      font-size: .83rem;
      font-family: inherit;
      transition: border-color .15s;
    }

    .relat-search-wrap input:focus {
      outline: none;
      border-color: var(--color-primary);
    }

    /* Intervalo de datas */
    .relat-date-range {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .relat-date-range input[type="date"] {
      width: 136px;
    }

    .relat-date-sep {
      color: var(--text-muted);
      font-size: .85rem;
    }

    /* Botões do filtro */
    .relat-filter-btns {
      display: flex;
      gap: 6px;
      padding-bottom: 1px;
    }

    /* Badge filtro ativo */
    #relatFiltroAtivoBadge {
      margin-top: 10px;
    }

    .relat-filtro-ativo {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: .74rem;
      font-weight: 500;
      background: rgba(56,189,248,.08);
      border: 1px solid rgba(56,189,248,.22);
      color: var(--color-primary);
      padding: 3px 12px;
      border-radius: 99px;
    }

    /* Sub-navegação */
    .relat-subnav {
      display: flex;
      flex-wrap: wrap;
      gap: 4px;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 8px 12px;
    }

    .relat-tab {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 7px 16px;
      border-radius: var(--radius-sm);
      border: 1px solid transparent;
      background: transparent;
      color: var(--text-muted);
      font-size: .82rem;
      font-weight: 500;
      cursor: pointer;
      transition: .15s ease;
      white-space: nowrap;
    }

    .relat-tab i { font-size: .78rem; }

    .relat-tab:hover {
      background: var(--bg-hover);
      color: var(--text-main);
    }

    .relat-tab.active {
      background: rgba(167,139,250,.14);
      border-color: rgba(167,139,250,.35);
      color: #a78bfa;
      font-weight: 600;
    }

    /* Área de conteúdo */
    .relat-content-card { padding: 20px; }

    /* KPI */
    .relat-kpi-badge {
      display: inline-flex;
      align-items: center;
      gap: 14px;
      border: 1px solid;
      border-radius: var(--radius-sm);
      padding: 12px 22px;
      margin-bottom: 18px;
      background: rgba(255,255,255,.03);
    }

    .relat-kpi-num {
      font-size: 2.2rem;
      font-weight: 700;
      line-height: 1;
    }

    .relat-kpi-label {
      font-size: .82rem;
      color: var(--text-muted);
      line-height: 1.5;
    }

    /* Loading */
    .relat-loading {
      text-align: center;
      padding: 60px 20px;
      opacity: .35;
    }

    .relat-loading i {
      display: block;
      font-size: 1.8rem;
      margin-bottom: 10px;
    }

    /* ── Card Resumo de Versões ── */
    .versoes-resumo-card {
      padding: 16px 20px;
    }

    .versoes-resumo-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      font-size: .8rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: var(--text-muted);
      margin-bottom: 14px;
    }

    .versoes-toggle-btn {
      font-size: .72rem;
      padding: 4px 12px;
      border-radius: 99px;
      text-transform: none;
      letter-spacing: 0;
      font-weight: 500;
    }

    .versoes-toggle-btn.all-mode {
      background: rgba(239,68,68,.12);
      color: #ef4444;
      border-color: rgba(239,68,68,.25);
    }

    .versoes-lista {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }

    .versao-item {
      display: flex;
      align-items: center;
      gap: 8px;
      background: var(--bg-hover);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 8px 14px;
      min-width: 130px;
    }

    .versao-item .versao-nome {
      font-size: .82rem;
      color: var(--text-muted);
      white-space: nowrap;
    }

    .versao-item .versao-total {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--color-primary);
      margin-left: auto;
    }

    .versao-item .versao-badge {
      font-size: .68rem;
      background: rgba(56,189,248,.12);
      color: var(--color-primary);
      border-radius: 99px;
      padding: 2px 8px;
      white-space: nowrap;
    }

    /* Pill "Com EXE" — destacada em âmbar, separada visualmente das versões */
    .versao-item--exe {
      border-color: rgba(245,158,11,.35);
      background: rgba(245,158,11,.08);
      margin-left: 4px;
    }

    .versao-item--exe .versao-nome {
      color: #f59e0b;
    }

    .versao-item--exe .versao-nome i {
      margin-right: 4px;
    }

    .versao-item--exe .versao-total {
      color: #f59e0b;
    }

    /* ── Coluna Integrações ── */
    .integracoes-cell {
      text-align: center;
      white-space: nowrap;
    }

    .integracoes-cell i {
      font-size: 15px;
      margin: 0 3px;
    }

    /* Botão "=" no topo do sidebar */
    .btn-collapse {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 38px;
      height: 38px;
      border-radius: 12px;
      border: 1px solid rgba(255, 255, 255, .08);
      background: rgba(255, 255, 255, .04);
      color: #fff;
      cursor: pointer;
      transition: .2s ease;
    }

    .btn-collapse:hover {
      transform: translateY(-1px);
      background: rgba(255, 255, 255, .07);
    }

    /* ── Segunda linha de filtros ── */
    .relat-filter-row-2 {
      margin-top: 10px;
      padding-top: 10px;
      border-top: 1px solid var(--border);
    }

    /* ── Período rápido ── */
    .relat-quick-dates {
      display: flex;
      flex-wrap: wrap;
      gap: 5px;
    }

    .btn-quick-date {
      padding: 5px 13px;
      border-radius: 99px;
      border: 1px solid var(--border);
      background: transparent;
      color: var(--text-muted);
      font-size: .76rem;
      font-weight: 500;
      cursor: pointer;
      font-family: inherit;
      transition: .15s ease;
      white-space: nowrap;
    }

    .btn-quick-date:hover {
      background: var(--bg-hover);
      color: var(--text-main);
      border-color: var(--color-primary);
    }

    .btn-quick-date.active {
      background: rgba(56,189,248,.12);
      color: var(--color-primary);
      border-color: rgba(56,189,248,.4);
      font-weight: 600;
    }

    /* ── Selects extras nos filtros ── */
    .relat-filter-group select {
      min-width: 120px;
    }
    /* ══════════════════════════════════════
       FILTRO GLOBAL DASHBOARD
    ══════════════════════════════════════ */
    .dash-filter-group {
      display: flex;
      gap: 4px;
    }

    .dash-filter-btn {
      padding: 4px 14px;
      border-radius: 99px;
      border: 1px solid var(--border);
      background: transparent;
      color: var(--text-muted);
      font-size: .74rem;
      font-weight: 500;
      cursor: pointer;
      transition: .15s ease;
      white-space: nowrap;
    }

    .dash-filter-btn:hover {
      background: var(--bg-hover);
      color: var(--text-main);
    }

    .dash-filter-btn.active {
      background: rgba(56,189,248,.14);
      border-color: rgba(56,189,248,.4);
      color: var(--color-primary);
      font-weight: 600;
    }

    /* ══════════════════════════════════════
       VIEW AUDITORIA
    ══════════════════════════════════════ */
    #view-auditoria:not(.hidden) {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .audit-filter-bar {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: flex-end;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 14px 16px;
    }

    .audit-filter-bar input,
    .audit-filter-bar select {
      padding: 7px 10px;
      background: var(--bg-hover);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      color: var(--text-main);
      font-size: .82rem;
      font-family: inherit;
    }

    .audit-filter-bar input:focus,
    .audit-filter-bar select:focus {
      outline: none;
      border-color: var(--color-primary);
    }

    .audit-filter-bar input[type="text"] { flex: 1; min-width: 180px; }
    .audit-filter-bar input[type="date"] { width: 140px; }

    .audit-table-wrap {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
    }

    .audit-table {
      width: 100%;
      border-collapse: collapse;
      font-size: .82rem;
    }

    .audit-table th {
      background: var(--bg-hover);
      padding: 10px 12px;
      text-align: left;
      font-size: .72rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: var(--text-muted);
      border-bottom: 1px solid var(--border);
      white-space: nowrap;
    }

    .audit-table td {
      padding: 10px 12px;
      border-bottom: 1px solid var(--border);
      vertical-align: top;
      color: var(--text-main);
    }

    .audit-table tr:last-child td { border-bottom: none; }
    .audit-table tr:hover td      { background: var(--bg-hover); }

    .audit-ip {
      font-family: monospace;
      font-size: .75rem;
      color: var(--text-muted);
    }

    .audit-badge {
      display: inline-block;
      padding: 2px 10px;
      border-radius: 99px;
      font-size: .7rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .05em;
    }

    .audit-create { background: rgba(34,197,94,.15);  color: #22c55e; }
    .audit-update { background: rgba(56,189,248,.15); color: #38bdf8; }
    .audit-delete { background: rgba(239,68,68,.15);  color: #ef4444; }

    .audit-diff-cell { max-width: 340px; }

    .diff-row {
      display: flex;
      align-items: baseline;
      flex-wrap: wrap;
      gap: 4px;
      font-size: .76rem;
      margin-bottom: 3px;
    }

    .diff-key   { font-weight: 600; color: var(--text-muted); min-width: 90px; }
    .diff-val   { color: var(--text-main); }
    .diff-before{ color: #ef4444; text-decoration: line-through; }
    .diff-after { color: #22c55e; }
    .diff-arrow { color: var(--text-muted); }

    .diff-add .diff-val { color: #22c55e; }
    .diff-del .diff-val { color: #ef4444; }

    .loading-cell,
    .empty-cell {
      text-align: center;
      padding: 40px;
      color: var(--text-muted);
      font-size: .85rem;
    }

    .text-muted { color: var(--text-muted); }

    #auditPaginacao { padding: 12px 16px; }

    .pag-row {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .pag-info {
      flex: 1;
      text-align: center;
      font-size: .78rem;
      color: var(--text-muted);
    }

    .btn-sm {
      padding: 5px 12px;
      font-size: .78rem;
    }
  </style>
</head>

<body>
  <div class="app">

    <!-- OVERLAY MOBILE (fecha sidebar ao clicar fora) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
      <div class="brand">
        <div class="brand-icon"><i class="fas fa-globe" style="color: #9c27b0;"></i>
        </div>

        <div class="brand-text">
          <strong><a href="https://sweb.tgasistemas.com.br/" target="_blank" rel="noopener">Clientes Web</a></strong>
          <span><a href="https://web.qloudme.com.br/" target="_blank">Controle Interno</a></span>
<br>  <br>
<img alt="visitas" src="https://hits.sh/tgameajuda.com/admweb.html.svg?color=007ced&amp;label=visitas&amp;labelColor=FFFFFF&amp;logo=https%3A%2F%2Fraw.githubusercontent.com%2Fsouzaseven%2Ftgahelpme%2FDesafios%2Ficon%2520bot%2520tga.ico">

        </div>

        <!-- ✅ Botão "=" para ocultar navbar (colapsar) -->
        <button class="btn-collapse" id="btnCollapseSidebar" type="button" title="Ocultar/Exibir menu">=</button>
      </div>

      <nav class="menu">
        <button class="menu-item active" data-tab="dashboard">
          <span class="mi-ico">📊</span>
          <span class="mi-label">Dashboard</span>
        </button>

        <button class="menu-item" data-tab="servidores">
          <span class="mi-ico"><i class="fas fa-server" style="color: #f44336;"></i>
          </span>
          <span class="mi-label">Servidores</span>
        </button>

        <button class="menu-item" data-tab="logins">
          <span class="mi-ico"><i class="fas fa-user-plus" style="color: #ff9800;"></i>
          </span>
          <span class="mi-label">Empresas TGA Web</span>
        </button>

        <button class="menu-item" data-tab="mobiles">
          <span class="mi-ico"><i class="fas fa-mobile-alt" style="color: #2196f3;"></i>
          </span>
          <span class="mi-label">FV / API MOBILE</span>
        </button>

        <button class="menu-item" data-tab="whatsapp">
          <span class="mi-ico"><i class="fab fa-whatsapp" style="color: #25D366;"></i></span>
          <span class="mi-label">WhatsApp</span>
        </button>

        <button class="menu-item" data-tab="pdvoff">
          <span class="mi-ico"><i class="fas fa-cash-register" style="color: #38bdf8;"></i></span>
          <span class="mi-label">PDV OFF</span>
        </button>

        <button class="menu-item" data-tab="bi">
          <span class="mi-ico"><i class="fas fa-chart-pie" style="color: #a78bfa;"></i></span>
          <span class="mi-label">BI</span>
        </button>

        <button class="menu-item" data-tab="usuarios-web">
          <span class="mi-ico">
            <i class="fas fa-users" style="color:#38bdf8;"></i>
          </span>
          <span class="mi-label">Usuários Web</span>
        </button>

        <button class="menu-item" data-tab="relatorios">
          <span class="mi-ico">
            <i class="fas fa-chart-bar" style="color:#a78bfa;"></i>
          </span>
          <span class="mi-label">Relatórios</span>
        </button>

        <button class="menu-item" data-tab="auditoria">
          <span class="mi-ico">
            <i class="fas fa-history" style="color:#f59e0b;"></i>
          </span>
          <span class="mi-label">Auditoria</span>
        </button>

        <button class="menu-item" data-tab="exportar">
          <span class="mi-ico">
            <i class="fas fa-file-download" style="color:#22c55e;"></i>
          </span>
          <span class="mi-label">Exportar</span>
        </button>
 <br><br>
 <div class="sidebar-footer"></div>
<!-- CLIENTES WEB -->
<button
  class="menu-item"
  onclick="window.open('https://sweb.tgasistemas.com.br/', '_blank', 'noopener')"
>
  <span class="mi-ico">
    <i class="fas fa-globe" style="color:#38bdf8;"></i>
  </span>
  <span class="mi-label"> Web Clientes</span>
</button>

<!-- QLOUDME -->
<button
  class="menu-item"
  onclick="window.open('https://web.qloudme.com.br/', '_blank', 'noopener')"
>
  <span class="mi-ico">
    <img src="https://web.qloudme.com.br/templates/creative/img/favicon.ico" alt="qloudme1" style="width:16px;height:16px;vertical-align:middle;">
  </span>
  <span class="mi-label">Qloudme1</span>
</button>
<!-- QLOUDME -->
<button
  class="menu-item qloudme-btn"
  onclick="window.open('http://tga.qloudme.com.br/', '_blank', 'noopener')"
>
  <span class="mi-ico">
    <img src="https://tga.qloudme.com.br/static/favicon.ico" alt="qloudme2" style="width:16px;height:16px;vertical-align:middle;">
  </span>
  <span class="mi-label">Qloudme 2</span>
</button>
<!-- QLOUDME MONITORAMENTO -->
<button
  class="menu-item"
  onclick="window.open('https://monitoramento.qloudme.com.br/public/login.htm?loginurl=%2Fwelcome.htm&errorid=0', '_blank', 'noopener')"
>
  <span class="mi-ico">
    <img src="https://monitoramento.qloudme.com.br/favicon.ico" alt="monitor" style="width:16px;height:16px;vertical-align:middle;">
  </span>
  <span class="mi-label">QLOUDME MONITORAMENTO</span>
</button>
 </nav>
      <div class="sidebar-footer">
        <div class="hint">
          <span class="dot"></span>
          <span>v2026.01.31_100 •</span>
        </div>
        <button class="btn ghost" id="btnToggleTheme">🌙 Alternar tema</button>

      </div>
    </aside>

    <!-- MAIN -->
    <main class="main">

      <!-- TOPBAR -->
      <header class="topbar">
        <button class="icon-btn" id="btnToggleSidebar">☰</button>

        <div class="topbar-title">
          <h1 id="pageTitle">Dashboard</h1>
          <p id="pageSubtitle">Visão geral e saúde dos acessos</p>


        </div>

        <div class="topbar-actions">
          <!-- 🔐 Timer de expiração da sessão -->
          <span
            id="tokenExpireTimer"
            class="refresh-timer"
            title="Ao expirar a sessão será encerrada automaticamente e será necessário realizar login novamente."
          >
            🔐 Expira em 02:00:00
          </span>

          <span id="autoRefreshTimer" class="refresh-timer">
            ⏳ Atualiza em 30:00
          </span>
          <button class="btn" id="btnRefresh">↻ Atualizar</button>
          <button class="btn danger" id="btnLogout" title="Sair do sistema">
            <i class="fas fa-sign-out-alt"></i> Sair
          </button>
        </div>
      </header>

      <!-- CONTENT -->
      <section class="content">
        <!-- DASHBOARD -->
        <div class="view" id="view-dashboard">

          <!-- KPIs -->
          <div class="grid kpis">

            <div class="card kpi kpi--orange" data-goto-tab="logins">
              <div class="kpi-icon-wrap orange">
                <i class="fas fa-user-plus" style="color:#ff9800;"></i>
              </div>
              <div class="kpi-label" id="kpiLoginsLabel">Empresas Ativas</div>
              <div class="kpi-value" id="kpiLoginsAtivos">—</div>
              <div class="kpi-sub" id="kpiLoginsTotal">—</div>
              <div class="kpi-delta" id="deltaLogins"></div>
            </div>

            <!-- CARD USUÁRIOS WEB -->
            <div class="card card-kpi users-web kpi--green" data-goto-tab="usuarios-web">

              <!-- HEADER (ícone + título juntos) -->
              <div class="card-kpi-header">
                <span class="card-kpi-icon">👥</span>
                <span class="card-kpi-title" id="kpiUsuariosLabel">Usuários Web</span>
              </div>

              <!-- TOTAL DE USUÁRIOS -->
              <div class="card-kpi-value" id="kpiUsuariosTotal">
                0
              </div>

              <!-- DETALHES -->
              <div class="card-kpi-sub users-web-sub">
                <span>
                  📄 <strong id="kpiUsuariosCadastros">0</strong> cadastros
                </span>
                <span>•</span>
                <span>
                  🟢 <strong id="kpiUsuariosAtivos">0</strong> ativos
                </span>
                <span> | </span>
                <span>
                  🔴 <strong id="kpiUsuariosInativos">0</strong> inativos
                </span>
              </div>
              <div class="kpi-delta" id="deltaUsuarios"></div>

            </div>

            <div class="card kpi kpi--blue kpi-split" data-goto-tab="mobiles">
              <div class="kpi-split-col">
                <div class="kpi-icon-wrap blue">
                  <i class="fas fa-mobile-alt" style="color:#2196f3;"></i>
                </div>
                <div class="kpi-label">FV Smart Client</div>
                <div class="kpi-value" id="kpimobilesOn">—</div>
                <div class="kpi-sub" id="kpiConexoesTotal"></div>
                <div class="kpi-delta" id="deltaMobileOn"></div>
              </div>

              <div class="kpi-split-divider"></div>

              <div class="kpi-split-col">
                <div class="kpi-icon-wrap sky">
                  <i class="fas fa-exchange-alt" style="color:#38bdf8;"></i>
                </div>
                <div class="kpi-label">API Força de Vendas</div>
                <div class="kpi-value" id="kpiApis">—</div>
                <div class="kpi-sub" id="kpiApisSub"></div>
                <div class="kpi-delta" id="deltaApi"></div>
              </div>
            </div>

            <div class="card kpi kpi--whatsapp" data-goto-tab="whatsapp">
              <div class="kpi-icon-wrap whatsapp">
                <i class="fab fa-whatsapp" style="color:#25D366;"></i>
              </div>
              <div class="kpi-label">API WhatsApp</div>
              <div class="kpi-value" id="kpiMobile">—</div>
              <div class="kpi-sub" id="kpiMobileSub"></div>
              <div class="kpi-delta" id="deltaWhatsapp"></div>
            </div>

            <div class="card kpi kpi--sky" data-goto-tab="pdvoff">
              <div class="kpi-icon-wrap sky">
                <i class="fas fa-cash-register" style="color:#38bdf8;"></i>
              </div>
              <div class="kpi-label">PDV OFF</div>
              <div class="kpi-value" id="kpiPdvOff">—</div>
              <div class="kpi-sub" id="kpiPdvOffPct"></div>
              <div class="kpi-delta" id="deltaPdvOff"></div>
            </div>

            <div class="card kpi kpi--purple" data-goto-tab="bi">
              <div class="kpi-icon-wrap purple">
                <i class="fas fa-chart-pie" style="color:#a78bfa;"></i>
              </div>
              <div class="kpi-label">BI</div>
              <div class="kpi-value" id="kpiBi">—</div>
              <div class="kpi-sub" id="kpiBiPct"></div>
              <div class="kpi-delta" id="deltaBi"></div>
            </div>

          </div>

          <!-- RESUMO DE VERSÕES -->
          <div class="card versoes-resumo-card" style="margin-top:14px;">
            <div class="versoes-resumo-header">
              <span>📦 Resumo de Versões — Empresas TGA Web</span>
              <div class="dash-filter-group">
                <button class="dash-filter-btn active" data-filter="ativos">🟢 Ativos</button>
                <button class="dash-filter-btn" data-filter="inativos">🔴 Inativos</button>
                <button class="dash-filter-btn" data-filter="todos">📋 Todos</button>
              </div>
            </div>
            <div id="kpiVersoesLista" class="versoes-lista">
              <span style="opacity:.5">Carregando...</span>
            </div>
          </div>

          <!-- GRÁFICOS -->
          <div class="charts-grid" style="margin-top:14px;">
            <div class="card chart-card">
              <div class="chart-title">
                <i class="fas fa-chart-line" style="color:#38bdf8"></i>
                Cadastros
              </div>
              <div class="chart-filter-bar">
                <div class="chart-period-btns">
                  <button class="chart-period-btn active" data-periodo="7d">7D</button>
                  <button class="chart-period-btn" data-periodo="30d">30D</button>
                  <button class="chart-period-btn" data-periodo="3m">3M</button>
                  <button class="chart-period-btn" data-periodo="6m">6M</button>
                  <button class="chart-period-btn" data-periodo="12m">12M</button>
                  <button class="chart-period-btn" data-periodo="24m">2A</button>
                  <button class="chart-period-btn" data-periodo="all">Tudo</button>
                </div>
                <div class="chart-period-btns" style="margin-left:6px;">
                  <button class="chart-period-btn" data-tipo="cadastros">Cadastros</button>
                  <button class="chart-period-btn" data-tipo="alteracoes">Alterações</button>
                  <button class="chart-period-btn active" data-tipo="ambos">Ambos</button>
                </div>
                <select id="chartCadastrosAgrup" class="chart-agrup-select">
                  <option value="dia" selected>Por dia</option>
                  <option value="mes">Por mês</option>
                  <option value="ano">Por ano</option>
                </select>
              </div>
              <div class="chart-wrap">
                <canvas id="chartCadastrosMes"></canvas>
              </div>
            </div>
            <div class="card chart-card">
              <div class="chart-title">
                <i class="fas fa-circle-notch" style="color:#a78bfa"></i>
                Distribuição por versão
              </div>
              <div class="chart-wrap">
                <canvas id="chartVersoes"></canvas>
              </div>
            </div>
            <div class="card chart-card">
              <div class="chart-title">
                <i class="fas fa-chart-bar" style="color:#22c55e"></i>
                Integrações ativas
              </div>
              <div class="chart-wrap">
                <canvas id="chartModulos"></canvas>
              </div>
            </div>
            <div class="card chart-card">
              <div class="chart-title">
                <i class="fas fa-map-marker-alt" style="color:#f59e0b"></i>
                Distribuição por região
              </div>
              <div class="chart-wrap">
                <canvas id="chartRegioes"></canvas>
              </div>
            </div>

          </div>

          <div class="charts-duo charts-duo--cidades">
            <!-- DISTRIBUIÇÃO POR CIDADE -->
            <div class="card chart-card">
              <div class="chart-title">
                <i class="fas fa-city" style="color:#38bdf8"></i>
                Distribuição por cidade
              </div>
              <div class="chart-filter-bar">
                <div class="chart-period-btns" id="filtroCidadesEstados"></div>
              </div>
              <div class="chart-wrap chart-wrap--legend-html">
                <div class="chart-wrap-canvas">
                  <canvas id="chartCidades"></canvas>
                </div>
                <div id="legendCidades" class="chart-legend-html"></div>
              </div>
            </div>

            <!-- DISTRIBUIÇÃO POR ESTADO -->
            <div class="card chart-card">
              <div class="chart-title">
                <i class="fas fa-flag" style="color:#22c55e"></i>
                Distribuição por estado
              </div>
              <div class="chart-wrap">
                <canvas id="chartEstados"></canvas>
              </div>
            </div>
          </div>

          <!-- CIDADES DO ESTADO SELECIONADO (aparece ao clicar num estado no filtro acima) -->
          <div class="card chart-card" id="cardCidadesDoEstado" style="display:none; margin-top:14px">
            <div class="chart-title">
              <i class="fas fa-map-pin" style="color:#f59e0b"></i>
              <span id="tituloCidadesDoEstado">Cidades do estado</span>
            </div>
            <div class="chart-wrap chart-wrap--legend-html">
              <div class="chart-wrap-canvas">
                <canvas id="chartCidadesDoEstado"></canvas>
              </div>
              <div id="legendCidadesDoEstado" class="chart-legend-html"></div>
            </div>
          </div>

          <div class="charts-duo">
            <!-- EVOLUÇÃO DE INTEGRAÇÕES -->
            <div class="card chart-card">
              <div class="chart-title">
                <i class="fas fa-chart-area" style="color:#22c55e"></i>
                Evolução de Integrações por mês
              </div>
              <div class="chart-filter-bar">
                <div class="chart-period-btns">
                  <button class="chart-period-btn" data-integ-periodo="3m">3M</button>
                  <button class="chart-period-btn" data-integ-periodo="6m">6M</button>
                  <button class="chart-period-btn active" data-integ-periodo="12m">12M</button>
                  <button class="chart-period-btn" data-integ-periodo="24m">2A</button>
                  <button class="chart-period-btn" data-integ-periodo="all">Tudo</button>
                </div>
              </div>
              <div class="chart-wrap chart-wrap--tall">
                <canvas id="chartIntegracoesEvolucao"></canvas>
              </div>
            </div>

            <!-- USUÁRIOS ADICIONADOS / REMOVIDOS -->
            <div class="card chart-card">
              <div class="chart-title">
                <i class="fas fa-users" style="color:#38bdf8"></i>
                Usuários Adicionados / Removidos por mês
              </div>
              <div class="chart-filter-bar">
                <div class="chart-period-btns">
                  <button class="chart-period-btn" data-usuarios-periodo="3m">3M</button>
                  <button class="chart-period-btn" data-usuarios-periodo="6m">6M</button>
                  <button class="chart-period-btn active" data-usuarios-periodo="12m">12M</button>
                  <button class="chart-period-btn" data-usuarios-periodo="24m">2A</button>
                  <button class="chart-period-btn" data-usuarios-periodo="all">Tudo</button>
                </div>
              </div>
              <div class="chart-wrap chart-wrap--tall">
                <canvas id="chartUsuarios"></canvas>
              </div>
            </div>

            <!-- ATIVIDADE POR DIA DA SEMANA -->
            <div class="card chart-card">
              <div class="chart-title">
                <i class="fas fa-calendar-week" style="color:#f59e0b"></i>
                Atividade por Dia da Semana
              </div>
              <div class="chart-filter-bar">
                <div class="chart-period-btns">
                  <button class="chart-period-btn" data-semana-periodo="30d">30D</button>
                  <button class="chart-period-btn" data-semana-periodo="3m">3M</button>
                  <button class="chart-period-btn" data-semana-periodo="6m">6M</button>
                  <button class="chart-period-btn active" data-semana-periodo="12m">12M</button>
                  <button class="chart-period-btn" data-semana-periodo="all">Tudo</button>
                </div>
                <select id="chartSemanaTipo" class="chart-agrup-select">
                  <option value="ambos">Cadastros + Alterações</option>
                  <option value="cadastros">Só Cadastros</option>
                  <option value="alteracoes">Só Alterações</option>
                </select>
              </div>
              <div class="chart-wrap chart-wrap--tall">
                <canvas id="chartAtividadeSemana"></canvas>
              </div>
            </div>

            <!-- CALENDÁRIO DE ATIVIDADE (MENSAL) -->
            <div class="card chart-card">
              <div class="chart-title">
                <i class="fas fa-calendar-alt" style="color:#22c55e"></i>
                Calendário de Atividade
              </div>
              <div class="cal-month-nav-bar">
                <button class="cal-nav-arrow" id="calNavPrev" title="Mês anterior">
                  <i class="fas fa-chevron-left"></i>
                </button>
                <span id="calNavLabel" class="cal-nav-label">—</span>
                <button class="cal-nav-arrow" id="calNavNext" title="Próximo mês">
                  <i class="fas fa-chevron-right"></i>
                </button>
              </div>
              <div id="calHeatmapWrap" class="cal-month-outer"></div>
              <div class="cal-legend">
                <span class="cal-leg-item"><span class="cal-dot cal-dot--create"></span> Cadastros</span>
                <span class="cal-leg-item"><span class="cal-dot cal-dot--update"></span> Alterações</span>
              </div>
            </div>

          </div>

          <!-- TABELAS -->
          <div id="dashTabelas">
          <div class="upd-section-head" style="margin-top:0">
            <i class="fas fa-plus-circle"></i>
            <h3>Últimos Cadastros</h3>
          </div>
          <div class="grid dash-tables-grid">

            <!-- Últimos Logins -->
            <div class="card">
              <div class="upd-card-head">
                <span class="upd-icon" style="--upd-color:#ff9800"><i class="fas fa-building"></i></span>
                <h3>Últimas Empresas Cadastradas</h3>
              </div>
              <div class="table-wrap">
                <table class="table" id="tblDashLogins">
                  <thead>
                    <tr>
                      <th>Código</th>
                      <th>Cliente / CNPJ</th>
                      <th>Versão</th>
                      <th class="text-center">Usuários</th>
                      <th class="text-center">Integrações</th>
                      <th>Status</th>
                      <th>Cadastrado</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>

            <!-- ÚLTIMAS EMPRESAS (USUÁRIOS WEB) -->
            <div class="card">
              <div class="upd-card-head">
                <span class="upd-icon" style="--upd-color:#38bdf8"><i class="fas fa-users"></i></span>
                <h3>Últimos Usuários Cadastrados</h3>
              </div>
              <div class="table-wrap">
                <table class="table" id="tblDashUsuariosWeb">
                  <thead>
                    <tr>
                      <th>Empresa / CNPJ</th>
                      <th>Código</th>
                      <th class="text-center">Qtd Usuários</th>
                      <th>Status</th>
                      <th>Cadastrado em</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>

            <!-- Últimos Mobile -->
            <div class="card">
              <div class="upd-card-head">
                <span class="upd-icon" style="--upd-color:#2196f3"><i class="fas fa-mobile-alt"></i></span>
                <h3>Últimos Mobile Cadastrados</h3>
              </div>
              <div class="table-wrap">
                <table class="table" id="tblDashConexoes">
                  <thead>
                    <tr>
                      <th>Código</th>
                      <th>Cliente / CNPJ</th>
                      <th>Servidor</th>
                      <th>Tipo</th>
                      <th>Cadastrado</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>

            <!-- Últimos WhatsApp -->
            <div class="card">
              <div class="upd-card-head">
                <span class="upd-icon" style="--upd-color:#25D366"><i class="fab fa-whatsapp"></i></span>
                <h3>Últimos WhatsApp Cadastrados</h3>
              </div>
              <div class="table-wrap">
                <table class="table" id="tblDashWhats">
                  <thead>
                    <tr>
                      <th>Código</th>
                      <th>Cliente / CNPJ</th>
                      <th>Servidor</th>
                      <th>Cadastrado</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>

            <!-- Últimos PDV OFF -->
            <div class="card">
              <div class="upd-card-head">
                <span class="upd-icon" style="--upd-color:#38bdf8"><i class="fas fa-cash-register"></i></span>
                <h3>Últimos PDV OFF Cadastrados</h3>
              </div>
              <div class="table-wrap">
                <table class="table" id="tblDashPdvOff">
                  <thead>
                    <tr>
                      <th>Código</th>
                      <th>Cliente / CNPJ</th>
                      <th>Servidor</th>
                      <th>Cadastrado</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>

            <!-- Últimos BI -->
            <div class="card">
              <div class="upd-card-head">
                <span class="upd-icon" style="--upd-color:#a78bfa"><i class="fas fa-chart-pie"></i></span>
                <h3>Últimos BI Cadastrados</h3>
              </div>
              <div class="table-wrap">
                <table class="table" id="tblDashBi">
                  <thead>
                    <tr>
                      <th>Código</th>
                      <th>Cliente / CNPJ</th>
                      <th>Cadastrado</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>

          </div>

          <!-- CARDS — ÚLTIMAS ATUALIZAÇÕES -->
          <div class="upd-section-head">
            <i class="fas fa-history"></i>
            <h3>Últimas Atualizações</h3>
          </div>
          <div class="grid upd-grid">

            <div class="card upd-card">
              <div class="upd-card-head">
                <span class="upd-icon" style="--upd-color:#ff9800"><i class="fas fa-building"></i></span>
                <h3>Empresas</h3>
              </div>
              <div id="cardUpdLogins" class="upd-list"><div class="upd-empty"><i class="fas fa-inbox"></i> Nenhuma atualização</div></div>
            </div>

            <div class="card upd-card">
              <div class="upd-card-head">
                <span class="upd-icon" style="--upd-color:#38bdf8"><i class="fas fa-users"></i></span>
                <h3>Usuários Web</h3>
              </div>
              <div id="cardUpdUsuarios" class="upd-list"><div class="upd-empty"><i class="fas fa-inbox"></i> Nenhuma atualização</div></div>
            </div>

            <div class="card upd-card">
              <div class="upd-card-head">
                <span class="upd-icon" style="--upd-color:#2196f3"><i class="fas fa-mobile-alt"></i></span>
                <h3>Mobile</h3>
              </div>
              <div id="cardUpdMobile" class="upd-list"><div class="upd-empty"><i class="fas fa-inbox"></i> Nenhuma atualização</div></div>
            </div>

            <div class="card upd-card">
              <div class="upd-card-head">
                <span class="upd-icon" style="--upd-color:#25D366"><i class="fab fa-whatsapp"></i></span>
                <h3>WhatsApp</h3>
              </div>
              <div id="cardUpdWhatsapp" class="upd-list"><div class="upd-empty"><i class="fas fa-inbox"></i> Nenhuma atualização</div></div>
            </div>

            <div class="card upd-card">
              <div class="upd-card-head">
                <span class="upd-icon" style="--upd-color:#38bdf8"><i class="fas fa-cash-register"></i></span>
                <h3>PDV OFF</h3>
              </div>
              <div id="cardUpdPdvOff" class="upd-list"><div class="upd-empty"><i class="fas fa-inbox"></i> Nenhuma atualização</div></div>
            </div>

            <div class="card upd-card">
              <div class="upd-card-head">
                <span class="upd-icon" style="--upd-color:#a78bfa"><i class="fas fa-chart-pie"></i></span>
                <h3>BI</h3>
              </div>
              <div id="cardUpdBi" class="upd-list"><div class="upd-empty"><i class="fas fa-inbox"></i> Nenhuma atualização</div></div>
            </div>

          </div>
          </div><!-- /#dashTabelas -->
        </div>

        <!-- LOGINS -->
        <div class="view hidden" id="view-logins">
          <div class="card" id="loginsCard">
            <div class="login-stats">
              <span id="statTotal">Total: 0</span>
              <span id="statAtivos">Ativos: 0</span>
              <span id="statInativos">Inativos: 0</span>
              <span id="statExe">Com EXE: 0</span>
              <span id="statMobileFv">Com Mobile FV (Smart Client): 0</span>
              <span id="statMobileForca">Com Mobile Força de Vendas (API Web): 0</span>
              <span id="statWhatsapp">Com WhatsApp: 0</span>
              <span id="statPdvoff">Com PDV OFF: 0</span>
              <span id="statBi">Com BI: 0</span>
            </div>


            <div class="toolbar">
              <div class="search">
                <input id="loginSearch" placeholder="Buscar por código, nome, caminho, versão">
              </div>

              <div class="filters">
                <div class="ms-wrapper" id="loginStatus"></div>
                <div class="ms-wrapper" id="loginExe"></div>
                <div class="ms-wrapper" id="loginVersao"></div>
                <div class="ms-wrapper" id="loginRegiao"></div>
                <div class="ms-wrapper" id="loginCidade"></div>
                <div class="ms-wrapper" id="loginEstado"></div>
                <div class="ms-wrapper" id="loginIntegracao"></div>

                <input
                  id="loginQtdUsuarios"
                  type="number"
                  min="1"
                  placeholder="Mín. usuários"
                  title="Filtrar empresas com no mínimo X usuários vinculados"
                />

                <select id="loginLimit" class="select">
                  <option value="5">5</option>
                  <option value="10">10</option>
                  <option value="20">20</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                  <option value="999999">Tudo</option>
                </select>

                <button class="btn btn-exp-inline" onclick="exportarCSV('logins')" title="Exportar CSV com filtros atuais">
                  <i class="fas fa-download"></i> CSV
                </button>
                <button class="btn primary" id="btnNovoLogin">
                  ➕ Novo Login
                </button>
              </div>
            </div>

            <div class="table-wrap">
              <table class="table" id="tblLogins">
                <thead>
                  <tr>
                    <th data-sort="codigo_cliente">Código</th>
                    <th data-sort="nome_cliente">Cliente</th>
                    <th data-sort="cnpj">CNPJ</th>
                    <th data-sort="caminho_acesso">Caminho</th>
                    <th data-sort="versao_padrao">Versão</th>
                    <th data-sort="regiao">Região</th>
                    <th>Usuários / Obs.</th>
                    <th data-sort="possui_exe">TEM_EXE</th>
                    <th>Integrações</th>
                    <th data-sort="status">Status</th>
                    <th data-sort="atualizado_em">Atualizado em</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>

            <div class="pager">
              <span id="loginInfo" class="muted"></span>
              <div>
                <button class="btn ghost" id="loginPrev">←</button>
                <span class="badge" id="loginPage">1</span>
                <button class="btn ghost" id="loginNext">→</button>
              </div>
            </div>

          </div>
        </div>


        <!-- MOBILE -->
        <div class="view hidden" id="view-mobiles">
          <div class="card">
            <!-- MINI RESUMO MOBILE -->
            <div class="mini-resumo-mobile" id="miniResumoMobile" style="grid-template-columns:repeat(4,1fr)">
              <div class="mini-item">
                <span>Total</span>
                <strong id="mobTotal">0</strong>
              </div>

              <div class="mini-item">
                <span>API Backoffice</span>
                <strong id="mobApi">0</strong>
              </div>

              <div class="mini-item">
                <span>FV Smart</span>
                <strong id="mobFv">0</strong>
              </div>

              <div class="mini-item clickable" id="btnAlterarVersaoApiFv" title="Clique para alterar a versão padrão API FV">
                <span>Versão API FV</span>
                <strong id="mobApiFvVersao">—</strong>
              </div>
            </div>

            <div class="toolbar">
              <div class="search">
                <input id="mobileSearch" placeholder="Buscar por código, cliente, servidor ou CNPJ">
              </div>

              <div class="filters">
                <div class="ms-wrapper" id="mobileTipo"></div>
                <div class="ms-wrapper" id="mobileVersaoApp"></div>
                <div class="ms-wrapper" id="mobileRegiao"></div>
                <div class="ms-wrapper" id="mobileVersaoEmp"></div>

                <select id="mobileLimit" class="select">
                  <option value="5">5</option>
                  <option value="10">10</option>
                  <option value="20">20</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                  <option value="999999">Tudo</option>
                </select>

                <button class="btn btn-exp-inline" onclick="exportarCSV('mobile')" title="Exportar CSV com filtros atuais">
                  <i class="fas fa-download"></i> CSV
                </button>
                <button class="btn primary" id="btnNovoMobile">➕ Novo Mobile</button>
              </div>
            </div>

            <div class="table-wrap">
              <table class="table" id="tblMobile">
                <thead>
  <tr>
    <th data-order="cod_cliente">Código</th>
    <th data-order="cliente">Cliente</th>
    <th data-order="acesso_server">Servidor</th>
    <th data-order="porta">Porta</th>
    <th data-order="tipo_acesso">Tipo</th>
    <th data-order="versao">Versão EXE</th>
    <th>Região</th>
    <th>Versão Emp.</th>
    <th>Caminho</th>
    <th data-order="observacao">Observação</th>
    <th>Empresa</th>
    <th>Ações</th>
  </tr>
</thead>

                <tbody></tbody>
              </table>
            </div>

            <div class="pager">
              <span id="mobileInfo" class="muted"></span>
              <div>
                <button class="btn ghost" id="mobilePrev">←</button>
                <span class="badge" id="mobilePage">1</span>
                <button class="btn ghost" id="mobileNext">→</button>
              </div>
            </div>
          </div>
        </div>

        <!-- SERVIDORES -->
        <div class="view hidden" id="view-servidores">
          <div class="srv-toolbar">
            <span class="srv-toolbar-hint">
              <i class="fas fa-grip-vertical"></i> Arraste os cards para reordenar
            </span>
            <div class="srv-layout-toggle">
              <button class="srv-layout-btn" id="btnSrvGrid" data-layout="grid" title="Grade (lado a lado)">
                <i class="fas fa-th-large"></i>
              </button>
              <button class="srv-layout-btn" id="btnSrvList" data-layout="list" title="Lista (um abaixo do outro)">
                <i class="fas fa-bars"></i>
              </button>
            </div>
            <button id="btnNovoServidor" class="btn primary">➕ Novo Card</button>
          </div>
          <div class="server-sections" id="servidoresGrid"></div>
        </div>

        <!-- WHATSAPP -->
        <div class="view hidden" id="view-whatsapp">
          <div class="card">

            <div class="mini-resumo-mobile" id="miniResumoWhats">
              <div class="mini-item clickable" id="btnAlterarVersaoWhats" title="Clique para alterar a versão padrão WhatsApp">
                <span>Versão padrão</span>
                <strong id="whatsVersaoPadraoDisplay">—</strong>
              </div>
            </div>

            <div class="toolbar">
              <div class="search">
                <input id="whatsSearch" placeholder="Buscar por código, cliente, servidor ou CNPJ">
              </div>

              <div class="filters">
                <div class="ms-wrapper" id="whatsVersaoApp"></div>
                <div class="ms-wrapper" id="whatsRegiao"></div>
                <div class="ms-wrapper" id="whatsVersaoEmp"></div>

                <select id="whatsLimit" class="select">
                  <option value="5">5</option>
                  <option value="10">10</option>
                  <option value="20">20</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                  <option value="999999">Tudo</option>
                </select>
                <button class="btn btn-exp-inline" onclick="exportarCSV('whatsapp')" title="Exportar CSV com filtros atuais">
                  <i class="fas fa-download"></i> CSV
                </button>
                <button class="btn primary" id="btnNovoWhats">➕ Novo WhatsApp</button>
              </div>
            </div>

            <div class="table-wrap">
              <table class="table" id="tblWhats">
                <thead>
                  <tr>
                  <th data-order="cod_cliente">Código</th>
            <th data-order="cliente">Cliente</th>
            <th data-order="acesso_server">Servidor</th>
                    <th data-order="versao">Versão EXE</th>
                    <th>Região</th>
                    <th>Versão Emp.</th>
                    <th>Caminho</th>
                    <th>Observação</th>
                    <th>Empresa</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>

            <div class="pager">
              <span id="whatsInfo" class="muted"></span>
              <div>
                <button class="btn ghost" id="whatsPrev">←</button>
                <span class="badge" id="whatsPage">1</span>
                <button class="btn ghost" id="whatsNext">→</button>
              </div>
            </div>

          </div>
        </div>

        <!-- PDV OFF -->
        <div class="view hidden" id="view-pdvoff">
          <div class="card">

            <div class="toolbar">
              <div class="search">
                <input id="pdvOffSearch" placeholder="Buscar por código, cliente, servidor">
              </div>

              <div class="filters">
                <div class="ms-wrapper" id="pdvOffRegiao"></div>
                <div class="ms-wrapper" id="pdvOffVersaoEmp"></div>

                <select id="pdvOffLimit" class="select">
                  <option value="5">5</option>
                  <option value="10">10</option>
                  <option value="20">20</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                  <option value="999999">Tudo</option>
                </select>

                <button class="btn btn-exp-inline" onclick="exportarCSV('pdvoff')" title="Exportar CSV com filtros atuais">
                  <i class="fas fa-download"></i> CSV
                </button>
                <button class="btn primary" id="btnNovoPdvOff">➕ Novo PDV OFF</button>
              </div>
            </div>

            <div class="table-wrap">
              <table class="table" id="tblPdvOff">
                <thead>
                  <tr>
                   <th data-order="cod_cliente">Código</th>
<th data-order="cliente">Cliente</th>
<th data-order="acesso_server">Servidor</th>
<th data-order="caixas">Caixas</th>
<th>Região</th>
<th>Versão</th>
<th>Caminho</th>
<th data-order="observacao">Observação</th>
<th>Empresa</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>

            <div class="pager">
              <span id="pdvOffInfo" class="muted"></span>
              <div>
                <button class="btn ghost" id="pdvOffPrev">←</button>
                <span class="badge" id="pdvOffPage">1</span>
                <button class="btn ghost" id="pdvOffNext">→</button>
              </div>
            </div>

          </div>
        </div>
        <!-- BI -->
        <div class="view hidden" id="view-bi">
          <div class="card">

            <div class="toolbar">
              <div class="search">
                <input id="biSearch" placeholder="Buscar por código, cliente, servidor">
              </div>

              <div class="filters">
                <div class="ms-wrapper" id="biRegiao"></div>
                <div class="ms-wrapper" id="biVersaoEmp"></div>

                <select id="biLimit" class="select">
                  <option value="5">5</option>
                  <option value="10">10</option>
                  <option value="20">20</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                  <option value="999999">Tudo</option>
                </select>

                <button class="btn btn-exp-inline" onclick="exportarCSV('bi')" title="Exportar CSV com filtros atuais">
                  <i class="fas fa-download"></i> CSV
                </button>
                <button class="btn primary" id="btnNovoBi">➕ Novo BI</button>
              </div>
            </div>

            <div class="table-wrap">
              <table class="table" id="tblBi">
                <thead>
                  <tr>
                    <th data-order="cod_cliente">Código</th>
                    <th data-order="cliente">Cliente</th>
                    <th data-order="acesso_server">Servidor</th>
                    <th>Região</th>
                    <th>Versão</th>
                    <th>Empresa</th>
                    <th data-order="atualizado_em">Atualizado</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>

            <div class="pager">
              <span id="biInfo" class="muted"></span>
              <div>
                <button class="btn ghost" id="biPrev">←</button>
                <span class="badge" id="biPage">1</span>
                <button class="btn ghost" id="biNext">→</button>
              </div>
            </div>

          </div>
        </div>

        <!-- USUÁRIOS WEB -->
        <div class="view hidden" id="view-usuarios-web">
          <div class="card">

            <!-- HEADER -->
            <div class="card-header">
              <h2>Usuários Web</h2>
            </div>

            <!-- MINI CARDS -->
            <div class="usuarios-web-stats">
              <div class="usuarios-web-stat total">
                <div>
                  <div class="label">Empresas</div>
                  <div class="value" id="uwTotal">0</div>
                </div>
              </div>

              <div class="usuarios-web-stat ativo">
                <div>
                  <div class="label">Ativas</div>
                  <div class="value" id="uwAtivos">0</div>
                </div>
              </div>

              <div class="usuarios-web-stat inativo">
                <div>
                  <div class="label">Inativas</div>
                  <div class="value" id="uwInativos">0</div>
                </div>
              </div>

              <div class="usuarios-web-stat users">
                <div>
                  <div class="label">Total Usuários</div>
                  <div class="value" id="uwUsuarios">0</div>
                </div>
              </div>
            </div>

            <!-- FILTROS + AÇÃO -->
            <div class="filters-bar">
              <div class="filters filters-inline">

                <input type="text" id="uwSearch" placeholder="Buscar por empresa, código, qtd usuários ou CNPJ" />

                <input type="number" id="uwMinUsers" placeholder="Qtd usuários" min="0" />

                <div class="ms-wrapper" id="uwStatus"></div>

                <select id="uwLimit">
                  <option value="5">5</option>
                  <option value="10">10</option>
                  <option value="20">20</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                  <option value="all">Todos</option>
                </select>

                <button class="btn btn-exp-inline" onclick="exportarCSV('usuarios-web')" title="Exportar CSV com filtros atuais">
                  <i class="fas fa-download"></i> CSV
                </button>
                <button class="btn primary" id="btnNovoUsuarioWeb">
                  ➕ Novo User
                </button>

              </div>
            </div>

            <!-- TABELA -->
            <div class="table-responsive">
              <table id="tblUsuariosWeb">
                <thead>
                  <tr>
                    <th data-order="codigo_empresa">Código</th>
                    <th data-order="nome_empresa">Empresa</th>
                    <th data-order="qtd_usuarios" class="sortable">
                      Qtd Usuários
                    </th>
                    <th data-order="observacao">Observação</th>
                    <th data-order="status">Status</th>
                    <th data-order="atualizado_em">Atualizado em</th>
                    <th style="width:120px">Ações</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>

            <!-- PAGINAÇÃO -->
            <div class="pagination-wrap">
              <div id="uwPagination" class="pagination"></div>
            </div>

          </div>
        </div>




        <!-- RELATÓRIOS -->
        <div class="view hidden" id="view-relatorios">

          <!-- ── Painel de Filtros ── -->
          <div class="relat-filter-panel">

            <!-- Linha 1: busca + status + versão + campo de data -->
            <div class="relat-filter-row">

              <div class="relat-filter-group relat-search-group">
                <label><i class="fas fa-search"></i> Busca</label>
                <div class="relat-search-wrap">
                  <i class="fas fa-search"></i>
                  <input type="text" id="relatSearch" placeholder="Código ou nome da empresa…">
                </div>
              </div>

              <div class="relat-filter-group">
                <label><i class="fas fa-toggle-on"></i> Status</label>
                <select id="relatStatus">
                  <option value="">Todos</option>
                  <option value="ATIVO">Ativo</option>
                  <option value="INATIVO">Inativo</option>
                </select>
              </div>

              <div class="relat-filter-group">
                <label><i class="fas fa-code-branch"></i> Versão</label>
                <select id="relatVersao">
                  <option value="">Todas</option>
                </select>
              </div>

              <div class="relat-filter-group">
                <label><i class="fas fa-calendar"></i> Campo de data</label>
                <select id="relatDateTipo">
                  <option value="criado_em">Criado em</option>
                  <option value="atualizado_em">Alterado em</option>
                  <option value="ambos" selected>Criação ou alteração</option>
                </select>
              </div>

            </div>

            <!-- Linha 2: período rápido + datas manuais + ações -->
            <div class="relat-filter-row relat-filter-row-2">

              <div class="relat-filter-group">
                <label><i class="fas fa-bolt"></i> Período rápido</label>
                <div class="relat-quick-dates">
                  <button class="btn-quick-date" data-quick="hoje" type="button">Hoje</button>
                  <button class="btn-quick-date" data-quick="ontem" type="button">Ontem</button>
                  <button class="btn-quick-date" data-quick="7d" type="button">7 dias</button>
                  <button class="btn-quick-date" data-quick="30d" type="button">30 dias</button>
                  <button class="btn-quick-date" data-quick="mes" type="button">Este mês</button>
                  <button class="btn-quick-date" data-quick="ano" type="button">Este ano</button>
                </div>
              </div>

              <div class="relat-filter-group">
                <label><i class="fas fa-calendar-alt"></i> Período manual</label>
                <div class="relat-date-range">
                  <input type="date" id="relatDateIni" title="Data inicial">
                  <span class="relat-date-sep">—</span>
                  <input type="date" id="relatDateFim" title="Data final">
                </div>
              </div>

              <div class="relat-filter-btns">
                <button class="btn primary" id="btnRelatFiltrar">
                  <i class="fas fa-search"></i> Aplicar
                </button>
                <button class="btn ghost" id="btnRelatLimpar" style="display:none">
                  <i class="fas fa-times"></i> Limpar
                </button>
              </div>

            </div>

            <!-- Badge de filtros ativos -->
            <div id="relatFiltroAtivoBadge"></div>
          </div>

          <!-- ── Sub-navegação ── -->
          <div class="relat-subnav">
            <button class="relat-tab active" data-relat="recentes">
              <i class="fas fa-calendar-alt"></i> Recentes
            </button>
            <button class="relat-tab" data-relat="inativos">
              <i class="fas fa-times-circle"></i> Inativos
            </button>
            <button class="relat-tab" data-relat="sem_integracao">
              <i class="fas fa-unlink"></i> Sem Integração
            </button>
            <button class="relat-tab" data-relat="com_integracao">
              <i class="fas fa-link"></i> Com Integração
            </button>
            <button class="relat-tab" data-relat="por_versao">
              <i class="fas fa-layer-group"></i> Por Versão
            </button>
            <button class="relat-tab" data-relat="desatualizados">
              <i class="fas fa-clock"></i> Desatualizados
            </button>
            <button class="relat-tab" data-relat="com_exe">
              <i class="fas fa-file-code"></i> Com EXE
            </button>
          </div>

          <!-- ── Conteúdo ── -->
          <div class="card relat-content-card">
            <div id="relatKpi"></div>
            <div id="relatContent">
              <div class="relat-loading">
                <i class="fas fa-chart-bar"></i>
                Selecione um relatório acima
              </div>
            </div>
          </div>

        </div>

        <!-- ══════════════════════════════════════
             VIEW AUDITORIA
        ══════════════════════════════════════ -->
        <div class="view hidden" id="view-auditoria">

          <!-- Filtros -->
          <div class="audit-filter-bar">
            <input type="text"  id="auditBusca"        placeholder="🔍 Buscar descrição, módulo, usuário…" oninput="clearTimeout(window._auditT); window._auditT = setTimeout(aplicarFiltrosAudit, 350)">
            <select id="auditFiltroModulo"  onchange="aplicarFiltrosAudit()"><option value="">Todos os módulos</option></select>
            <select id="auditFiltroUsuario" onchange="aplicarFiltrosAudit()" style="display:none"></select>
            <select id="auditFiltroAcao"    onchange="aplicarFiltrosAudit()">
              <option value="">Todas as ações</option>
              <option value="CREATE">Criação</option>
              <option value="UPDATE">Atualização</option>
              <option value="DELETE">Exclusão</option>
            </select>
            <input type="date"  id="auditFiltroDe"  onchange="aplicarFiltrosAudit()" title="De">
            <input type="date"  id="auditFiltroAte" onchange="aplicarFiltrosAudit()" title="Até">
            <button class="btn ghost btn-sm" onclick="limparFiltrosAudit()">✕ Limpar</button>
            <button class="btn btn-sm"       onclick="loadAuditoria()">↻ Atualizar</button>
          </div>

          <!-- Tabela -->
          <div class="audit-table-wrap">
            <table class="audit-table">
              <thead>
                <tr>
                  <th>Data/Hora</th>
                  <th>Usuário</th>
                  <th>Ação</th>
                  <th>Módulo</th>
                  <th>Descrição</th>
                  <th>Alterações</th>
                  <th>IP</th>
                </tr>
              </thead>
              <tbody id="auditTbody">
                <tr><td colspan="7" class="loading-cell"><i class="fas fa-spinner fa-spin"></i> Carregando…</td></tr>
              </tbody>
            </table>
            <div id="auditPaginacao"></div>
          </div>

        </div>

        <!-- ═══════════════════════════════════════
             EXPORTAR
        ═══════════════════════════════════════ -->
        <div class="view hidden" id="view-exportar">

          <p class="exp-subtitle">
            Os filtros aplicados em cada módulo são preservados na exportação.
            Navegue até o módulo, filtre os dados e volte aqui para exportar.
          </p>

          <div class="exp-grid">

            <div class="exp-card">
              <div class="exp-card-icon"><i class="fas fa-building" style="color:#ff9800"></i></div>
              <div class="exp-card-body">
                <h3>Empresas Web</h3>
                <p>Logins, versões, status e integrações</p>
                <div class="exp-card-btns">
                  <button class="btn exp-btn-csv" data-exp-modulo="logins" onclick="exportarCSV('logins')">
                    <i class="fas fa-file-csv"></i> Exportar CSV
                  </button>
                </div>
              </div>
            </div>

            <div class="exp-card">
              <div class="exp-card-icon"><i class="fas fa-mobile-alt" style="color:#2196f3"></i></div>
              <div class="exp-card-body">
                <h3>Mobile (FV + API)</h3>
                <p>Conexões Smart Client e API Força de Vendas</p>
                <div class="exp-card-btns">
                  <button class="btn exp-btn-csv" data-exp-modulo="mobile" onclick="exportarCSV('mobile')">
                    <i class="fas fa-file-csv"></i> Exportar CSV
                  </button>
                </div>
              </div>
            </div>

            <div class="exp-card">
              <div class="exp-card-icon"><i class="fab fa-whatsapp" style="color:#25D366"></i></div>
              <div class="exp-card-body">
                <h3>WhatsApp</h3>
                <p>Clientes integrados ao WhatsApp</p>
                <div class="exp-card-btns">
                  <button class="btn exp-btn-csv" data-exp-modulo="whatsapp" onclick="exportarCSV('whatsapp')">
                    <i class="fas fa-file-csv"></i> Exportar CSV
                  </button>
                </div>
              </div>
            </div>

            <div class="exp-card">
              <div class="exp-card-icon"><i class="fas fa-cash-register" style="color:#38bdf8"></i></div>
              <div class="exp-card-body">
                <h3>PDV OFF</h3>
                <p>Cadastros de PDV Offline</p>
                <div class="exp-card-btns">
                  <button class="btn exp-btn-csv" data-exp-modulo="pdvoff" onclick="exportarCSV('pdvoff')">
                    <i class="fas fa-file-csv"></i> Exportar CSV
                  </button>
                </div>
              </div>
            </div>

            <div class="exp-card">
              <div class="exp-card-icon"><i class="fas fa-chart-pie" style="color:#a78bfa"></i></div>
              <div class="exp-card-body">
                <h3>BI</h3>
                <p>Clientes integrados ao Business Intelligence</p>
                <div class="exp-card-btns">
                  <button class="btn exp-btn-csv" data-exp-modulo="bi" onclick="exportarCSV('bi')">
                    <i class="fas fa-file-csv"></i> Exportar CSV
                  </button>
                </div>
              </div>
            </div>

            <div class="exp-card">
              <div class="exp-card-icon"><i class="fas fa-users" style="color:#e879f9"></i></div>
              <div class="exp-card-body">
                <h3>Usuários Web</h3>
                <p>Empresas e quantidade de usuários cadastrados</p>
                <div class="exp-card-btns">
                  <button class="btn exp-btn-csv" data-exp-modulo="usuarios-web" onclick="exportarCSV('usuarios-web')">
                    <i class="fas fa-file-csv"></i> Exportar CSV
                  </button>
                </div>
              </div>
            </div>

            <!-- ═══════════════════════════════════
                 MOVIMENTAÇÃO MENSAL
            ═══════════════════════════════════ -->
            <div class="exp-card exp-card--full">
              <div class="exp-card-icon"><i class="fas fa-calendar-alt" style="color:#f59e0b"></i></div>
              <div class="exp-card-body">
                <h3>Movimentação Mensal de Usuários</h3>
                <p>Relatório de usuários criados e desativados, agrupados por empresa — mesmo formato do modelo de movimentação oficial.</p>
                <div class="exp-card-btns exp-mov-row">
                  <label for="expMovMes" class="exp-filter-label" style="margin:0 4px 0 0">Mês:</label>
                  <input type="month" id="expMovMes" class="exp-filter-input exp-mov-month">
                  <button class="btn exp-btn-csv" id="btnExpMovimentacao" onclick="exportarMovimentacao()">
                    <i class="fas fa-file-csv"></i> Gerar Relatório
                  </button>
                </div>
              </div>
            </div>

          </div>
        </div>

      </section>
    </main>
  </div>

  <!-- EXPORT FILTER MODAL -->
  <div class="modal-backdrop hidden" id="expFilterBackdrop">
    <div class="modal exp-filter-modal">
      <div class="modal-head">
        <h2 id="expFilterTitle">Exportar</h2>
        <button class="icon-btn" type="button" onclick="closeExpFilterModal()">✕</button>
      </div>
      <div class="exp-filter-body">

        <p class="exp-filter-hint">
          <i class="fas fa-info-circle"></i>
          Os filtros do módulo são preservados. Defina um período adicional e escolha as colunas do relatório.
        </p>

        <!-- Período -->
        <div class="exp-filter-section">
          <span class="exp-filter-section-label">📅 Período de cadastro</span>
          <div class="exp-filter-row">
            <div class="exp-filter-col">
              <label class="exp-filter-label">De</label>
              <input type="date" id="ef_de" class="exp-filter-input">
            </div>
            <div class="exp-filter-col">
              <label class="exp-filter-label">Até</label>
              <input type="date" id="ef_ate" class="exp-filter-input">
            </div>
          </div>
        </div>

        <!-- Status -->
        <div class="exp-filter-section" id="ef_status_group">
          <span class="exp-filter-section-label">📋 Status</span>
          <div class="exp-filter-checks">
            <label class="exp-filter-check"><input type="checkbox" id="ef_ativo"   checked> Ativo</label>
            <label class="exp-filter-check"><input type="checkbox" id="ef_inativo" checked> Inativo</label>
          </div>
        </div>

        <!-- Ordenação -->
        <div class="exp-filter-section">
          <span class="exp-filter-section-label">↕ Ordenar por</span>
          <div class="exp-filter-row">
            <div class="exp-filter-col">
              <label class="exp-filter-label">Coluna</label>
              <select id="ef_sort_col" class="exp-filter-input">
                <option value="">— padrão do módulo —</option>
              </select>
            </div>
            <div class="exp-filter-col">
              <label class="exp-filter-label">Direção</label>
              <select id="ef_sort_dir" class="exp-filter-input">
                <option value="ASC">A → Z (crescente)</option>
                <option value="DESC">Z → A (decrescente)</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Agrupamento -->
        <div class="exp-filter-section">
          <span class="exp-filter-section-label">📁 Agrupar por</span>
          <div class="exp-filter-col">
            <label class="exp-filter-label">Insere separadores no CSV entre cada grupo</label>
            <select id="ef_group_col" class="exp-filter-input">
              <option value="">— sem agrupamento —</option>
            </select>
          </div>
        </div>

        <!-- Colunas -->
        <div class="exp-filter-section">
          <span class="exp-filter-section-label">📊 Colunas do relatório</span>
          <div id="ef_cols_wrap" class="exp-filter-cols-wrap"></div>
        </div>

      </div>
      <div class="modal-actions">
        <button class="btn ghost" type="button" onclick="closeExpFilterModal()">Cancelar</button>
        <button class="btn primary" type="button" id="expFilterConfirmBtn">
          <i class="fas fa-download"></i> Exportar CSV
        </button>
      </div>
    </div>
  </div>

  <!-- CONFIRM DIALOG -->
  <div class="modal-backdrop hidden" id="confirmBackdrop">
    <div class="modal" style="max-width:420px">
      <div class="modal-head">
        <span class="confirm-icon" aria-hidden="true"><i class="fas fa-triangle-exclamation"></i></span>
        <h2 id="confirmTitle">Confirmar</h2>
      </div>
      <div style="padding:16px 24px 4px">
        <p id="confirmMessage" style="font-size:.9rem;line-height:1.6;color:var(--text-main)"></p>
      </div>
      <div class="modal-actions">
        <button class="btn ghost" id="confirmCancel">Cancelar</button>
        <button class="btn danger" id="confirmOk">Confirmar</button>
      </div>
    </div>
  </div>

  <!-- MODAL GLOBAL -->
  <div class="modal-backdrop hidden" id="modalBackdrop">
    <div class="modal">
      <div class="modal-head">
        <h2 id="modalTitle">—</h2>
        <button class="icon-btn" id="btnCloseModal" type="button">✕</button>
      </div>

      <form id="modalForm">
        <input type="hidden" id="mEntity">
        <input type="hidden" id="mId">

        <div class="form-grid" id="modalFields"></div>

        <div class="modal-actions">
          <button class="btn ghost" type="button" id="btnCancelModal">Cancelar</button>
          <button class="btn primary" type="submit">Salvar</button>
        </div>
      </form>
    </div>
  </div>



  <!-- TOASTS -->
  <div class="toasts" id="toasts"></div>

  <!-- CSRF GLOBAL -->
  <script>
    window.__CSRF__            = <?= json_encode($csrf) ?>;
    window.__TOKEN_REMAINING__ = <?= (int)$tokenRemaining ?>;
  </script>

  <!-- API base -->
  <script src="assets/js/api.js?v=<?= filemtime(__DIR__.'/assets/js/api.js') ?>"></script>

  <!-- Módulos -->
  <script src="assets/js/dashboard.js?v=<?= filemtime(__DIR__.'/assets/js/dashboard.js') ?>"></script>
  <script src="assets/js/logins.js?v=<?= filemtime(__DIR__.'/assets/js/logins.js') ?>"></script>

  <script src="assets/js/mobiles.js?v=<?= filemtime(__DIR__.'/assets/js/mobiles.js') ?>"></script>
  <script src="assets/js/whatsapp.js?v=<?= filemtime(__DIR__.'/assets/js/whatsapp.js') ?>"></script>
  <script src="assets/js/pdvoff.js?v=<?= filemtime(__DIR__.'/assets/js/pdvoff.js') ?>"></script>
  <script src="assets/js/bi.js?v=<?= filemtime(__DIR__.'/assets/js/bi.js') ?>"></script>

  <script src="assets/js/token_timer.js?v=<?= filemtime(__DIR__.'/assets/js/token_timer.js') ?>"></script>

  <!-- Módulos (antes do app.js para evitar race condition) -->
  <script src="assets/js/usuarios_web.js?v=<?= filemtime(__DIR__.'/assets/js/usuarios_web.js') ?>"></script>
  <script src="assets/js/empresa_usuarios_bridge.js?v=<?= filemtime(__DIR__.'/assets/js/empresa_usuarios_bridge.js') ?>"></script>

  <script src="assets/js/servidores.js?v=<?= filemtime(__DIR__.'/assets/js/servidores.js') ?>"></script>
  <script src="assets/js/relatorios_view.js?v=<?= filemtime(__DIR__.'/assets/js/relatorios_view.js') ?>"></script>
  <script src="assets/js/auditoria.js?v=<?= filemtime(__DIR__.'/assets/js/auditoria.js') ?>"></script>
  <script src="assets/js/exportar.js?v=<?= filemtime(__DIR__.'/assets/js/exportar.js') ?>"></script>
  <script src="assets/js/dash_widgets.js?v=<?= filemtime(__DIR__.'/assets/js/dash_widgets.js') ?>"></script>

  <!-- APP (orquestrador, SEMPRE ÚLTIMO) -->
  <script src="assets/js/app.js?v=<?= filemtime(__DIR__.'/assets/js/app.js') ?>"></script>



  <script>
    document.getElementById('btnToggleTheme')?.addEventListener('click', () => {
      if (typeof rerenderCharts === 'function') setTimeout(rerenderCharts, 50);
    });
  </script>

  <script>
    const btnLogout = document.getElementById('btnLogout');

    if (btnLogout) {
      btnLogout.addEventListener('click', async () => {
        const ok = await confirmDialog('Deseja realmente sair do sistema?', {
          title: 'Sair do sistema',
          confirmLabel: 'Sair',
          danger: true
        });
        if (!ok) return;
        window.location.href = 'backend/logout.php';
      });
    }
  </script>

</body>

</html>