<?php
header('Content-Type: application/json; charset=utf-8');

$baseDir = realpath(__DIR__ . '/../txt');
$data = json_decode(file_get_contents('php://input'), true);

$caminho  = trim($data['caminho'] ?? '');
$nome     = trim($data['nome'] ?? '');
$conteudo = $data['conteudo'] ?? '';

if (!$nome || pathinfo($nome, PATHINFO_EXTENSION) !== 'txt') {
  http_response_code(400);
  echo json_encode(['erro' => 'Nome do arquivo deve terminar com .txt']);
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

if (!is_dir($destino)) {
  mkdir($destino, 0775, true);
}

$arquivo = $destino . DIRECTORY_SEPARATOR . $nome;

if (file_put_contents($arquivo, $conteudo) !== false) {
  echo json_encode(['sucesso' => true]);
} else {
  http_response_code(500);
  echo json_encode(['erro' => 'Erro ao salvar arquivo']);
}
