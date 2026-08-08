<?php
require_once __DIR__ . '/includes/session.php';
session_unset();
session_destroy();

// Remove o cookie do navegador para não reautenticar com ID inválido
setcookie(session_name(), '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Lax',
]);

header('Location: login.php');
exit;
