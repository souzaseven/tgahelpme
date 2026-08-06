<?php
// whatsapp/enviar_massa.php — Envia mensagem de relatório para o contato de controle
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
auth_guard_ajax();

require_once __DIR__ . '/whatsapp_client.php';

$total    = intval($_POST['total']    ?? 0);
$enviados = intval($_POST['enviados'] ?? 0);
$falhas   = intval($_POST['falhas']   ?? 0);
$usuario  = $_SESSION['usuario'] ?? 'sistema';

// Número de controle — vem do formulário (editável pelo operador)
$numRaw         = preg_replace('/\D/', '', trim($_POST['num_controle'] ?? '65999892901'));
$numeroControle = strlen($numRaw) <= 11 ? "55{$numRaw}" : $numRaw;

$data    = date('d/m/Y');
$hora    = date('H:i');
$msg     = "✅ *Relatório de Envio — Igreja Renascer*\n\n";
$msg    .= "📅 Data: {$data} às {$hora}\n";
$msg    .= "👤 Operador: {$usuario}\n\n";
$msg    .= "📊 *Resultado:*\n";
$msg    .= "• Total de aniversariantes: {$total}\n";
$msg    .= "• Mensagens enviadas: {$enviados}\n";
$msg    .= "• Falhas: {$falhas}\n\n";

if ($falhas === 0) {
    $msg .= "🎉 Todos os envios foram concluídos com sucesso!";
} else {
    $msg .= "⚠️ {$falhas} envio(s) falharam. Verifique manualmente.";
}

$resultado = whatsappSendText($numeroControle, $msg);

if (!empty($resultado['success']) && $resultado['success'] === true) {
    echo json_encode(['ok' => true, 'msg' => 'Relatório enviado para o controle.']);
} else {
    $httpCode = $resultado['httpCode'] ?? 0;
    echo json_encode(['ok' => false, 'msg' => "Não foi possível enviar o relatório (HTTP {$httpCode})."]);
}
