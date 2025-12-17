<?php
header('Content-Type: application/json; charset=utf-8');
require_once "../../backend/conexao.php";

function resposta($arr) {
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

try {

    // ---------------------------------------------------------
    // 1. PODE RECEBER GET OU POST
    // ---------------------------------------------------------
    $equipe = $_GET["equipe"] ?? $_POST["equipe"] ?? null;

    if (!$equipe) {
        resposta([
            "success" => false,
            "erro"    => "Equipe não informada."
        ]);
    }

    // ---------------------------------------------------------
    // 2. BUSCAR OPERADORES DA MESMA EQUIPE (apenas 1 por ID)
    // ---------------------------------------------------------
    $sql = $pdo->prepare("
        SELECT 
            id,
            nome_usuario AS nome
        FROM controle_pausa
        WHERE equipe = :equipe
        GROUP BY id, nome_usuario
        ORDER BY nome_usuario ASC
    ");

    $sql->execute([":equipe" => $equipe]);
    $operadores = $sql->fetchAll(PDO::FETCH_ASSOC);

    // ---------------------------------------------------------
    // 3. RETORNO FINAL
    // ---------------------------------------------------------
    resposta([
        "success"    => true,
        "operadores" => $operadores
    ]);

} catch (Exception $e) {

    resposta([
        "success" => false,
        "erro"    => $e->getMessage()
    ]);
}
