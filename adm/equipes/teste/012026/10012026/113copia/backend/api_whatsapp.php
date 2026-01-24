<?php
require_once __DIR__ . '/conexao.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success'=>false,'message'=>'Método não permitido']);
  exit;
}

if (!validateCSRF()) {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'CSRF inválido']);
  exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $body['action'] ?? '';

function norm($v){ return trim((string)$v); }

try {

  /* ==========================
     LIST
  ========================== */
  if ($action === 'list') {
    $page  = max(1, (int)($body['page'] ?? 1));
    $limit = min(200, max(5, (int)($body['limit'] ?? 10)));
    $q     = norm($body['q'] ?? '');

    $where  = [];
    $params = [];

    if ($q !== '') {
      $where[] = "(cod_cliente LIKE ? OR cliente LIKE ? OR acesso_server LIKE ?)";
      $like = "%$q%";
      array_push($params, $like, $like, $like);
    }

    $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';
    $offset   = ($page - 1) * $limit;

    $stmtT = $pdo->prepare("
      SELECT COUNT(*) c 
      FROM clientes_web_api_whats
      $whereSql
    ");
    $stmtT->execute($params);
    $total = (int)$stmtT->fetch()['c'];

    $stmt = $pdo->prepare("
      SELECT id, cod_cliente, cliente, acesso_server, tipo_acesso, observacao
      FROM clientes_web_api_whats
      $whereSql
      ORDER BY id DESC
      LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);

    echo json_encode([
      'success'     => true,
      'page'        => $page,
      'total'       => $total,
      'count_page'  => $stmt->rowCount(),
      'rows'        => $stmt->fetchAll()
    ]);
    exit;
  }

  /* ==========================
     GET
  ========================== */
  if ($action === 'get') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido');

    $stmt = $pdo->prepare("
      SELECT * FROM clientes_web_api_whats
      WHERE id = ?
      LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) throw new Exception('Registro não encontrado');

    echo json_encode(['success'=>true,'row'=>$row]);
    exit;
  }

  /* ==========================
     CREATE
  ========================== */
  if ($action === 'create') {
    $d = $body['data'] ?? [];

    $cod  = norm($d['cod_cliente'] ?? '');
    $cli  = norm($d['cliente'] ?? '');
    $srv  = norm($d['acesso_server'] ?? '');
    $obs  = norm($d['observacao'] ?? '');
    $tipo = 'API_WHATS';

    if ($cod === '' || $cli === '') {
      throw new Exception('Código e Cliente são obrigatórios');
    }

    $stmt = $pdo->prepare("
      INSERT INTO clientes_web_api_whats
      (cod_cliente, cliente, acesso_server, tipo_acesso, observacao, criado_em, atualizado_em)
      VALUES (?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([$cod, $cli, $srv, $tipo, $obs]);

    echo json_encode(['success'=>true]);
    exit;
  }

  /* ==========================
     UPDATE
  ========================== */
  if ($action === 'update') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido');

    $d = $body['data'] ?? [];

    $cod = norm($d['cod_cliente'] ?? '');
    $cli = norm($d['cliente'] ?? '');
    $srv = norm($d['acesso_server'] ?? '');
    $obs = norm($d['observacao'] ?? '');

    if ($cod === '' || $cli === '') {
      throw new Exception('Código e Cliente são obrigatórios');
    }

    $stmt = $pdo->prepare("
      UPDATE clientes_web_api_whats
      SET cod_cliente=?, cliente=?, acesso_server=?, observacao=?, atualizado_em=NOW()
      WHERE id=?
      LIMIT 1
    ");
    $stmt->execute([$cod, $cli, $srv, $obs, $id]);

    echo json_encode(['success'=>true]);
    exit;
  }

  /* ==========================
     DELETE
  ========================== */
  if ($action === 'delete') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido');

    $stmt = $pdo->prepare("DELETE FROM clientes_web_api_whats WHERE id=? LIMIT 1");
    $stmt->execute([$id]);

    echo json_encode(['success'=>true]);
    exit;
  }

  throw new Exception('Ação inválida');

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
