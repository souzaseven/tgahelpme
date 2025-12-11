<?php
// login.php — Tela de Login (FASE 6)
// Login por equipe → operador
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Controle de Pausas</title>

    <link rel="icon" href="https://tgameajuda.com/img/principal/bot-tga.webp">

    <!-- CSS -->
    <link rel="stylesheet" href="login.css">

    <!-- FontAwesome -->
    <link rel="stylesheet" 
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="login-container">
    <div class="login-box">

        <h1><i class="fas fa-users"></i> Controle de Pausas</h1>
        <p class="subtitulo">Escolha sua equipe e depois seu operador</p>

        <!-- ============================================================
             STEP 1 — LISTA DE EQUIPES
        ============================================================ -->
        <div id="stepEquipes" class="step ativo">
            <h3>Selecione sua Equipe</h3>

            <div id="listaEquipes" class="grid-equipes">
                <!-- carregado via JS -->
            </div>
        </div>

        <!-- ============================================================
             STEP 2 — LISTA DE OPERADORES
        ============================================================ -->
        <div id="stepOperadores" class="step">
            <h3 id="tituloEquipe">Equipe selecionada:</h3>

            <div id="listaOperadores" class="grid-operadores">
                <!-- carregado via JS -->
            </div>

            <p id="erroOperador" class="erro oculto">Selecione um operador.</p>

            <button id="btnVoltar" class="voltar-btn">
                <i class="fas fa-arrow-left"></i> Voltar
            </button>

            <button id="btnConfirmar" class="confirmar-btn" disabled>
                <i class="fas fa-check-circle"></i> Entrar
            </button>
        </div>

    </div>
</div>

<!-- Scripts -->
<script src="bootstrap_login.js"></script>
<script src="login.js"></script>

</body>
</html>
