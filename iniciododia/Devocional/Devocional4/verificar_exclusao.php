<?php
require 'conexao.php';
header('Content-Type: application/json');

$id = $_POST['id'] ?? null;
$usuario = $_POST['usuario'] ?? '';
$senha = $_POST['senha'] ?? '';

if (!$id || !$usuario || !$senha) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

try {
    // Verificar se é o usuário Anderson com a senha correta
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE nome = ? AND senha = ?");
    $stmt->execute([$usuario, $senha]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && $usuario['nome'] === 'anderson' && $usuario['senha'] === 'soueu') {
        // Excluir o devocional
        $stmt = $pdo->prepare("DELETE FROM devocionais WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Acesso negado. Contate o administrador.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}