<?php
// ============================================================
// wpp_status.php — Status da conexão WhatsApp (ApiBrasil)
// GET: retorna status de conexão do dispositivo
// ============================================================

header("Content-Type: application/json; charset=utf-8");

$config = require __DIR__ . '/config.php';
$api = $config['apibrasil'];

function wppGet(string $endpoint, array $api): array
{
    $url = rtrim($api['base_url'], '/') . $endpoint;

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . trim($api['token']),
        'DeviceToken: ' . trim($api['device_token']),
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err)
        return ['code' => 0, 'error' => $err, 'body' => null];

    return [
        'code' => $code,
        'body' => json_decode($body, true),
        'raw' => $body,
    ];
}

try {
    // Usa info/{deviceKey} como proxy de status — endpoint /instance/status não existe na ApiBrasil
    $deviceKey = $api['device_token'] ?? '';
    $resp = wppGet('/api/v2/whatsmeow/instance/info/' . urlencode($deviceKey), $api);

    $body     = $resp['body'] ?? [];
    $httpCode = $resp['code'];
    $conectado = false;
    $statusMsg = 'Desconhecido';

    if ($httpCode === 200 && is_array($body)) {
        // WhatsMeow retorna device.status no corpo da resposta
        $deviceStatus = strtolower($body['device']['status'] ?? '');
        $state = $deviceStatus ?: strtolower(
            $body['status']    ??
            $body['state']     ??
            $body['connected'] ??
            ($body['data']['status'] ?? '')
        );
        $conectado = in_array($state, ['connected', 'open', 'loggedin', 'true', '1'], true)
            || ($body['connected'] ?? false) === true
            || ($body['device']['connected'] ?? false) === true;
        $statusMsg = ucfirst($state) ?: ($conectado ? 'Conectado' : 'Desconectado');
    } elseif ($httpCode === 401) {
        $statusMsg = 'Token inválido';
    } elseif ($httpCode === 404) {
        $statusMsg = 'Instância não encontrada';
    } elseif ($httpCode === 0) {
        $statusMsg = 'Erro de conexão: ' . ($resp['error'] ?? '');
    } else {
        $statusMsg = $body['message'] ?? ('Erro HTTP ' . $httpCode);
    }

    echo json_encode([
        'success'   => true,
        'conectado' => $conectado,
        'status'    => $statusMsg,
        'instance'  => $api['instance_id'] ?? '',
        'http_code' => $httpCode,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'erro' => $e->getMessage()]);
}
