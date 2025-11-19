<?php
// ============================================================
// listar_equipes_login.php (v4.0) - Retorna operadores como {id, nome}
// ============================================================
// - Busca equipes baseado na coluna "lider" da tabela operadores
// - Retorna operadores como objetos (id, nome)
// - Compatível com o novo login e o novo painel
// ============================================================

require_once "conexao.php";

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {

    // Verifica existência da tabela operadores
    $tabela = $pdo->query("SHOW TABLES LIKE 'operadores'")->fetch(PDO::FETCH_ASSOC);

    if ($tabela) {

        // Buscar todos líderes distintos
        $sql = "SELECT DISTINCT lider 
                FROM operadores 
                WHERE lider IS NOT NULL AND lider <> '' 
                ORDER BY lider ASC";

        $stmt = $pdo->query($sql);
        $lideres = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $equipes = [];

        foreach ($lideres as $lider) {

            // Agora retorna ID + NOME do operador
            $sqlOp = "SELECT id, nome 
                      FROM operadores 
                      WHERE lider = ? AND nome IS NOT NULL 
                      ORDER BY nome ASC";

            $q = $pdo->prepare($sqlOp);
            $q->execute([$lider]);
            $operadores = $q->fetchAll(PDO::FETCH_ASSOC); // <-- AQUI É A CORREÇÃO

            // Montar grupo
            $equipes[] = [
                "lider" => $lider,
                "operadores" => $operadores // <-- Agora é lista de objetos
            ];
        }

        echo json_encode([
            "success" => true,
            "equipes" => $equipes,
            "fonte" => "banco"
        ], JSON_UNESCAPED_UNICODE);

    } else {

        // Fallback antigo (mantivemos igual)
        $equipesFallback = [
            [
                "lider" => "Daniel Feix",
                "operadores" => [
                    ["id" => 0, "nome" => "Daniel Feix"],
                    ["id" => 0, "nome" => "Operador 1 - Daniel"],
                    ["id" => 0, "nome" => "Operador 2 - Daniel"]
                ]
            ],
            [
                "lider" => "Alex Sandro Braulio",
                "operadores" => [
                    ["id" => 0, "nome" => "Alex Sandro Braulio"],
                    ["id" => 0, "nome" => "Operador 1 - Alex"],
                    ["id" => 0, "nome" => "Operador 2 - Alex"]
                ]
            ],
            [
                "lider" => "Willian Pereira Reis",
                "operadores" => [
                    ["id" => 0, "nome" => "Willian Pereira Reis"],
                    ["id" => 0, "nome" => "Operador 1 - Willian"],
                    ["id" => 0, "nome" => "Operador 2 - Willian"]
                ]
            ]
        ];

        echo json_encode([
            "success" => true,
            "equipes" => $equipesFallback,
            "fonte" => "fallback"
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "erro" => "Erro ao carregar equipes",
        "detalhe" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

}
