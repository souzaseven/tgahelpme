<?php
require_once __DIR__ . '/../conexao.php';
$config = require __DIR__ . '/../config/evolux.php';

$url = $config['EVOLUX_BASE_URL'] . '/api/v1/queues';

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => ['token: '.$config['EVOLUX_TOKEN']]
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

echo json_encode([
  "success" => true,
  "filas" => $data['data'] ?? []
], JSON_UNESCAPED_UNICODE);
