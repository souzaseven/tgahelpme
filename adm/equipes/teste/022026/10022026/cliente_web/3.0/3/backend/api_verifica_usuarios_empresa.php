<?php
require_once __DIR__ . '/conexao.php';
header('Content-Type: application/json; charset=utf-8');

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$codigo = trim($body['codigo_empresa'] ?? '');

if ($codigo === '') {
  echo json_encode([
    'success' => false,
    'message' => 'Código da empresa não informado'
  ]);
  exit;
}

$stmt = $pdo->prepare("
  SELECT COUNT(*) 
  FROM clientes_tga_web_usuarios
  WHERE codigo_empresa = ?
");
$stmt->execute([$codigo]);

$total = (int)$stmt->fetchColumn();

echo json_encode([
  'success' => true,
  'total'   => $total
]);
