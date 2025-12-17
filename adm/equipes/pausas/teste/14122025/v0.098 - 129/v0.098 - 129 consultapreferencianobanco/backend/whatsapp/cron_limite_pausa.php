<?php
// ============================================================
// cron_limite_pausa.php — ENVIO AUTOMÁTICO VIA CRON
// Controle de Pausa | WhatsApp
// ============================================================

$DEBUG = false; // <<< PRODUÇÃO = false

if ($DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

if (php_sapi_name() !== 'cli' && !$DEBUG) {
    exit; // Segurança extra
}

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/zapi_client.php';

try {

    if ($DEBUG) echo "=== CRON LIMITE PAUSA ===\n";

    $stmt = $pdo->prepare("
        SELECT
            cp.id AS operador_id,
            cp.nome_usuario,
            cp.inicio_pausa,
            cp.whatsapp,
            cp.whatsapp_optin,
            cp.whatsapp_limite_enviado,
            cp.whatsapp_tentativas,
            TIMESTAMPDIFF(MINUTE, cp.inicio_pausa, NOW()) AS minutos
        FROM controle_pausa cp
        WHERE cp.status IN ('pausa','PAUSA')
          AND cp.inicio_pausa IS NOT NULL
          AND cp.inicio_pausa <= NOW()
          AND TIMESTAMPDIFF(MINUTE, cp.inicio_pausa, NOW()) >= 20
          AND (cp.whatsapp_limite_enviado = 0 OR cp.whatsapp_limite_enviado IS NULL)
          AND cp.whatsapp IS NOT NULL
          AND cp.whatsapp_optin = 1
          AND IFNULL(cp.whatsapp_tentativas,0) < 3
    ");
    $stmt->execute();
    $pausas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pausas as $p) {

        $mensagem =
            "⚠️ *Limite de Pausa Excedido*\n\n" .
            "Olá {$p['nome_usuario']},\n" .
            "Você está em pausa há *{$p['minutos']} minutos*.\n\n" .
            "Por favor, retorne ao atendimento.";

        $res = enviarWhatsapp($p['whatsapp'], $mensagem);
        $sucesso = !empty($res['success']);

        // LOG
        $log = $pdo->prepare("
            INSERT INTO logs_whatsapp
            (operador_id, evento, telefone, mensagem, sucesso, retorno_api)
            VALUES (?, 'limite_pausa_cron', ?, ?, ?, ?)
        ");
        $log->execute([
            $p['operador_id'],
            $p['whatsapp'],
            $mensagem,
            $sucesso ? 1 : 0,
            json_encode($res, JSON_UNESCAPED_UNICODE)
        ]);

        if ($sucesso) {
            $pdo->prepare("
                UPDATE controle_pausa
                SET whatsapp_limite_enviado = 1
                WHERE id = ?
            ")->execute([$p['operador_id']]);
        } else {
            $pdo->prepare("
                UPDATE controle_pausa
                SET whatsapp_tentativas = IFNULL(whatsapp_tentativas,0) + 1
                WHERE id = ?
            ")->execute([$p['operador_id']]);
        }
    }

} catch (Throwable $e) {
    error_log("[CRON WHATSAPP] " . $e->getMessage());
}
