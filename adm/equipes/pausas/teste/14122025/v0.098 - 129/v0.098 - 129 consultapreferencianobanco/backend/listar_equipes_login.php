<?php
// ============================================================
// listar_equipes_login.php — FASE 6
// Retorna equipes + operadores do controle_pausa
// Formato compatível com login.js FASE 6
// ============================================================

header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

try {

    // ============================================================
    // 1) BUSCAR LISTA DE EQUIPES
    // ============================================================
    $sqlEq = $pdo->prepare("
        SELECT DISTINCT equipe
        FROM controle_pausa
        WHERE equipe IS NOT NULL AND equipe <> ''
        ORDER BY equipe ASC
    ");
    $sqlEq->execute();
    $equipes = $sqlEq->fetchAll(PDO::FETCH_COLUMN);

    if (!$equipes) {
        respostaJSON([
            "success" => true,
            "equipes" => [],
            "fonte"   => "controle_pausa"
        ]);
    }

    $resultado = [];

    // ============================================================
    // 2) PARA CADA EQUIPE, BUSCAR OPERADORES
    // ============================================================
    foreach ($equipes as $equipe) {

$sqlOp = $pdo->prepare("
    SELECT 
        id,
        nome_usuario AS nome,
        senha,
        is_admin,
        elider
    FROM controle_pausa
    WHERE equipe = :equipe
    ORDER BY nome_usuario ASC
");


        $sqlOp->execute([":equipe" => $equipe]);
        $operadoresDB = $sqlOp->fetchAll(PDO::FETCH_ASSOC);

        $operadores = [];
        foreach ($operadoresDB as $op) {
$operadores[] = [
    "id"       => (int)$op["id"],
    "nome"     => $op["nome"],
    "senha"    => $op["senha"],
    "is_admin" => isset($op["is_admin"]) ? (int)$op["is_admin"] : 0,
    "elider"   => isset($op["elider"]) ? (int)$op["elider"] : 0
];

        }

        // Estrutura compatível com login.js
        $resultado[] = [
            "lider"       => $equipe,
            "nome_equipe" => $equipe,
            "operadores"  => $operadores
        ];
    }

    // ============================================================
    // 3) RETORNO FINAL
    // ============================================================
    respostaJSON([
        "success" => true,
        "equipes" => $resultado,
        "fonte"   => "controle_pausa"
    ]);

} catch (Exception $e) {

    respostaJSON([
        "success" => false,
        "erro"    => "Erro ao carregar equipes",
        "detalhe" => $e->getMessage()
    ]);
}
