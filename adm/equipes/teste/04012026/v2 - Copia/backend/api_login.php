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
    $status = norm($body['status'] ?? '');

    $where = [];
    $params = [];

    if ($q !== '') {
      $where[] = "(codigo_cliente LIKE ? OR nome_cliente LIKE ? OR caminho_acesso LIKE ? OR versao_padrao LIKE ?)";
      $like = "%$q%";
      array_push($params, $like, $like, $like, $like);
    }

    if ($status !== '') {
      $where[] = "UPPER(status)=UPPER(?)";
      $params[] = $status;
    }

    $whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";
    $offset = ($page - 1) * $limit;

    $stmtT = $pdo->prepare("SELECT COUNT(*) c FROM clientes_web_login $whereSql");
    $stmtT->execute($params);
    $total = (int)$stmtT->fetch()['c'];
    $pages = (int)ceil($total / $limit);

    $stmt = $pdo->prepare("
      SELECT id, codigo_cliente, nome_cliente, caminho_acesso, versao_padrao, status
      FROM clientes_web_login
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

    $stmt = $pdo->prepare("SELECT * FROM clientes_web_login WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) throw new Exception("Registro não encontrado");

    echo json_encode(['success'=>true,'row'=>$row]);
    exit;
  }

  if ($action === 'create') {
    $d = $body['data'] ?? [];
    $codigo = norm($d['codigo_cliente'] ?? '');
    $nome = norm($d['nome_cliente'] ?? '');
    $caminho = norm($d['caminho_acesso'] ?? '');
    $versao = norm($d['versao_padrao'] ?? '');
    $status = strtoupper(norm($d['status'] ?? 'ATIVO'));

    if ($codigo === '' || $nome === '') throw new Exception("Código e Nome são obrigatórios");

    $stmt = $pdo->prepare("
      INSERT INTO clientes_web_login (codigo_cliente, nome_cliente, caminho_acesso, versao_padrao, status, criado_em)
      VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$codigo, $nome, $caminho, $versao, $status]);

    $id = (int)$pdo->lastInsertId();
    logAction($pdo, 'CREATE', 'clientes_web_login', $id, "codigo=$codigo; nome=$nome");

    echo json_encode(['success'=>true,'id'=>$id]);
    exit;
  }

  if ($action === 'update') {
    $id = (int)($body['id'] ?? 0);
    $d = $body['data'] ?? [];
    if ($id <= 0) throw new Exception("ID inválido");

    $codigo = norm($d['codigo_cliente'] ?? '');
    $nome = norm($d['nome_cliente'] ?? '');
    $caminho = norm($d['caminho_acesso'] ?? '');
    $versao = norm($d['versao_padrao'] ?? '');
    $status = strtoupper(norm($d['status'] ?? 'ATIVO'));

    if ($codigo === '' || $nome === '') throw new Exception("Código e Nome são obrigatórios");

    $stmt = $pdo->prepare("
      UPDATE clientes_web_login
      SET codigo_cliente=?, nome_cliente=?, caminho_acesso=?, versao_padrao=?, status=?, atualizado_em=NOW()
      WHERE id=?
      LIMIT 1
    ");
    $stmt->execute([$codigo, $nome, $caminho, $versao, $status, $id]);

    logAction($pdo, 'UPDATE', 'clientes_web_login', $id, "codigo=$codigo; nome=$nome");
    echo json_encode(['success'=>true]);
    exit;
  }

  if ($action === 'delete') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception("ID inválido");

    $stmt = $pdo->prepare("DELETE FROM clientes_web_login WHERE id=? LIMIT 1");
    $stmt->execute([$id]);

    logAction($pdo, 'DELETE', 'clientes_web_login', $id, "deleted");
    echo json_encode(['success'=>true]);
    exit;
  }

  throw new Exception("Ação inválida");

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
  exit;
}
