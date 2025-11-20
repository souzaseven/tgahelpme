<?php
require_once "../conexao.php";

header("Content-Type: application/json; charset=utf-8");

$equipe = $_GET["equipe"] ?? "";

if (!$equipe) {
    echo json_encode(["success" => false]);
    exit;
}

try {
    // Fila
    $sql = $pdo->prepare("
        SELECT f.operador_id, o.nome, f.inicio
        FROM controle_pausa_fila f
        INNER JOIN operadores o ON o.id = f.operador_id
        WHERE f.equipe = :e
        ORDER BY f.inicio ASC
    ");
    $sql->execute([":e" => $equipe]);
    $fila = $sql->fetchAll(PDO::FETCH_ASSOC);

    // Pausas
    $sql2 = $pdo->prepare("
        SELECT operador_id, inicio
        FROM controle_pausa_pausas
        WHERE equipe = :e AND ativo = 1
    ");
    $sql2->execute([":e" => $equipe]);
    $pausas = $sql2->fetchAll(PDO::FETCH_ASSOC);

    $vagas = 2 - count($pausas);

    echo json_encode([
        "success" => true,
        "fila" => $fila,
        "pausas" => $pausas,
        "vagas_pausa" => max(0, $vagas)
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "erro" => $e->getMessage()
    ]);
}
