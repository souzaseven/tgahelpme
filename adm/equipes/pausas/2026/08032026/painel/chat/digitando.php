<?php
// ============================================================
// chat/digitando.php
// GET  → retorna quem está digitando para mim agora
// POST → registra que estou digitando para alguém
// Expira após 5 segundos sem atualização
// ============================================================

header("Content-Type: application/json; charset=utf-8");
require_once "../../backend/conexao.php";

$acao = $_SERVER["REQUEST_METHOD"] === "POST" ? "set" : "get";

// ------------------------------------------------------------
// POST — Registrar que estou digitando
// ------------------------------------------------------------
if ($acao === "set") {

    $operadorId = intval($_POST["operador_id"] ?? 0);
    $paraId     = intval($_POST["para_id"]     ?? 0);
    $equipe     = trim($_POST["equipe"]         ?? "");

    if ($operadorId <= 0 || $paraId <= 0 || $equipe === "") {
        echo json_encode(["success" => false, "erro" => "Parâmetros inválidos."]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO chat_digitando (operador_id, para_id, equipe, atualizado_em)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE atualizado_em = NOW()
        ");
        $stmt->execute([$operadorId, $paraId, $equipe]);

        echo json_encode(["success" => true]);

    } catch (Throwable $e) {
        // Nunca quebrar o chat por isso
        echo json_encode(["success" => false]);
    }
    exit;
}

// ------------------------------------------------------------
// GET — Verificar se alguém está digitando para mim
// ------------------------------------------------------------
$meuId  = intval($_GET["meu_id"]  ?? 0);
$paraId = intval($_GET["para_id"] ?? 0);

if ($meuId <= 0 || $paraId <= 0) {
    echo json_encode(["digitando" => false]);
    exit;
}

try {
    // Considera "digitando" se atualizou nos últimos 5 segundos
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM chat_digitando
        WHERE operador_id = ?
          AND para_id = ?
          AND atualizado_em >= DATE_SUB(NOW(), INTERVAL 5 SECOND)
    ");
    $stmt->execute([$paraId, $meuId]);

    $digitando = (int)$stmt->fetchColumn() > 0;

    echo json_encode(["digitando" => $digitando]);

} catch (Throwable $e) {
    echo json_encode(["digitando" => false]);
}