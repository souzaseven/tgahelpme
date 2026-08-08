<?php
require_once __DIR__ . '/../includes/session.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autenticado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../backend/conexao.php';

$method = $_SERVER['REQUEST_METHOD'];

// GET — lista períodos de férias ativos (data_fim >= hoje)
if ($method === 'GET') {
    try {
        $stmt = $pdo->query(
            "SELECT id, operador_id, nome_operador, data_inicio, data_fim, criado_por, criado_em
             FROM operador_ferias
             WHERE data_fim >= CURDATE()
             ORDER BY data_inicio"
        );
        echo json_encode(['ferias' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        echo json_encode(['ferias' => []], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';

// POST action=criar — registra período de férias
if ($action === 'criar') {
    $operadorId   = (int) ($body['operador_id']   ?? 0);
    $nomeOperador = trim($body['nome_operador']   ?? '');
    $dataInicio   = $body['data_inicio'] ?? '';
    $dataFim      = $body['data_fim']    ?? '';

    if ($operadorId <= 0
        || $nomeOperador === ''
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)
    ) {
        http_response_code(400);
        echo json_encode(['erro' => 'Dados inválidos.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($dataFim < $dataInicio) {
        http_response_code(400);
        echo json_encode(['erro' => 'A data de fim deve ser igual ou posterior à data de início.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        // Remove qualquer período ativo anterior para o mesmo operador
        $pdo->prepare(
            "DELETE FROM operador_ferias WHERE operador_id = ? AND data_fim >= CURDATE()"
        )->execute([$operadorId]);

        $stmt = $pdo->prepare(
            "INSERT INTO operador_ferias (operador_id, nome_operador, data_inicio, data_fim, criado_por)
             VALUES (:operador_id, :nome_operador, :data_inicio, :data_fim, :criado_por)"
        );
        $stmt->execute([
            ':operador_id'   => $operadorId,
            ':nome_operador' => substr($nomeOperador, 0, 150),
            ':data_inicio'   => $dataInicio,
            ':data_fim'      => $dataFim,
            ':criado_por'    => (int) $_SESSION['usuario_id'],
        ]);

        echo json_encode(['success' => true, 'id' => (int) $pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao salvar férias.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// POST action=cancelar — remove período de férias pelo id
if ($action === 'cancelar') {
    $id = (int) ($body['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['erro' => 'ID inválido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $pdo->prepare("DELETE FROM operador_ferias WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao cancelar férias.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

http_response_code(400);
echo json_encode(['erro' => 'Ação inválida.'], JSON_UNESCAPED_UNICODE);
