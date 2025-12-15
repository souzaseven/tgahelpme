<?php
// ============================================================
// chat/marcar_lido.php
// Marca mensagens como lidas por operador/contato
// Preparado para histórico de leitura (campo lido_em)
// ============================================================

header("Content-Type: application/json; charset=utf-8");

// Caminho correto para conexão
require_once "../../backend/conexao.php";

// ------------------------------------------------------------
// Captura dados
// ------------------------------------------------------------
$operadorId = intval($_POST['operador_id'] ?? 0);
$contatoId  = intval($_POST['contato_id']  ?? 0);
$ultimoId   = intval($_POST['ultimo_id']   ?? 0);

if ($operadorId <= 0 || $contatoId <= 0 || $ultimoId <= 0) {
    echo json_encode([
        "success" => false,
        "erro"    => "Parâmetros inválidos."
    ]);
    exit;
}

// ------------------------------------------------------------
// UPSERT (MySQL)
// ------------------------------------------------------------
// OBS:
// - atualizado_em: controle técnico
// - lido_em: histórico de leitura (uso futuro)
// ------------------------------------------------------------
try {

    $stmt = $pdo->prepare("
        INSERT INTO chat_leituras_controle_pausa
            (operador_id, contato_id, ultimo_id_lido, lido_em)
        VALUES
            (?, ?, ?, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE
            ultimo_id_lido = VALUES(ultimo_id_lido),
            atualizado_em = CURRENT_TIMESTAMP,
            lido_em       = CURRENT_TIMESTAMP
    ");

    $stmt->execute([
        $operadorId,
        $contatoId,
        $ultimoId
    ]);

    echo json_encode([
        "success"        => true,
        "msg"            => "Leitura registrada com sucesso.",
        "operador_id"    => $operadorId,
        "contato_id"     => $contatoId,
        "ultimo_id_lido" => $ultimoId
    ]);
    exit;

} catch (Throwable $e) {

    // Nunca quebrar o chat por erro de leitura
    echo json_encode([
        "success" => false,
        "erro"    => "Erro ao registrar leitura.",
        "detalhe" => $e->getMessage()
    ]);
    exit;
}
