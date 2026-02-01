<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/conexao.php';

    $r = $pdo->query("SHOW TABLES LIKE 'plantoes_%'")->fetchAll();

    echo json_encode([
        'success' => true,
        'tables' => $r
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'erro' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
