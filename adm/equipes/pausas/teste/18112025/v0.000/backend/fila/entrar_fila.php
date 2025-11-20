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
    // Já está na fila?
    $chk = $pdo->prepare("
        SELECT id 
        FROM controle_pausa_fila 
        WHERE operador_id = :id 
          AND equipe      = :e
    ");
    $chk->execute([":id" => $id, ":e" => $equipe]);

    if ($chk->rowCount() > 0) {
        echo json_encode(["success" => false, "erro" => "Você já está na fila"]);
        exit;
    }

    // Inserir na fila
    $sql = $pdo->prepare("
        INSERT INTO controle_pausa_fila (operador_id, equipe) 
        VALUES (:id, :e)
    ");
    $sql->execute([":id" => $id, ":e" => $equipe]);

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "erro" => $e->getMessage()]);
}
