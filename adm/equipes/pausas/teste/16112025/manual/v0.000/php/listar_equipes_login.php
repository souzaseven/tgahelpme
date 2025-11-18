<?php
// =============================================
// listar_equipes_login.php (v1.0)
// Lista equipes e operadores para o login
// =============================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once 'conexao.php';

try {
    // Tenta buscar do banco
    $tabelaExiste = false;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'operadores'");
        $tabelaExiste = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $tabelaExiste = false;
    }

    if ($tabelaExiste) {
        // Busca operadores do banco agrupados por líder
        $sql = "SELECT DISTINCT lider FROM operadores WHERE lider IS NOT NULL";
        $stmt = $pdo->query($sql);
        $lideres = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $equipes = [];
        foreach ($lideres as $lider) {
            $sqlOperadores = "SELECT nome FROM operadores WHERE lider = ? ORDER BY nome";
            $stmtOperadores = $pdo->prepare($sqlOperadores);
            $stmtOperadores->execute([$lider]);
            $operadores = $stmtOperadores->fetchAll(PDO::FETCH_COLUMN);
            
            $equipes[] = [
                'lider' => $lider,
                'operadores' => $operadores
            ];
        }

        echo json_encode([
            'success' => true,
            'equipes' => $equipes,
            'fonte' => 'banco'
        ], JSON_UNESCAPED_UNICODE);

    } else {
        // Fallback com dados padrão
        $equipesFallback = [
            [
                'lider' => 'Daniel Feix',
                'operadores' => ['Daniel Feix', 'Operador 1 - Daniel', 'Operador 2 - Daniel']
            ],
            [
                'lider' => 'Alex Sandro Braulio',
                'operadores' => ['Alex Sandro Braulio', 'Operador 1 - Alex', 'Operador 2 - Alex']
            ],
            [
                'lider' => 'Willian Pereira Reis', 
                'operadores' => ['Willian Pereira Reis', 'Operador 1 - Willian', 'Operador 2 - Willian']
            ]
        ];

        echo json_encode([
            'success' => true,
            'equipes' => $equipesFallback,
            'fonte' => 'fallback'
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao carregar equipes: ' . $e->getMessage(),
        'equipes' => []
    ], JSON_UNESCAPED_UNICODE);
}
?>