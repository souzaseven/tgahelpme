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
        COUNT(*) AS total_pausas,
        SUM(duracao_segundos) AS total_segundos,
        SUM(CASE WHEN excedeu = 1 THEN 1 ELSE 0 END) AS vezes_excedeu,
        SUM(CASE WHEN excedeu_nivel = 1 THEN 1 ELSE 0 END) AS excesso_10,
        SUM(CASE WHEN excedeu_nivel = 2 THEN 1 ELSE 0 END) AS excesso_20
    FROM log_pausas
    WHERE data = CURDATE()
      AND equipe = :equipe
    GROUP BY operador_id, operador_nome
    ORDER BY total_pausas DESC, total_segundos DESC
");

$sql->execute([":equipe" => $equipe]);

echo json_encode([
    "success" => true,
    "dados"   => $sql->fetchAll(PDO::FETCH_ASSOC)
], JSON_UNESCAPED_UNICODE);
