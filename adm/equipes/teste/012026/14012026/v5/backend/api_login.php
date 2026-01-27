<?php
require_once __DIR__ . '/conexao.php';
header('Content-Type: application/json; charset=utf-8');

/* =========================================================
   Validações iniciais
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
   Input
========================================================= */
$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $body['action'] ?? '';

function norm($v) {
  return trim((string)$v);
}

try {

  /* =========================================================
     LISTAGEM (com filtros e paginação)
  ========================================================= */
  if ($action === 'list') {

    $page   = max(1, (int)($body['page'] ?? 1));
    $limit  = min(200, max(5, (int)($body['limit'] ?? 10)));
    $q      = norm($body['q'] ?? '');
    $status = norm($body['status'] ?? '');
    $versao = norm($body['versao'] ?? '');
$exe = $body['exe'] ?? '';



if ($status !== '') {
  $where[] = "UPPER(status) = UPPER(?)";
  $params[] = $status;
}

if ($versao !== '') {
  $where[] = "versao_padrao = ?";
  $params[] = $versao;
}

if ($exe === '1') {
  $where[] = "possui_exe = 1";
}

if ($exe === '0') {
  $where[] = "(possui_exe IS NULL OR possui_exe = 0)";
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
/* =========================================================
   FILTROS DA LISTAGEM (TABELA)
========================================================= */
$where  = [];
$params = [];

/* 🔍 Busca textual — afeta SOMENTE a listagem */
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

/* Status */
if ($status !== '') {
  $where[]  = "UPPER(status) = UPPER(?)";
  $params[] = $status;
}

/* Versão */
if ($versao !== '') {
  $where[]  = "versao_padrao = ?";
  $params[] = $versao;
}

/* Filtro EXE */
if ($exe === '1') {
  $where[] = "possui_exe = 1";
}

if ($exe === '0') {
  $where[] = "(possui_exe IS NULL OR possui_exe = 0)";
}

/* WHERE final da LISTA */
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* =========================================================
   RESUMO (MINI CARDS / KPIs)
   ⚠️ NÃO usa busca textual (q)
========================================================= */
$whereStats  = [];
$paramsStats = [];

/* Status */
if ($status !== '') {
  $whereStats[]  = "UPPER(status) = UPPER(?)";
  $paramsStats[] = $status;
}

/* Versão */
if ($versao !== '') {
  $whereStats[]  = "versao_padrao = ?";
  $paramsStats[] = $versao;
}

/* Filtro EXE */
if ($exe === '1') {
  $whereStats[] = "possui_exe = 1";
}

if ($exe === '0') {
  $whereStats[] = "(possui_exe IS NULL OR possui_exe = 0)";
}

/* WHERE final do RESUMO */
$whereStatsSql = $whereStats
  ? 'WHERE ' . implode(' AND ', $whereStats)
  : '';

/* Consulta do resumo */
$stmtS = $pdo->prepare("
  SELECT
    COUNT(*)               AS total,
    SUM(status = 'ATIVO')  AS ativos,
    SUM(status = 'INATIVO') AS inativos,
    SUM(possui_exe = 1)    AS com_exe
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


/* =========================================================
   ORDENAÇÃO
========================================================= */
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
  'possui_exe'
];

$sortDir = $sortDir === 'DESC' ? 'DESC' : 'ASC';

$orderBy = in_array($sortField, $allowedSortFields, true)
  ? "ORDER BY {$sortField} {$sortDir}"
  : "ORDER BY id DESC";

/* =========================================================
   TOTAL (PAGINAÇÃO)
========================================================= */
$stmtT = $pdo->prepare("
  SELECT COUNT(*) c
  FROM clientes_web_login
  {$whereSql}
");
$stmtT->execute($params);
$total = (int)$stmtT->fetch()['c'];
$pages = (int)ceil($total / $limit);

/* =========================================================
   DADOS
========================================================= */
$stmt = $pdo->prepare("
  SELECT
    id,
    codigo_cliente,
    nome_cliente,
    caminho_acesso,
    versao_padrao,
    status,
    possui_exe,
    exe_nome
  FROM clientes_web_login
  {$whereSql}
  {$orderBy}
  LIMIT {$limit} OFFSET {$offset}
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* =========================================================
   RESPONSE
========================================================= */
echo json_encode([
  'success'     => true,
  'page'        => $page,
  'pages'       => max(1, $pages),
  'total'       => $total,
  'count_page'  => count($rows),
  'rows'        => $rows,
  'stats'       => $stats
]);
exit;

  }

  /* =========================================================
     LISTAR VERSÕES (SELECT)
  ========================================================= */
  if ($action === 'versions') {

    $stmt = $pdo->query("
      SELECT DISTINCT versao_padrao
      FROM clientes_web_login
      WHERE versao_padrao IS NOT NULL
        AND versao_padrao <> ''
      ORDER BY versao_padrao DESC
    ");

    $versions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
      'success'  => true,
      'versions' => $versions
    ]);
    exit;
  }

  /* =========================================================
     GET (edição)
  ========================================================= */
  if ($action === 'get') {

    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) {
      throw new Exception('ID inválido');
    }

    $stmt = $pdo->prepare("SELECT * FROM clientes_web_login WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
      throw new Exception('Registro não encontrado');
    }

    echo json_encode(['success' => true, 'row' => $row]);
    exit;
  }

  /* =========================================================
     CREATE
  ========================================================= */
  if ($action === 'create') {

    $d       = $body['data'] ?? [];
    $codigo  = norm($d['codigo_cliente'] ?? '');
    $nome    = norm($d['nome_cliente'] ?? '');
    $caminho = norm($d['caminho_acesso'] ?? '');
    $versao  = norm($d['versao_padrao'] ?? '');
   // $status  = strtoupper(norm($d['status'] ?? 'ATIVO'));
$status = strtoupper(norm($d['status'] ?? ''));
$possui_exe = isset($d['possui_exe']) && $d['possui_exe'] == 1 ? 1 : null;
$exe_nome   = $possui_exe ? trim($d['exe_nome'] ?? '') : null;

if ($possui_exe && $exe_nome === '') {
  throw new Exception('Informe o nome do EXE');
}

if (!in_array($status, ['ATIVO', 'INATIVO'], true)) {
  $status = 'ATIVO';
}

    if ($codigo === '' || $nome === '') {
      throw new Exception('Código e Nome são obrigatórios');
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


    $id = (int)$pdo->lastInsertId();
    logAction($pdo, 'CREATE', 'clientes_web_login', $id, "codigo={$codigo}; nome={$nome}");

    echo json_encode(['success' => true, 'id' => $id]);
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

    $codigo  = norm($d['codigo_cliente'] ?? '');
    $nome    = norm($d['nome_cliente'] ?? '');
    $caminho = norm($d['caminho_acesso'] ?? '');
    $versao  = norm($d['versao_padrao'] ?? '');
    //$status  = strtoupper(norm($d['status'] ?? 'ATIVO'));
$possui_exe = isset($d['possui_exe']) && $d['possui_exe'] == 1 ? 1 : null;
$exe_nome   = $possui_exe ? trim($d['exe_nome'] ?? '') : null;

$status = strtoupper(norm($d['status'] ?? ''));

if ($possui_exe && $exe_nome === '') {
  throw new Exception('Informe o nome do EXE');
}


if (!in_array($status, ['ATIVO', 'INATIVO'], true)) {
  $status = 'ATIVO';
}

    if ($codigo === '' || $nome === '') {
      throw new Exception('Código e Nome são obrigatórios');
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


    logAction($pdo, 'UPDATE', 'clientes_web_login', $id, "codigo={$codigo}; nome={$nome}");
    echo json_encode(['success' => true]);
    exit;
  }

  /* =========================================================
     DELETE
  ========================================================= */
  if ($action === 'delete') {

    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) {
      throw new Exception('ID inválido');
    }

    $stmt = $pdo->prepare("DELETE FROM clientes_web_login WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);

    logAction($pdo, 'DELETE', 'clientes_web_login', $id, 'deleted');
    echo json_encode(['success' => true]);
    exit;
  }

  /* =========================================================
     AÇÃO INVÁLIDA
  ========================================================= */
  throw new Exception('Ação inválida');

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'message' => $e->getMessage()
  ]);
  exit;
}
