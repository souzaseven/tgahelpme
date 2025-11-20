<?php
require_once "../conexao.php";

header("Content-Type: application/json; charset=utf-8");

$id     = $_POST["operador_id"] ?? null;
$equipe = $_POST["equipe"]      ?? "";

if (!$id || !$equipe) {
    echo json_encode(["success" => false, "erro" => "Dados inválidos"]);
    exit;
}

try {
    $sql = $pdo->prepare("
        DELETE FROM controle_pausa_fila
        WHERE operador_id = :id 
          AND equipe      = :e
    ");
    $sql->execute([":id" => $id, ":e" => $equipe]);

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "erro" => $e->getMessage()]);
}
