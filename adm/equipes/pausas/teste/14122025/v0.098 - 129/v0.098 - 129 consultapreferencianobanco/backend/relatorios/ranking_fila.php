<?php
header("Content-Type: application/json; charset=utf-8");
require_once "../conexao.php";

$equipe = $_GET["equipe"] ?? null;

if (!$equipe) {
    echo json_encode(["success" => false, "erro" => "Equipe não informada"]);
    exit;
}

$sql = $pdo->prepare("
    SELECT
        operador_id,
        operador_nome,
        COUNT(*) AS entradas_fila,
        SUM(tempo_espera_segundos) AS total_segundos
    FROM log_fila
    WHERE data = CURDATE()
      AND equipe = :equipe
    GROUP BY operador_id, operador_nome
    ORDER BY total_segundos DESC
");

$sql->execute([":equipe" => $equipe]);

echo json_encode([
    "success" => true,
    "dados"   => $sql->fetchAll(PDO::FETCH_ASSOC)
], JSON_UNESCAPED_UNICODE);
