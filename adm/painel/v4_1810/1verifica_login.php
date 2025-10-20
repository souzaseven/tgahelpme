<?php
session_start();

// Verificar se o usuário está logado e é o Anderson
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== 'anderson' || !isset($_SESSION['is_admin'])) {
    // Redirecionar para login se não for o 
    header('Location: login.php'); // 
    exit;
}

// Verificar tempo de inatividade (opcional - 30 minutos)
$tempo_inatividade = 1800; // 30 minutos em segundos
if (isset($_SESSION['ultimo_acesso']) && (time() - $_SESSION['ultimo_acesso'] > $tempo_inatividade)) {
    session_destroy();
    header('Location: login.html?erro=2');
    exit;
}

// Atualizar último acesso
$_SESSION['ultimo_acesso'] = time();
?>