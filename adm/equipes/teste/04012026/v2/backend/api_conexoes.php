<?php
require_once __DIR__ . '/conexao.php';
header('Content-Type: application/json; charset=utf-8');

/* =====================================================
   VALIDACOES BASICAS
===================================================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Método não permitido']);
  exit;
}

if (!validateCSRF()) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'CSRF inválido']);
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
   CONSTANTES DE NEGOCIO
===================================================== */
const TIPOS_PERMITIDOS  = ['FV_MOBILE_ON', 'API_MOBILE_OFF'];
const STATUS_PERMITIDOS = ['ON', 'OFF', 'ERRO'];

try {

  /* =====================================================
     LISTAGEM
  ===================================================== */
  if ($action === 'list') {

    $page    = max(1, (int)($body['page'] ?? 1));
    $limit   = min(200, max(5, (int)($body['limit'] ?? 10)));
    $q       = norm($body['q'] ?? '');
    $tipo    = strtoupper(norm($body['tipo'] ?? ''));
    $status  = strtoupper(norm($body['status'] ?? ''));
    $groupBy = norm($body['groupBy'] ?? '');

    $where  = [];
    $params = [];

    /* =================================================
       🔒 REGRA FIXA DE NEGÓCIO
       CONEXÕES ≠ WHATSAPP
       (WhatsApp NUNCA entra aqui)
    ================================================= */
    $where[] = "UPPER(tipo_acesso) IN ('SMART_CLIENTE', 'API')";

    /* ===== BUSCA ===== */
    if ($q !== '') {
      $where[] = "(cod_cliente LIKE ? OR cliente LIKE ? OR acesso_server LIKE ?)";
      $like = "%$q%";
      array_push($params, $like, $like, $like);
    }

    /* ===== FILTRO POR TIPO ===== */
    if ($tipo !== '') {
      if (!in_array($tipo, TIPOS_PERMITIDOS, true)) {
        throw new Exception("Tipo de acesso inválido");
      }

      // FV_MOBILE_ON → SMART_CLIENTE
      // API_MOBILE_OFF → API
      if ($tipo === 'FV_MOBILE_ON') {
        $where[] = "UPPER(tipo_acesso) = 'SMART_CLIENTE'";
      } else {
        $where[] = "UPPER(tipo_acesso) = 'API'";
      }
    }

    /* ===== FILTRO STATUS ===== */
    if ($status !== '') {
      if (!in_array($status, STATUS_PERMITIDOS, true)) {
        throw new Exception("Status inválido");
      }
      $where[]  = "UPPER(status) = ?";
      $params[] = $status;
    }

    $whereSql = "WHERE " . implode(" AND ", $where);
    $offset   = ($page - 1) * $limit;

    /* ===== ORDENAÇÃO ===== */
    $orderSql = "ORDER BY id DESC";

    if ($groupBy === 'tipo') {
      $orderSql = "ORDER BY tipo_acesso ASC, status ASC, id DESC";
    }

    if ($groupBy === 'status') {
      $orderSql = "ORDER BY status ASC, tipo_acesso ASC, id DESC";
    }

    /* ===== TOTAL ===== */
    $stmtT = $pdo->prepare("
      SELECT COUNT(*) c
      FROM clientes_web_conexoes
      $whereSql
    ");
    $stmtT->execute($params);
    $total = (int)$stmtT->fetch()['c'];
    $pages = (int)ceil($total / $limit);

    /* ===== DADOS ===== */
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
      FROM clientes_web_conexoes
      $whereSql
      $orderSql
      LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);

    echo json_encode([
      'success'    => true,
      'page'       => $page,
      'pages'      => max(1, $pages),
      'total'      => $total,
      'count_page' => $stmt->rowCount(),
      'rows'       => $stmt->fetchAll()
    ]);
    exit;
  }

  /* =====================================================
     GET
  ===================================================== */
  if ($action === 'get') {

    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) {
      throw new Exception("ID inválido");
    }

    $stmt = $pdo->prepare("
      SELECT *
      FROM clientes_web_conexoes
      WHERE id = ?
      LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
      throw new Exception("Registro não encontrado");
    }

    echo json_encode(['success' => true, 'row' => $row]);
    exit;
  }

  /* =====================================================
     CREATE
  ===================================================== */
  if ($action === 'create') {

    $d       = $body['data'] ?? [];
    $cod     = norm($d['cod_cliente'] ?? '');
    $cliente = norm($d['cliente'] ?? '');
    $server  = norm($d['acesso_server'] ?? '');
    $porta   = $d['porta'] ?? null;
    $tipo    = strtoupper(norm($d['tipo_acesso'] ?? 'FV_MOBILE_ON'));
    $status  = strtoupper(norm($d['status'] ?? 'OFF'));
    $obs     = norm($d['observacao'] ?? '');

    if ($cod === '' || $cliente === '') {
      throw new Exception("Código e Cliente são obrigatórios");
    }

    if (!in_array($tipo, TIPOS_PERMITIDOS, true)) {
      throw new Exception("Tipo de acesso inválido");
    }

    if (!in_array($status, STATUS_PERMITIDOS, true)) {
      throw new Exception("Status inválido");
    }

    $tipoDb = ($tipo === 'FV_MOBILE_ON') ? 'SMART_CLIENTE' : 'API';

    $stmt = $pdo->prepare("
      INSERT INTO clientes_web_conexoes
      (cod_cliente, cliente, acesso_server, porta, tipo_acesso, status, observacao, criado_em, atualizado_em)
      VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");

    $stmt->execute([
      $cod,
      $cliente,
      $server,
      $porta === null ? null : (int)$porta,
      $tipoDb,
      $status,
      $obs
    ]);

    echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
    exit;
  }

  /* =====================================================
     UPDATE
  ===================================================== */
  if ($action === 'update') {

    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) {
      throw new Exception("ID inválido");
    }

    $d       = $body['data'] ?? [];
    $cod     = norm($d['cod_cliente'] ?? '');
    $cliente = norm($d['cliente'] ?? '');
    $server  = norm($d['acesso_server'] ?? '');
    $porta   = $d['porta'] ?? null;
    $tipo    = strtoupper(norm($d['tipo_acesso'] ?? 'FV_MOBILE_ON'));
    $status  = strtoupper(norm($d['status'] ?? 'OFF'));
    $obs     = norm($d['observacao'] ?? '');

    if ($cod === '' || $cliente === '') {
      throw new Exception("Código e Cliente são obrigatórios");
    }

    if (!in_array($tipo, TIPOS_PERMITIDOS, true)) {
      throw new Exception("Tipo de acesso inválido");
    }

    if (!in_array($status, STATUS_PERMITIDOS, true)) {
      throw new Exception("Status inválido");
    }

    $tipoDb = ($tipo === 'FV_MOBILE_ON') ? 'SMART_CLIENTE' : 'API';

    $stmt = $pdo->prepare("
      UPDATE clientes_web_conexoes
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
      $porta === null ? null : (int)$porta,
      $tipoDb,
      $status,
      $obs,
      $id
    ]);

    echo json_encode(['success' => true]);
    exit;
  }

  /* =====================================================
     DELETE
  ===================================================== */
  if ($action === 'delete') {

    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) {
      throw new Exception("ID inválido");
    }

    $stmt = $pdo->prepare("DELETE FROM clientes_web_conexoes WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
    exit;
  }

  throw new Exception("Ação inválida");

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'message' => $e->getMessage()
  ]);
  exit;
}
