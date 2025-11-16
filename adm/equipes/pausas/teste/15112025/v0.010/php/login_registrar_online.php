<?php
// ============================================================
// login_registrar_online.php
// Define que o operador está ONLINE após o login
// Cria o operador na tabela caso ainda não exista
// ============================================================

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/conexao.php";

$nome = $_POST['nome'] ?? null;
$equipe = $_POST['equipe'] ?? null;

if (!$nome || !$equipe) {
    echo json_encode([
        "success" => false,
        "error"   => "Nome e equipe são obrigatórios."
    ]);
    exit;
}

try {
    // Verifica se existe
    $st = $pdo->prepare("
        SELECT id FROM controle_pausa 
        WHERE nome_usuario = ? AND equipe = ?
        LIMIT 1
    ");
    $st->execute([$nome, $equipe]);
    $existe = $st->fetch(PDO::FETCH_ASSOC);

    // Se não existe → cria automaticamente
    if (!$existe) {
        $stInsert = $pdo->prepare("
            INSERT INTO controle_pausa (nome_usuario, equipe, status)
            VALUES (?, ?, 'ativo')
        ");
        $stInsert->execute([$nome, $equipe]);
    }

    // Marca como “online lógico” registrando no BD
    echo json_encode([
        "success" => true,
        "msg"     => "Operador registrado como ONLINE",
        "nome"    => $nome,
        "online"  => true
    ]);

} catch (Throwable $e) {
    echo json_encode([
        "success" => false,
        "error"   => "Erro ao registrar online.",
        "detalhe" => $e->getMessage()
    ]);
}
