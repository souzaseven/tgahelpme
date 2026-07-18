<?php
// Endpoint chamado por handleConfirmarExclusao (script.js) para autenticar
// e, se autorizado, excluir um devocional
require 'conexao.php';
header('Content-Type: application/json');

// Dados recebidos via POST: id do devocional a excluir + credenciais informadas
$id = $_POST['id'] ?? null;
$usuario = $_POST['usuario'] ?? '';
$senha = $_POST['senha'] ?? '';

// Validação básica: todos os campos são obrigatórios
if (!$id || !$usuario || !$senha) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

try {
    // Busca o usuário informado na tabela de usuários
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE nome = ? AND senha = ?");
    $stmt->execute([$usuario, $senha]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Além de existir na tabela, só o usuário "anderson" (fixo no código) pode excluir
    if ($usuario && $usuario['nome'] === 'anderson' && $usuario['senha'] === 'soueu') {
        // Autorizado: exclui o devocional pelo id
        $stmt = $pdo->prepare("DELETE FROM devocionais WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
    } else {
        // Usuário/senha não confere ou não é o autorizado a excluir
        echo json_encode(['success' => false, 'message' => 'Acesso negado. Contate o administrador.']);
    }
} catch (Exception $e) {
    // Erro de banco/consulta
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}