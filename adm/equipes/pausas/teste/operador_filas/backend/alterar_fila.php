<?php
require_once __DIR__ . '/../conexao.php';
$config = require __DIR__ . '/../config/evolux.php';

$id = (int)$_POST['operador_id'];
$agent = (int)$_POST['agent_id'];
$queue = (int)$_POST['queue_id'];
$nome  = $_POST['queue_nome'];

$url = $config['EVOLUX_BASE_URL'] . "/api/v1/agents/{$agent}";

$payload = json_encode(["queues" => [$queue]]);

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_CUSTOMREQUEST => 'PUT',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POSTFIELDS => $payload,
  CURLOPT_HTTPHEADER => [
    'token: '.$config['EVOLUX_TOKEN'],
    'Content-Type: application/json'
  ]
]);

curl_exec($ch);
curl_close($ch);

$stmt = $pdo->prepare("
  UPDATE operadores
  SET fila = ?, evolux_queue_id = ?, data_atualizacao = NOW()
  WHERE id = ?
");
$stmt->execute([$nome, $queue, $id]);

echo json_encode(["success" => true]);
