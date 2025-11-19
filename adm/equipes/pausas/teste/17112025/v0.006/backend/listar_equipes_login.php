<?php
// ============================================================
// listar_equipes_login.php (v3.0) - Compatível com sistema antigo
// ============================================================
// - Busca equipes baseado na coluna "lider" da tabela operadores
// - Se tabela não existir, usa fallback padrão
// ============================================================

require_once "conexao.php";

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {

    // Verifica existência da tabela operadores
    $tabela = $pdo->query("SHOW TABLES LIKE 'operadores'")->fetch(PDO::FETCH_ASSOC);

    if ($tabela) {

        // Buscar todos líderes distintos
        $sql = "SELECT DISTINCT lider FROM operadores 
                WHERE lider IS NOT NULL AND lider <> '' 
                ORDER BY lider ASC";

        $stmt = $pdo->query($sql);
        $lideres = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $equipes = [];

        foreach ($lideres as $lider) {

            // Buscar operadores da equipe
            $sqlOp = "SELECT nome FROM operadores 
                      WHERE lider = ? AND nome IS NOT NULL 
                      ORDER BY nome ASC";

            $q = $pdo->prepare($sqlOp);
            $q->execute([$lider]);
            $operadores = $q->fetchAll(PDO::FETCH_COLUMN);

            // Montar grupo
            $equipes[] = [
                "lider" => $lider,
                "operadores" => $operadores
            ];
        }

        echo json_encode([
            "success" => true,
            "equipes" => $equipes,
            "fonte" => "banco"
        ], JSON_UNESCAPED_UNICODE);

    } else {

        // Fallback antigo
        $equipesFallback = [
            [
                "lider" => "Daniel Feix",
                "operadores" => [
                    "Daniel Feix",
                    "Operador 1 - Daniel",
                    "Operador 2 - Daniel"
                ]
            ],
            [
                "lider" => "Alex Sandro Braulio",
                "operadores" => [
                    "Alex Sandro Braulio",
                    "Operador 1 - Alex",
                    "Operador 2 - Alex"
                ]
            ],
            [
                "lider" => "Willian Pereira Reis",
                "operadores" => [
                    "Willian Pereira Reis",
                    "Operador 1 - Willian",
                    "Operador 2 - Willian"
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
