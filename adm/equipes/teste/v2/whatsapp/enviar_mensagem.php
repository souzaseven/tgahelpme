<?php
// whatsapp/enviar_mensagem.php
// Endpoint para envio de mensagens WhatsApp via ApiBrasil.
// Usado pelo painel admin do Renascer.
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
auth_guard_ajax();

require_once __DIR__ . '/whatsapp_client.php';

$numero   = trim($_POST['numero']   ?? '');
$mensagem = trim($_POST['mensagem'] ?? '');

if (!$numero) {
    echo json_encode(['ok' => false, 'msg' => 'Informe o número de destino.']);
    exit;
}
if (!$mensagem) {
    echo json_encode(['ok' => false, 'msg' => 'A mensagem não pode estar vazia.']);
    exit;
}

/* Formata número para padrão BR: remove não-dígitos e adiciona 55 se necessário */
$n = preg_replace('/\D/', '', $numero);
if (strlen($n) === 10 || strlen($n) === 11) {
    $n = "55{$n}";
} elseif (strlen($n) >= 12 && str_starts_with($n, '55')) {
    // já tem código do país
} else {
    echo json_encode(['ok' => false, 'msg' => "Número inválido \"$numero\". Use DDD + número (ex: 11 99999-9999)."]);
    exit;
}

$resultado = whatsappSendText($n, $mensagem);

if (!empty($resultado['success']) && $resultado['success'] === true) {
    echo json_encode(['ok' => true, 'msg' => "Mensagem enviada para +$n com sucesso!"]);
} else {
    $resp     = $resultado['response'] ?? [];
    $httpCode = $resultado['httpCode'] ?? 0;
    $raw      = $resultado['raw'] ?? '';

    /* Extrai mensagem de erro em ordem de preferência */
    $detalhe = $resp['message']
            ?? $resp['msg']
            ?? $resp['error']
            ?? $resp['detail']
            ?? (is_string($raw) && $raw !== '' ? $raw : null)
            ?? "HTTP $httpCode — sem resposta da API.";

    if (!is_string($detalhe)) {
        $detalhe = json_encode($detalhe, JSON_UNESCAPED_UNICODE);
    }

    echo json_encode([
        'ok'   => false,
        'msg'  => "Falha ao enviar (HTTP $httpCode): $detalhe",
        'http' => $httpCode,
    ]);
}
