<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_telefone'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'erro' => 'Nao autenticado.']);
  exit;
}

require_once './conexao_page.php';
$config = require __DIR__ . '/config_evolux.php';

$operadorId    = (int)($_POST['operador_id'] ?? 0);
$evoluxAgentId = trim((string)($_POST['evolux_agent_id'] ?? ''));
$queueId       = trim((string)($_POST['queue_id'] ?? ''));
$queueNome     = trim((string)($_POST['queue_nome'] ?? ''));

if (!$operadorId || $evoluxAgentId === '' || $queueId === '') {
  echo json_encode(['success' => false, 'erro' => 'Parametros invalidos.']);
  exit;
}

$url = rtrim($config['EVOLUX_BASE_URL'], '/') . "/api/v1/agents/{$evoluxAgentId}";
$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CUSTOMREQUEST  => 'PUT',
  CURLOPT_TIMEOUT        => $config['EVOLUX_TIMEOUT'],
  CURLOPT_HTTPHEADER     => ['token: ' . $config['EVOLUX_TOKEN'], 'Content-Type: application/json'],
  CURLOPT_POSTFIELDS     => json_encode(['queues' => [(int) $queueId]], JSON_UNESCAPED_UNICODE),
]);

$resposta = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
  echo json_encode([
    'success'   => false,
    'erro'      => 'Evolux recusou a alteracao.',
    'http_code' => $httpCode,
    'resposta'  => $resposta,
  ]);
  exit;
}

$pdo->prepare("UPDATE operadores SET fila = ?, evolux_queue_id = ?, data_atualizacao = NOW() WHERE id = ?")
    ->execute([$queueNome, $queueId, $operadorId]);

echo json_encode(['success' => true]);
