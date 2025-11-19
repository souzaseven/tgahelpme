<?php
// ============================================================
// entrar_pausa.php - Operador entra em pausa (somente primeiro da fila)
// ============================================================

require_once "../conexao.php";

header("Content-Type: application/json; charset=utf-8");

$operador_id = $_POST['operador_id'] ?? null;
$equipe      = $_POST['equipe']      ?? null;

if (!$operador_id || !$equipe) {
    echo json_encode(["success" => false, "erro" => "Dados incompletos."]);
    exit;
}

try {
    // 1) Verificar quantidade em pausa
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM fila_pausas WHERE equipe = ? AND status = 'pausa'");
    $stmt->execute([$equipe]);
    $emPausa = $stmt->fetchColumn();

    if ($emPausa >= 2) {
        echo json_encode(["success" => false, "erro" => "Limite de 2 pausas atingido."]);
        exit;
    }

    // 2) Verificar fila + se é o primeiro
    $stmt = $pdo->prepare("
        SELECT operador_id 
        FROM fila_espera 
        WHERE equipe = ?
        ORDER BY posicao ASC
        LIMIT 1
    ");
    $stmt->execute([$equipe]);
    $primeiro = $stmt->fetchColumn();

    if ($primeiro != $operador_id) {
        echo json_encode(["success" => false, "erro" => "Somente o primeiro da fila pode entrar em pausa."]);
        exit;
    }

    // 3) Iniciar pausa
    $stmt = $pdo->prepare("
        INSERT INTO fila_pausas (operador_id, equipe, inicio_pausa, status)
        VALUES (?, ?, NOW(), 'pausa')
    ");
    $stmt->execute([$operador_id, $equipe]);

    // 4) Remover da fila de espera
    $pdo->prepare("DELETE FROM fila_espera WHERE operador_id = ? AND equipe = ?")
        ->execute([$operador_id, $equipe]);

    // 5) Reorganizar fila (posições)
    $pdo->prepare("
        SET @i = 0;
        UPDATE fila_espera 
        SET posicao = (@i := @i + 1)
        WHERE equipe = ?
        ORDER BY posicao ASC;
    ")->execute([$equipe]);

    echo json_encode([
        "success" => true,
        "msg" => "Operador entrou em pausa.",
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "erro" => $e->getMessage()]);
}
