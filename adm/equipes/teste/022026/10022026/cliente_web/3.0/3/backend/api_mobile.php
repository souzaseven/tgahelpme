<?php
require_once __DIR__ . '/conexao.php';
header('Content-Type: application/json; charset=utf-8');

/* =====================================================
   VALIDAÇÕES BÁSICAS
===================================================== */
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

/* =====================================================
   INPUT
===================================================== */
$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $body['action'] ?? '';

function norm($v) {
  return trim((string)$v);
}

/* =====================================================
   CONSTANTES
===================================================== */
const TIPOS_PERMITIDOS = [
  'API_FORCA_DE_VENDA',
  'FV_SMART_CLIENT'
];

const STATUS_PERMITIDOS = [
  'ON',
  'OFF',
  'ERRO'
];

/* =====================================================
   COLUNAS PERMITIDAS PARA ORDENAÇÃO
===================================================== */
$ORDERABLE_FIELDS = [
  'id',
  'cod_cliente',
  'cliente',
  'acesso_server',
  'porta',
  'tipo_acesso',
  'status',
  'atualizado_em',
  'criado_em'
];

try {

  /* =====================================================
     LIST
  ===================================================== */
  if ($action === 'list') {

    $page   = max(1, (int)($body['page'] ?? 1));
    $limit  = min(200, max(5, (int)($body['limit'] ?? 10)));
    $q      = norm($body['q'] ?? '');
    $tipo   = strtoupper(norm($body['tipo'] ?? ''));
    $status = strtoupper(norm($body['status'] ?? ''));

    /* ORDENAÇÃO */
    $orderBy  = $body['order_by'] ?? 'id';
    $orderDir = strtoupper($body['order_dir'] ?? 'DESC');

    if (!in_array($orderBy, $ORDERABLE_FIELDS, true)) {
      $orderBy = 'id';
    }

    if (!in_array($orderDir, ['ASC','DESC'], true)) {
      $orderDir = 'DESC';
    }

    $where  = [];
    $params = [];

    /* BUSCA */
    if ($q !== '') {
      $where[] = "(
        cod_cliente LIKE ?
        OR cliente LIKE ?
        OR acesso_server LIKE ?
        OR porta LIKE ?
        OR observacao LIKE ?
      )";
      $like = "%{$q}%";
      array_push($params, $like, $like, $like, $like, $like);
    }

    /* FILTRO TIPO */
    if ($tipo !== '') {
      if (!in_array($tipo, TIPOS_PERMITIDOS, true)) {
        throw new Exception('Tipo de acesso inválido');
      }
      $where[]  = "tipo_acesso = ?";
      $params[] = $tipo;
    }

    /* FILTRO STATUS */
    if ($status !== '') {
      if (!in_array($status, STATUS_PERMITIDOS, true)) {
        throw new Exception('Status inválido');
      }
      $where[]  = "status = ?";
      $params[] = $status;
    }

    $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';
    $offset   = ($page - 1) * $limit;

    /* ===============================
       RESUMO (MINI CARDS)
    =============================== */
    $stmtS = $pdo->prepare("
      SELECT
        COUNT(*) AS total,
        SUM(tipo_acesso = 'API_FORCA_DE_VENDA') AS api,
        SUM(tipo_acesso = 'FV_SMART_CLIENT') AS fv
      FROM clientes_web_mobile
      $whereSql
    ");
    $stmtS->execute($params);
    $stats = $stmtS->fetch(PDO::FETCH_ASSOC);

    /* TOTAL */
    $stmtT = $pdo->prepare("
      SELECT COUNT(*) c
      FROM clientes_web_mobile
      $whereSql
    ");
    $stmtT->execute($params);
    $total = (int)$stmtT->fetch()['c'];
    $pages = max(1, (int)ceil($total / $limit));

    /* DADOS */
    $stmt = $pdo->prepare("
      SELECT
        id,
        cod_cliente,
        cliente,
        acesso_server,
        porta,
        tipo_acesso,
        status,
        observacao,
        criado_em,
        atualizado_em
      FROM clientes_web_mobile
      $whereSql
      ORDER BY {$orderBy} {$orderDir}
      LIMIT {$limit} OFFSET {$offset}
    ");
    $stmt->execute($params);

    echo json_encode([
      'success'    => true,
      'page'       => $page,
      'pages'      => $pages,
      'total'      => $total,
      'count_page' => $stmt->rowCount(),
      'order_by'   => $orderBy,
      'order_dir'  => $orderDir,
      'rows'       => $stmt->fetchAll(),
      'stats'      => [
        'total' => (int)($stats['total'] ?? 0),
        'api'   => (int)($stats['api'] ?? 0),
        'fv'    => (int)($stats['fv'] ?? 0)
      ]
    ]);
    exit;
  }

  /* =====================================================
     GET
  ===================================================== */
  if ($action === 'get') {

    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido');

    $stmt = $pdo->prepare("
      SELECT *
      FROM clientes_web_mobile
      WHERE id = ?
      LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) throw new Exception('Registro não encontrado');

    echo json_encode(['success'=>true,'row'=>$row]);
    exit;
  }

  /* =====================================================
     CREATE
  ===================================================== */
  if ($action === 'create') {

    $d = $body['data'] ?? [];

    $cod     = norm($d['cod_cliente'] ?? '');
    $cliente = norm($d['cliente'] ?? '');
    $server  = norm($d['acesso_server'] ?? '');
    $porta   = norm($d['porta'] ?? '');
    $tipo    = strtoupper(norm($d['tipo_acesso'] ?? ''));
    $status  = strtoupper(norm($d['status'] ?? 'OFF'));
    $obs     = norm($d['observacao'] ?? '');

    if ($cod === '' || $cliente === '') {
      throw new Exception('Código e Cliente são obrigatórios');
    }

    if ($porta !== '' && !is_numeric($porta)) {
      throw new Exception('Porta deve ser numérica');
    }

    if (!in_array($tipo, TIPOS_PERMITIDOS, true)) {
      throw new Exception('Tipo de acesso inválido');
    }

    if (!in_array($status, STATUS_PERMITIDOS, true)) {
      throw new Exception('Status inválido');
    }

    $stmt = $pdo->prepare("
      INSERT INTO clientes_web_mobile
      (cod_cliente, cliente, acesso_server, porta, tipo_acesso, status, observacao, criado_em, atualizado_em)
      VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");

    $stmt->execute([
      $cod,
      $cliente,
      $server,
      $porta !== '' ? (int)$porta : null,
      $tipo,
      $status,
      $obs
    ]);

    echo json_encode(['success'=>true]);
    exit;
  }

  /* =====================================================
     UPDATE
  ===================================================== */
  if ($action === 'update') {

    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido');

    $d = $body['data'] ?? [];

    $cod     = norm($d['cod_cliente'] ?? '');
    $cliente = norm($d['cliente'] ?? '');
    $server  = norm($d['acesso_server'] ?? '');
    $porta   = norm($d['porta'] ?? '');
    $tipo    = strtoupper(norm($d['tipo_acesso'] ?? ''));
    $status  = strtoupper(norm($d['status'] ?? 'OFF'));
    $obs     = norm($d['observacao'] ?? '');

    if ($porta !== '' && !is_numeric($porta)) {
      throw new Exception('Porta deve ser numérica');
    }

    if (!in_array($tipo, TIPOS_PERMITIDOS, true)) {
      throw new Exception('Tipo de acesso inválido');
    }

    if (!in_array($status, STATUS_PERMITIDOS, true)) {
      throw new Exception('Status inválido');
    }

    $stmt = $pdo->prepare("
      UPDATE clientes_web_mobile
      SET
        cod_cliente   = ?,
        cliente       = ?,
        acesso_server = ?,
        porta         = ?,
        tipo_acesso   = ?,
        status        = ?,
        observacao    = ?,
        atualizado_em = NOW()
      WHERE id = ?
      LIMIT 1
    ");

    $stmt->execute([
      $cod,
      $cliente,
      $server,
      $porta !== '' ? (int)$porta : null,
      $tipo,
      $status,
      $obs,
      $id
    ]);

    echo json_encode(['success'=>true]);
    exit;
  }

  /* =====================================================
     DELETE
  ===================================================== */
  if ($action === 'delete') {

    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido');

    $pdo->prepare("
      DELETE FROM clientes_web_mobile
      WHERE id = ?
      LIMIT 1
    ")->execute([$id]);

    echo json_encode(['success'=>true]);
    exit;
  }

  throw new Exception('Ação inválida');

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode([
    'success'=>false,
    'message'=>$e->getMessage()
  ]);
}
