<?php
$config = require "config.php";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle de Pausas - TGA</title>

    <link rel="icon" href="https://tgameajuda.com/img/principal/bot-tga.webp">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="painel/css/painel.css?v=<?php echo time(); ?>">

    <script src="./setup_sistema.php?v=<?php echo time(); ?>"></script>
</head>

<body>

<!-- SIDEBAR -->
<nav class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-titulo">
            <i class="fas fa-clock"></i>
            <span class="texto-item">Controle de Pausas</span>
        </div>
        <button id="btnToggleSidebar" class="sidebar-toggle">
            <i class="fas fa-angle-left"></i>
        </button>
    </div>

    <ul class="sidebar-menu">

        <li class="sidebar-item" id="btnSidebarInicio">
            <i class="fas fa-home"></i>
            <span class="texto-item">Início</span>
        </li>

        <li class="sidebar-item" id="btnSidebarEquipes">
            <i class="fas fa-users"></i>
            <span class="texto-item">Todas as equipes</span>
        </li>

        <li class="sidebar-item" id="btnSidebarPrefs">
            <i class="fas fa-sliders-h"></i>
            <span class="texto-item">Preferências</span>
        </li>

        <li class="sidebar-item sair" id="btnSidebarSair">
            <i class="fas fa-right-from-bracket"></i>
            <span class="texto-item">Sair</span>
        </li>

    </ul>
</nav>

<!-- CONTEÚDO PRINCIPAL -->
<div id="app">
    <div class="carregando-sistema">Carregando painel...</div>
</div>

<!-- MODAL DE PREFERÊNCIAS -->
<div id="modalPreferencias" class="modal-pref hidden">
    <div class="modal-pref-content">

        <h2><i class="fas fa-sliders-h"></i> Preferências do Operador</h2>

        <div class="pref-item">
            <label class="switch">
                <input type="checkbox" id="pref_audio">
                <span class="slider"></span>
            </label>
            <span>Som</span>
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

<!-- JS principal -->
<script src="painel/js/bootstrap.js?v=<?php echo time(); ?>"></script>
<script src="painel/js/equipes_todas.js?v=<?php echo time(); ?>"></script>
<script src="painel/js/botoes_operador.js?v=<?php echo time(); ?>"></script>
<script src="painel/js/atualizador_listas.js?v=<?php echo time(); ?>"></script>
<script src="painel/js/cronometro.js?v=<?php echo time(); ?>"></script>

</body>
</html>
