<?php
// ============================================================
// wpp_acao.php — Ações de gerenciamento WhatsMeow (ApiBrasil)
// POST acao=connect      → instance/connect  (gera QR)
// POST acao=disconnect   → instance/disconnect
// POST acao=logout       → instance/logout (DELETE)
// GET  acao=info         → instance/info/{devicekey}
// ============================================================

header("Content-Type: application/json; charset=utf-8");

$config = require __DIR__ . '/config.php';
$api    = $config['apibrasil'];

$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';

function wppReq(string $method, string $endpoint, array $api, array $payload = []): array
{
    $url = rtrim($api['base_url'], '/') . $endpoint;

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . trim($api['token']),
        'DeviceToken: ' . trim($api['device_token']),
    ];

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
    ];
    if (!empty($payload)) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
    curl_setopt_array($ch, $opts);

    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) return ['success' => false, 'erro' => 'Erro CURL: ' . $err];

    $data = json_decode($body, true);
    $ok   = ($code >= 200 && $code < 300) && (!isset($data['error']) || $data['error'] !== true);

    return [
        'success'   => $ok,
        'http_code' => $code,
        'data'      => $data,
        'raw'       => $body,
    ];
}

switch ($acao) {

    // ──────────────────────────────────────────────────────────
    // CONNECT — gera QR Code para autenticação
    // ──────────────────────────────────────────────────────────
    case 'connect':
        $r = wppReq('POST', '/api/v2/whatsmeow/instance/connect', $api);
        $d = $r['data'] ?? [];
        // ApiBrasil WhatsMeow retorna QR em response.qr.data.Qrcode (Q maiúsculo)
        $qr = $d['response']['qr']['data']['Qrcode'] ??
              $d['response']['qr']['data']['qrcode'] ??
              $d['response']['qr']['qrcode']         ??
              $d['qrcode']                           ??
              $d['qr']                               ??
              $d['base64']                           ??
              $d['data']['qrcode']                   ??
              null;
        echo json_encode([
            'success' => $r['success'],
            'qrcode'  => $qr,
            'msg'     => $d['message'] ?? ($r['success'] ? 'QR gerado' : 'Falha ao conectar'),
            'raw'     => $d,
        ], JSON_UNESCAPED_UNICODE);
        break;

    // ──────────────────────────────────────────────────────────
    // DISCONNECT — desconecta sessão (mantém dados)
    // ──────────────────────────────────────────────────────────
    case 'disconnect':
        $r = wppReq('POST', '/api/v2/whatsmeow/instance/disconnect', $api);
        echo json_encode([
            'success' => $r['success'],
            'msg'     => $r['data']['message'] ?? ($r['success'] ? 'Desconectado' : 'Falha ao desconectar'),
            'raw'     => $r['data'],
        ], JSON_UNESCAPED_UNICODE);
        break;

    // ──────────────────────────────────────────────────────────
    // LOGOUT — logout e remove a sessão
    // ──────────────────────────────────────────────────────────
    case 'logout':
        $r = wppReq('DELETE', '/api/v2/whatsmeow/instance/logout', $api);
        echo json_encode([
            'success' => $r['success'],
            'msg'     => $r['data']['message'] ?? ($r['success'] ? 'Logout realizado' : 'Falha no logout'),
            'raw'     => $r['data'],
        ], JSON_UNESCAPED_UNICODE);
        break;

    // ──────────────────────────────────────────────────────────
    // INFO — informações da instância
    // ──────────────────────────────────────────────────────────
    case 'info':
        $deviceKey = $api['device_token'] ?? '';
        $r = wppReq('GET', '/api/v2/whatsmeow/instance/info/' . urlencode($deviceKey), $api);
        echo json_encode([
            'success' => $r['success'],
            'info'    => $r['data'],
        ], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['success' => false, 'erro' => 'Ação inválida: ' . htmlspecialchars($acao)]);
}
