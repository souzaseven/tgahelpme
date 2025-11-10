<?php
// =============================================
// listar_operadores.php (v1.4)
// Lista operadores agrupados por líder + status
// =============================================
header('Content-Type: application/json; charset=utf-8');
require_once 'conexao.php';

try {
    // 🔹 Líderes fixos
    $lideres = [
        'Daniel Feix',
        'Alex Sandro Braulio',
        'Willian Pereira Reis'
    ];

    $resultado = [];

    foreach ($lideres as $lider) {
        $stmt = $pdo->prepare("
            SELECT 
                nome,
                lider,
                fila,
                COALESCE(status_pausa, 'disponivel') AS status,
                tempo_pausa,
                tempo_espera,
                motivo_pausa
            FROM operadores
            WHERE lider = :lider
            ORDER BY nome ASC
        ");
        $stmt->execute(['lider' => $lider]);
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado[] = [
            'lider' => $lider,
            'fila' => $dados[0]['fila'] ?? '—',
            'operadores' => $dados
        ];
    }

    echo json_encode([
        'success' => true,
        'equipes' => $resultado
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao listar operadores: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
