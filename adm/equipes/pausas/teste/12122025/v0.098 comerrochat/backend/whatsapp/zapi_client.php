<?php
// ============================================================
// zapi_client.php — CLIENTE Z-API (COM CLIENT-TOKEN)
// ============================================================

// Carrega configuração
$configPath = __DIR__ . '/config.php';

if (!file_exists($configPath)) {
    throw new Exception("Arquivo config.php não encontrado");
}

$config = require $configPath;

// ------------------------------------------------------------
// FUNÇÃO DE ENVIO WHATSAPP
// ------------------------------------------------------------
function enviarWhatsapp($telefone, $mensagem) {
    global $config;

    // Validação mínima
    if (
        empty($config['base_url']) ||
        empty($config['instance_id']) ||
        empty($config['token']) ||
        empty($config['client_token'])
    ) {
        return [
            "success" => false,
            "erro"    => "Configuração da Z-API incompleta"
        ];
    }

    $url =
        rtrim($config['base_url'], '/') .
        "/instances/{$config['instance_id']}" .
        "/token/{$config['token']}" .
        "/send-text";

    $payload = [
        "phone"   => preg_replace('/\D/', '', $telefone),
        "message" => $mensagem
    ];

    $headers = [
        'Content-Type: application/json',
        'Client-Token: ' . $config['client_token'] // 🔑 OBRIGATÓRIO
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 15
    ]);

    $response = curl_exec($ch);
    $error    = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    // Erro de CURL
    if ($error) {
        return [
            "success" => false,
            "erro"    => "Erro CURL",
            "detalhe" => $error
        ];
    }

    // Decodifica resposta
    $decoded = json_decode($response, true);

    return [
        "success"   => ($httpCode >= 200 && $httpCode < 300),
        "httpCode" => $httpCode,
        "response" => $decoded,
        "raw"      => $response
    ];
}
