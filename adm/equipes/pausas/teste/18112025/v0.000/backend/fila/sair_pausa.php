<?php
require_once "../conexao.php";
header('Content-Type: application/json; charset=utf-8');

$operador_id = $_POST['operador_id'] ?? null;
$equipe      = $_POST['equipe'] ?? null;

if (!$operador_id || !$equipe) {
    echo json_encode(["success" => false, "erro" => "Dados incompletos"]);
    exit;
}

try {

    // Buscar pausa ativa
    $sql = $pdo->prepare("
        SELECT id 
        FROM controle_pausa_pausas
        WHERE operador_id = :op
          AND equipe = :eq
          AND ativo = 1
        LIMIT 1
    ");
    $sql->execute([
        ":op" => $operador_id,
        ":eq" => $equipe
    ]);

    $pausa = $sql->fetch(PDO::FETCH_ASSOC);

    if (!$pausa) {
        echo json_encode(["success" => false, "erro" => "Nenhuma pausa ativa encontrada."]);
        exit;
    }

    // Encerrar pausa → NÃO apaga, só marca ativo = 0
    $close = $pdo->prepare("
        UPDATE controle_pausa_pausas
        SET ativo = 0, fim = NOW()
        WHERE id = :id
    ");
    $close->execute([":id" => $pausa["id"]]);

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "erro" => "Erro ao sair da pausa",
        "detalhe" => $e->getMessage()
    ]);
}
