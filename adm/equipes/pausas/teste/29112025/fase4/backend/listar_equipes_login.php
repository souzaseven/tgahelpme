<?php
// ============================================================
// listar_equipes_login.php - Login baseado na TABELA controle_pausa
// ============================================================
// Retorna equipes + operadores no formato esperado pelo login.js
// Agora incluindo campo is_admin (0 = comum, 1 = admin)
// ============================================================

header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

// DEBUG TEMPORÁRIO — remover depois se quiser
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {

    // ============================================================
    // 1) BUSCAR EQUIPES (DISTINTAS)
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
        echo json_encode([
            "success" => true,
            "equipes" => [],
            "fonte"   => "controle_pausa"
        ]);
        exit;
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
                is_admin
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
                "is_admin" => isset($op["is_admin"]) ? (int)$op["is_admin"] : 0
            ];
        }

        $resultado[] = [
            "lider"       => $equipe,
            "nome_equipe" => $equipe,
            "operadores"  => $operadores
        ];
    }

    // ============================================================
    // 3) RETORNO FINAL
    // ============================================================
    echo json_encode([
        "success" => true,
        "equipes" => $resultado,
        "fonte"   => "controle_pausa"
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "erro"    => "Erro ao carregar equipes",
        "detalhe" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
