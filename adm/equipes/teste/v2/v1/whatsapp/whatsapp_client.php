<?php
// =====================================================
// WhatsApp Client — ApiBrasil (API WhatsApp Wpp)
// =====================================================

$config = require __DIR__ . '/config.php';
$api    = $config['apibrasil'];

function whatsappSendText(string $number, string $message): array
{
    global $api;

    $url = rtrim($api['base_url'], '/') . '/api/v2/whatsmeow/send/text';

    $payload = [
        'number' => preg_replace('/\D/', '', $number),
        'text'   => $message
    ];

    return whatsappRequest($url, $payload);
}

function whatsappRequest(string $url, array $payload): array
{
    global $api;

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . trim($api['token']),
        'DeviceToken: ' . trim($api['device_token'])
    ];

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 30
    ]);

    $response = curl_exec($ch);
    $error    = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($error) {
        return [
            'success'  => false,
            'httpCode' => 0,
            'response' => null,
            'raw'      => 'Erro CURL: ' . $error,
        ];
    }

    $decoded = json_decode($response, true);
    $httpOk  = ($httpCode >= 200 && $httpCode < 300);

    /* Detecta erro no corpo: campo "error" booleano true, ou "success" booleano false */
    $bodyOk = true;
    if (isset($decoded['error']) && $decoded['error'] === true) $bodyOk = false;
    if (isset($decoded['success']) && $decoded['success'] === false) $bodyOk = false;

    return [
        'success'  => $httpOk && $bodyOk,
        'httpCode' => $httpCode,
        'response' => $decoded,
        'raw'      => $response,
    ];
}
