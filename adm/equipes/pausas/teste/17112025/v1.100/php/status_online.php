<?php
// ============================================================
// status_online.php
// Retorna status "online/offline" baseado em:
//
    // ✔ Existe registro na tabela → ONLINE
    // ✔ Não existe registro → OFFLINE
//
// NÃO usa last_seen
// NÃO depende de tempo
// Totalmente independente do sistema principal
// ============================================================

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/conexao.php";

// Nome do operador enviado pelo frontend
$nome = $_GET['nome'] ?? $_POST['nome'] ?? null;
$equipe = $_GET['equipe'] ?? $_POST['equipe'] ?? null;

if (!$nome || !$equipe) {
    echo json_encode([
        "success" => false,
        "online"  => false,
        "error"   => "Nome e equipe são obrigatórios."
    ]);
    exit;
}

try {
    // Consulta simples: existe o operador na tabela?
    $st = $pdo->prepare("
        SELECT id 
        FROM controle_pausa
        WHERE nome_usuario = ? AND equipe = ?
        LIMIT 1
    ");
    $st->execute([$nome, $equipe]);
    $existe = $st->fetch(PDO::FETCH_ASSOC);

    if ($existe) {
        echo json_encode([
            "success" => true,
            "nome"    => $nome,
            "online"  => true
        ]);
    } else {
        echo json_encode([
            "success" => true,
            "nome"    => $nome,
            "online"  => false
        ]);
    }

} catch (Throwable $e) {
    echo json_encode([
        "success" => false,
        "online"  => false,
        "error"   => "Erro ao verificar status.",
        "detalhe" => $e->getMessage()
    ]);
}
