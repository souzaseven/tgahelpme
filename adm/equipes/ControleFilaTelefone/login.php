<?php
session_start();
require_once __DIR__ . '/conexao_page.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = trim($_POST['usuario'] ?? '');
$senha   = trim($_POST['senha'] ?? '');

/* consulta mínima, como solicitado */
$sql = "
  SELECT nome, senha, edita_fila_telefone
  FROM tgamea80_SUPORTE.usuarios
  WHERE nome = :usuario
  LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['usuario' => $usuario]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* usuário não encontrado */
if (!$user) {
  echo json_encode([
    'success' => false,
    'message' => 'Usuário não encontrado'
  ]);
  exit;
}

/* senha em texto puro */
if ($senha !== $user['senha']) {
  echo json_encode([
    'success' => false,
    'message' => 'Usuário ou senha inválidos'
  ]);
  exit;
}

/* permissão */
if ((int)$user['edita_fila_telefone'] !== 1) {
  echo json_encode([
    'success' => false,
    'message' => 'Usuário sem permissão'
  ]);
  exit;
}

/* login OK */
$_SESSION['usuario_telefone'] = [
  'nome' => $user['nome']
];

echo json_encode([
  'success' => true
]);
exit;
