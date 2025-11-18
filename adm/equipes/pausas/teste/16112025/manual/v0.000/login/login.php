<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

$acao = $_GET['acao'] ?? '';

if ($acao === 'validar_operador') {
    $input = json_decode(file_get_contents('php://input'), true);
    $nome = $input['nome'] ?? '';
    
    if (empty($nome)) {
        echo json_encode(['success' => false, 'error' => 'Nome não informado']);
        exit;
    }
    
    // Aceita qualquer nome com mais de 2 caracteres
    if (strlen($nome) >= 3) {
        echo json_encode([
            'success' => true,
            'nome_canonico' => ucwords(strtolower($nome)),
            'role' => 'operador'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Nome muito curto']);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Ação não suportada']);
}
?>