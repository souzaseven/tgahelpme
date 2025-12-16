<?php
// =====================================================
// FUNÇÃO PADRÃO DE LOG (BACKEND)
// =====================================================

function registrarLogAcao($acao, $detalhes = []) {
    try {
        require __DIR__ . '/conexao.php';

        $stmt = $pdo->prepare("
            INSERT INTO logs_sistema
            (acao, detalhes, data)
            VALUES (?, ?, NOW())
        ");

        $stmt->execute([
            $acao,
            json_encode($detalhes, JSON_UNESCAPED_UNICODE)
        ]);

    } catch (Exception $e) {
        // Evita quebrar o fluxo por erro de log
        error_log('[LOG ERRO] ' . $e->getMessage());
    }
}
