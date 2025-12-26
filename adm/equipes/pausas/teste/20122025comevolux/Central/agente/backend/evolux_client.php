<?php

$config = require __DIR__ . '/config.php';

function evoluxRequest(string $method, string $endpoint, ?array $body = null): array
{
    global $config;

    $ch = curl_init($config['base_url'] . $endpoint);

    $headers = [
        'Content-Type: application/json',
        'token: ' . $config['token']
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => $config['timeout'],
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        $erro = curl_error($ch);
        curl_close($ch);
        throw new Exception('Erro CURL Evolux: ' . $erro);
    }

    curl_close($ch);

    return [
        'code'     => $httpCode,
        'raw'      => $response, // 🔍 ajuda debug
        'body'     => json_decode($response, true)
    ];
}
