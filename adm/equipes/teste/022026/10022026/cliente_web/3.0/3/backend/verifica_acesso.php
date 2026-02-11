<?php
/**
 * verifica_acesso.php — TGA Clientes Web
 *
 * O que mudei em relação ao original:
 *
 *  1. Troquei login.html por login.php nas três ocorrências de redirecionamento.
 *
 *  2. Adicionei session_regenerate_id() periódico — a cada 30 minutos renovo
 *     o ID de sessão para dificultar session hijacking mesmo em sessões longas.
 *
 *  3. Usei session_unset() + session_destroy() no lugar de só session_destroy()
 *     nos timeouts — mesma limpeza segura que apliquei no logout.php.
 *
 *  4. Corrigi o espaço ausente em "Location:https://..." que estava no timeout
 *     original (alguns servidores rejeitam headers malformados).
 *
 * O restante (lógica de sessão, checagem admweb, timeout de 2h) é idêntico.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// URL centralizada — se o domínio mudar, altero só aqui
define('LOGIN_URL', 'https://tgameajuda.com/adm/cliente_web/login.php');

/* ── Login existe? ────────────────────────────────────────────────────────── */
if (empty($_SESSION['usuario_id']) || empty($_SESSION['admweb'])) {
    header('Location: ' . LOGIN_URL);
    exit;
}

/* ── Permissão web ────────────────────────────────────────────────────────── */
if ((int)$_SESSION['admweb'] !== 1) {
    http_response_code(403);
    exit('Acesso negado');
}

/* ── Timeout (2h) ─────────────────────────────────────────────────────────── */
$timeout = 7200;

if (!isset($_SESSION['login_time'])) {
    session_unset();
    session_destroy();
    header('Location: ' . LOGIN_URL);
    exit;
}

if (time() - $_SESSION['login_time'] > $timeout) {
    session_unset();
    session_destroy();
    header('Location: ' . LOGIN_URL);
    exit;
}

// Atualizo o timestamp a cada requisição (janela deslizante de 2h)
$_SESSION['login_time'] = time();

/* ── Renovação periódica do ID de sessão (a cada 30min) ──────────────────── */
// Reduz janela de exploração em caso de session hijacking sem forçar logout.
if (empty($_SESSION['last_regen']) || time() - $_SESSION['last_regen'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regen'] = time();
}