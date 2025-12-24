<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexao.php';
$config = require __DIR__ . '/../config/evolux.php';

$operadorId    = intval($_POST['operador_id'] ?? 0);
$agentId       = intval($_POST['evolux_agent_id'] ?? 0);
$queueId       = intval($_POST['queue_id'] ?? 0);
$queueNome     = trim($_POST['queue_nome'] ?? '');

if (!$operadorId || !$agentId || !$queueId || !$queueNome) {
    echo json_encode([
        'success' => false,
        'erro' => 'Dados inválidos ou incompletos'
    ]);
    exit;
}

/* ===============================
   CHAMADA EVOLUX
================================ */
$url = rtrim($config['EVOLUX_BASE_URL'], '/') . "/api/v1/agents/{$agentId}";

$payload = json_encode([
    'queues' => [$queueId]
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST  => 'PUT',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => $config['EVOLUX_TIMEOUT'] ?? 15,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'token: ' . $config['EVOLUX_TOKEN'],
        'Content-Type: application/json'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode([
        'success' => false,
        'erro' => 'Evolux recusou a alteração',
        'http_code' => $httpCode,
        'resposta' => $response
    ]);
    exit;
}

/* ===============================
   ATUALIZA BANCO LOCAL
================================ */
$stmt = $pdo->prepare("
    UPDATE operadores
    SET fila = :fila,
        evolux_queue_id = :queue_id,
        data_atualizacao = NOW()
    WHERE id = :id
");

$stmt->execute([
    ':fila'     => $queueNome,
    ':queue_id'=> $queueId,
    ':id'       => $operadorId
]);

echo json_encode([
    'success' => true,
    'mensagem' => 'Fila alterada com sucesso no Evolux'
]);
