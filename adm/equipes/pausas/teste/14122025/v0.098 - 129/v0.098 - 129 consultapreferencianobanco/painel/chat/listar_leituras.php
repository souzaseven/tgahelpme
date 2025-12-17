<?php
// ============================================================
// chat/listar_leituras.php
// Retorna último ID lido por contato (multi navegador)
// ============================================================

header("Content-Type: application/json; charset=utf-8");
// Caminho correto para conexão
require_once "../../backend/conexao.php";

// ------------------------------------------------------------
// Captura operador
// ------------------------------------------------------------
$operadorId = intval($_GET['operador_id'] ?? 0);

if ($operadorId <= 0) {
    echo json_encode([
        "success" => false,
        "erro" => "Operador inválido."
    ]);
    exit;
}

// ------------------------------------------------------------
// Busca leituras
// ------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT 
        contato_id,
        ultimo_id_lido
    FROM chat_leituras_controle_pausa
    WHERE operador_id = ?
");
$stmt->execute([$operadorId]);

$leituras = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $leituras[(int)$row['contato_id']] = (int)$row['ultimo_id_lido'];
}

// ------------------------------------------------------------
// Retorno
// ------------------------------------------------------------
echo json_encode([
    "success"  => true,
    "leituras" => $leituras
]);
exit;
