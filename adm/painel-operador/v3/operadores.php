<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once 'conexao.php'; // Arquivo com sua conexão PDO

$method = $_SERVER['REQUEST_METHOD'];

// Definir as equipes fixas
$equipesFixas = [
    'Alex Sandro Braulio',
    'Daniel Feix',
    'Willian Pereira Reis'
];

try {
    switch ($method) {
        case 'GET':
            // Consulta para obter as equipes responsáveis por cada fila
            $queryFila = "
                SELECT 
                    CASE 
                        WHEN fila LIKE '%Suporte Matriz%' THEN 'telefone'
                        WHEN fila LIKE '%Fila Matriz Chat/Whats%' THEN 'chat'
                    END AS tipo,
                    GROUP_CONCAT(DISTINCT lider ORDER BY lider ASC SEPARATOR ', ') AS equipes
                FROM 
                    operadores
                WHERE 
                    (fila LIKE '%Suporte Matriz%' OR fila LIKE '%Fila Matriz Chat/Whats%')
                    AND lider IN ('Alex Sandro Braulio', 'Daniel Feix', 'Willian Pereira Reis')
                GROUP BY 
                    tipo
            ";
            
            $stmtFila = $pdo->query($queryFila);
            $resultadosFila = $stmtFila->fetchAll(PDO::FETCH_ASSOC);

            // Inicializar valores padrão
            $semana = [
                'telefone' => 'Nenhuma equipe definida',
                'chat' => 'Nenhuma equipe definida'
            ];

            // Processar resultados das filas
            foreach ($resultadosFila as $row) {
                if ($row['tipo'] === 'telefone') {
                    $semana['telefone'] = $row['equipes'];
                } elseif ($row['tipo'] === 'chat') {
                    $semana['chat'] = $row['equipes'];
                }
            }

            // Consulta para obter todos os operadores (mantendo a funcionalidade original)
            $stmtOperadores = $pdo->query("SELECT * FROM operadores ORDER BY lider, nome");
            $operadores = $stmtOperadores->fetchAll();

            echo json_encode([
                'success' => true,
                'data' => $operadores,
                'semana' => $semana
            ]);
            break;

        case 'POST':
            // Adicionar/Atualizar operador
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['nome']) || empty($data['lider']) || empty($data['fila']) || empty($data['link'])) {
                throw new Exception('Dados incompletos');
            }

            // Validar se a equipe é uma das permitidas
            if (!in_array($data['lider'], $equipesFixas)) {
                throw new Exception('Equipe/líder inválido');
            }

            $fila = json_encode($data['fila']);

            if (!empty($data['id'])) {
                // Atualizar
                $stmt = $pdo->prepare("UPDATE operadores SET nome = ?, lider = ?, fila = ?, link = ? WHERE id = ?");
                $stmt->execute([$data['nome'], $data['lider'], $fila, $data['link'], $data['id']]);
                $message = 'Operador atualizado com sucesso';
            } else {
                // Inserir
                $stmt = $pdo->prepare("INSERT INTO operadores (nome, lider, fila, link) VALUES (?, ?, ?, ?)");
                $stmt->execute([$data['nome'], $data['lider'], $fila, $data['link']]);
                $message = 'Operador cadastrado com sucesso';
            }

            echo json_encode(['success' => true, 'message' => $message]);
            break;

        case 'DELETE':
            // Remover operador
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['id'])) {
                throw new Exception('ID do operador não informado');
            }

            $stmt = $pdo->prepare("DELETE FROM operadores WHERE id = ?");
            $stmt->execute([$data['id']]);

            echo json_encode(['success' => true, 'message' => 'Operador removido com sucesso']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}