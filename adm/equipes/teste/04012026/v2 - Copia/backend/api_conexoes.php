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

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $body['action'] ?? '';

function norm($v){ return trim((string)$v); }

try {

  if ($action === 'list') {
    $page = max(1, (int)($body['page'] ?? 1));
    $limit = min(200, max(5, (int)($body['limit'] ?? 10)));
    $q = norm($body['q'] ?? '');
    $tipo = norm($body['tipo'] ?? '');
    $status = norm($body['status'] ?? '');

    $where = [];
    $params = [];

    if ($q !== '') {
      $where[] = "(cod_cliente LIKE ? OR cliente LIKE ? OR acesso_server LIKE ?)";
      $like = "%$q%";
      array_push($params, $like, $like, $like);
    }

    if ($tipo !== '') {
      $where[] = "UPPER(tipo_acesso)=UPPER(?)";
      $params[] = $tipo;
    }

    if ($status !== '') {
      $where[] = "UPPER(status)=UPPER(?)";
      $params[] = $status;
    }

    $whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";
    $offset = ($page - 1) * $limit;

    $stmtT = $pdo->prepare("SELECT COUNT(*) c FROM clientes_web_conexoes $whereSql");
    $stmtT->execute($params);
    $total = (int)$stmtT->fetch()['c'];
    $pages = (int)ceil($total / $limit);

    $stmt = $pdo->prepare("
      SELECT id, cod_cliente, cliente, acesso_server, porta, tipo_acesso, status, observacao
      FROM clientes_web_conexoes
      $whereSql
      ORDER BY id DESC
      LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    echo json_encode([
      'success'=>true,
      'page'=>$page,
      'pages'=>max(1,$pages),
      'total'=>$total,
      'count_page'=>count($rows),
      'rows'=>$rows
    ]);
    exit;
  }

  if ($action === 'get') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception("ID inválido");

    $stmt = $pdo->prepare("SELECT * FROM clientes_web_conexoes WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) throw new Exception("Registro não encontrado");

    echo json_encode(['success'=>true,'row'=>$row]);
    exit;
  }

  if ($action === 'create') {
    $d = $body['data'] ?? [];
    $cod = norm($d['cod_cliente'] ?? '');
    $cliente = norm($d['cliente'] ?? '');
    $server = norm($d['acesso_server'] ?? '');
    $porta = $d['porta'] ?? null;
    $tipo = strtoupper(norm($d['tipo_acesso'] ?? 'API'));
    $status = strtoupper(norm($d['status'] ?? 'OFF'));
    $obs = norm($d['observacao'] ?? '');

    if ($cod === '' || $cliente === '') throw new Exception("Código e Cliente são obrigatórios");

    $stmt = $pdo->prepare("
      INSERT INTO clientes_web_conexoes (cod_cliente, cliente, acesso_server, porta, tipo_acesso, status, observacao, criado_em, atualizado_em)
      VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([$cod, $cliente, $server, $porta === null ? null : (int)$porta, $tipo, $status, $obs]);

    $id = (int)$pdo->lastInsertId();
    logAction($pdo, 'CREATE', 'clientes_web_conexoes', $id, "cod=$cod; cliente=$cliente; tipo=$tipo");

    echo json_encode(['success'=>true,'id'=>$id]);
    exit;
  }

  if ($action === 'update') {
    $id = (int)($body['id'] ?? 0);
    $d = $body['data'] ?? [];
    if ($id <= 0) throw new Exception("ID inválido");

    $cod = norm($d['cod_cliente'] ?? '');
    $cliente = norm($d['cliente'] ?? '');
    $server = norm($d['acesso_server'] ?? '');
    $porta = $d['porta'] ?? null;
    $tipo = strtoupper(norm($d['tipo_acesso'] ?? 'API'));
    $status = strtoupper(norm($d['status'] ?? 'OFF'));
    $obs = norm($d['observacao'] ?? '');

    if ($cod === '' || $cliente === '') throw new Exception("Código e Cliente são obrigatórios");

    $stmt = $pdo->prepare("
      UPDATE clientes_web_conexoes
      SET cod_cliente=?, cliente=?, acesso_server=?, porta=?, tipo_acesso=?, status=?, observacao=?, atualizado_em=NOW()
      WHERE id=?
      LIMIT 1
    ");
    $stmt->execute([$cod, $cliente, $server, $porta === null ? null : (int)$porta, $tipo, $status, $obs, $id]);

    logAction($pdo, 'UPDATE', 'clientes_web_conexoes', $id, "cod=$cod; cliente=$cliente; tipo=$tipo");
    echo json_encode(['success'=>true]);
    exit;
  }

  if ($action === 'delete') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception("ID inválido");

    $stmt = $pdo->prepare("DELETE FROM clientes_web_conexoes WHERE id=? LIMIT 1");
    $stmt->execute([$id]);

    logAction($pdo, 'DELETE', 'clientes_web_conexoes', $id, "deleted");
    echo json_encode(['success'=>true]);
    exit;
  }

  throw new Exception("Ação inválida");

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
  exit;
}
