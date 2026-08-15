<?php

declare(strict_types=1);

// Recebe o callback "wh_connect" configurado no start() — disparado quando
// o número finalmente pareia com sucesso (QR Code escaneado).

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/StateStore.php';

use App\StateStore;

header('Content-Type: application/json; charset=utf-8');

$payload = json_decode((string) file_get_contents('php://input'), true) ?: [];

(new StateStore(DATA_DIR))->merge([
    'status' => 'connected',
    'connect_payload' => $payload,
    'updated_at' => date('c'),
]);

http_response_code(200);
echo json_encode(['ok' => true]);
