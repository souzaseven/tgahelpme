<?php
// ============================================================
// enviar_limite_manual.php — ENVIO MANUAL DE ALERTA DE PAUSA
// ============================================================

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/zapi_client.php';

$operadorId = intval($_POST['operador_id'] ?? 0);

if ($operadorId <= 0) {
    echo json_encode(["success" => false, "erro" => "Operador inválido"]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        id,
        nome_usuario,
        inicio_pausa,
        whatsapp,
        whatsapp_optin,
        TIMESTAMPDIFF(MINUTE, inicio_pausa, NOW()) AS minutos
    FROM controle_pausa
    WHERE id = ?
      AND LOWER(status) = 'pausa'
      AND inicio_pausa IS NOT NULL
");
$stmt->execute([$operadorId]);
$op = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$op) {
    echo json_encode(["success" => false, "erro" => "Operador não está em pausa"]);
    exit;
}

if ($op['minutos'] < 19) {
    echo json_encode(["success" => false, "erro" => "Pausa ainda dentro do limite"]);
    exit;
}

if (empty($op['whatsapp']) || !$op['whatsapp_optin']) {
    echo json_encode(["success" => false, "erro" => "WhatsApp não habilitado"]);
    exit;
}

$mensagem =
    "⚠️ *Aviso Manual de Pausa*\n\n" .
    "Olá {$op['nome_usuario']},\n" .
    "Você está em pausa há *{$op['minutos']} minutos*.\n\n" .
    "Por favor, retorne ao atendimento.";

$res = enviarWhatsapp($op['whatsapp'], $mensagem);

$sucesso = !empty($res['success']) && $res['success'] === true;

// Log
$log = $pdo->prepare("
    INSERT INTO logs_whatsapp
        (operador_id, evento, telefone, mensagem, sucesso, retorno_api)
    VALUES
        (?, 'limite_pausa_manual', ?, ?, ?, ?)
");
$log->execute([
    $op['id'],
    $op['whatsapp'],
    $mensagem,
    $sucesso ? 1 : 0,
    json_encode($res, JSON_UNESCAPED_UNICODE)
]);

echo json_encode([
    "success" => $sucesso,
    "retorno" => $res
]);
