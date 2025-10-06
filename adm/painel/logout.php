<?php
session_start();

// Registrar logout nos logs se possível
if (isset($_SESSION['usuario_logado'])) {
    // Você pode adicionar um registro de log aqui se desejar
    error_log("Usuário " . $_SESSION['usuario_logado'] . " fez logout");
}

// Destruir completamente a sessão
$_SESSION = array();

// Se deseja destruir a sessão completamente, apague também o cookie de sessão.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redirecionar para login
header("Location: login.html");
exit;
?>