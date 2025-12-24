<?php
// ============================================================
// logout.php — encerra sessão e volta para o login
// ============================================================

session_set_cookie_params([
    'path'     => '/telefonia-evolux/filas_agentes/',
    'secure'   => true,      // HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// Limpa variáveis de sessão
$_SESSION = [];

// Remove cookie da sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroi sessão
session_destroy();

// 🔴 CAMINHO CORRETO
header("Location: /telefonia-evolux/filas_agentes/login.php");
exit;
