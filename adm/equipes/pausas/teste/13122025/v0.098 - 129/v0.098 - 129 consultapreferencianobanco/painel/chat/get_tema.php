<?php
header("Content-Type: application/json; charset=utf-8");
require_once "../../backend/conexao.php";

$operador_id = intval($_GET['operador_id'] ?? 0);
if ($operador_id <= 0) {
    echo json_encode(["tema" => null]);
    exit;
}

$stmt = $pdo->prepare("SELECT pref_tema FROM controle_pausa WHERE id = ?");
$stmt->execute([$operador_id]);
$tema = $stmt->fetchColumn();

echo json_encode(["tema" => $tema ?: null]);
