<?php
session_set_cookie_params([
    'path'     => '/telefonia-evolux/filas_agentes/',
    'secure'   => true,      // HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// Bloqueio total
if (
    empty($_SESSION['usuario_logado']) ||
    $_SESSION['usuario_logado'] !== true ||
    empty($_SESSION['editafila']) ||
    $_SESSION['editafila'] !== 1
) {
    header("Location: /telefonia-evolux/filas_agentes/login.php");
    exit;
}

// ⏱️ Expiração da sessão (30 minutos)
if (
    !empty($_SESSION['ultimo_acesso']) &&
    time() - $_SESSION['ultimo_acesso'] > 1800
) {
    session_destroy();
    header("Location: /telefonia-evolux/filas_agentes/login.php?erro=exp");
    exit;
}

$_SESSION['ultimo_acesso'] = time();
