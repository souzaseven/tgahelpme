<?php
$config = require "../config.php";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Controle de Pausas</title>

    <link rel="icon" href="https://tgameajuda.com/img/principal/bot-tga.webp">
    <link rel="stylesheet" href="login.css?v=<?= time() ?>">

    <!-- Configuração global (versão / caminhos) -->
    <script src="../setup_sistema.php?v=<?= time() ?>"></script>

    <!-- Boot de login -->
    <script src="bootstrap_login.js?v=<?= time() ?>"></script>
</head>

<body>

<div class="login-container">

    <div class="login-box">
        <h1><i class="fas fa-user-circle"></i> Identifique-se</h1>
        <p class="subtitulo">Selecione sua equipe e seu nome.</p>

        <!-- Passo 1: Equipes -->
        <div id="stepEquipes" class="step ativo">
            <h3>Escolha sua equipe</h3>
            <div id="listaEquipes" class="grid-equipes">
                <!-- Preenchido pelo login.js -->
            </div>
        </div>

        <!-- Passo 2: Operadores -->
        <div id="stepOperadores" class="step oculto">
            <button id="btnVoltar" class="voltar-btn"><i class="fas fa-arrow-left"></i> Voltar</button>

            <h3 id="tituloEquipe"></h3>
            <div id="listaOperadores" class="grid-operadores">
                <!-- Preenchido pelo login.js -->
            </div>

            <button id="btnConfirmar" class="confirmar-btn" disabled>Confirmar</button>
            <p id="erroOperador" class="erro oculto"></p>
        </div>

    </div>

</div>

<script src="login.js?v=<?= time() ?>"></script>

</body>
</html>
