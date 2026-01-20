<?php
header('Content-Type: application/json; charset=utf-8');

$baseDir = realpath(__DIR__ . '/../txt');
$arquivo = $_GET['arquivo'] ?? '';

$caminho = realpath($baseDir . DIRECTORY_SEPARATOR . $arquivo);

if (!$caminho || !str_starts_with($caminho, $baseDir)) {
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso inválido']);
    exit;
}

if (!file_exists($caminho)) {
    http_response_code(404);
    echo json_encode(['erro' => 'Arquivo não encontrado']);
    exit;
}

echo json_encode([
  'conteudo' => file_get_contents($caminho)
], JSON_UNESCAPED_UNICODE);
