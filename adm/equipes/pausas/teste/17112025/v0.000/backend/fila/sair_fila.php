<?php
require_once "../conexao.php";

$operador = $_POST['operador_id'] ?? null;
$equipe   = $_POST['equipe'] ?? null;

if (!$operador || !$equipe) {
    echo json_encode(["success" => false, "erro" => "Dados incompletos"]);
    exit;
}

// Remover operador
$del = $pdo->prepare("DELETE FROM controle_fila WHERE operador_id = ? AND equipe = ?");
$del->execute([$operador, $equipe]);

// Reorganizar posições
$sql = "SELECT id FROM controle_fila WHERE equipe = ? ORDER BY posicao ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$equipe]);
$f = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pos = 1;
foreach ($f as $row) {
    $u = $pdo->prepare("UPDATE controle_fila SET posicao = ? WHERE id = ?");
    $u->execute([$pos++, $row['id']]);
}

echo json_encode(["success" => true]);
