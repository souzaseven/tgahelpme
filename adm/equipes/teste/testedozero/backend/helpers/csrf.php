<?php

declare(strict_types=1);

/**
 * Proteção CSRF baseada em token de sessão.
 * Requer que iniciarSessaoSegura() já tenha sido chamado.
 */

/**
 * Gera (ou reaproveita) o token CSRF da sessão atual.
 * Use no formulário: <input type="hidden" name="csrf_token" value="...">
 */
function gerarTokenCSRF(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Valida um token CSRF recebido (formulário ou header AJAX X-CSRF-Token).
 * Comparação em tempo constante para evitar timing attacks.
 */
function validarTokenCSRF(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}
