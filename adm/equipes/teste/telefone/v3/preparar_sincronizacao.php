<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_telefone'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'erro' => 'Nao autenticado.']);
  exit;
}

require_once './conexao_page.php';

$ordem = [
  'Alex Sandro Braulio',
  'Daniel Feix',
  'Willian Pereira Reis'
];

function calcularRotacao($atual, $ordem) {
  $idx = array_search($atual, $ordem, true);
  if ($idx === false) $idx = 0;

  return [
    'anterior' => $ordem[($idx - 1 + count($ordem)) % count($ordem)],
    'atual'    => $ordem[$idx],
    'proxima'  => $ordem[($idx + 1) % count($ordem)]
  ];
}

$acao = $_POST['acao'] ?? '';
$novoAtual = null;

if ($acao === 'avancar') {
  $atualBanco = 'Daniel Feix';
  $stmt = $pdo->query("SELECT atual_lider FROM rotacao_telefone WHERE id = 1");
  if ($stmt && ($row = $stmt->fetch())) {
    $atualBanco = $row['atual_lider'];
  }
  $rotAtual = calcularRotacao($atualBanco, $ordem);
  $novoAtual = $rotAtual['proxima'];
}

if ($acao === 'manual' && !empty($_POST['atual'])) {
  $novoAtual = $_POST['atual'];
}

if (!$novoAtual) {
  echo json_encode(['success' => false, 'erro' => 'Acao invalida.']);
  exit;
}

$rot = calcularRotacao($novoAtual, $ordem);

$pdo->prepare("UPDATE rotacao_telefone SET atual_lider=? WHERE id=1")->execute([$novoAtual]);

$stmtFila = $pdo->prepare("UPDATE operadores SET fila=? WHERE lider=?");
$stmtFila->execute(['Suporte Matriz', $rot['atual']]);
$stmtFila->execute(['Fila Matriz Chat/Whats', $rot['anterior']]);
$stmtFila->execute(['Fila Matriz Chat/Whats', $rot['proxima']]);

echo json_encode([
  'success' => true,
  'grupos' => [
    ['lider' => $rot['atual'], 'fila' => 'Suporte Matriz'],
    ['lider' => $rot['anterior'], 'fila' => 'Fila Matriz Chat/Whats'],
    ['lider' => $rot['proxima'], 'fila' => 'Fila Matriz Chat/Whats'],
  ]
]);
