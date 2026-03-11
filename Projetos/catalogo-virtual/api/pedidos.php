<?php
// ============================================
// API - pedidos.php
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sessao = $_SESSION['sessao_carrinho'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ============================================
// GET - Listar ou detalhar pedido
// ============================================
if ($method === 'GET') {
    $id         = $_GET['id']         ?? null;
    $empresa_id = $_GET['empresa_id'] ?? 1;

    // Detalhar pedido específico
    if ($id) {
        try {
            $stmt = $pdo->prepare("
                SELECT p.*, e.nome AS empresa_nome
                FROM pedidos p
                INNER JOIN empresas e ON e.id = p.empresa_id
                WHERE p.id = ?
            ");
            $stmt->execute([$id]);
            $pedido = $stmt->fetch();

            if (!$pedido) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Pedido não encontrado']);
                exit;
            }

            // Buscar itens do pedido
            $stmt = $pdo->prepare("
                SELECT pi.*, pr.nome, pr.codigo, pr.foto,
                       (pi.quantidade * pi.preco) AS subtotal
                FROM pedido_itens pi
                INNER JOIN produtos pr ON pr.id = pi.produto_id
                WHERE pi.pedido_id = ?
            ");
            $stmt->execute([$id]);
            $itens = $stmt->fetchAll();

            $total = array_sum(array_column($itens, 'subtotal'));

            echo json_encode([
                'success' => true,
                'pedido'  => $pedido,
                'itens'   => $itens,
                'total'   => number_format($total, 2, '.', '')
            ]);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar pedido']);
        }
        exit;
    }

    // Listar pedidos da empresa
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   COUNT(pi.id) AS total_itens,
                   SUM(pi.quantidade * pi.preco) AS total_valor
            FROM pedidos p
            LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
            WHERE p.empresa_id = ?
            GROUP BY p.id
            ORDER BY p.data DESC
        ");
        $stmt->execute([$empresa_id]);
        $pedidos = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'total'   => count($pedidos),
            'data'    => $pedidos
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao listar pedidos']);
    }
    exit;
}

// ============================================
// POST - Gerar pedido a partir do carrinho
// ============================================
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $acao = $body['acao'] ?? '';

    // ---- GERAR PEDIDO ----
    if ($acao === 'gerar') {
        $empresa_id      = $body['empresa_id']      ?? 1;
        $cliente_nome    = $body['cliente_nome']    ?? '';
        $cliente_telefone = $body['cliente_telefone'] ?? '';
        $cliente_email   = $body['cliente_email']   ?? '';

        if (empty($cliente_nome)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nome do cliente é obrigatório']);
            exit;
        }

        if (empty($sessao)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Sessão do carrinho não encontrada']);
            exit;
        }

        try {
            // Buscar carrinho da sessão
            $stmt = $pdo->prepare("SELECT id FROM carrinho WHERE sessao = ?");
            $stmt->execute([$sessao]);
            $carrinho = $stmt->fetch();

            if (!$carrinho) {
                echo json_encode(['success' => false, 'message' => 'Carrinho vazio ou não encontrado']);
                exit;
            }

            $carrinho_id = $carrinho['id'];

            // Buscar itens do carrinho
            $stmt = $pdo->prepare("
                SELECT ci.produto_id, ci.quantidade, ci.preco_unitario,
                       p.nome, e.quantidade AS estoque
                FROM carrinho_itens ci
                INNER JOIN produtos p ON p.id = ci.produto_id
                LEFT JOIN estoque e ON e.produto_id = ci.produto_id
                WHERE ci.carrinho_id = ?
            ");
            $stmt->execute([$carrinho_id]);
            $itens = $stmt->fetchAll();

            if (empty($itens)) {
                echo json_encode(['success' => false, 'message' => 'Carrinho está vazio']);
                exit;
            }

            // Verificar estoque de todos os itens
            foreach ($itens as $item) {
                if ($item['estoque'] < $item['quantidade']) {
                    echo json_encode([
                        'success' => false,
                        'message' => "Estoque insuficiente para o produto: {$item['nome']}"
                    ]);
                    exit;
                }
            }

            $pdo->beginTransaction();

            // Criar pedido
            $stmt = $pdo->prepare("
                INSERT INTO pedidos (empresa_id, cliente_nome, cliente_telefone, cliente_email, status)
                VALUES (?, ?, ?, ?, 'pendente')
            ");
            $stmt->execute([$empresa_id, $cliente_nome, $cliente_telefone, $cliente_email]);
            $pedido_id = $pdo->lastInsertId();

            // Inserir itens do pedido e atualizar estoque
            foreach ($itens as $item) {
                // Inserir item no pedido
                $stmt = $pdo->prepare("
                    INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $pedido_id,
                    $item['produto_id'],
                    $item['quantidade'],
                    $item['preco_unitario']
                ]);

                // Atualizar estoque
                $stmt = $pdo->prepare("
                    UPDATE estoque SET quantidade = quantidade - ?
                    WHERE produto_id = ?
                ");
                $stmt->execute([$item['quantidade'], $item['produto_id']]);
            }

            // Limpar carrinho após gerar pedido
            $stmt = $pdo->prepare("DELETE FROM carrinho_itens WHERE carrinho_id = ?");
            $stmt->execute([$carrinho_id]);

            $pdo->commit();

            echo json_encode([
                'success'   => true,
                'message'   => 'Pedido gerado com sucesso',
                'pedido_id' => $pedido_id
            ]);

        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao gerar pedido']);
        }
        exit;
    }

    // ---- ATUALIZAR STATUS ----
    if ($acao === 'atualizar_status') {
        $pedido_id = $body['pedido_id'] ?? null;
        $status    = $body['status']    ?? '';

        $status_validos = ['pendente', 'confirmado', 'cancelado'];

        if (!$pedido_id || !in_array($status, $status_validos)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
            $stmt->execute([$status, $pedido_id]);

            echo json_encode(['success' => true, 'message' => 'Status atualizado']);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar status']);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    exit;
}

// Método não permitido
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método não permitido']);