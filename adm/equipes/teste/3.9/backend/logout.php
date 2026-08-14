<?php
/**
 * backend/logout.php — TGA Clientes Web
 *
 * Mantive a lógica original de redirecionamento relativo (que funciona
 * bem para múltiplas versões do painel no mesmo servidor).
 *
 * O que mudei:
 *  1. Aponto para login.php em vez de login.html.
 *  2. Limpo o array $_SESSION antes de destruir — garante que dados
 *     sensíveis não fiquem na memória até o GC do PHP agir.
 *  3. Envio um cookie de sessão expirado para forçar o navegador a
 *     descartá-lo imediatamente, sem esperar o browser fechar.
 */

session_start();

// ── Limpeza segura da sessão ───────────────────────────────────────────────
// Limpo os dados antes de destruir para não deixar resíduo na memória
$_SESSION = [];

// Expiro o cookie de sessão no navegador imediatamente
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

// ── Redirecionamento relativo (lógica original mantida) ───────────────────
// Calcula o caminho base removendo "backend/logout.php" da URL atual,
// permitindo que o mesmo arquivo funcione em /adm/v1/, /adm/v2/, etc.
$path     = $_SERVER['REQUEST_URI'];
$parts    = explode('/', trim($path, '/'));
$basePath = implode('/', array_slice($parts, 0, -2));

header("Location: /{$basePath}/login.php");
exit;