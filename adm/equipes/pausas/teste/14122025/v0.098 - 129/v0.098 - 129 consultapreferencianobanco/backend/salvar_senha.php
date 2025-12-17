<?php
require 'conexao.php'; 

$id = $_POST['id'] ?? null;
$senha = $_POST['senha'] ?? null;

if (!$id || !$senha) {
    echo json_encode(['success' => false, 'erro' => 'Dados incompletos']);
    exit;
}

// Grava como texto puro — futuramente altere para hash com password_hash
$stmt = $pdo->prepare("UPDATE controle_pausa SET senha = :senha WHERE id = :id");
$ok = $stmt->execute(['senha' => $senha, 'id' => $id]);

echo json_encode(['success' => $ok]);
