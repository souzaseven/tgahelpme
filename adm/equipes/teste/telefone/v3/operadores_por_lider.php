<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_telefone'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'erro' => 'Nao autenticado.']);
  exit;
}

require_once './conexao_page.php';

$lider = $_GET['lider'] ?? '';
if ($lider === '') {
  echo json_encode(['success' => false, 'erro' => 'Lider nao informado.']);
  exit;
}

$stmt = $pdo->prepare("SELECT id, nome, evolux_agent_id FROM operadores WHERE lider = ?");
$stmt->execute([$lider]);

echo json_encode(['success' => true, 'operadores' => $stmt->fetchAll()]);
