<?php
// includes/auth.php — Controle centralizado de autenticação
// Adicione aqui os usuários permitidos: 'usuario' => 'senha'
$USUARIOS_VALIDOS = [
    'maiara'   => 'teste',
    'anderson' => 'soueu',
];

/**
 * Verifica se o usuário está logado. Redireciona para login.php caso não esteja.
 * Chame session_start() ANTES de incluir este arquivo nas páginas admin.
 */
function auth_guard(): void
{
    if (empty($_SESSION['usuario'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Versão para endpoints AJAX: retorna JSON 403 em vez de redirecionar.
 */
function auth_guard_ajax(): void
{
    if (empty($_SESSION['usuario'])) {
        echo json_encode(['ok' => false, 'msg' => 'Não autorizado.']);
        exit;
    }
}

/**
 * Verifica credenciais de login.
 */
function verificar_login(string $usuario, string $senha): bool
{
    global $USUARIOS_VALIDOS;
    return isset($USUARIOS_VALIDOS[$usuario]) && $USUARIOS_VALIDOS[$usuario] === $senha;
}
