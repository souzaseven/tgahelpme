<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =====================================================
   HEADERS DE SEGURANÇA
===================================================== */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

/* =====================================================
   CONFIG BANCO DE DADOS
===================================================== */
$host = getenv('DB_HOST') ?: '108.167.151.50';
$dbname = getenv('DB_NAME') ?: 'tgamea80_SUPORTE';
$user = getenv('DB_USER') ?: 'tgamea80_tgamea80';
$password = getenv('DB_PASS') ?: 'anderson@2250';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_TIMEOUT => 5,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    PDO::ATTR_PERSISTENT => false
];

/* =====================================================
   CSRF TOKEN
===================================================== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =====================================================
   CONEXÃO PDO
===================================================== */
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $password,
        $options
    );

    // Tabela de auditoria completa (antes/depois)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS auditoria (
            id          INT          NOT NULL AUTO_INCREMENT,
            usuario     VARCHAR(100) NOT NULL DEFAULT 'Sistema',
            acao        ENUM('CREATE','UPDATE','DELETE') NOT NULL,
            modulo      VARCHAR(80)  NOT NULL,
            registro_id INT          DEFAULT NULL,
            descricao   VARCHAR(255) DEFAULT NULL,
            dados_antes JSON         DEFAULT NULL,
            dados_depois JSON        DEFAULT NULL,
            ip          VARCHAR(45)  DEFAULT NULL,
            criado_em   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_criado_em (criado_em),
            INDEX idx_modulo    (modulo),
            INDEX idx_acao      (acao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

} catch (PDOException $e) {
    error_log("Erro de conexão: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro na conexão com o banco de dados'
    ]);
    exit;
}

/* =====================================================
   AUDITORIA
===================================================== */

/**
 * Retorna string com os campos que mudaram: "campo1: antes→depois, campo2: antes→depois"
 */
function resumoAlteracoes(array $antes, array $depois): string {
    $partes = [];
    $todos  = array_unique(array_merge(array_keys($antes), array_keys($depois)));
    foreach ($todos as $k) {
        $va = (string)($antes[$k]  ?? '');
        $vd = (string)($depois[$k] ?? '');
        if ($va !== $vd) {
            $partes[] = "{$k}: \"{$va}\" → \"{$vd}\"";
        }
    }
    return implode(' | ', $partes);
}

function logAction($pdo, $acao, $modulo, $id = null, $dadosAntes = null, $dadosDepois = null, $descricao = '') {
    try {
        $usuario = $_SESSION['usuario'] ?? 'Sistema';
        $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $stmt = $pdo->prepare("
            INSERT INTO auditoria
              (usuario, acao, modulo, registro_id, descricao, dados_antes, dados_depois, ip)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $usuario,
            $acao,
            $modulo,
            $id,
            $descricao ?: null,
            $dadosAntes  !== null ? json_encode($dadosAntes,  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $dadosDepois !== null ? json_encode($dadosDepois, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $ip
        ]);
    } catch (Exception $e) {
        error_log("Erro ao registrar auditoria: " . $e->getMessage());
    }
}

/* =====================================================
   SANITIZAÇÃO (MANTIDA)
===================================================== */
function sanitizeData($data, $table) {
    $allowed = [
        'usuarios' => ['nome', 'email', 'telefone', 'senha', 'sexo', 'data_cadastro'],
        'operadores' => ['nome', 'link', 'lider', 'fila'],
        'devocionais' => ['tema', 'texto', 'ministrado_por', 'data']
    ];

    return array_intersect_key($data, array_flip($allowed[$table] ?? []));
}

/* =====================================================
   VALIDAÇÃO CSRF (CORRIGIDA)
===================================================== */
function validateCSRF(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $headers = function_exists('getallheaders') ? getallheaders() : [];

    $token =
        $headers['X-CSRF-Token']
        ?? $headers['x-csrf-token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';

    if (!$token || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}
