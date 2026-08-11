<?php

declare(strict_types=1);

/**
 * Inicializa a sessão PHP com configurações seguras.
 * Deve ser chamado no início de toda página/rota que dependa de sessão
 * (área do candidato, área da empresa, admin).
 */
function iniciarSessaoSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name('tga_sessao');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/**
 * Regenera o ID da sessão preservando os dados já armazenados.
 * OBRIGATÓRIO logo após um login bem-sucedido — previne session fixation.
 */
function regenerarSessao(): void
{
    session_regenerate_id(true);
}

/**
 * Encerra a sessão atual por completo (logout), limpando dados e cookie.
 */
function encerrarSessao(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $parametros['path'],
            $parametros['domain'],
            $parametros['secure'],
            $parametros['httponly']
        );
    }

    session_destroy();
}
