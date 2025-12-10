<?php
header("Content-Type: application/json; charset=utf-8");

require_once "conexao.php";

try {
    $sql = $pdo->query("
        SELECT nome_usuario AS nome, equipe
        FROM controle_pausa
        ORDER BY equipe ASC, nome_usuario ASC
    ");

    $dados = $sql->fetchAll(PDO::FETCH_ASSOC);

    $grupos = [];

    foreach ($dados as $d) {
        $eq = $d["equipe"];

        if (!isset($grupos[$eq])) {
            $grupos[$eq] = [];
        }

        $grupos[$eq][] = $d["nome"];
    }

    echo json_encode([
        "success" => true,
        "equipes" => $grupos
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "erro" => $e->getMessage()
    ]);
}
