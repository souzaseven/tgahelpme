<?php
// Configurações do banco de dados (substitua por variáveis de ambiente na produção)
$host = getenv('DB_HOST') ?: '108.167.151.50';
$dbname = getenv('DB_NAME') ?: 'tgamea80_SUPORTE';
$user = getenv('DB_USER') ?: 'tgamea80_tgamea80';
$password = getenv('DB_PASS') ?: 'anderson@2250';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5, // Timeout de 5 segundos
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        PDO::ATTR_PERSISTENT => false // Conexões persistentes podem ser problemáticas
    ]);
} catch (PDOException $e) {
    error_log('[' . date('Y-m-d H:i:s') . '] Erro na conexão: ' . $e->getMessage() . "\n", 3, 'database_errors.log');
    http_response_code(500);
    header('Content-Type: application/json');
    header("Access-Control-Allow-Origin: *");
    echo json_encode([
        'success' => false,
        'message' => 'Erro na conexão com o banco de dados',
        'error_code' => $e->getCode()
    ]);
    exit;
}
