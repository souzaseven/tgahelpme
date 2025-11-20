<?php
require_once "conexao.php";

header('Content-Type: application/json; charset=utf-8');

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id) {
    echo json_encode([
        "success" => false,
        "erro" => "ID inválido."
    ]);
    exit;
}

try {
    $sql = "SELECT pref_audio, pref_notificacao 
            FROM operadores 
            WHERE id = :id LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $dados = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dados) {
        echo json_encode([
            "success" => false,
            "erro" => "Operador não encontrado."
        ]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "audio" => intval($dados["pref_audio"]),
        "notificacao" => intval($dados["pref_notificacao"])
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "erro" => "Erro ao carregar preferências.",
        "detalhe" => $e->getMessage()
    ]);
}
