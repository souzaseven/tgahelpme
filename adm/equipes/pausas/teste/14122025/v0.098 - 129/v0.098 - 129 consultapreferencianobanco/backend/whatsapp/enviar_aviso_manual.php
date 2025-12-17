<?php
// ============================================================
// enviar_aviso_manual.php — WhatsApp manual (limite de pausa)
// VERSÃO FINAL / ESTÁVEL
// ============================================================

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../conexao.php";
require_once __DIR__ . "/zapi_client.php";

// ------------------------------------------------------------
// Helper de resposta JSON
// ------------------------------------------------------------
function resposta(array $arr): void {
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

// ------------------------------------------------------------
// Validação do ID
// ------------------------------------------------------------
$id = (int)($_POST["id"] ?? 0);

if ($id <= 0) {
    resposta([
        "success" => false,
        "erro"    => "ID inválido."
    ]);
}

try {

    // =========================================================
    // 1) Buscar operador em pausa
    // =========================================================
    $stmt = $pdo->prepare("
        SELECT
            id,
            nome_usuario,
            whatsapp,
            whatsapp_optin,
            whatsapp_limite_enviado,
            inicio_pausa,
            TIMESTAMPDIFF(MINUTE, inicio_pausa, NOW()) AS minutos
        FROM controle_pausa
        WHERE id = :id
          AND LOWER(status) = 'pausa'
          AND inicio_pausa IS NOT NULL
        LIMIT 1
    ");
    $stmt->execute([":id" => $id]);
    $op = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$op) {
        throw new Exception("Operador não encontrado ou não está em pausa.");
    }

    // =========================================================
    // 2) Regras de envio
    // =========================================================
    if ((int)$op["whatsapp_optin"] !== 1 || empty($op["whatsapp"])) {
        throw new Exception("WhatsApp não habilitado.");
    }

    // Anti-spam (manual + cron)
    if ((int)$op["whatsapp_limite_enviado"] === 1) {
        throw new Exception("Aviso já enviado anteriormente.");
    }

    // Regra dos 19 minutos
    if ((int)$op["minutos"] < 19) {
        throw new Exception("Aviso permitido somente após 19 minutos de pausa.");
    }

    // =========================================================
    // 3) Montar mensagem
    // =========================================================
    $mensagem =
        "⚠️ *Limite de Pausa Excedido*\n\n" .
        "Olá {$op['nome_usuario']},\n" .
        "Você está em pausa há *{$op['minutos']} minutos*.\n\n" .
        "Por favor, retorne ao atendimento.";

    // =========================================================
    // 4) Enviar WhatsApp
    // =========================================================
    $res = enviarWhatsapp($op["whatsapp"], $mensagem);
    $sucesso = (!empty($res["success"]) && $res["success"] === true);

    // =========================================================
    // 5) Registrar LOG (estrutura REAL da tabela)
    // =========================================================
    $log = $pdo->prepare("
        INSERT INTO logs_whatsapp
        (
            operador_id,
            tipo_evento,
            numero,
            mensagem,
            enviado,
            erro,
            data_envio,
            tentativas,
            ultimo_erro
        )
        VALUES
        (
            :operador_id,
            'limite_pausa_manual',
            :numero,
            :mensagem,
            :enviado,
            NULL,
            NOW(),
            1,
            :ultimo_erro
        )
    ");

    $log->execute([
        ":operador_id" => (int)$op["id"],
        ":numero"      => $op["whatsapp"],
        ":mensagem"    => $mensagem,
        ":enviado"     => $sucesso ? 1 : 0,
        ":ultimo_erro" => $sucesso ? null : json_encode($res, JSON_UNESCAPED_UNICODE)
    ]);

    // =========================================================
    // 6) Se falhou, não trava e retorna erro
    // =========================================================
    if (!$sucesso) {
        throw new Exception("Falha ao enviar WhatsApp.");
    }

    // =========================================================
    // 7) Trava anti-spam (manual + cron)
    // =========================================================
    $upd = $pdo->prepare("
        UPDATE controle_pausa
        SET whatsapp_limite_enviado = 1
        WHERE id = :id
        LIMIT 1
    ");
    $upd->execute([":id" => (int)$op["id"]]);

    // =========================================================
    // 8) Retorno final
    // =========================================================
    resposta([
        "success" => true
    ]);

} catch (Throwable $e) {

    resposta([
        "success" => false,
        "erro"    => $e->getMessage()
    ]);
}
