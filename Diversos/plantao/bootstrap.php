<?php
/**
 * bootstrap.php — inicialização compartilhada da aplicação.
 *
 * Responsável por: carregar o .env, definir o timezone e iniciar a sessão
 * com cookies endurecidos (HttpOnly / SameSite / Secure quando em HTTPS).
 *
 * Deve ser o PRIMEIRO require de todo ponto de entrada
 * (index.php, login.php, logout.php, auth_guard.php, backend/conexao.php).
 */

if (defined('APP_BOOTSTRAPPED')) {
    return;
}
define('APP_BOOTSTRAPPED', true);
define('APP_ROOT', __DIR__);

/* =====================================================
   CARREGA .env
===================================================== */
function env_load(string $file): void
{
    if (!is_readable($file)) {
        return;
    }

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // remove aspas externas opcionais: FOO="bar" -> bar
        if (strlen($value) >= 2
            && ($value[0] === '"' || $value[0] === "'")
            && $value[strlen($value) - 1] === $value[0]) {
            $value = substr($value, 1, -1);
        }

        if ($key === '' || array_key_exists($key, $_ENV)) {
            continue;
        }

        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}
env_load(APP_ROOT . '/.env');

/* =====================================================
   TIMEZONE
===================================================== */
date_default_timezone_set(getenv('APP_TZ') ?: 'America/Cuiaba');

/* =====================================================
   SESSÃO (cookies endurecidos)
===================================================== */
if (session_status() === PHP_SESSION_NONE) {
    $isHttps =
        (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

    // Mesma sessão do restante do site (verifica_acesso.php usa a sessão padrão).
    // NÃO definir session_name aqui, senão o painel e o site ficam em sessões
    // diferentes e o login entra em loop de redirecionamento.
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $isHttps,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* =====================================================
   TOKEN CSRF (disponível para qualquer página)
===================================================== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =====================================================
   TIMEOUT DE INATIVIDADE + ROTAÇÃO PERIÓDICA DO ID
===================================================== */
$idleLimit = (int)(getenv('SESSION_IDLE_LIMIT') ?: 7200); // 2h

if (!empty($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > $idleLimit) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['last_activity'] = time();

if (empty($_SESSION['created_at'])) {
    $_SESSION['created_at'] = time();
} elseif (time() - (int)$_SESSION['created_at'] > 1800) { // 30 min
    session_regenerate_id(true);
    $_SESSION['created_at'] = time();
}
