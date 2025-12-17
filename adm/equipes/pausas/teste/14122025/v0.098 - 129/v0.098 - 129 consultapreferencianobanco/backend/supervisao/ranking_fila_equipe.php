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
        SUM(tempo_espera_segundos) AS total_segundos
    FROM log_fila
    WHERE equipe = :equipe
      AND data = :data
    GROUP BY operador_id, operador_nome
    ORDER BY total_segundos DESC
");

$sql->execute([
    ":equipe" => $equipe,
    ":data"   => $data
]);

$dados = [];
foreach ($sql->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $dados[] = [
        "operador" => $row["operador_nome"],
        "tempo"    => gmdate("H:i:s", $row["total_segundos"])
    ];
}

echo json_encode($dados);
