<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use ApiBrasil\Baileys\BaileysMessageService;
use ApiBrasil\Baileys\Config\ApiBrasilConfig;
use ApiBrasil\Baileys\Dto\PresenceType;
use ApiBrasil\Baileys\Dto\SendTextOptions;
use ApiBrasil\Baileys\Dto\SendTextRequest;
use ApiBrasil\Baileys\Exception\ApiBrasilException;

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido. Utilize POST.']);
    exit;
}

$input = json_decode(file_get_contents('php://input') ?: '[]', true);
$input = is_array($input) ? $input : [];

$number = trim((string) ($input['number'] ?? ''));
$text = trim((string) ($input['text'] ?? ''));

if ($number === '' || $text === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Os campos "number" e "text" são obrigatórios.']);
    exit;
}

try {
    $service = new BaileysMessageService(ApiBrasilConfig::fromEnv());

    $response = $service->sendText(new SendTextRequest(
        number: $number,
        text: $text,
        options: new SendTextOptions(delay: 1, presence: PresenceType::Composing),
        homolog: false,
    ));

    http_response_code(200);
    echo json_encode([
        'success' => $response->success,
        'message' => $response->message,
        'messageId' => $response->messageId,
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
    // Erro inesperado (rede, config ausente, etc.) — não expõe detalhes internos ao cliente.
    error_log('[send-message] ' . $exception->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno ao processar o envio.',
    ]);
}
