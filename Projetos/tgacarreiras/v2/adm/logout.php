<?php
/**
 * Logout - Módulo Candidato
 * Plataforma de Vagas
 */

session_start();

/* ────────────────
   Limpar variáveis
──────────────── */
$_SESSION = [];

/* ────────────────
   Remover cookie da sessão
──────────────── */
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

/* ────────────────
   Destruir sessão
──────────────── */
session_destroy();

/* ────────────────
   Redirecionar
──────────────── */
header("Location: login.php");
exit;
