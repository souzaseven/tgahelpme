<?php
require_once "../conexao.php";

header("Content-Type: application/json; charset=utf-8");

$operador_id = $_POST["operador_id"] ?? null;
$equipe      = $_POST["equipe"]      ?? "";

if (!$operador_id || !$equipe) {
    echo json_encode([
        "success" => false,
        "erro"    => "Dados inválidos (operador ou equipe ausentes)."
    ]);
    exit;
}

try {
    // 1) Verificar se operador JÁ está em pausa ativa
    $verif = $pdo->prepare("
        SELECT id 
        FROM controle_pausa_pausas
        WHERE operador_id = :op
          AND equipe      = :eq
          AND ativo       = 1
        LIMIT 1
    ");
    $verif->execute([
        ":op" => $operador_id,
        ":eq" => $equipe
    ]);

    if ($verif->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode([
            "success" => false,
            "erro"    => "Você já está em pausa ativa."
        ]);
        exit;
    }

    // 2) Verificar vagas (máximo 2 ativas por equipe)
    $sql = $pdo->prepare("
        SELECT COUNT(*) 
        FROM controle_pausa_pausas 
        WHERE equipe = :e 
          AND ativo  = 1
    ");
    $sql->execute([":e" => $equipe]);
    $ocupadas = (int) $sql->fetchColumn();

    if ($ocupadas >= 2) {
        echo json_encode([
            "success" => false,
            "erro"    => "Não há vagas para pausa no momento."
        ]);
        exit;
    }

    // 3) Remover da FILA (se estiver)
    $del = $pdo->prepare("
        DELETE FROM controle_pausa_fila 
        WHERE operador_id = :id 
          AND equipe      = :e
    ");
    $del->execute([
        ":id" => $operador_id,
        ":e"  => $equipe
    ]);

    // 4) Inserir nova pausa ativa
    $ins = $pdo->prepare("
        INSERT INTO controle_pausa_pausas (operador_id, equipe, inicio, ativo) 
        VALUES (:id, :e, NOW(), 1)
    ");
    $ins->execute([
        ":id" => $operador_id,
        ":e"  => $equipe
    ]);

    $pausa_id = $pdo->lastInsertId();

    echo json_encode([
        "success" => true,
        "pausa_id" => $pausa_id
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "erro"    => "Erro ao entrar em pausa.",
        "detalhe" => $e->getMessage()
    ]);
}
