<?php
require_once "../conexao.php";

$equipe = $_GET['equipe'] ?? null;

if (!$equipe) {
    echo json_encode(["success" => false]);
    exit;
}

$sql = "SELECT operador_id FROM controle_pausa WHERE equipe = ? AND status = 'ativa'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$equipe]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    "success" => true,
    "pausas" => $rows,
    "vagas_disponiveis" => max(0, 2 - count($rows))
]);
