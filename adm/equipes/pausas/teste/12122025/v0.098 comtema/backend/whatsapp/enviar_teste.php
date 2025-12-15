<?php
// ============================================================
// enviar_teste.php — ENVIO REAL DE TESTE WHATSAPP (Z-API)
// ============================================================

header("Content-Type: application/json; charset=utf-8");

try {

    // ========================================================
    // PATHS CORRETOS (ESSENCIAL)
    // ========================================================
    require_once __DIR__ . '/../conexao.php';
    require_once __DIR__ . '/zapi_client.php';

    error_log("[WHATSAPP TESTE] Script iniciado");

    // --------------------------------------------------------
    // Captura operador
    // --------------------------------------------------------
    $operadorId = intval($_POST['operador_id'] ?? 0);

    if ($operadorId <= 0) {
        echo json_encode([
            "success" => false,
            "erro" => "Operador inválido."
        ]);
        exit;
    }

    // --------------------------------------------------------
    // Buscar dados do operador
    // --------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT 
            id,
            nome_usuario,
            whatsapp_habilitado,
            whatsapp_numero
        FROM controle_pausa
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$operadorId]);

    $op = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$op) {
        echo json_encode([
            "success" => false,
            "erro" => "Operador não encontrado."
        ]);
        exit;
    }

    // --------------------------------------------------------
    // Valida WhatsApp
    // --------------------------------------------------------
    if ((int)$op['whatsapp_habilitado'] !== 1) {
        echo json_encode([
            "success" => false,
            "erro" => "WhatsApp não habilitado."
        ]);
        exit;
    }

    if (empty($op['whatsapp_numero'])) {
        echo json_encode([
            "success" => false,
            "erro" => "Número de WhatsApp não informado."
        ]);
        exit;
    }

    // --------------------------------------------------------
    // Monta mensagem
    // --------------------------------------------------------
    $mensagem = "✅ *Teste de WhatsApp*\n\n"
              . "Olá {$op['nome_usuario']}!\n"
              . "Este é um teste real do *Controle de Pausa*.\n\n"
              . "Se você recebeu esta mensagem, o envio está funcionando corretamente.";

    // --------------------------------------------------------
    // ENVIO REAL
    // --------------------------------------------------------
    error_log("[WHATSAPP TESTE] Enviando para {$op['whatsapp_numero']}");

    $resultado = enviarWhatsapp($op['whatsapp_numero'], $mensagem);

    error_log("[WHATSAPP TESTE] Retorno Z-API: " . json_encode($resultado));

    $sucesso = !empty($resultado['success']);

    // --------------------------------------------------------
    // LOG
    // --------------------------------------------------------
    try {
        $log = $pdo->prepare("
            INSERT INTO logs_whatsapp
            (operador_id, evento, telefone, mensagem, sucesso, retorno_api)
            VALUES (?, 'teste_manual', ?, ?, ?, ?)
        ");
        $log->execute([
            $op['id'],
            $op['whatsapp_numero'],
            $mensagem,
            $sucesso ? 1 : 0,
            json_encode($resultado, JSON_UNESCAPED_UNICODE)
        ]);
    } catch (Throwable $e) {
        error_log("[WHATSAPP TESTE] Erro ao gravar log: " . $e->getMessage());
    }

    // --------------------------------------------------------
    // RETORNO FINAL
    // --------------------------------------------------------
    if ($sucesso) {
        echo json_encode([
            "success" => true,
            "msg" => "Mensagem de teste enviada com sucesso."
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "erro" => "Falha no envio via WhatsApp.",
            "retorno" => $resultado
        ]);
    }
    exit;

} catch (Throwable $e) {

    error_log("[WHATSAPP TESTE] ERRO FATAL: " . $e->getMessage());

    echo json_encode([
        "success" => false,
        "erro" => "Erro interno no envio de teste.",
        "detalhe" => $e->getMessage()
    ]);
    exit;
}
