<?php
// ============================================
// API - carrinho.php
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/conexao.php';

// Inicia sessão para identificar o carrinho do cliente
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cria sessão do carrinho se não existir
if (empty($_SESSION['sessao_carrinho'])) {
    $_SESSION['sessao_carrinho'] = bin2hex(random_bytes(16));
}

$sessao  = $_SESSION['sessao_carrinho'];
$method  = $_SERVER['REQUEST_METHOD'];
$acao    = $_GET['acao'] ?? '';

// ============================================
// Função: Buscar ou criar carrinho da sessão
// ============================================
function obterCarrinho($pdo, $sessao) {
    $stmt = $pdo->prepare("SELECT id FROM carrinho WHERE sessao = ?");
    $stmt->execute([$sessao]);
    $carrinho = $stmt->fetch();

    if (!$carrinho) {
        $stmt = $pdo->prepare("INSERT INTO carrinho (sessao) VALUES (?)");
        $stmt->execute([$sessao]);
        return $pdo->lastInsertId();
    }

    return $carrinho['id'];
}

// ============================================
// GET - Listar itens ou contar
// ============================================
if ($method === 'GET') {

    // Contar total de itens no carrinho
    if ($acao === 'contar') {
        $carrinho_id = obterCarrinho($pdo, $sessao);

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(quantidade), 0) AS total
            FROM carrinho_itens
            WHERE carrinho_id = ?
        ");
        $stmt->execute([$carrinho_id]);
        $resultado = $stmt->fetch();

        echo json_encode(['success' => true, 'total' => (int) $resultado['total']]);
        exit;
    }

    // Listar todos os itens do carrinho
    try {
        $carrinho_id = obterCarrinho($pdo, $sessao);

        $stmt = $pdo->prepare("
            SELECT ci.id, ci.quantidade, ci.preco_unitario,
                   p.nome, p.codigo, p.foto,
                   (ci.quantidade * ci.preco_unitario) AS subtotal
            FROM carrinho_itens ci
            INNER JOIN produtos p ON p.id = ci.produto_id
            WHERE ci.carrinho_id = ?
        ");
        $stmt->execute([$carrinho_id]);
        $itens = $stmt->fetchAll();

        $total = array_sum(array_column($itens, 'subtotal'));

        echo json_encode([
            'success' => true,
            'itens'   => $itens,
            'total'   => number_format($total, 2, '.', '')
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao listar carrinho']);
    }
    exit;
}

// ============================================
// POST - Adicionar / Remover / Atualizar
// ============================================
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $acao = $body['acao'] ?? '';

    // ---- ADICIONAR ITEM ----
    if ($acao === 'adicionar') {
        $produto_id = $body['produto_id'] ?? null;
        $quantidade = $body['quantidade'] ?? 1;

        if (!$produto_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'produto_id é obrigatório']);
            exit;
        }

        try {
            // Verifica se produto existe e tem estoque
            $stmt = $pdo->prepare("
                SELECT p.id, p.preco, p.nome, e.quantidade AS estoque
                FROM produtos p
                LEFT JOIN estoque e ON e.produto_id = p.id
                WHERE p.id = ? AND p.ativo = 1
            ");
            $stmt->execute([$produto_id]);
            $produto = $stmt->fetch();

            if (!$produto) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
                exit;
            }

            if ($produto['estoque'] < $quantidade) {
                echo json_encode(['success' => false, 'message' => 'Estoque insuficiente']);
                exit;
            }

            $carrinho_id = obterCarrinho($pdo, $sessao);

            // Verifica se o produto já está no carrinho
            $stmt = $pdo->prepare("
                SELECT id, quantidade FROM carrinho_itens
                WHERE carrinho_id = ? AND produto_id = ?
            ");
            $stmt->execute([$carrinho_id, $produto_id]);
            $item_existente = $stmt->fetch();

            if ($item_existente) {
                // Atualiza quantidade
                $nova_quantidade = $item_existente['quantidade'] + $quantidade;
                $stmt = $pdo->prepare("
                    UPDATE carrinho_itens SET quantidade = ? WHERE id = ?
                ");
                $stmt->execute([$nova_quantidade, $item_existente['id']]);
            } else {
                // Insere novo item
                $stmt = $pdo->prepare("
                    INSERT INTO carrinho_itens (carrinho_id, produto_id, quantidade, preco_unitario)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$carrinho_id, $produto_id, $quantidade, $produto['preco']]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Produto adicionado ao carrinho'
            ]);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao adicionar ao carrinho']);
        }
        exit;
    }

    // ---- REMOVER ITEM ----
    if ($acao === 'remover') {
        $item_id = $body['item_id'] ?? null;

        if (!$item_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'item_id é obrigatório']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM carrinho_itens WHERE id = ?");
            $stmt->execute([$item_id]);

            echo json_encode(['success' => true, 'message' => 'Item removido do carrinho']);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao remover item']);
        }
        exit;
    }

    // ---- ATUALIZAR QUANTIDADE ----
    if ($acao === 'atualizar') {
        $item_id    = $body['item_id']    ?? null;
        $quantidade = $body['quantidade'] ?? 1;

        if (!$item_id || $quantidade < 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE carrinho_itens SET quantidade = ? WHERE id = ?
            ");
            $stmt->execute([$quantidade, $item_id]);

            echo json_encode(['success' => true, 'message' => 'Quantidade atualizada']);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar quantidade']);
        }
        exit;
    }

    // ---- LIMPAR CARRINHO ----
    if ($acao === 'limpar') {
        try {
            $carrinho_id = obterCarrinho($pdo, $sessao);

            $stmt = $pdo->prepare("DELETE FROM carrinho_itens WHERE carrinho_id = ?");
            $stmt->execute([$carrinho_id]);

            echo json_encode(['success' => true, 'message' => 'Carrinho limpo']);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao limpar carrinho']);
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