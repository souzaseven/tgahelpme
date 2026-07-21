<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_telefone'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'erro' => 'Nao autenticado.']);
  exit;
}

$config = require __DIR__ . '/config_evolux.php';

$url = rtrim($config['EVOLUX_BASE_URL'], '/') . '/api/v1/queues';
$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT        => $config['EVOLUX_TIMEOUT'],
  CURLOPT_HTTPHEADER     => ['token: ' . $config['EVOLUX_TOKEN']],
]);

$resposta = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resposta === false || $httpCode !== 200) {
  echo json_encode(['success' => false, 'filas' => []]);
  exit;
}

$dados = json_decode($resposta, true);
echo json_encode(['success' => true, 'filas' => $dados['data'] ?? []]);
