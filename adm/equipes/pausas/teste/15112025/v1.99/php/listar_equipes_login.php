<?php
// =============================================
// listar_equipes_login.php
// Listagem exclusiva para o MODAL DE LOGIN
// - Retorna líderes + nomes de operadores
// - Não altera a lógica do listar_operadores.php atual
// =============================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once 'conexao.php';

try {
    // 🔹 Líderes fixos usados para montar as equipes
    $lideres = ['Daniel Feix', 'Alex Sandro Braulio', 'Willian Pereira Reis'];

    $resposta = [
        'success' => true,
        'equipes' => []
    ];

    // Verificar se a tabela operadores existe
    $tabelaExiste = false;

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'operadores'");
        $tabelaExiste = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $tabelaExiste = false;
    }

    if ($tabelaExiste) {
        // Busca operadores por líder
        $placeholders = str_repeat('?,', count($lideres) - 1) . '?';

        $sql = "
            SELECT nome, lider
            FROM operadores
            WHERE lider IN ($placeholders)
            ORDER BY lider, nome ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($lideres);
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agrupar por líder
        $porLider = [];
        foreach ($dados as $linha) {
            $lider = $linha['lider'];
            if (!isset($porLider[$lider])) {
                $porLider[$lider] = [];
            }
            $porLider[$lider][] = $linha['nome'];
        }

        // Montar resposta final
        foreach ($lideres as $lider) {
            $resposta['equipes'][] = [
                'lider' => $lider,
                'operadores' => $porLider[$lider] ?? []
            ];
        }

        $resposta['fonte'] = 'banco';

    } else {
        // 🔹 Fallback se não existir tabela operadores
        foreach ($lideres as $lider) {
            $primeiroNome = explode(' ', $lider)[0];

            $resposta['equipes'][] = [
                'lider' => $lider,
                'operadores' => [
                    $lider,
                    "Operador 1 - $primeiroNome"
                ]
            ];
        }

        $resposta['fonte'] = 'fallback';
        $resposta['aviso'] = 'Tabela operadores não encontrada, usando dados padrão.';
    }

    echo json_encode($resposta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao listar equipes para login: ' . $e->getMessage(),
        'equipes' => []
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
