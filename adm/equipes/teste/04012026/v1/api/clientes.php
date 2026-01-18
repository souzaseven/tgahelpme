<?php
require_once '../conexao.php';

header('Content-Type: application/json');

// ===============================
// CSRF
// ===============================
if (!validateCSRF()) {
    echo json_encode([
        'success' => false,
        'message' => 'CSRF inválido'
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {

    switch ($method) {

        // ===============================
        // LISTAR
        // ===============================
        case 'GET':
            $stmt = $pdo->query("
                SELECT *
                FROM clientes_web_conexoes
                ORDER BY cliente
            ");

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll()
            ]);
            break;

        // ===============================
        // INSERIR
        // ===============================
        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);

            $stmt = $pdo->prepare("
                INSERT INTO clientes_web_conexoes
                (cod_cliente, cliente, acesso_server, porta, tipo_acesso, status, observacao)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['cod_cliente'],
                $data['cliente'],
                $data['acesso_server'],
                $data['porta'] ?: null,
                $data['tipo_acesso'],
                $data['status'],
                $data['observacao'] ?: null
            ]);

            logAction($pdo, 'INSERT', 'clientes_web_conexoes', $pdo->lastInsertId());

            echo json_encode([
                'success' => true,
                'message' => 'Cliente cadastrado com sucesso'
            ]);
            break;

        // ===============================
        // ATUALIZAR
        // ===============================
        case 'PUT':
            $data = json_decode(file_get_contents("php://input"), true);

            $stmt = $pdo->prepare("
                UPDATE clientes_web_conexoes SET
                    cod_cliente = ?,
                    cliente = ?,
                    acesso_server = ?,
                    porta = ?,
                    tipo_acesso = ?,
                    status = ?,
                    observacao = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $data['cod_cliente'],
                $data['cliente'],
                $data['acesso_server'],
                $data['porta'] ?: null,
                $data['tipo_acesso'],
                $data['status'],
                $data['observacao'] ?: null,
                $data['id']
            ]);

            logAction($pdo, 'UPDATE', 'clientes_web_conexoes', $data['id']);

            echo json_encode([
                'success' => true,
                'message' => 'Cliente atualizado com sucesso'
            ]);
            break;

        // ===============================
        // EXCLUIR
        // ===============================
        case 'DELETE':
            parse_str($_SERVER['QUERY_STRING'], $params);

            $stmt = $pdo->prepare("
                DELETE FROM clientes_web_conexoes
                WHERE id = ?
            ");
            $stmt->execute([$params['id']]);

            logAction($pdo, 'DELETE', 'clientes_web_conexoes', $params['id']);

            echo json_encode([
                'success' => true,
                'message' => 'Cliente removido'
            ]);
            break;

        default:
            throw new Exception("Método não permitido");

    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
