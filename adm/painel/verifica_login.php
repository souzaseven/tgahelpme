<?php
session_start();

// Verificar se o usuário está logado e é o Anderson
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== 'anderson' || !isset($_SESSION['is_admin'])) {
    // Redirecionar para login se não for o usuário autorizado
    header('Location: login.html');
    exit;
}

// Verificar tempo de inatividade (30 minutos)
$tempo_inatividade = 1800; // 30 minutos em segundos
if (isset($_SESSION['ultimo_acesso']) && (time() - $_SESSION['ultimo_acesso'] > $tempo_inatividade)) {
    session_destroy();
    header('Location: login.html?erro=2'); // erro=2 para tempo de inatividade
    exit;
}

// Atualizar último acesso
$_SESSION['ultimo_acesso'] = time();

// Renovar o CSRF token periodicamente para maior segurança
if (!isset($_SESSION['csrf_token']) || time() - ($_SESSION['csrf_token_time'] ?? 0) > 3600) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}
?>