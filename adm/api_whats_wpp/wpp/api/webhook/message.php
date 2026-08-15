<?php

declare(strict_types=1);

// Recebe o callback "wh_message" configurado no start() — mensagens
// recebidas pelo número conectado.

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/StateStore.php';

use App\StateStore;

header('Content-Type: application/json; charset=utf-8');

$payload = json_decode((string) file_get_contents('php://input'), true) ?: [];

(new StateStore(DATA_DIR))->appendMessage([
    'direction' => 'in',
    'payload' => $payload,
    'received_at' => date('c'),
]);

http_response_code(200);
echo json_encode(['ok' => true]);
