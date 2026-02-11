<?php
require_once __DIR__ . '/conexao.php';
header('Content-Type: application/json; charset=utf-8');

/* =========================================================
   VALIDAÇÕES INICIAIS
========================================================= */
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

/* =========================================================
   FUNÇÕES AUXILIARES
========================================================= */
function norm($v) {
  return trim((string)$v);
}

/* =========================================================
   INPUT
========================================================= */
$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $body['action'] ?? '';

try {

  /* =========================================================
     LISTAGEM (FILTROS + PAGINAÇÃO + ORDENAÇÃO)
  ========================================================= */
  if ($action === 'list') {

    /* ==========================
       INPUT DA LISTAGEM
    ========================== */
    $page   = max(1, (int)($body['page'] ?? 1));
    $limit  = min(200, max(5, (int)($body['limit'] ?? 10)));
    $q      = norm($body['q'] ?? '');
    $status = norm($body['status'] ?? '');
    $versao = norm($body['versao'] ?? '');
    $exe    = $body['exe'] ?? '';

    /* ==========================
       FILTROS (LISTAGEM)
    ========================== */
    $where  = [];
    $params = [];

    if ($q !== '') {
      $where[] = "(
        codigo_cliente  LIKE ?
        OR nome_cliente LIKE ?
        OR caminho_acesso LIKE ?
        OR versao_padrao LIKE ?
        OR exe_nome LIKE ?
      )";
      $like = "%{$q}%";
      array_push($params, $like, $like, $like, $like, $like);
    }

    if ($status !== '') {
      $where[]  = "UPPER(status) = UPPER(?)";
      $params[] = $status;
    }

    if ($versao !== '') {
      $where[]  = "versao_padrao = ?";
      $params[] = $versao;
    }

    if ($exe === '1') {
      $where[] = "possui_exe = 1";
    }

    if ($exe === '0') {
      $where[] = "(possui_exe IS NULL OR possui_exe = 0)";
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    /* ==========================
       RESUMO (KPIs)
    ========================== */
    $whereStats  = [];
    $paramsStats = [];

    if ($status !== '') {
      $whereStats[]  = "UPPER(status) = UPPER(?)";
      $paramsStats[] = $status;
    }

    if ($versao !== '') {
      $whereStats[]  = "versao_padrao = ?";
      $paramsStats[] = $versao;
    }

    if ($exe === '1') {
      $whereStats[] = "possui_exe = 1";
    }

    if ($exe === '0') {
      $whereStats[] = "(possui_exe IS NULL OR possui_exe = 0)";
    }

    $whereStatsSql = $whereStats
      ? 'WHERE ' . implode(' AND ', $whereStats)
      : '';

    $stmtS = $pdo->prepare("
      SELECT
        COUNT(*)                AS total,
        SUM(status = 'ATIVO')   AS ativos,
        SUM(status = 'INATIVO') AS inativos,
        SUM(possui_exe = 1)     AS com_exe
      FROM clientes_web_login
      {$whereStatsSql}
    ");
    $stmtS->execute($paramsStats);

    $stats = $stmtS->fetch(PDO::FETCH_ASSOC) ?: [
      'total'    => 0,
      'ativos'   => 0,
      'inativos' => 0,
      'com_exe'  => 0
    ];

    /* ==========================
       ORDENAÇÃO
    ========================== */
    $offset    = ($page - 1) * $limit;
    $sortField = $body['sortField'] ?? '';
    $sortDir   = strtoupper($body['sortDir'] ?? 'ASC');

    $allowedSortFields = [
      'id',
      'codigo_cliente',
      'nome_cliente',
      'caminho_acesso',
      'versao_padrao',
      'status',
      'possui_exe',
      'atualizado_em'
    ];

    $sortDir = $sortDir === 'DESC' ? 'DESC' : 'ASC';

    $orderBy = in_array($sortField, $allowedSortFields, true)
      ? "ORDER BY {$sortField} {$sortDir}"
      : "ORDER BY id DESC";

    /* ==========================
       TOTAL
    ========================== */
    $stmtT = $pdo->prepare("
      SELECT COUNT(*) c
      FROM clientes_web_login
      {$whereSql}
    ");
    $stmtT->execute($params);
    $total = (int)$stmtT->fetch()['c'];
    $pages = (int)ceil($total / $limit);

    /* ==========================
       DADOS
    ========================== */
    $stmt = $pdo->prepare("
      SELECT
        id,
        codigo_cliente,
        nome_cliente,
        caminho_acesso,
        versao_padrao,
        status,
        possui_exe,
        exe_nome,
        DATE_FORMAT(
          COALESCE(atualizado_em, criado_em),
          '%d/%m/%Y %H:%i:%s'
        ) AS atualizado_em
      FROM clientes_web_login
      {$whereSql}
      {$orderBy}
      LIMIT {$limit} OFFSET {$offset}
    ");

    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* ==========================
       RESPONSE
    ========================== */
    echo json_encode([
      'success'    => true,
      'page'       => $page,
      'pages'      => max(1, $pages),
      'total'      => $total,
      'count_page' => count($rows),
      'rows'       => $rows,
      'stats'      => $stats
    ]);
    exit;
  }

  /* =========================================================
     LISTAR VERSÕES
  ========================================================= */
  if ($action === 'versions') {
    $stmt = $pdo->query("
      SELECT DISTINCT versao_padrao
      FROM clientes_web_login
      WHERE versao_padrao IS NOT NULL
        AND versao_padrao <> ''
      ORDER BY versao_padrao DESC
    ");
    echo json_encode([
      'success'  => true,
      'versions' => $stmt->fetchAll(PDO::FETCH_COLUMN)
    ]);
    exit;
  }

  /* =========================================================
     GET
  ========================================================= */
  if ($action === 'get') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido');

    $stmt = $pdo->prepare("SELECT * FROM clientes_web_login WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) throw new Exception('Registro não encontrado');

    echo json_encode(['success' => true, 'row' => $row]);
    exit;
  }

  /* =========================================================
     CREATE
  ========================================================= */
  if ($action === 'create') {

    $d = $body['data'] ?? [];

    $codigo  = norm($d['codigo_cliente'] ?? '');
    $nome    = norm($d['nome_cliente'] ?? '');
    $caminho = norm($d['caminho_acesso'] ?? '');
    $versao  = norm($d['versao_padrao'] ?? '');
    $status  = strtoupper(norm($d['status'] ?? 'ATIVO'));

    $possui_exe = isset($d['possui_exe']) && $d['possui_exe'] == 1 ? 1 : null;
    $exe_nome   = $possui_exe ? trim($d['exe_nome'] ?? '') : null;

    if ($codigo === '' || $nome === '') {
      throw new Exception('Código e Nome são obrigatórios');
    }

    if ($possui_exe && $exe_nome === '') {
      throw new Exception('Informe o nome do EXE');
    }

    if (!in_array($status, ['ATIVO', 'INATIVO'], true)) {
      $status = 'ATIVO';
    }

    $stmt = $pdo->prepare("
      INSERT INTO clientes_web_login
      (
        codigo_cliente,
        nome_cliente,
        caminho_acesso,
        versao_padrao,
        status,
        possui_exe,
        exe_nome,
        criado_em
      )
      VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
      $codigo,
      $nome,
      $caminho,
      $versao,
      $status,
      $possui_exe,
      $exe_nome
    ]);

    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    exit;
  }

  /* =========================================================
     UPDATE
  ========================================================= */
  if ($action === 'update') {

    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido');

    $d = $body['data'] ?? [];

    $codigo  = norm($d['codigo_cliente'] ?? '');
    $nome    = norm($d['nome_cliente'] ?? '');
    $caminho = norm($d['caminho_acesso'] ?? '');
    $versao  = norm($d['versao_padrao'] ?? '');
    $status  = strtoupper(norm($d['status'] ?? 'ATIVO'));

    $possui_exe = isset($d['possui_exe']) && $d['possui_exe'] == 1 ? 1 : null;
    $exe_nome   = $possui_exe ? trim($d['exe_nome'] ?? '') : null;

    if ($codigo === '' || $nome === '') {
      throw new Exception('Código e Nome são obrigatórios');
    }

    if ($possui_exe && $exe_nome === '') {
      throw new Exception('Informe o nome do EXE');
    }

    if (!in_array($status, ['ATIVO', 'INATIVO'], true)) {
      $status = 'ATIVO';
    }

    $stmt = $pdo->prepare("
      UPDATE clientes_web_login
      SET
        codigo_cliente = ?,
        nome_cliente   = ?,
        caminho_acesso = ?,
        versao_padrao  = ?,
        status         = ?,
        possui_exe     = ?,
        exe_nome       = ?,
        atualizado_em  = NOW()
      WHERE id = ?
      LIMIT 1
    ");

    $stmt->execute([
      $codigo,
      $nome,
      $caminho,
      $versao,
      $status,
      $possui_exe,
      $exe_nome,
      $id
    ]);

    echo json_encode(['success' => true]);
    exit;
  }

  /* =========================================================
     DELETE
  ========================================================= */
  if ($action === 'delete') {

    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido');

    $stmt = $pdo->prepare("DELETE FROM clientes_web_login WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
    exit;
  }

  throw new Exception('Ação inválida');

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'message' => $e->getMessage()
  ]);
  exit;
}
