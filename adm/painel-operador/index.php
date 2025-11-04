<?php
session_start();
if (!isset($_SESSION['usuario_logado'])) {
    header("Location: login.html");
    exit;
}
?>



<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Controle - Operadores</title>
    <link rel="icon" href="https://raw.githubusercontent.com/souzaseven/Site2/Desafios/icon%20eu.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <meta name="theme-color" content="#007bff">

<!--souza system-->
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044"
     crossorigin="anonymous"></script>

<!--meajudatga-->
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-E7ZNTJSRYR"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-E7ZNTJSRYR');
</script>

</head>
<body data-theme="dark">
    <div class="container">
        <header>
            <div class="header-title">
                <h1 onclick="window.open('https://tgasistemas.evolux.io/callcenter/agent', '_blank')" tabindex="0" aria-label="Painel de Operadores - Clique para acessar">
                    <i class="fas fa-users-cog" aria-hidden="true"></i> Painel de Operadores
                </h1>
                <div class="quick-links">
                    <a href="https://tgasistemas.evolux.io/panel/queue?id=9&slug=suporte_matriz&type=queue#details" target="_blank" aria-label="Painel de Ligação">
                        <i class="fas fa-phone" aria-hidden="true"></i> Painel de Ligação
                    </a>
                    <a href="https://tgasistemas.evolux.io/callcenter/agent" target="_blank" aria-label="Painel de Operadores">
                        <i class="fas fa-user-tie" aria-hidden="true"></i> Painel de Operadores
                    </a>
                    <a href="https://tgasistemas.evolux.io/callcenter/supervisor/agents_monitor" target="_blank" aria-label="Monitor de Operadores">
                        <i class="fas fa-desktop" aria-hidden="true"></i> Monitor de Operadores
                    </a>
                </div>
            </div>
       <div class="header-controls">
    <button class="theme-toggle" tabindex="0" aria-label="Alternar modo claro/escuro">
        <i class="fas fa-sun" aria-hidden="true"></i> Modo Claro
    </button>
    

    <div class="search-container">
        <input type="text" id="searchInput" placeholder="Buscar por nome, equipe ou fila..." 
               oninput="filterOperators()" tabindex="0" aria-label="Campo de busca">
        <button onclick="filterOperators()" tabindex="0" aria-label="Buscar">
            <i class="fas fa-search" aria-hidden="true"></i>
        </button>
    </div>

   <form action="logout.php" method="POST">
        <button type="submit" class="theme-toggle" style="background-color: var(--team3-color);" tabindex="0">
            <i class="fas fa-sign-out-alt" aria-hidden="true"></i> Sair
        </button>
    </form>

</div>

        </header>

        <div class="dashboard">
            <div class="stats-card total">
                <h3>Total de Operadores</h3>
                <span id="totalOperators">0</span>
            </div>
            <div class="stats-card team1">
                <h3>Alex Sandro Braulio</h3>
                <span id="team1Count">0</span>
            </div>
            <div class="stats-card team2">
                <h3>Daniel Feix</h3>
                <span id="team2Count">0</span>
            </div>
            <div class="stats-card team3">
                <h3>Willian Pereira Reis</h3>
                <span id="team3Count">0</span>
            </div>
        </div>

<div class="weekly-teams">
  <div class="stats-card telefone">
    <h3><i class="fas fa-phone"></i> Equipe Telefone da Semana</h3>
    <span id="telefoneSemana">Carregando...</span>
  </div>
  <div class="stats-card chat">
    <h3><i class="fas fa-comments"></i> Equipe Chat da Semana</h3>
    <span id="chatSemana">Carregando...</span>
  </div>
</div>

<div class="equipe-rotativa">
  <div><strong>Anterior:</strong> <span id="telefoneAnterior">Carregando...</span></div>
  <div><strong>Atual:</strong> <span id="telefoneAtual">Carregando...</span></div>
  <div><strong>Próxima:</strong> <span id="telefoneProxima">Carregando...</span></div>
</div>



        <div class="filters">
            <div class="filter-section">
                <h3><i class="fas fa-filter" aria-hidden="true"></i> Filtros</h3>
                <div class="filter-row">
                    <div class="filter-group">
                        <h4>Grupos de Fila:</h4>
                        <label><input type="checkbox" name="queue" value="Suporte Matriz" checked tabindex="0"> Suporte Matriz</label>
                        <label><input type="checkbox" name="queue" value="Fila Matriz Chat/Whats" checked tabindex="0"> Fila Matriz Chat/Whats</label>
                    </div>
                    <div class="filter-group">
                        <h4>Equipes:</h4>
                        <label><input type="checkbox" name="team" value="Alex Sandro Braulio" checked tabindex="0"> Alex Sandro Braulio</label>
                        <label><input type="checkbox" name="team" value="Daniel Feix" checked tabindex="0"> Daniel Feix</label>
                        <label><input type="checkbox" name="team" value="Willian Pereira Reis" checked tabindex="0"> Willian Pereira Reis</label>
                    </div>
                </div>
            </div>
        </div>



        <div class="operators-container" id="operatorsContainer">
            <!-- Os operadores serão carregados aqui via JavaScript -->
        </div>

        <footer><p>Painel de Controle - TGA Sistemas | Versão 12.5</p> <br>
<!--
            <img src="https://profile-counter.glitch.me/tgameajuda-paineloperador/count.svg" alt="Contador de Visitantes">
            -->

<div style="display: flex; justify-content: center; margin: 10px 0;">
  <img alt="visitas" src="https://hits.sh/tgameajuda.com/painel-operadores.html.svg?color=007ced&label=visitas&labelColor=FFFFFF&logo=https%3A%2F%2Fraw.githubusercontent.com%2Fsouzaseven%2Ftgahelpme%2FDesafios%2Ficon%2520bot%2520tga.ico"/>
</div>
        </footer>
    </div>

    <div class="open-selected-container"></div>
<!--
    <script src="data.js"></script>-->
    <script src="script.js"></script>
</body>
</html>