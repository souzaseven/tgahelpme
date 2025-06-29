<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once 'conexao.php'; // Arquivo com sua conexão PDO

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Listar operadores
            $stmt = $pdo->query("SELECT * FROM operadores ORDER BY lider, nome");
            $operadores = $stmt->fetchAll();
            
            // Converter fila de JSON para array
            array_walk($operadores, function(&$op) {
                $op['filas'] = json_decode($op['fila'], true);
            });
            
            echo json_encode(['success' => true, 'data' => $operadores]);
            break;
            
        case 'POST':
            // Adicionar/Atualizar operador
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['nome']) || empty($data['lider']) || empty($data['fila']) || empty($data['link'])) {
                throw new Exception('Dados incompletos');
            }
            
            $fila = json_encode($data['fila']);
            
            // Verifica se é atualização (tem ID)
            if (!empty($data['id'])) {
                $stmt = $pdo->prepare("UPDATE operadores SET nome = ?, lider = ?, fila = ?, link = ? WHERE id = ?");
                $stmt->execute([$data['nome'], $data['lider'], $fila, $data['link'], $data['id']]);
                $message = 'Operador atualizado com sucesso';
            } else {
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