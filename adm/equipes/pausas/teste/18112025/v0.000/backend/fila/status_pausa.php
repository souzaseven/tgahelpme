<?php
require_once "../conexao.php";

header("Content-Type: application/json; charset=utf-8");

$equipe = $_GET['equipe'] ?? null;

if (!$equipe) {
    echo json_encode(["success" => false, "erro" => "Equipe inválida"]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT operador_id
        FROM controle_pausa_pausas
        WHERE equipe = :e
          AND ativo  = 1
    ");
    $stmt->execute([":e" => $equipe]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $vagas = max(0, 2 - count($rows));

    echo json_encode([
        "success"        => true,
        "pausas"         => $rows,
        "vagas_pausa"    => $vagas
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "erro"    => $e->getMessage()
    ]);
}
