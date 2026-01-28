<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/config.php';
require __DIR__ . '/evolux_client.php';

$client = new EvoluxClient($config);

try {
    $res = $client->get('/api/v1/queues');
    echo json_encode($res['response']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
