<?php
// ============================================================
// conexao.php — Conexão central com o banco (Controle Financeiro v1)
// ============================================================

// Impede acesso direto ao arquivo
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(["erro" => "Acesso direto não permitido."], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// CONFIGURAÇÃO (constantes de ambiente/banco vêm de config/config.php)
// ============================================================
require_once __DIR__ . '/../../config/config.php';

// ============================================================
// CONEXÃO PDO
// ============================================================
try {
    $driver = DB_DRIVER;

    $dsn = $driver === 'sqlite'
        ? 'sqlite:' . DB_NAME
        : 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    $opcoes = $driver === 'sqlite'
        ? [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
        : [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-03:00'"
        ];

    $pdo = new PDO(
        $dsn,
        $driver === 'sqlite' ? null : DB_USER,
        $driver === 'sqlite' ? null : DB_PASS,
        $opcoes
    );

    if ($driver === 'sqlite') {
        $pdo->exec('PRAGMA foreign_keys = ON');
        // SQLite não tem NOW()/MONTH()/YEAR() nativos; vários endpoints
        // usam essas funções (sintaxe MySQL) — estes shims existem só
        // para permitir testar esses endpoints contra SQLite sem
        // alterar o SQL de produção.
        // Retornam STRING (não int): parâmetros vinculados via
        // execute([...]) chegam ao SQLite como TEXT, e comparação entre
        // um resultado de função (sem afinidade de coluna) e um
        // parâmetro de tipos diferentes (INTEGER vs TEXT) nunca dá
        // igual no SQLite — só funciona TEXT=TEXT.
        $pdo->sqliteCreateFunction('NOW',   static fn () => date('Y-m-d H:i:s'));
        $pdo->sqliteCreateFunction('MONTH', static fn (?string $d) => $d ? (string)(int)substr($d, 5, 2) : null);
        $pdo->sqliteCreateFunction('YEAR',  static fn (?string $d) => $d ? (string)(int)substr($d, 0, 4) : null);
    }
} catch (PDOException $e) {

    $msgErro = (APP_ENV === 'dev') ? $e->getMessage() : "Erro interno.";

    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "erro"    => "Falha ao conectar ao banco de dados.",
        "detalhe" => $msgErro
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// Utilitário: resposta JSON padronizada
// ============================================================
function respostaJSON(array $array): never
{
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($array, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function erroInterno(Throwable $e, string $msg = 'Erro interno.'): never
{
    error_log('[ControleFinanceiro] ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    respostaJSON(['success' => false, 'erro' => (defined('APP_ENV') && APP_ENV === 'dev') ? $e->getMessage() : $msg]);
}

// ─── Handler global de exceções não capturadas ───────────────────────────────
set_exception_handler(function (Throwable $e) {
    // Evita double-output se já foi enviada resposta
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    $debug = defined('APP_ENV') && APP_ENV === 'dev';
    echo json_encode([
        'success' => false,
        'erro'    => $debug ? $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine()
                            : 'Erro interno do servidor.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
    // Converte erros PHP em exceções para serem capturados pelo handler acima
    if (!(error_reporting() & $errno)) return false;
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// ── Headers de segurança ──────────────────────────────────────
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// CSP: 'unsafe-inline' em script/style é necessário porque o app usa
// <script>/<style>/onclick= inline em toda a base (sem nonce) — mesmo
// assim, restringir default-src/script-src/connect-src a 'self' + só
// os CDNs realmente usados já bloqueia a carga de recurso de qualquer
// domínio arbitrário, que é o vetor mais comum de payload de XSS.
header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; " .
    "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
    "img-src 'self' data: https://cdn-icons-png.flaticon.com; " .
    "connect-src 'self'; " .
    "object-src 'none'; " .
    "base-uri 'self'; " .
    "frame-ancestors 'none'"
);

// ── Sessão e CSRF ─────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    // 'secure' fica condicional a HTTPS: forçar sempre quebraria o
    // cookie em ambiente local/dev servido por HTTP puro (o browser
    // recusa gravar cookie "secure" fora de conexão HTTPS).
    $httpsAtivo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    session_name('financeOS_sess');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => $httpsAtivo,
    ]);
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'DELETE', 'PUT', 'PATCH'])) {
    $reqToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$reqToken || !hash_equals($_SESSION['csrf_token'], $reqToken)) {
        http_response_code(403);
        respostaJSON(['success' => false, 'erro' => 'Requisição inválida.']);
    }
}

// ── Autenticação (senha única de acesso) ───────────────────────
// login.php e backend/api/auth.php ficam fora do gate (são a porta de
// entrada); todo o resto — páginas e demais endpoints, incluindo os
// scripts de setup na raiz (iniciar.php, migrar.php) — exige sessão
// autenticada, já que este arquivo é incluído por todos eles.
$scriptAtual   = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$isRotaDeLogin = str_ends_with($scriptAtual, '/login.php')
              || str_ends_with($scriptAtual, '/backend/api/auth.php');

if (!$isRotaDeLogin && empty($_SESSION['autenticado'])) {
    if (str_contains($scriptAtual, '/backend/api/')) {
        http_response_code(401);
        respostaJSON(['success' => false, 'erro' => 'Não autenticado.']);
    }
    // Evita dirname(): no Windows ele devolve "\" (separador de diretório
    // do SO) em vez de "/" para o caso raiz, quebrando este header.
    $barra   = strrpos($scriptAtual, '/');
    $baseDir = $barra !== false ? substr($scriptAtual, 0, $barra) : '';
    header('Location: ' . $baseDir . '/login.php');
    exit;
}
