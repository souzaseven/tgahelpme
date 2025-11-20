<?php
require_once "../conexao.php";

header("Content-Type: application/json; charset=utf-8");

try {

    $sql = $pdo->prepare("
        SELECT 
            p.id,
            p.operador_id,
            o.nome,
            p.inicio,
            p.equipe
        FROM controle_pausa_pausas p
        INNER JOIN operadores o ON o.id = p.operador_id
        WHERE p.ativo = 1
        ORDER BY p.inicio ASC
    ");
    $sql->execute();

    $pausas = $sql->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "pausas" => $pausas
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "erro" => $e->getMessage()
    ]);

}
