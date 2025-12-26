<?php
/**
 * ===================================================
 * BUSCAR AGENTE — BACKEND (PROXY EVOLUX)
 * ===================================================
 */

header("Content-Type: application/json; charset=utf-8");

$config = require __DIR__ . "/config.php";

$baseUrl = rtrim($config["base_url"], "/");
$token   = $config["token"];
$timeout = $config["timeout"] ?? 15;

$agentId = $_GET["agent_id"] ?? null;

if (!$agentId) {
    echo json_encode([
        "success" => false,
        "erro" => "agent_id não informado"
    ]);
    exit;
}

$url = "{$baseUrl}/api/v1/agents/{$agentId}";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => $timeout,
    CURLOPT_HTTPHEADER     => [
        "token: {$token}",
        "Accept: application/json"
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode([
        "success" => false,
        "erro"    => "Agente não encontrado",
        "raw"     => $response
    ]);
    exit;
}

$data = json_decode($response, true)["data"] ?? [];

echo json_encode([
    "success" => true,
    "data" => [
        "nome"  => $data["name"] ?? "",
        "login" => $data["login"] ?? "",
        "ramal" => $data["extension"] ?? "",
        "fila"  => $data["queues"][0]["name"] ?? "—"
    ]
], JSON_UNESCAPED_UNICODE);
