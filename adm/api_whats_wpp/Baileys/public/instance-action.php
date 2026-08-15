<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use ApiBrasil\Baileys\BaileysInstanceService;
use ApiBrasil\Baileys\Config\ApiBrasilConfig;
use ApiBrasil\Baileys\Exception\ApiBrasilException;

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido. Utilize POST.']);
    exit;
}

$input = json_decode(file_get_contents('php://input') ?: '[]', true);
$input = is_array($input) ? $input : [];
$action = (string) ($input['action'] ?? '');

$allowedActions = ['connect', 'connectionState', 'restart', 'logout'];

if (!in_array($action, $allowedActions, true)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Ação inválida. Use uma de: ' . implode(', ', $allowedActions) . '.',
    ]);
    exit;
}

try {
    $service = new BaileysInstanceService(ApiBrasilConfig::fromEnv());

    $result = match ($action) {
        'connect' => $service->connect(),
        'connectionState' => $service->connectionState(),
        'restart' => $service->restart(),
        'logout' => $service->logout(),
    };

    http_response_code(200);
    echo json_encode([
        'success' => !$result->error,
        'message' => $result->message,
        'instance' => $result->instance !== null ? [
            'instanceName' => $result->instance->instanceName,
            'instanceId' => $result->instance->instanceId,
            'status' => $result->instance->status,
        ] : null,
        'qrcode' => $result->qrcode !== null ? [
            'base64' => $result->qrcode->base64,
            'pairingCode' => $result->qrcode->pairingCode,
        ] : null,
    ]);
} catch (ApiBrasilException $exception) {
    $status = $exception->getHttpStatusCode();
    http_response_code($status >= 400 && $status < 600 ? $status : 502);

    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
        'errorCode' => $exception->getApiErrorCode(),
    ]);
} catch (\Throwable $exception) {
    error_log('[instance-action] ' . $exception->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno ao processar a ação.',
    ]);
}
