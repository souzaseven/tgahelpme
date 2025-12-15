<?php
header("Content-Type: application/json; charset=utf-8");
require_once "../conexao.php";

$equipe = $_GET["equipe"] ?? null;
$data   = date("Y-m-d");

if (!$equipe) {
    echo json_encode([]);
    exit;
}

$sql = $pdo->prepare("
    SELECT 
        operador_nome,
        COUNT(*) AS total_pausas
    FROM log_pausas
    WHERE equipe = :equipe
      AND data = :data
    GROUP BY operador_id, operador_nome
    ORDER BY total_pausas DESC
");

$sql->execute([
    ":equipe" => $equipe,
    ":data"   => $data
]);

echo json_encode($sql->fetchAll(PDO::FETCH_ASSOC));
