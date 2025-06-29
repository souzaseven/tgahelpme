<?php
// salvar_pergunta.php

// Conexão com o banco
$pdo = new PDO('mysql:host=localhost;dbname=chatbot;charset=utf8', 'usuario', 'senha');

// Prepara e salva a pergunta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['pergunta'])) {
    $pergunta = trim($_POST['pergunta']);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
    $hora = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("INSERT INTO perguntas (pergunta, ip, data_hora) VALUES (?, ?, ?)");
    $stmt->execute([$pergunta, $ip, $hora]);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'erro' => 'Pergunta vazia']);
}
