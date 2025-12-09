<?php
header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

$id   = intval($_POST["id"] ?? 0);
$som  = intval($_POST["pref_som"] ?? 0);

if ($id <= 0) {
    respostaJSON([
        "success" => false,
        "erro" => "ID inválido"
    ]);
}

try {
    $sql = $pdo->prepare("
        UPDATE controle_pausa
        SET pref_som = :som
        WHERE id = :id
        LIMIT 1
    ");

    $sql->execute([
        ":som" => $som,
        ":id"  => $id
    ]);

    respostaJSON([
        "success" => true,
        "msg" => "Preferências salvas com sucesso."
    ]);

} catch (Exception $e) {
    respostaJSON([
        "success" => false,
        "erro"    => $e->getMessage()
    ]);
}
