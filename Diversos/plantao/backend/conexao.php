<?php
/**
 * Conexão PDO com o banco (MySQL) + carga de ambiente/sessão.
 */

require_once dirname(__DIR__) . '/bootstrap.php';

/* =====================================================
   CONFIG BANCO DE DADOS
===================================================== */
$host     = getenv('DB_HOST') ?: '';
$dbname   = getenv('DB_NAME') ?: '';
$user     = getenv('DB_USER') ?: '';
$password = getenv('DB_PASS') ?: '';

if ($host === '' || $dbname === '' || $user === '') {
    error_log('[conexao] Variáveis de banco ausentes no .env');
    throw new RuntimeException('Configuração do banco de dados ausente. Verifique o arquivo .env');
}

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_TIMEOUT            => 5,
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
    PDO::ATTR_PERSISTENT         => false,
];

/* =====================================================
   CONEXÃO PDO
===================================================== */
try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        $options
    );
} catch (PDOException $e) {
    error_log('[conexao] Falha ao conectar: ' . $e->getMessage());
    throw new RuntimeException('Erro ao conectar no banco de dados.');
}
