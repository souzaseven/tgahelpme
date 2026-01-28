<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/evolux_client.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $config = require __DIR__ . '/config.php';
    $client = new EvoluxClient($config);

    $result = $client->get('/api/v1/queues', [
        'include_archived' => 'true'
    ]);

    if ($result['http_code'] !== 200) {
        throw new Exception('Erro ao buscar filas (HTTP ' . $result['http_code'] . ')');
    }

    echo json_encode([
        'success' => true,
        'queues'  => $result['response']['data'] ?? []
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
