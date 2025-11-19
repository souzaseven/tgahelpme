<?php
require_once "../conexao.php";

$operador = $_POST['operador_id'] ?? null;
$equipe   = $_POST['equipe'] ?? null;

if (!$operador || !$equipe) {
    echo json_encode(["success" => false, "erro" => "Dados incompletos"]);
    exit;
}

// Verifica se já está na fila
$check = $pdo->prepare("SELECT id FROM controle_fila WHERE operador_id = ? AND equipe = ?");
$check->execute([$operador, $equipe]);

if ($check->fetch()) {
    echo json_encode(["success" => false, "erro" => "Operador já está na fila"]);
    exit;
}

// Buscar última posição
$max = $pdo->prepare("SELECT MAX(posicao) FROM controle_fila WHERE equipe = ?");
$max->execute([$equipe]);
$posicao = ($max->fetchColumn() ?? 0) + 1;

// Inserir
$insert = $pdo->prepare("INSERT INTO controle_fila (equipe, operador_id, posicao) VALUES (?, ?, ?)");
$insert->execute([$equipe, $operador, $posicao]);

echo json_encode(["success" => true, "posicao" => $posicao]);
