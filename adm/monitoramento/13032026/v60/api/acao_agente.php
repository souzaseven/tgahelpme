<?php
$config = require '../config/api.php';

$agent_id = $_POST['agent_id'] ?? null;
$acao     = $_POST['acao']     ?? null;
$pause_id = $_POST['pause_id'] ?? null;

if (!$agent_id || !$acao) {
    http_response_code(400);
    echo json_encode(["erro" => "Parâmetros inválidos"]);
    exit;
}

$url = $config['base_url'] . "/api/v1/agents/{$agent_id}/{$acao}";

$body = null;
if ($acao === "pause" && $pause_id) {
    $body = json_encode(["pause_id" => (int)$pause_id]);
}

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => $config['timeout'],
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        "token: " . $config['token'],
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS     => $body ?? ""
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

http_response_code($httpCode);
header('Content-Type: application/json');
echo $response;