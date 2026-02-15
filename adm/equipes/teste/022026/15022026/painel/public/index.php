<?php
session_start();
require_once __DIR__ . '/../app/EvoluxAPI.php';

$api = new EvoluxAPI();
$page = $_GET['page'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Evolux - Administração</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📞</text></svg>">


  <!-- Google Tag Manager -->
  <script>
    (function(w,d,s,l,i){
      w[l]=w[l]||[];
      w[l].push({'gtm.start': new Date().getTime(),event:'gtm.js'});
      var f=d.getElementsByTagName(s)[0],
          j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
      j.async=true;
      j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;
      f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-K2XFNTVZ');
  </script>

  <!-- Google Ads -->
  <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044"
    crossorigin="anonymous"></script>

  <!-- Google Analytics -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-E7ZNTJSRYR"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-E7ZNTJSRYR');
  </script>

  <script async src="https://www.googletagmanager.com/gtag/js?id=G-S8EC5C2WTG"></script>
  <script>
    gtag('config', 'G-S8EC5C2WTG');
  </script>
</head>

</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <span>📞</span>
                    <span>Evolux Admin</span>
                </div>
                <div class="header-actions">
                    <!-- Indicador de auto-refresh será inserido aqui via JavaScript -->
                    <button class="btn btn-sm btn-secondary" onclick="location.reload()" title="Atualizar agora">
                        🔄 Atualizar
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="nav">
        <div class="container">
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="?page=dashboard" class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>" title="Dashboard">
                        <span class="nav-icon">📊</span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=agentes" class="nav-link <?= $page === 'agentes' ? 'active' : '' ?>" title="Agentes">
                        <span class="nav-icon">👤</span>
                        <span class="nav-text">Agentes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=callcenter" class="nav-link <?= $page === 'callcenter' ? 'active' : '' ?>" title="CallCenter">
                        <span class="nav-icon">📞</span>
                        <span class="nav-text">CallCenter</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=chamadas" class="nav-link <?= $page === 'chamadas' ? 'active' : '' ?>" title="Chamadas">
                        <span class="nav-icon">📲</span>
                        <span class="nav-text">Chamadas</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=cdr" class="nav-link <?= $page === 'cdr' ? 'active' : '' ?>" title="CDR">
                        <span class="nav-icon">📋</span>
                        <span class="nav-text">CDR</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=discador" class="nav-link <?= $page === 'discador' ? 'active' : '' ?>" title="Discador">
                        <span class="nav-icon">🎯</span>
                        <span class="nav-text">Discador</span>
                    </a>
                </li>
<li class="nav-item">
    <a href="?page=feature_plan" class="nav-link <?= $page === 'feature_plan' ? 'active' : '' ?>">
        <span class="nav-icon">📋</span>
        <span class="nav-text">Feature Plan</span>
    </a>
</li>

                <li class="nav-item">
                    <a href="?page=filas" class="nav-link <?= $page === 'filas' ? 'active' : '' ?>" title="Filas">
                        <span class="nav-icon">👥</span>
                        <span class="nav-text">Filas</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=ramais" class="nav-link <?= $page === 'ramais' ? 'active' : '' ?>" title="ramais">
                        <span class="nav-icon">☎️</span>
                        <span class="nav-text">PBX</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=realtime" class="nav-link <?= $page === 'realtime' ? 'active' : '' ?>" title="Realtime">
                        <span class="nav-icon">⚡</span>
                        <span class="nav-text">Realtime</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=relatorios" class="nav-link <?= $page === 'relatorios' ? 'active' : '' ?>" title="Relatórios">
                        <span class="nav-icon">📈</span>
                        <span class="nav-text">Relatórios</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=tarefas" class="nav-link <?= $page === 'tarefas' ? 'active' : '' ?>" title="Tarefas">
                        <span class="nav-icon">✓</span>
                        <span class="nav-text">Tarefas</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=usuarios" class="nav-link <?= $page === 'usuarios' ? 'active' : '' ?>" title="Usuários">
                        <span class="nav-icon">👨‍💼</span>
                        <span class="nav-text">Usuários</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=chat" class="nav-link <?= $page === 'chat' ? 'active' : '' ?>" title="Chat">
                        <span class="nav-icon">💬</span>
                        <span class="nav-text">Chat</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container">
        <?php
        $pageFile = __DIR__ . "/pages/{$page}.php";
        if (file_exists($pageFile)) {
            include $pageFile;
        } else {
            include __DIR__ . '/pages/dashboard.php';
        }
        ?>
    </main>

    <!-- Footer -->
    <footer style="text-align: center; padding: 2rem 0; color: var(--text-muted); font-size: 0.875rem; border-top: 1px solid var(--border); margin-top: 3rem;">
        <div class="container">
            <p>📞 Evolux Admin Panel - Modo Escuro v2.0</p>
            <p style="margin-top: 0.5rem;">Desenvolvido com ❤️ para Evolux CX</p>
        </div>
    </footer>

    <script src="../assets/js/app.js"></script>
</body>
</html>