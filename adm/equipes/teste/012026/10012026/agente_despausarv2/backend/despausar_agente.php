<?php
/**
 * ===================================================
 * DESPAUSAR AGENTE — EVOLUX (OFICIAL)
 * ===================================================
 * POST /api/v1/agents/{agent_id}/unpause
 * Header: token
 * ===================================================
 */

header("Content-Type: application/json; charset=utf-8");

$config = require __DIR__ . "/config.php";

$input = json_decode(file_get_contents("php://input"), true);
$agentId = $input["agent_id"] ?? null;

if (!$agentId) {
  echo json_encode([
    "success" => false,
    "error" => "agent_id não informado"
  ]);
  exit;
}

$url = rtrim($config["base_url"], "/") . "/api/v1/agents/{$agentId}/unpause";

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_POST           => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER     => [
    "token: {$config['token']}",
    "Content-Type: application/json"
  ],
  CURLOPT_TIMEOUT        => $config["timeout"] ?? 15
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
  echo json_encode([
    "success" => true
  ]);
} else {
  echo json_encode([
    "success" => false,
    "error" => "Erro ao despausar agente",
    "http" => $httpCode,
    "response" => $response
  ]);
}
