<?php
require_once __DIR__ . '/conexao.php';
header('Content-Type: application/json; charset=utf-8');

/* =========================================================
   VALIDAÇÕES INICIAIS
========================================================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Método inválido']);
  exit;
}

if (!validateCSRF()) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'CSRF inválido']);
  exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $body['action'] ?? '';

/* Sanitização simples */
function n($v) {
  return trim((string)$v);
}

try {

  /* =========================================================
     LISTAGEM + KPIs
  ========================================================= */
  if ($action === 'list') {

    $q        = n($body['q'] ?? '');
    $status   = strtoupper(n($body['status'] ?? ''));

    $minUsers = (isset($body['min_users']) && $body['min_users'] !== '')
      ? (int)$body['min_users']
      : null;

    /* 🔃 Ordenação */
    $orderBy  = $body['order_by'] ?? 'qtd_usuarios';
    $orderDir = strtoupper($body['order_dir'] ?? 'DESC');

    $allowedOrder = [
      'qtd_usuarios',
      'nome_empresa',
      'codigo_empresa',
      'criado_em',
      'atualizado_em'
    ];

    if (!in_array($orderBy, $allowedOrder, true)) {
      $orderBy = 'qtd_usuarios';
    }

    $orderDir = $orderDir === 'ASC' ? 'ASC' : 'DESC';

    $where  = [];
    $params = [];

    /* 🔎 Busca textual */
    if ($q !== '') {
      $where[] = "(
        codigo_empresa LIKE ?
        OR nome_empresa LIKE ?
        OR CAST(qtd_usuarios AS CHAR) LIKE ?
        OR observacao LIKE ?
      )";
      $like = "%{$q}%";
      array_push($params, $like, $like, $like, $like);
    }

    /* 🔘 Status */
    if (in_array($status, ['ATIVO', 'INATIVO'], true)) {
      $where[]  = "status = ?";
      $params[] = $status;
    }

    /* 👥 Quantidade EXATA de usuários */
    if ($minUsers !== null) {
      $where[]  = "qtd_usuarios = ?";
      $params[] = $minUsers;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    /* ===== KPIs (respeitando filtros) ===== */
    $stmtS = $pdo->prepare("
      SELECT
        COUNT(*)                AS total,
        SUM(status = 'ATIVO')   AS ativos,
        SUM(status = 'INATIVO') AS inativos,
        COALESCE(SUM(qtd_usuarios),0) AS total_usuarios
      FROM clientes_tga_web_usuarios
      {$whereSql}
    ");
    $stmtS->execute($params);
    $stats = $stmtS->fetch(PDO::FETCH_ASSOC);

    /* Paginação server-side */
    $total  = (int)($stats['total'] ?? 0);
    $page   = max(1, (int)($body['page']  ?? 1));
    $limit  = min(500, max(5, (int)($body['limit'] ?? 10)));
    $pages  = max(1, (int)ceil($total / $limit));
    $offset = ($page - 1) * $limit;

    /* Listagem com LIMIT/OFFSET no banco */
    $stmt = $pdo->prepare("
      SELECT
        id,
        codigo_empresa,
        nome_empresa,
        qtd_usuarios,
        status,
        observacao,
        criado_em,
        atualizado_em
      FROM clientes_tga_web_usuarios
      {$whereSql}
      ORDER BY {$orderBy} {$orderDir}
      LIMIT {$limit} OFFSET {$offset}
    ");
    $stmt->execute($params);

    echo json_encode([
      'success' => true,
      'page'    => $page,
      'pages'   => $pages,
      'total'   => $total,
      'rows'    => $stmt->fetchAll(PDO::FETCH_ASSOC),
      'stats'   => $stats
    ]);
    exit;
  }

  /* =========================================================
     GET (EDIÇÃO)
  ========================================================= */
  if ($action === 'get') {

    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) {
      throw new Exception('ID inválido');
    }

    $stmt = $pdo->prepare("
      SELECT
        id,
        codigo_empresa,
        nome_empresa,
        qtd_usuarios,
        status,
        observacao,
        criado_em,
        atualizado_em
      FROM clientes_tga_web_usuarios
      WHERE id = ?
      LIMIT 1
    ");
    $stmt->execute([$id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      throw new Exception('Registro não encontrado');
    }

    echo json_encode([
      'success' => true,
      'row'     => $row
    ]);
    exit;
  }

  /* =========================================================
     CREATE
  ========================================================= */
  if ($action === 'create') {

    $d = $body['data'] ?? [];

    $codigo = n($d['codigo_empresa'] ?? '');
    $nome   = n($d['nome_empresa'] ?? '');
    $qtd    = max(0, (int)($d['qtd_usuarios'] ?? 0));
    $status = strtoupper(n($d['status'] ?? 'ATIVO'));
    $obs    = n($d['observacao'] ?? '');

    if ($codigo === '' || $nome === '') {
      throw new Exception('Código e Nome são obrigatórios');
    }

    if (!in_array($status, ['ATIVO','INATIVO'], true)) {
      $status = 'ATIVO';
    }

    $stmt = $pdo->prepare("
      INSERT INTO clientes_tga_web_usuarios
        (codigo_empresa, nome_empresa, qtd_usuarios, status, observacao)
      VALUES
        (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
      $codigo,
      $nome,
      $qtd,
      $status,
      ($obs === '' ? null : $obs)
    ]);

    echo json_encode(['success' => true]);
    exit;
  }

  /* =========================================================
     UPDATE
  ========================================================= */
  if ($action === 'update') {

    $id = (int)($body['id'] ?? 0);
    $d  = $body['data'] ?? [];

    if ($id <= 0) {
      throw new Exception('ID inválido');
    }

    $codigo = n($d['codigo_empresa'] ?? '');
    $nome   = n($d['nome_empresa'] ?? '');
    $qtd    = max(0, (int)($d['qtd_usuarios'] ?? 0));
    $status = strtoupper(n($d['status'] ?? 'ATIVO'));
    $obs    = n($d['observacao'] ?? '');

    if ($codigo === '' || $nome === '') {
      throw new Exception('Código e Nome são obrigatórios');
    }

    if (!in_array($status, ['ATIVO','INATIVO'], true)) {
      $status = 'ATIVO';
    }

    $stmt = $pdo->prepare("
      UPDATE clientes_tga_web_usuarios
      SET
        codigo_empresa = ?,
        nome_empresa   = ?,
        qtd_usuarios   = ?,
        status         = ?,
        observacao     = ?,
        atualizado_em  = NOW()
      WHERE id = ?
      LIMIT 1
    ");
    $stmt->execute([
      $codigo,
      $nome,
      $qtd,
      $status,
      ($obs === '' ? null : $obs),
      $id
    ]);

    echo json_encode(['success' => true]);
    exit;
  }

  /* =========================================================
     DELETE (somente INATIVO)
  ========================================================= */
  if ($action === 'delete') {

    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) {
      throw new Exception('ID inválido');
    }

    $stmtC = $pdo->prepare("
      SELECT status
      FROM clientes_tga_web_usuarios
      WHERE id = ?
      LIMIT 1
    ");
    $stmtC->execute([$id]);
    $row = $stmtC->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      throw new Exception('Registro não encontrado');
    }

    if (strtoupper($row['status']) !== 'INATIVO') {
      throw new Exception('Só é permitido excluir registros INATIVOS');
    }

    $stmt = $pdo->prepare("
      DELETE FROM clientes_tga_web_usuarios
      WHERE id = ?
      LIMIT 1
    ");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
    exit;
  }

/* =========================================================
   ÚLTIMOS CADASTRADOS
========================================================= */
if ($action === 'last') {

  $stmt = $pdo->query("
    SELECT
      id,
      nome_empresa,
      codigo_empresa,
      qtd_usuarios,
      status,
      observacao,
      criado_em,
      atualizado_em
    FROM clientes_tga_web_usuarios
    ORDER BY COALESCE(atualizado_em, criado_em) DESC
    LIMIT 5
  ");

  echo json_encode([
    'success' => true,
    'rows'    => $stmt->fetchAll(PDO::FETCH_ASSOC)
  ]);
  exit;
}


  throw new Exception('Ação inválida');

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'message' => $e->getMessage()
  ]);
}