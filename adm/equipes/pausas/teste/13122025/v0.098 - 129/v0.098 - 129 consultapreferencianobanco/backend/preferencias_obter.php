<?php
// ============================================================
// preferencias_obter.php — FASE 9 (FONTE DA VERDADE = BANCO)
// Tabela: controle_pausa
// ============================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");

// ⚠️ AJUSTE O CAMINHO SE NECESSÁRIO
require_once "conexao.php";

// ------------------------------------------------------------
// Captura e valida ID
// ------------------------------------------------------------
$id = intval($_POST["id"] ?? 0);

if ($id <= 0) {
    echo json_encode([
        "erro" => "ID inválido"
    ]);
    exit;
}

// ------------------------------------------------------------
// Consulta segura
// ------------------------------------------------------------
try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            pref_toast,
            pref_fala,
            pref_audio,
            pref_notif,
            pref_voz,
            pref_alerta_alvo,
            pref_volume_fala,
            whatsapp_habilitado,
            whatsapp_numero,
            pref_tema
        FROM controle_pausa
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->bindValue(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $dados = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dados) {
        echo json_encode([
            "erro" => "Registro não encontrado"
        ]);
        exit;
    }

    echo json_encode($dados);

} catch (Throwable $e) {

    echo json_encode([
        "erro"    => "Erro ao buscar preferências",
        "detalhe" => $e->getMessage()
    ]);
}
