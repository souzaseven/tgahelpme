<?php
header("Content-Type: application/json; charset=utf-8");
require_once "../conexao.php";

$equipe = $_GET["equipe"] ?? null;
$data   = date("Y-m-d");

if (!$equipe) {
    echo json_encode(["success" => false, "erro" => "Equipe não informada"]);
    exit;
}

$sql = $pdo->prepare("
    SELECT 
        operador_nome,
        COUNT(*) AS total_excessos,
        SUM(CASE WHEN nivel_excesso = 1 THEN 1 ELSE 0 END) AS excesso_10,
        SUM(CASE WHEN nivel_excesso = 2 THEN 1 ELSE 0 END) AS excesso_20
    FROM log_excessos
    WHERE equipe = :equipe
      AND data = :data
    GROUP BY operador_id, operador_nome
    ORDER BY excesso_20 DESC, excesso_10 DESC
");

$sql->execute([
    ":equipe" => $equipe,
    ":data"   => $data
]);

echo json_encode([
    "success" => true,
    "dados"   => $sql->fetchAll(PDO::FETCH_ASSOC)
]);
