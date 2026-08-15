<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use ApiBrasil\Baileys\BaileysInstanceService;
use ApiBrasil\Baileys\Config\ApiBrasilConfig;
use ApiBrasil\Baileys\Exception\ApiBrasilException;

$service = new BaileysInstanceService(ApiBrasilConfig::fromEnv());

try {
    // Obtém um novo QR code para parear/reconectar o device já existente.
    $response = $service->connect();

    if ($response->qrcode !== null && $response->qrcode->base64 !== null) {
        echo "QR code (base64) recebido, escaneie no WhatsApp do celular.\n";
        // Ex.: salvar em arquivo para abrir no navegador:
        // file_put_contents(__DIR__ . '/qrcode.html', '<img src="' . $response->qrcode->base64 . '">');
    } else {
        echo "Nenhum QR code retornado — dispositivo já deve estar conectado.\n";
    }

    $status = $service->connectionState();
    echo 'Status atual: ' . ($status->instance->status ?? 'desconhecido') . "\n";
} catch (ApiBrasilException $exception) {
    echo "Falha na APIBrasil [HTTP {$exception->getHttpStatusCode()}]: {$exception->getMessage()}\n";
} catch (\RuntimeException $exception) {
    echo "Falha de comunicação: {$exception->getMessage()}\n";
}
