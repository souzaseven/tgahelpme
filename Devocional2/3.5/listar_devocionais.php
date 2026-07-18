<?php
// Endpoint chamado pelo front-end (carregarDevocionais em script.js) para
// popular o histórico, o painel anterior/atual/próximo e os filtros
require_once 'conexao.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Busca todos os devocionais cadastrados, do mais recente para o mais antigo
    $stmt = $pdo->query('SELECT id, data, tema, texto, ministrado_por FROM devocionais ORDER BY data DESC');
    $devocionais = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Responde em JSON; se não houver registros, retorna array vazio (nunca null)
    echo json_encode([
        'success' => true,
        'data' => $devocionais ?: []
    ]);
} catch (Exception $e) {
    // Erro de banco/consulta: responde com HTTP 500 e detalhes do erro
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar devocionais: ' . $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
}
