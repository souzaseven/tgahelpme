<?php
header("Content-Type: application/json; charset=utf-8");

$config = require __DIR__ . '/config.php';
$input  = json_decode(file_get_contents("php://input"), true);

$ids = $input['ids'] ?? [];

if (!is_array($ids) || empty($ids)) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "ids ausentes"]);
    exit;
}

$resultados = [];
$baseUrl = rtrim($config['base_url'], '/');

foreach ($ids as $agentId) {

    $url = "{$baseUrl}/api/v1/agents/{$agentId}/logoff";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $config['timeout'],
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            "token: {$config['token']}"
        ]
    ]);

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $resultados[] = [
        "agent_id" => $agentId,
        "http"     => $httpCode
    ];
}

echo json_encode([
    "success"    => true,
    "resultados" => $resultados,
    "status"     => "offline"
], JSON_UNESCAPED_UNICODE);
