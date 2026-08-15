<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use ApiBrasil\Baileys\BaileysMessageService;
use ApiBrasil\Baileys\Config\ApiBrasilConfig;
use ApiBrasil\Baileys\Dto\PresenceType;
use ApiBrasil\Baileys\Dto\SendTextOptions;
use ApiBrasil\Baileys\Dto\SendTextRequest;
use ApiBrasil\Baileys\Exception\ApiBrasilException;

// Config lida de variáveis de ambiente (.env) — nunca hardcode tokens aqui.
$service = new BaileysMessageService(ApiBrasilConfig::fromEnv());

$request = new SendTextRequest(
    number: '5531994359434',
    text: 'Teste Evolution API, via APIBRASIL!',
    options: new SendTextOptions(delay: 1, presence: PresenceType::Composing),
    homolog: false,
);

try {
    $response = $service->sendText($request);

    echo "Sucesso: {$response->success}\n";
    echo "Mensagem: {$response->message}\n";
    echo "ID da mensagem: {$response->messageId}\n";
} catch (ApiBrasilException $exception) {
    // Erro retornado pela APIBrasil (4xx/5xx) — já vem com status, código e corpo bruto.
    echo "Falha na APIBrasil [HTTP {$exception->getHttpStatusCode()}]: {$exception->getMessage()}\n";
    echo "Código do erro: " . ($exception->getApiErrorCode() ?? 'n/d') . "\n";
} catch (\RuntimeException $exception) {
    // Falha de comunicação (timeout, DNS, TLS, config ausente, etc.)
    echo "Falha de comunicação: {$exception->getMessage()}\n";
}
