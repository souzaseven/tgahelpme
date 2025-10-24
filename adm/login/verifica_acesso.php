<?php
session_start();

// Tempo máximo de inatividade (5 minutos)
$tempoMaximo = 5 * 60;

if (isset($_SESSION['ultimo_acesso'])) {
    $tempoInativo = time() - $_SESSION['ultimo_acesso'];
    if ($tempoInativo > $tempoMaximo) {
        session_unset();
        session_destroy();
        header('Location: /login.html?logoutType=3');
        exit;
    }
}
$_SESSION['ultimo_acesso'] = time(); // atualiza o timestamp

// Verifica se está autenticado
if (!isset($_SESSION['suporte_autenticado']) || $_SESSION['suporte_autenticado'] !== true) {
    $_SESSION['url_origem'] = $_SERVER['REQUEST_URI']; // Salva URL atual
    header('Location: /login.html');
    exit;
}
