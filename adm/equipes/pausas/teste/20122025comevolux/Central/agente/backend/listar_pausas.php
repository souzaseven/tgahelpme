<?php
header("Content-Type: application/json; charset=utf-8");

$config = require __DIR__ . '/config.php';

$agentId = $_GET['agent_id'] ?? null;
$page    = $_GET['page'] ?? 1;
$limit   = $_GET['limit'] ?? 50;

if (!$agentId) {
    http_response_code(400);
    echo json_encode(["erro" => "agent_id não informado"]);
    exit;
}

$url = "{$config['base_url']}/api/v1/agents/{$agentId}/pauses?limit={$limit}&page={$page}";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => $config['timeout'],
    CURLOPT_HTTPHEADER     => [
        "token: {$config['token']}"
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode);
echo $response;
