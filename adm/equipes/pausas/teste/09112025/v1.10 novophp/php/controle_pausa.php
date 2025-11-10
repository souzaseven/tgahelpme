<?php
// =============================================
// listar_operadores.php (v1.8 - Souza System)
// Lista operadores agrupados por líder (usando campo verlider)
// Filtro “somente minha equipe” baseado em verlider
// =============================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once 'conexao.php';

try {
    // 🔹 Líderes fixos
    $lideres = ['Daniel Feix', 'Alex Sandro Braulio', 'Willian Pereira Reis'];
    $resultado = [];

    // 🔹 Parâmetros de filtro vindos do frontend
    $operador = isset($_GET['operador']) ? trim($_GET['operador']) : '';
    $somenteMinhaEquipe = isset($_GET['minha']) && $_GET['minha'] === '1';

    // 🔹 Verifica se a tabela "operadores" existe
    $tabelaExiste = false;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'operadores'");
        $tabelaExiste = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro ao verificar tabela: " . $e->getMessage());
    }

    if ($tabelaExiste) {
        // ===================================================
        // 🔹 Busca os operadores (usando verlider)
        // ===================================================
        $placeholders = str_repeat('?,', count($lideres) - 1) . '?';
        $sql = "
            SELECT 
                nome,
                lider,
                COALESCE(verlider, lider) AS verlider,
                fila,
                COALESCE(status_pausa, 'disponivel') AS status,
                tempo_pausa,
                tempo_espera,
                motivo_pausa
            FROM operadores
            WHERE COALESCE(verlider, lider) IN ($placeholders)
            ORDER BY COALESCE(verlider, lider), nome ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($lideres);
        $dadosBanco = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ===================================================
        // 🔹 Agrupa operadores por verlider
        // ===================================================
        $dadosPorLider = [];
        foreach ($dadosBanco as $operadorItem) {
            $verlider = $operadorItem['verlider'];
            $dadosPorLider[$verlider][] = $operadorItem;
        }

        // ===================================================
        // 🔹 Monta resultado final
        // ===================================================
        foreach ($lideres as $lider) {
            $operadores = $dadosPorLider[$lider] ?? [];
            $fila = !empty($operadores) ? ($operadores[0]['fila'] ?? 'Suporte TGA') : 'Suporte TGA';
            
            $resultado[] = [
                'verlider' => $lider,
                'fila' => $fila,
                'operadores' => $operadores
            ];
        }

        // ===================================================
        // 🔹 Filtro “somente minha equipe”
        // ===================================================
        if ($somenteMinhaEquipe && $operador !== '') {
            // Descobre o líder (verlider) do operador logado
            $stmt = $pdo->prepare("SELECT COALESCE(verlider, lider) AS verlider FROM operadores WHERE nome = ? LIMIT 1");
            $stmt->execute([$operador]);
            $linha = $stmt->fetch(PDO::FETCH_ASSOC);
            $meuLider = $linha['verlider'] ?? null;

            if ($meuLider) {
                $resultado = array_filter($resultado, fn($eq) => $eq['verlider'] === $meuLider);
                $resultado = array_values($resultado);
            }
        }

        // ===================================================
        // 🔹 Retorno JSON
        // ===================================================
        echo json_encode([
            'success' => true,
            'equipes' => $resultado,
            'fonte' => 'banco',
            'total_operadores' => count($dadosBanco),
            'filtro_ativo' => $somenteMinhaEquipe ? $operador : null
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    } else {
        // ===================================================
        // 🔸 Fallback se tabela não existir
        // ===================================================
        $equipesFallback = [
            [
                'verlider' => 'Daniel Feix',
                'fila' => 'Suporte TGA',
                'operadores' => [
                    ['nome' => 'Daniel Feix', 'fila' => 'Suporte TGA', 'status' => 'disponivel'],
                    ['nome' => 'Operador 1 - Daniel', 'fila' => 'Suporte TGA', 'status' => 'disponivel']
                ]
            ],
            [
                'verlider' => 'Alex Sandro Braulio',
                'fila' => 'Suporte TGA',
                'operadores' => [
                    ['nome' => 'Alex Sandro Braulio', 'fila' => 'Suporte TGA', 'status' => 'disponivel'],
                    ['nome' => 'Operador 1 - Alex', 'fila' => 'Suporte TGA', 'status' => 'disponivel']
                ]
            ],
            [
                'verlider' => 'Willian Pereira Reis',
                'fila' => 'Suporte TGA',
                'operadores' => [
                    ['nome' => 'Willian Pereira Reis', 'fila' => 'Suporte TGA', 'status' => 'disponivel'],
                    ['nome' => 'Operador 1 - Willian', 'fila' => 'Suporte TGA', 'status' => 'disponivel']
                ]
            ]
        ];

        echo json_encode([
            'success' => true,
            'equipes' => $equipesFallback,
            'fonte' => 'fallback',
            'aviso' => 'Tabela "operadores" não encontrada, usando dados padrão'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

} catch (Exception $e) {
    // ===================================================
    // 🔴 Fallback final em caso de erro grave
    // ===================================================
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao carregar operadores: ' . $e->getMessage(),
        'equipes' => []
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>
