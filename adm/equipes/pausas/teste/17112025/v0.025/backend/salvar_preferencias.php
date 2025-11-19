<?php
require_once "conexao.php";

header('Content-Type: application/json; charset=utf-8');

$id  = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
$audio = isset($_POST["audio"]) ? intval($_POST["audio"]) : 1;
$notificacao = isset($_POST["notificacao"]) ? intval($_POST["notificacao"]) : 1;

if (!$id) {
    echo json_encode([
        "success" => false,
        "msg" => "ID inválido."
    ]);
    exit;
}

try {
    $sql = "UPDATE operadores 
            SET pref_audio = :audio,
                pref_notificacao = :notificacao
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(":audio", $audio, PDO::PARAM_INT);
    $stmt->bindValue(":notificacao", $notificacao, PDO::PARAM_INT);
    $stmt->bindValue(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode([
        "success" => true,
        "msg" => "Preferências salvas com sucesso!"
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "msg" => "Erro ao salvar preferências.",
        "detalhe" => $e->getMessage()
    ]);
}
