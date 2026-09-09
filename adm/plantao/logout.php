<?php
require_once __DIR__ . '/bootstrap.php';

/* A sessão é compartilhada com o site (verifica_acesso.php); por isso
   limpamos só as chaves do painel — o usuário continua logado no site. */
unset(
    $_SESSION['admin_logged'],
    $_SESSION['csrf_token'],
    $_SESSION['login_attempts'],
    $_SESSION['created_at']
);

session_regenerate_id(true);

header('Location: login.php');
exit;
