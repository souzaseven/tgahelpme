<?php
header("Content-Type: application/json; charset=utf-8");

$config = require __DIR__ . "/config.php";

$agentId = $_POST['agent_id'] ?? null;

if (!$agentId) {
    echo json_encode([
        "success" => false,
        "error" => "agent_id não informado"
    ]);
    exit;
}

$url = $config['base_url'] . "/api/v1/agents/{$agentId}/logoff";

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "token: {$config['token']}"
    ],
    CURLOPT_TIMEOUT => $config['timeout'] ?? 15
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo json_encode([
        "success" => true,
        "message" => "Agente deslogado com sucesso"
    ]);
} else {
    echo json_encode([
        "success" => false,
        "http_code" => $httpCode,
        "error" => "Falha ao deslogar agente",
        "response" => $response
    ]);
}
