<?php
header("Content-Type: application/json; charset=utf-8");

$config = require __DIR__ . "/config.php";

$input = json_decode(file_get_contents("php://input"), true);
$agentId = $input["agent_id"] ?? null;

if (!$agentId) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "erro" => "agent_id não informado"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$url = rtrim($config["base_url"], "/") . "/api/v1/agents/{$agentId}/unpause";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => $config["timeout"],
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        "token: {$config['token']}",
        "Accept: application/json"
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode);

echo json_encode([
    "success"  => $httpCode >= 200 && $httpCode < 300,
    "agent_id" => $agentId,
    "http"     => $httpCode,
    "raw"      => $response
], JSON_UNESCAPED_UNICODE);
