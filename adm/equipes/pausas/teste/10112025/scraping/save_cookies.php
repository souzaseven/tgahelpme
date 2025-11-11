<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cookies = $_POST['cookies'] ?? '';
    
    if (empty($cookies)) {
        echo json_encode(['success' => false, 'error' => 'Nenhum cookie recebido']);
        exit;
    }
    
    // Salvar cookies em arquivo
    $cookiesData = [
        'cookies' => $cookies,
        'updated_at' => date('Y-m-d H:i:s'),
        'timestamp' => time()
    ];
    
    if (file_put_contents('cookies.json', json_encode($cookiesData))) {
        echo json_encode(['success' => true, 'message' => 'Cookies atualizados com sucesso']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erro ao salvar arquivo']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
}
?>