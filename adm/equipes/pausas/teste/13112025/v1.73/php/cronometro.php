<?php
header("Content-Type: application/json; charset=utf-8");

require_once "conexao.php";

$data = json_decode(file_get_contents("php://input"), true);

$nome = $data["nome"] ?? null;
$tipo = $data["tipo"] ?? null;
$inicio = $data["inicio"] ?? null;
$fim = $data["fim"] ?? null;
$duracao = $data["duracao"] ?? null;

if (!$nome || !$tipo || !$inicio || !$fim) {
    echo json_encode(["success" => false, "error" => "Dados incompletos"]);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO controle_tempo
    (usuario, tipo, inicio, fim, duracao_segundos, status)
    VALUES (:usuario, :tipo, FROM_UNIXTIME(:inicio), FROM_UNIXTIME(:fim), :duracao, 'finalizado')
");

$stmt->execute([
    ":usuario" => $nome,
    ":tipo" => $tipo,
    ":inicio" => intval($inicio / 1000),
    ":fim" => intval($fim / 1000),
    ":duracao" => $duracao
]);

echo json_encode(["success" => true]);
?>
