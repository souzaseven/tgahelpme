<?php
require_once __DIR__ . '/includes/session.php';

if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$nomeUsuario = htmlspecialchars($_SESSION['nome']);
$isAdmin     = (int) $_SESSION['is_admin'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repasse de Tickets</title>
    <link rel="icon" href="https://tgameajuda.com/img/principal/bot-tga.webp" type="image/x-icon">
    <link rel="stylesheet" href="assets/style.css?v=20260807-4">
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-E7ZNTJSRYR"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-E7ZNTJSRYR');
    </script>
</head>
<body>
<script src="assets/common.js?v=20260807-1"></script>

<!-- ──────────────── HEADER FIXO ──────────────── -->
<div class="sticky-header">
<!-- ──────────────── HEADER ──────────────── -->
<header class="topbar">
    <div class="topbar-left">
        <span class="topbar-icon">🎫</span>
        <h1 class="topbar-title">Repasse de Tickets</h1>
    </div>
    <div class="topbar-right">
        <a href="dashboard.php" class="btn btn-outline btn-sm">📊 Dashboard</a>
        <a href="operadores.php" class="btn btn-outline btn-sm">👥 Operadores</a>
        <?php if ($isAdmin): ?>
            <span class="badge-admin">Admin</span>
            <button id="btn-notificacao" class="btn btn-outline btn-sm" title="Notificações" style="position:relative">
                <span id="icon-sino">🔔</span>
                <span id="badge-notificacao" style="display:none;position:absolute;top:0;right:0;background:#dc2626;color:#fff;border-radius:50%;font-size:10px;padding:1px 5px;">!</span>
            </button>
            <a href="usuarios.php"  class="btn btn-outline btn-sm">🔑 Usuários</a>
            <a href="historico.php" class="btn btn-outline btn-sm">📋 Histórico</a>
        <?php else: ?>
            <a href="historico.php" class="btn btn-outline btn-sm">📋 Histórico</a>
        <?php endif; ?>
        <span class="topbar-user">👤 <?= $nomeUsuario ?></span>
        <button id="btn-tema" class="btn btn-outline btn-sm" title="Alternar modo claro/escuro">🌙</button>
        <a href="logout.php" class="btn btn-outline btn-sm">Sair</a>
    </div>
</header>

<!-- ──────────────── SEMANA NAV ──────────────── -->
<div class="week-nav-bar">
    <button id="btn-semana-anterior" class="btn btn-outline">← Semana Anterior</button>
    <div class="week-label">
        <span id="semana-titulo">Carregando...</span>
    </div>
    <button id="btn-proxima-semana" class="btn btn-outline">Próxima Semana →</button>
</div>

<!-- ──────────────── FILTROS ──────────────── -->
<div class="filter-bar">
    <input type="text" id="busca-operador" class="filter-select filter-search" placeholder="🔍 Buscar operador..." autocomplete="off">
    <span class="filter-sep"></span>
    <span class="filter-bar-label">Suportes:</span>
    <button class="filter-btn active" data-filter="todos">Todos</button>
    <button class="filter-btn" data-filter="tef">🏧 Apenas TEF</button>
    <button class="filter-btn" data-filter="troca_regime">🔁 Troca de Regime</button>
    <button class="filter-btn" data-filter="ferias">🏖 Em Férias</button>
    <button class="filter-btn" data-filter="nao_repassar">🚫 Não Repassar</button>
    <span class="filter-sep"></span>
    <span class="filter-bar-label">Ordenar:</span>
    <select id="ordem-tabela" class="filter-select" title="Ordenar tabela de operadores">
        <option value="ordem_original">Padrão da equipe</option>
        <option value="nome_asc">Nome A-Z</option>
        <option value="nome_desc">Nome Z-A</option>
        <option value="tempo_desc">Maior tempo de empresa</option>
        <option value="tempo_asc">Menor tempo de empresa</option>
        <option value="total_desc">Maior total</option>
        <option value="total_asc">Menor total</option>
        <option value="seg_desc">Maior SEG</option>
        <option value="ter_desc">Maior TER</option>
        <option value="qua_desc">Maior QUA</option>
        <option value="qui_desc">Maior QUI</option>
        <option value="sex_desc">Maior SEX</option>
        <option value="sab_desc">Maior SÁB</option>
    </select>
    <span class="filter-sep"></span>
    <span class="filter-bar-label">Semana:</span>
    <span id="total-semana-geral" class="filter-total-pill">Total geral: 0</span>
    <span class="filter-sep"></span>
    <span class="filter-bar-label">Equipe:</span>
    <div id="filter-equipes" class="filter-equipes-wrap"></div>
</div>
</div>

<!-- ──────────────── CONTEÚDO ──────────────── -->
<main id="conteudo" class="main-content">
    <div class="loading-spinner" id="loading">
        <div class="spinner"></div>
        <p>Carregando dados...</p>
    </div>
</main>

<!-- ──────────────── AÇÕES FIXAS ──────────────── -->
<div class="action-bar">
    <span id="indicador-nao-salvo" class="indicador-nao-salvo" hidden>● alterações não salvas</span>
    <button id="btn-salvar" class="btn btn-success btn-lg">
        💾 Salvar Semana
    </button>
</div>

<!-- ──────────────── TOAST ──────────────── -->
<div id="toast" class="toast" hidden></div>

<script>
    const USUARIO = {
        id:      <?= json_encode($_SESSION['usuario_id']) ?>,
        nome:    <?= json_encode($_SESSION['nome']) ?>,
        isAdmin: <?= json_encode((bool) $isAdmin) ?>
    };
</script>
<script src="assets/repasse.js?v=20260807-9"></script>
</body>
</html>
