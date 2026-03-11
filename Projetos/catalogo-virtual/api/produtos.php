<?php
// ============================================
// API - produtos.php
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/conexao.php';

$method = $_SERVER['REQUEST_METHOD'];
$acao   = $_GET['acao'] ?? '';
$id     = $_GET['id'] ?? null;

// ============================================
// GET - Listar ou detalhar produtos
// ============================================
if ($method === 'GET') {

    // Detalhar produto específico
    if ($id) {
        try {
            $stmt = $pdo->prepare("
                SELECT p.*, e.quantidade AS estoque
                FROM produtos p
                LEFT JOIN estoque e ON e.produto_id = p.id
                WHERE p.id = ? AND p.ativo = 1
            ");
            $stmt->execute([$id]);
            $produto = $stmt->fetch();

            if ($produto) {
                echo json_encode(['success' => true, 'data' => $produto]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar produto']);
        }
        exit;
    }

    // Listar todos os produtos
    try {
        $empresa_id = $_GET['empresa_id'] ?? 1;
        $busca      = $_GET['busca'] ?? '';

        $sql = "
            SELECT p.*, e.quantidade AS estoque
            FROM produtos p
            LEFT JOIN estoque e ON e.produto_id = p.id
            WHERE p.ativo = 1 AND p.empresa_id = ?
        ";

        $params = [$empresa_id];

        if (!empty($busca)) {
            $sql .= " AND (p.nome LIKE ? OR p.codigo LIKE ? OR p.descricao LIKE ?)";
            $termo = "%$busca%";
            $params[] = $termo;
            $params[] = $termo;
            $params[] = $termo;
        }

        $sql .= " ORDER BY p.nome ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $produtos = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'total'   => count($produtos),
            'data'    => $produtos
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao listar produtos']);
    }
    exit;
}

// ============================================
// POST - Cadastrar produto (admin)
// ============================================
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    $empresa_id = $body['empresa_id'] ?? null;
    $codigo     = $body['codigo']     ?? '';
    $nome       = $body['nome']       ?? '';
    $descricao  = $body['descricao']  ?? '';
    $preco      = $body['preco']      ?? 0;
    $quantidade = $body['quantidade'] ?? 0;

    if (!$empresa_id || empty($nome)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Campos obrigatórios: empresa_id e nome']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Inserir produto
        $stmt = $pdo->prepare("
            INSERT INTO produtos (empresa_id, codigo, nome, descricao, preco, ativo)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$empresa_id, $codigo, $nome, $descricao, $preco]);
        $produto_id = $pdo->lastInsertId();

        // Inserir estoque
        $stmt = $pdo->prepare("
            INSERT INTO estoque (produto_id, quantidade) VALUES (?, ?)
        ");
        $stmt->execute([$produto_id, $quantidade]);

        $pdo->commit();

        echo json_encode([
            'success'    => true,
            'message'    => 'Produto cadastrado com sucesso',
            'produto_id' => $produto_id
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar produto']);
    }
    exit;
}

// Método não permitido
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método não permitido']);