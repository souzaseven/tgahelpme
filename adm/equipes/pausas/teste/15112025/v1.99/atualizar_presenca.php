<?php
require "conexao.php";

$nome = $_POST['nome'] ?? null;

if (!$nome) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $pdo->prepare("UPDATE controle_pausa SET last_seen = NOW() WHERE nome_usuario = ?");
$stmt->execute([$nome]);

echo json_encode(['success' => true]);
