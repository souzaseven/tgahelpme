<?php
/**
 * ===================================================
 * AGENTE — PAUSAR (EVOLUX API PROXY)
 * ===================================================
 * Endpoint:
 * POST /api/v1/agents/{agent_id}/pause
 *
 * Body JSON:
 * {
 *   "pause_id": 1
 * }
 * ===================================================
 */

header("Content-Type: application/json; charset=utf-8");

$config = require __DIR__ . "/config.php";

$baseUrl = rtrim($config['base_url'], '/');
$token   = $config['token'];
$timeout = $config['timeout'] ?? 15;

// Captura JSON do frontend
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

// Validações
$agentId = intval($data['agent_id'] ?? 0);
$pauseId = intval($data['pause_id'] ?? 0);

if ($agentId <= 0 || $pauseId <= 0) {
    echo json_encode([
        "success" => false,
        "error"   => "agent_id e pause_id são obrigatórios."
    ]);
    exit;
}

// URL da API Evolux
$url = "{$baseUrl}/api/v1/agents/{$agentId}/pause";

// Body para API
$payload = json_encode([
    "pause_id" => $pauseId
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => "POST",
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        "Content-Type: application/json",
        "token: {$token}"
    ],
    CURLOPT_TIMEOUT        => $timeout
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);

curl_close($ch);

if ($error) {
    echo json_encode([
        "success" => false,
        "error"   => "Erro CURL: {$error}"
    ]);
    exit;
}

// Resposta OK
if ($httpCode === 200) {
    echo json_encode([
        "success" => true,
        "message" => "Agente pausado com sucesso.",
        "api"     => json_decode($response, true)
    ]);
    exit;
}

// Erro da API
echo json_encode([
    "success"   => false,
    "httpCode" => $httpCode,
    "api"      => json_decode($response, true)
]);
