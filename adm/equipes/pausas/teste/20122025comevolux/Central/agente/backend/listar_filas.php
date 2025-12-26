<?php
/**
 * =====================================================
 * listar_filas.php — Lista filas da Evolux
 * =====================================================
 */

header('Content-Type: application/json; charset=utf-8');

try {
    $config = require __DIR__ . '/config.php';

    // 🔒 Validação da configuração
    if (
        empty($config['base_url']) ||
        empty($config['token'])
    ) {
        throw new Exception('Configuração da Evolux inválida');
    }

    $url = rtrim($config['base_url'], '/') . '/api/v1/queues';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'token: ' . $config['token']
        ],
        CURLOPT_TIMEOUT => $config['timeout'] ?? 15
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        throw new Exception('Erro CURL: ' . curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Erro Evolux HTTP {$httpCode}");
    }

    $data = json_decode($response, true);

    echo json_encode([
        'success' => true,
        'filas'   => $data['data'] ?? []
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'erro'    => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
