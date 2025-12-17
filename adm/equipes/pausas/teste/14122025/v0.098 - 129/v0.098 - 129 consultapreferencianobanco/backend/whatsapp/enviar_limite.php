<?php
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/zapi_client.php';

header("Content-Type: application/json; charset=utf-8");

$LIMITE_MINUTOS = 20;

try {

    $operadorId = intval($_POST['operador_id'] ?? 0);

    if ($operadorId <= 0) {
        error_log("[WHATSAPP] operador_id inválido");
        echo json_encode(["success" => false, "motivo" => "operador_id inválido"]);
        exit;
    }

    // =========================================================
    // 1️⃣ Buscar pausa ativa + tempo
    // =========================================================
    $stmt = $pdo->prepare("
        SELECT 
            cp.id AS pausa_id,
            cp.inicio_pausa,
            cp.whatsapp_limite_enviado,
            TIMESTAMPDIFF(MINUTE, cp.inicio_pausa, NOW()) AS minutos_pausa,
            o.nome_usuario,
            o.whatsapp_numero
        FROM controle_pausa cp
        JOIN operadores o ON o.id = cp.operador_id
        WHERE cp.operador_id = ?
          AND cp.status = 'PAUSA'
        LIMIT 1
    ");
    $stmt->execute([$operadorId]);
    $pausa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pausa) {
        error_log("[WHATSAPP] Operador {$operadorId} sem pausa ativa");
        echo json_encode(["success" => false, "motivo" => "sem pausa ativa"]);
        exit;
    }

    error_log("[WHATSAPP] Operador {$operadorId} em pausa há {$pausa['minutos_pausa']} min");

    // =========================================================
    // 2️⃣ Ainda não passou do limite
    // =========================================================
    if ((int)$pausa['minutos_pausa'] < $LIMITE_MINUTOS) {
        error_log("[WHATSAPP] Limite NÃO atingido");
        echo json_encode([
            "success" => false,
            "motivo"  => "limite_nao_atingido",
            "minutos" => $pausa['minutos_pausa']
        ]);
        exit;
    }

    // =========================================================
    // 3️⃣ Já enviado anteriormente
    // =========================================================
    if ((int)$pausa['whatsapp_limite_enviado'] === 1) {
        error_log("[WHATSAPP] Já enviado anteriormente");
        echo json_encode(["success" => false, "motivo" => "ja_enviado"]);
        exit;
    }

    // =========================================================
    // 4️⃣ Envio para operador
    // =========================================================
    $mensagem = "⚠️ *Limite de Pausa Excedido*\n\n"
              . "Olá {$pausa['nome_usuario']},\n"
              . "Você ultrapassou {$LIMITE_MINUTOS} minutos de pausa.\n\n"
              . "Por favor, retorne ao atendimento.";

    error_log("[WHATSAPP] Enviando para operador {$operadorId}");

    $result = enviarWhatsapp($pausa['whatsapp_numero'], $mensagem);
    $sucesso = !empty($result['success']);

    error_log("[WHATSAPP] Retorno operador: " . json_encode($result));

    // =========================================================
    // 5️⃣ Log operador
    // =========================================================
    $pdo->prepare("
        INSERT INTO logs_whatsapp
            (operador_id, evento, telefone, mensagem, sucesso, retorno_api)
        VALUES
            (?, 'limite_pausa', ?, ?, ?, ?)
    ")->execute([
        $operadorId,
        $pausa['whatsapp_numero'],
        $mensagem,
        $sucesso ? 1 : 0,
        json_encode($result, JSON_UNESCAPED_UNICODE)
    ]);

    // =========================================================
    // 6️⃣ Marcar como enviado
    // =========================================================
    if ($sucesso) {
        $pdo->prepare("
            UPDATE controle_pausa
            SET whatsapp_limite_enviado = 1
            WHERE id = ?
        ")->execute([$pausa['pausa_id']]);
    }

    echo json_encode([
        "success" => $sucesso,
        "minutos" => $pausa['minutos_pausa']
    ]);
    exit;

} catch (Throwable $e) {
    error_log("[WHATSAPP ERRO] " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "erro" => "Erro interno",
        "detalhe" => $e->getMessage()
    ]);
    exit;
}
