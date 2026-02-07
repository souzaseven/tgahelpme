<?php
require_once __DIR__ . '/conexao.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$nome  = trim($body['nome'] ?? '');
$senha = trim($body['senha'] ?? '');

if ($nome === '' || $senha === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
  exit;
}

$stmt = $pdo->prepare("
  SELECT id, nome, senha, admweb
  FROM usuarios
  WHERE nome = :nome
  LIMIT 1
");
$stmt->execute([':nome' => $nome]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* ❌ senha errada */
if (!$user || $user['senha'] !== $senha) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Usuário ou senha incorretos']);
  exit;
}

/* ❌ sem permissão */
if ((int)$user['admweb'] !== 1) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Acesso não autorizado']);
  exit;
}

/* ✅ login OK */
session_start();
$_SESSION['usuario_id'] = $user['id'];
$_SESSION['usuario']    = $user['nome'];
$_SESSION['admweb']     = 1;
$_SESSION['login_time'] = time();

echo json_encode(['success' => true]);
