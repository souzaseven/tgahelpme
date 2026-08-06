<?php
// ============================================================
// wpp_qrcode.php — Proxy backend para QR Code WhatsMeow
// GET: retorna base64 do QR Code para reconexão
// ============================================================

header("Content-Type: application/json; charset=utf-8");

$config = require __DIR__ . '/config.php';
$api = $config['apibrasil'];

$url = rtrim($api['base_url'], '/') . '/api/v2/whatsmeow/instance/qr';

$headers = [
    'Authorization: Bearer ' . trim($api['token']),
    'DeviceToken: ' . trim($api['device_token']),
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 20,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['success' => false, 'erro' => 'Erro CURL: ' . $err]);
    exit;
}

$data = json_decode($body, true);

if ($code === 401) {
    echo json_encode(['success' => false, 'erro' => 'Token inválido ou expirado. Atualize o token no config.php.', 'http_code' => 401]);
    exit;
}

if ($code === 400) {
    $apiMsg = $data['message'] ?? $data['error'] ?? $data['msg'] ?? null;
    $conectado = $apiMsg && preg_match('/connect|session|ativo|already|logged/i', $apiMsg);
    echo json_encode([
        'success'    => false,
        'ja_conectado' => (bool) $conectado,
        'erro'       => $conectado
            ? 'Instância já conectada — nenhum QR necessário.'
            : ('Erro 400: ' . ($apiMsg ?? $body)),
        'http_code'  => 400,
        'raw'        => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($code !== 200 || empty($data)) {
    $apiMsg = $data['message'] ?? $data['error'] ?? null;
    echo json_encode(['success' => false, 'erro' => 'Erro ao buscar QR Code (HTTP ' . $code . ')' . ($apiMsg ? ': ' . $apiMsg : '.'), 'raw' => $data]);
    exit;
}

// ApiBrasil WhatsMeow retorna QR em response.qr.data.Qrcode (Q maiúsculo)
$qrcode = $data['response']['qr']['data']['Qrcode'] ??
          $data['response']['qr']['data']['qrcode'] ??
          $data['response']['qr']['qrcode']         ??
          $data['qrcode']                           ??
          $data['qr']                               ??
          $data['data']['qrcode']                   ??
          null;

echo json_encode([
    'success' => $qrcode !== null,
    'qrcode'  => $qrcode,
    'raw'     => $data,
], JSON_UNESCAPED_UNICODE);
