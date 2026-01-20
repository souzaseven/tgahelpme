<?php
header('Content-Type: application/json; charset=utf-8');

$baseDir = realpath(__DIR__ . '/../txt');

$caminho = trim($_POST['caminho'] ?? '');

if (!isset($_FILES['arquivo'])) {
  http_response_code(400);
  echo json_encode(['erro' => 'Arquivo não enviado']);
  exit;
}

$arquivo = $_FILES['arquivo'];

if (pathinfo($arquivo['name'], PATHINFO_EXTENSION) !== 'txt') {
  http_response_code(400);
  echo json_encode(['erro' => 'Apenas arquivos .txt são permitidos']);
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

$arquivoFinal = $destino . DIRECTORY_SEPARATOR . basename($arquivo['name']);

if (move_uploaded_file($arquivo['tmp_name'], $arquivoFinal)) {
  echo json_encode(['sucesso' => true]);
} else {
  http_response_code(500);
  echo json_encode(['erro' => 'Falha no upload']);
}
