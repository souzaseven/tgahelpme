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

    // Cria tabela de logs se não existir
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            usuario VARCHAR(100),
            action VARCHAR(50),
            table_name VARCHAR(50),
            record_id INT,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
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
   LOGS
===================================================== */
function logAction($pdo, $action, $table, $id = null, $details = '') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO logs 
            (usuario, action, table_name, record_id, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'Sistema',
            $action,
            $table,
            $id,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Erro ao registrar log: " . $e->getMessage());
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
