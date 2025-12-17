<?php
header("Content-Type: application/json; charset=utf-8");
require_once "../../backend/conexao.php";

$operador_id = intval($_POST['operador_id'] ?? 0);
$tema        = $_POST['tema'] ?? "";

if ($operador_id <= 0 || !in_array($tema, ["claro", "escuro"], true)) {
    echo json_encode(["success" => false]);
    exit;
}

$stmt = $pdo->prepare("UPDATE controle_pausa SET pref_tema = ? WHERE id = ?");
$ok = $stmt->execute([$tema, $operador_id]);

echo json_encode(["success" => $ok]);
