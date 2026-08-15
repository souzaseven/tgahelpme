<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/ApiBrasilClient.php';
require_once __DIR__ . '/../src/WhatsAppService.php';
require_once __DIR__ . '/../src/StateStore.php';

use App\ApiBrasilClient;
use App\ApiBrasilException;
use App\WhatsAppService;
use App\StateStore;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => true, 'message' => 'Método não permitido.']);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true) ?: [];
$session = trim((string) ($input['session'] ?? WHATSAPP_SESSION));

if ($session === '') {
    http_response_code(422);
    echo json_encode(['error' => true, 'message' => 'Informe o nome da sessão.']);
    exit;
}

$store = new StateStore(DATA_DIR);

try {
    $client = new ApiBrasilClient(API_BASE_URL, WHATSAPP_BEARER_TOKEN, WHATSAPP_DEVICE_TOKEN);
    $service = new WhatsAppService($client);

    $result = $service->start($session);

    $store->save([
        'session' => $session,
        'status' => 'connecting',
        'start_response' => $result,
        'messages' => $store->read()['messages'] ?? [],
        'updated_at' => date('c'),
    ]);

    echo json_encode(['error' => false, 'data' => $result]);
} catch (ApiBrasilException $e) {
    $store->merge([
        'status' => 'error',
        'last_error' => $e->getMessage(),
        'updated_at' => date('c'),
    ]);

    http_response_code($e->getHttpCode() ?: 500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'code' => $e->getApiCode(),
    ]);
}
