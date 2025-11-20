<?php
require_once "../conexao.php";

header("Content-Type: application/json; charset=utf-8");

$id = $_POST["operador_id"] ?? null;
$equipe = $_POST["equipe"] ?? "";

if (!$id || !$equipe) {
    echo json_encode(["success" => false, "erro" => "Dados inválidos"]);
    exit;
}

try {
    // Verificar vagas
    $sql = $pdo->prepare("
        SELECT COUNT(*) FROM controle_pausa_pausas 
        WHERE equipe = :e AND ativo = 1
    ");
    $sql->execute([":e" => $equipe]);
    $ocupadas = $sql->fetchColumn();

    if ($ocupadas >= 2) {
        echo json_encode(["success" => false, "erro" => "Não há vagas para pausa"]);
        exit;
    }

    // Remover da fila
    $del = $pdo->prepare("
        DELETE FROM controle_pausa_fila 
        WHERE operador_id = :id AND equipe = :e
    ");
    $del->execute([":id" => $id, ":e" => $equipe]);

    // Inserir pausa
    $ins = $pdo->prepare("
        INSERT INTO controle_pausa_pausas (operador_id, equipe) 
        VALUES (:id, :e)
    ");
    $ins->execute([":id" => $id, ":e" => $equipe]);

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "erro" => $e->getMessage()]);
}
