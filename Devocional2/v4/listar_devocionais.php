<?php
require_once 'conexao.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->query('SELECT id, data, tema, texto, ministrado_por FROM devocionais ORDER BY data DESC');
    $devocionais = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $devocionais ?: []
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar devocionais: ' . $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
}
