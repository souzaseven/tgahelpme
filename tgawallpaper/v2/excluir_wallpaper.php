<?php
require 'conexao.php';
session_start();

// Verificação simples (pode ser substituída por token/login)
if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] !== 'Anderson') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$id = $_POST['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID ausente.']);
    exit;
}

// Buscar nome e tipo do arquivo
$stmt = $pdo->prepare("SELECT nome_arquivo, tipo FROM wallpapers WHERE id = ?");
$stmt->execute([$id]);
$arquivo = $stmt->fetch();

if ($arquivo) {
    $caminho = "tgawallpaper/{$arquivo['tipo']}/{$arquivo['nome_arquivo']}";
    if (file_exists($caminho)) unlink($caminho);

    $stmt = $pdo->prepare("DELETE FROM wallpapers WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Arquivo excluído com sucesso.']);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Arquivo não encontrado.']);
}
