<?php
// =============================================
// listar_operadores.php (v1.8 - Souza System)
// Lista operadores agrupados por líder + status
// Suporte a filtro por operador logado (minha equipe)
// Inclui campo verlider (visão do líder)
// =============================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once 'conexao.php';

try {
    // 🔹 Líderes fixos (base principal)
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
        // 🔹 Busca os operadores (com verlider incluso)
        // ===================================================
        $placeholders = str_repeat('?,', count($lideres) - 1) . '?';
        $sql = "
            SELECT 
                nome,
                lider,
                verlider,
                fila,
                COALESCE(status_pausa, 'disponivel') AS status,
                tempo_pausa,
                tempo_espera,
                motivo_pausa
            FROM operadores
            WHERE lider IN ($placeholders)
            ORDER BY verlider, nome ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($lideres);
        $dadosBanco = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ===================================================
        // 🔹 Agrupa operadores por verlider (ou lider se null)
        // ===================================================
        $dadosPorLider = [];
        foreach ($dadosBanco as $operadorItem) {
            $verLider = $operadorItem['verlider'] ?: $operadorItem['lider'];
            $dadosPorLider[$verLider][] = $operadorItem;
        }

        // ===================================================
        // 🔹 Monta resultado final
        // ===================================================
        foreach ($lideres as $lider) {
            $operadores = $dadosPorLider[$lider] ?? [];
            $fila = !empty($operadores) ? ($operadores[0]['fila'] ?? 'Suporte TGA') : 'Suporte TGA';

            $resultado[] = [
                'lider' => $lider,
                'fila' => $fila,
                'operadores' => $operadores
            ];
        }

        // ===================================================
        // 🔹 Filtro “somente minha equipe”
        // ===================================================
        if ($somenteMinhaEquipe && $operador !== '') {
            $resultado = array_filter($resultado, function($eq) use ($operador) {
                foreach ($eq['operadores'] as $op) {
                    if (strcasecmp($op['nome'], $operador) === 0) {
                        return true;
                    }
                }
                return false;
            });
            $resultado = array_values($resultado); // reorganiza índices
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
                'lider' => 'Daniel Feix',
                'fila' => 'Suporte TGA',
                'operadores' => [
                    ['nome' => 'Daniel Feix', 'fila' => 'Suporte TGA', 'status' => 'disponivel'],
                    ['nome' => 'Operador 1 - Daniel', 'fila' => 'Suporte TGA', 'status' => 'disponivel']
                ]
            ],
            [
                'lider' => 'Alex Sandro Braulio',
                'fila' => 'Suporte TGA',
                'operadores' => [
                    ['nome' => 'Alex Sandro Braulio', 'fila' => 'Suporte TGA', 'status' => 'disponivel'],
                    ['nome' => 'Operador 1 - Alex', 'fila' => 'Suporte TGA', 'status' => 'disponivel']
                ]
            ],
            [
                'lider' => 'Willian Pereira Reis',
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
