<?php
require_once "../conexao.php";

header("Content-Type: application/json; charset=utf-8");

$equipe = $_GET['equipe'] ?? "";

if (!$equipe) {
    echo json_encode(["success" => false, "erro" => "Equipe não informada."]);
    exit;
}

try {

    // Agora busca NOME do operador E o ID ÚNICO da pausa
    $sql = $pdo->prepare("
        SELECT 
            p.id AS pausa_id,
            p.operador_id,
            p.inicio,
            o.nome
        FROM controle_pausa_pausas p
        LEFT JOIN operadores o ON o.id = p.operador_id
        WHERE p.equipe = :e 
          AND p.ativo = 1
        ORDER BY p.inicio ASC
    ");
    $sql->execute([":e" => $equipe]);
    $pausas = $sql->fetchAll(PDO::FETCH_ASSOC);

    // Calcula vagas ainda disponíveis
    $vagas = max(0, 2 - count($pausas));

    echo json_encode([
        "success"     => true,
        "pausas"      => $pausas,
        "vagas_pausa" => $vagas
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "erro" => $e->getMessage()
    ]);
}
