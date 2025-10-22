<?php
require_once 'conexao.php';

$senha = $_POST['senha'] ?? '';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

try {
    $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE senha = ?");
    $stmt->execute([$senha]);

    echo json_encode(["sucesso" => $stmt->fetch() ? true : false]);
} catch (Exception $e) {
    echo json_encode(["sucesso" => false, "erro" => $e->getMessage()]);
}
