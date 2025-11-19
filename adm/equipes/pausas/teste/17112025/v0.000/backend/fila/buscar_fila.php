<?php
require_once "../conexao.php";

$equipe = $_GET['equipe'] ?? null;

if (!$equipe) {
    echo json_encode(["success" => false, "erro" => "Equipe não enviada"]);
    exit;
}

// Buscar fila ordenada
$sql = "SELECT f.operador_id, o.nome, f.posicao, f.tempo_entrada
        FROM controle_fila f
        JOIN operadores o ON o.id = f.operador_id
        WHERE f.equipe = ?
        ORDER BY f.posicao ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$equipe]);
$fila = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar pausas ativas
$sql2 = "SELECT p.operador_id, o.nome, p.inicio
         FROM controle_pausa p
         JOIN operadores o ON o.id = p.operador_id
         WHERE p.equipe = ? AND p.status = 'ativa'";

$stmt2 = $pdo->prepare($sql2);
$stmt2->execute([$equipe]);
$pausas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    "success" => true,
    "fila" => $fila,
    "pausas" => $pausas,
    "vagas_pausa" => max(0, 2 - count($pausas))
]);
