<?php
// ============================================================
// index.php - Painel de Controle de Pausas (Nova Estrutura v0.004)
// ============================================================

$config = require "config.php";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle de Pausas - TGA</title>

    <!-- Ícone -->
    <link rel="icon" href="https://tgameajuda.com/img/principal/bot-tga.webp">

    <!-- Font Awesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- CSS principal do painel -->
    <link rel="stylesheet" href="painel/css/painel.css?v=<?php echo time(); ?>">

    <!-- Setup do sistema (versão, caminhos, módulos) -->
    <script src="./setup_sistema.php?v=<?php echo time(); ?>"></script>


</head>

<body>
<!-- SIDEBAR LATERAL -->
<nav class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-titulo">
            <i class="fas fa-clock"></i>
            <span class="texto-item">Controle de Pausas</span>
        </div>
        <button id="btnToggleSidebar" class="sidebar-toggle" title="Mostrar/ocultar menu">
            <i class="fas fa-angle-left"></i>
        </button>
    </div>

    <ul class="sidebar-menu">

        <li class="sidebar-item" id="btnSidebarInicio">
            <i class="fas fa-home"></i>
            <span class="texto-item">Início</span>
        </li>

        <li class="sidebar-item sair" id="btnSidebarSair">
            <i class="fas fa-right-from-bracket"></i>
            <span class="texto-item">Sair</span>
        </li>

        <li class="sidebar-item" id="btnSidebarEquipes">
            <i class="fas fa-users"></i>
            <span class="texto-item">Todas as equipes</span>
        </li>

        <li class="sidebar-item" id="btnSidebarPrefs">
            <i class="fas fa-sliders-h"></i>
            <span class="texto-item">Preferências</span>
        </li>

    </ul>
</nav>


<!-- CONTEÚDO PRINCIPAL -->
<div id="app">


    <div class="carregando-sistema">
        Carregando painel...
    </div>
</div>

<!-- JS do painel -->
<script src="painel/js/bootstrap.js?v=<?php echo time(); ?>"></script>
<!-- MODAL DE PREFERÊNCIAS -->
<div id="modalPreferencias" class="modal-pref hidden">
    <div class="modal-pref-content">

        <h2><i class="fas fa-sliders-h"></i> Preferências do Operador</h2>

        <div class="pref-item">
            <label class="switch">
                <input type="checkbox" id="pref_audio">
                <span class="slider"></span>
            </label>
            <span>Som / Falas</span>

            <button id="btnTesteAudio" class="btn-teste">Testar</button>
        </div>

        <div class="pref-item">
            <label class="switch">
                <input type="checkbox" id="pref_notif">
                <span class="slider"></span>
            </label>
            <span>Notificação do Windows</span>

            <button id="btnTesteNotif" class="btn-teste">Testar</button>
        </div>

        <button id="btnSalvarPreferencias" class="btn-salvar-pref">
            Salvar Preferências
        </button>

        <button id="btnFecharPref" class="btn-fechar-pref">Fechar</button>
    </div>
</div>
</body>
</html>
