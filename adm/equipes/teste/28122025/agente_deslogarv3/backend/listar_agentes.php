<?php
header("Content-Type: application/json; charset=utf-8");

$config = require __DIR__ . "/config.php";

$ch = curl_init($config['base_url'] . "/api/v1/agents");

curl_setopt_array($ch, [
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
    echo $response;
} else {
    echo json_encode([
        "success" => false,
        "http_code" => $httpCode,
        "error" => "Erro ao listar agentes"
    ]);
}
