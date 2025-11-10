<?php
// =============================================
// listar_operadores.php (v1.6 - Corrigido)
// Lista operadores agrupados por líder + status
// =============================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once 'conexao.php';

try {
    // 🔹 Líderes fixos
    $lideres = ['Daniel Feix', 'Alex Sandro Braulio', 'Willian Pereira Reis'];
    $resultado = [];

    // 🔹 Tenta buscar do banco se a tabela existir
    $tabelaExiste = false;
    try {
        // 🔧 CORREÇÃO: Sintaxe correta para verificar tabela
        $stmt = $pdo->query("SHOW TABLES LIKE 'operadores'");
        $tabelaExiste = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $tabelaExiste = false;
        error_log("Erro ao verificar tabela: " . $e->getMessage());
    }

    if ($tabelaExiste) {
        // Busca do banco - CORREÇÃO: usando placeholders corretamente
        $placeholders = str_repeat('?,', count($lideres) - 1) . '?';
        
        $sql = "
            SELECT 
                nome,
                lider,
                fila,
                COALESCE(status_pausa, 'disponivel') AS status,
                tempo_pausa,
                tempo_espera,
                motivo_pausa
            FROM operadores
            WHERE lider IN ($placeholders)
            ORDER BY lider, nome ASC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($lideres);
        $dadosBanco = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Organizar por líder
        $dadosPorLider = [];
        foreach ($dadosBanco as $operador) {
            $lider = $operador['lider'];
            if (!isset($dadosPorLider[$lider])) {
                $dadosPorLider[$lider] = [];
            }
            $dadosPorLider[$lider][] = $operador;
        }

        // Montar resultado final
        foreach ($lideres as $lider) {
            $operadores = $dadosPorLider[$lider] ?? [];
            $fila = !empty($operadores) ? ($operadores[0]['fila'] ?? 'Suporte TGA') : 'Suporte TGA';
            
            $resultado[] = [
                'lider' => $lider,
                'fila' => $fila,
                'operadores' => $operadores
            ];
        }

        echo json_encode([
            'success' => true,
            'equipes' => $resultado,
            'fonte' => 'banco',
            'total_operadores' => count($dadosBanco)
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    } else {
        // 🔹 Fallback: dados padrão se tabela não existir
        $equipesFallback = [
            [
                'lider' => 'Daniel Feix',
                'fila' => 'Suporte TGA',
                'operadores' => [
                    ['nome' => 'Daniel Feix', 'fila' => 'Suporte TGA', 'status' => 'disponivel', 'tempo_pausa' => null, 'tempo_espera' => null, 'motivo_pausa' => null],
                    ['nome' => 'Operador 1 - Daniel', 'fila' => 'Suporte TGA', 'status' => 'disponivel', 'tempo_pausa' => null, 'tempo_espera' => null, 'motivo_pausa' => null]
                ]
            ],
            [
                'lider' => 'Alex Sandro Braulio', 
                'fila' => 'Suporte TGA',
                'operadores' => [
                    ['nome' => 'Alex Sandro Braulio', 'fila' => 'Suporte TGA', 'status' => 'disponivel', 'tempo_pausa' => null, 'tempo_espera' => null, 'motivo_pausa' => null],
                    ['nome' => 'Operador 1 - Alex', 'fila' => 'Suporte TGA', 'status' => 'disponivel', 'tempo_pausa' => null, 'tempo_espera' => null, 'motivo_pausa' => null]
                ]
            ],
            [
                'lider' => 'Willian Pereira Reis',
                'fila' => 'Suporte TGA', 
                'operadores' => [
                    ['nome' => 'Willian Pereira Reis', 'fila' => 'Suporte TGA', 'status' => 'disponivel', 'tempo_pausa' => null, 'tempo_espera' => null, 'motivo_pausa' => null],
                    ['nome' => 'Operador 1 - Willian', 'fila' => 'Suporte TGA', 'status' => 'disponivel', 'tempo_pausa' => null, 'tempo_espera' => null, 'motivo_pausa' => null]
                ]
            ]
        ];

        echo json_encode([
            'success' => true,
            'equipes' => $equipesFallback,
            'fonte' => 'fallback',
            'aviso' => 'Tabela operadores não encontrada, usando dados padrão'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

} catch (Exception $e) {
    // 🔹 Fallback final em caso de erro grave
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao carregar operadores: ' . $e->getMessage(),
        'equipes' => []
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>