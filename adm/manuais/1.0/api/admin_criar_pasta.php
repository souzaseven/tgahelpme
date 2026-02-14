<?php
header('Content-Type: application/json; charset=utf-8');

$baseDir = realpath(__DIR__ . '/../txt');
$data = json_decode(file_get_contents('php://input'), true);

$caminho = trim($data['caminho'] ?? '');
$nome    = trim($data['nome'] ?? '');

if (!$nome) {
  http_response_code(400);
  echo json_encode(['erro' => 'Nome da pasta é obrigatório']);
  exit;
}

$destino = $baseDir;

if ($caminho) {
  $destino .= DIRECTORY_SEPARATOR . $caminho;
}

$destino = realpath($destino) ?: $destino;

if (!str_starts_with($destino, $baseDir)) {
  http_response_code(403);
  echo json_encode(['erro' => 'Caminho inválido']);
  exit;
}

$novaPasta = $destino . DIRECTORY_SEPARATOR . $nome;

if (file_exists($novaPasta)) {
  echo json_encode(['erro' => 'Pasta já existe']);
  exit;
}

if (mkdir($novaPasta, 0775, true)) {
  echo json_encode(['sucesso' => true]);
} else {
  http_response_code(500);
  echo json_encode(['erro' => 'Falha ao criar pasta']);
}
