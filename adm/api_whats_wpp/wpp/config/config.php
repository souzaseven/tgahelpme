<?php

declare(strict_types=1);

/**
 * Carrega variáveis do arquivo .env (parser simples, sem dependências externas)
 * e expõe as configurações do painel via constantes globais.
 */

function loadEnv(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

loadEnv(__DIR__ . '/../.env');

define('API_BASE_URL', getenv('API_BASE_URL') ?: 'https://gateway.apibrasil.io/api/v2');
define('WHATSAPP_BEARER_TOKEN', getenv('WHATSAPP_BEARER_TOKEN') ?: '');
define('WHATSAPP_DEVICE_TOKEN', getenv('WHATSAPP_DEVICE_TOKEN') ?: '');
define('WHATSAPP_SESSION', getenv('WHATSAPP_SESSION') ?: 'painel_whatsapp');
define('DATA_DIR', __DIR__ . '/../data');

if (WHATSAPP_BEARER_TOKEN === '' || WHATSAPP_DEVICE_TOKEN === '') {
    error_log('[config] Atenção: WHATSAPP_BEARER_TOKEN ou WHATSAPP_DEVICE_TOKEN não definidos no .env');
}
