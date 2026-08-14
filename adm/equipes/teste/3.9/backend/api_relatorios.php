<?php
require_once __DIR__ . '/conexao.php';

header('Content-Type: application/json; charset=utf-8');

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

$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $body['action'] ?? '';

/* =====================================================
   FILTROS COMPARTILHADOS
===================================================== */
$q = trim($body['q'] ?? '');

$date_tipo = $body['date_tipo'] ?? 'criado_em';
$date_ini  = trim($body['date_ini'] ?? '');
$date_fim  = trim($body['date_fim'] ?? '');

// aceita só YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_ini)) $date_ini = '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_fim)) $date_fim = '';

if (!in_array($date_tipo, ['criado_em', 'atualizado_em', 'ambos'], true)) {
  $date_tipo = 'criado_em';
}

// Filtro de status
$status = strtoupper(trim($body['status'] ?? ''));
if (!in_array($status, ['ATIVO', 'INATIVO', ''], true)) $status = '';

// Filtro de versão
$versao = trim($body['versao'] ?? '');

/**
 * Monta cláusulas WHERE e params para o filtro de data.
 */
function buildDateFilter(string $tipo, string $ini, string $fim): array {
  if ($ini === '' && $fim === '') return [[], []];

  $where  = [];
  $params = [];

  if ($tipo === 'ambos') {
    if ($ini !== '' && $fim !== '') {
      $where[]  = "(DATE(criado_em) BETWEEN ? AND ?
                    OR DATE(COALESCE(atualizado_em, criado_em)) BETWEEN ? AND ?)";
      array_push($params, $ini, $fim, $ini, $fim);
    } elseif ($ini !== '') {
      $where[]  = "(DATE(criado_em) >= ? OR DATE(COALESCE(atualizado_em, criado_em)) >= ?)";
      array_push($params, $ini, $ini);
    } else {
      $where[]  = "(DATE(criado_em) <= ? OR DATE(COALESCE(atualizado_em, criado_em)) <= ?)";
      array_push($params, $fim, $fim);
    }
    return [$where, $params];
  }

  $col = $tipo === 'atualizado_em'
    ? 'COALESCE(atualizado_em, criado_em)'
    : 'criado_em';

  if ($ini !== '' && $fim !== '') {
    $where[]  = "DATE({$col}) BETWEEN ? AND ?";
    array_push($params, $ini, $fim);
  } elseif ($ini !== '') {
    $where[]  = "DATE({$col}) >= ?";
    $params[] = $ini;
  } else {
    $where[]  = "DATE({$col}) <= ?";
    $params[] = $fim;
  }

  return [$where, $params];
}

[$dateWhere, $dateParams] = buildDateFilter($date_tipo, $date_ini, $date_fim);
$hasDate = !empty($dateWhere);

// Filtro de busca por texto — duas variantes (com e sem alias l.)
$qWhere  = [];
$qWhereL = [];
$qParams = [];
if ($q !== '') {
  $like      = "%{$q}%";
  $qWhere[]  = "(codigo_cliente LIKE ? OR nome_cliente LIKE ?)";
  $qWhereL[] = "(l.codigo_cliente LIKE ? OR l.nome_cliente LIKE ?)";
  array_push($qParams, $like, $like);
}

// Filtro de status — duas variantes (com e sem alias l.)
$statusWhere  = [];
$statusWhereL = [];
$statusParams = [];
if ($status !== '') {
  $statusWhere[]  = "UPPER(status) = ?";
  $statusWhereL[] = "UPPER(l.status) = ?";
  $statusParams[] = $status;
}

// Filtro de versão — duas variantes (com e sem alias l.)
$versaoWhere  = [];
$versaoWhereL = [];
$versaoParams = [];
if ($versao !== '') {
  $versaoWhere[]  = "TRIM(versao_padrao) = ?";
  $versaoWhereL[] = "TRIM(l.versao_padrao) = ?";
  $versaoParams[] = $versao;
}

try {

  /* =====================================================
     RECENTES — últimos 30 dias (ou intervalo informado)
  ===================================================== */
  if ($action === 'recentes') {
    if ($hasDate) {
      $where  = $dateWhere;
      $params = $dateParams;
    } else {
      $where  = ['criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)'];
      $params = [];
    }
    foreach ($qWhere       as $c) $where[]  = $c;
    foreach ($qParams      as $p) $params[] = $p;
    foreach ($statusWhere  as $c) $where[]  = $c;
    foreach ($statusParams as $p) $params[] = $p;
    foreach ($versaoWhere  as $c) $where[]  = $c;
    foreach ($versaoParams as $p) $params[] = $p;

    $stmt = $pdo->prepare("
      SELECT
        codigo_cliente,
        nome_cliente,
        versao_padrao,
        status,
        DATE_FORMAT(criado_em, '%d/%m/%Y %H:%i') AS criado_em
      FROM clientes_web_login
      WHERE " . implode(' AND ', $where) . "
      ORDER BY criado_em DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'rows' => $rows, 'total' => count($rows)]);
    exit;
  }

  /* =====================================================
     INATIVOS
  ===================================================== */
  if ($action === 'inativos') {
    $where  = ["UPPER(status) = 'INATIVO'"];
    $params = [];

    foreach ($dateWhere    as $c) $where[]  = $c;
    foreach ($dateParams   as $p) $params[] = $p;
    foreach ($qWhere       as $c) $where[]  = $c;
    foreach ($qParams      as $p) $params[] = $p;
    // status ignorado: sempre INATIVO
    foreach ($versaoWhere  as $c) $where[]  = $c;
    foreach ($versaoParams as $p) $params[] = $p;

    $stmt = $pdo->prepare("
      SELECT
        codigo_cliente,
        nome_cliente,
        versao_padrao,
        DATE_FORMAT(COALESCE(atualizado_em, criado_em), '%d/%m/%Y %H:%i') AS atualizado_em
      FROM clientes_web_login
      WHERE " . implode(' AND ', $where) . "
      ORDER BY nome_cliente ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'rows' => $rows, 'total' => count($rows)]);
    exit;
  }

  /* =====================================================
     SEM INTEGRAÇÃO
  ===================================================== */
  if ($action === 'sem_integracao') {
    $where = [
      "NOT EXISTS (SELECT 1 FROM clientes_web_mobile    m WHERE m.cod_cliente = l.codigo_cliente LIMIT 1)",
      "NOT EXISTS (SELECT 1 FROM clientes_web_api_whats w WHERE w.cod_cliente = l.codigo_cliente LIMIT 1)",
      "NOT EXISTS (SELECT 1 FROM clientes_web_pdvoff    p WHERE p.cod_cliente = l.codigo_cliente LIMIT 1)"
    ];
    $params = [];

    foreach ($dateWhere    as $c) $where[]  = $c;
    foreach ($dateParams   as $p) $params[] = $p;
    foreach ($qWhereL      as $c) $where[]  = $c;
    foreach ($qParams      as $p) $params[] = $p;
    foreach ($statusWhereL as $c) $where[]  = $c;
    foreach ($statusParams as $p) $params[] = $p;
    foreach ($versaoWhereL as $c) $where[]  = $c;
    foreach ($versaoParams as $p) $params[] = $p;

    $stmt = $pdo->prepare("
      SELECT
        l.codigo_cliente,
        l.nome_cliente,
        l.versao_padrao,
        l.status,
        DATE_FORMAT(COALESCE(l.atualizado_em, l.criado_em), '%d/%m/%Y %H:%i') AS atualizado_em
      FROM clientes_web_login l
      WHERE " . implode(' AND ', $where) . "
      ORDER BY l.nome_cliente ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'rows' => $rows, 'total' => count($rows)]);
    exit;
  }

  /* =====================================================
     COM INTEGRAÇÃO
  ===================================================== */
  if ($action === 'com_integracao') {
    $where = ["(
      EXISTS (SELECT 1 FROM clientes_web_mobile    m WHERE m.cod_cliente = l.codigo_cliente LIMIT 1)
      OR EXISTS (SELECT 1 FROM clientes_web_api_whats w WHERE w.cod_cliente = l.codigo_cliente LIMIT 1)
      OR EXISTS (SELECT 1 FROM clientes_web_pdvoff    p WHERE p.cod_cliente = l.codigo_cliente LIMIT 1)
    )"];
    $params = [];

    foreach ($dateWhere    as $c) $where[]  = $c;
    foreach ($dateParams   as $p) $params[] = $p;
    foreach ($qWhereL      as $c) $where[]  = $c;
    foreach ($qParams      as $p) $params[] = $p;
    foreach ($statusWhereL as $c) $where[]  = $c;
    foreach ($statusParams as $p) $params[] = $p;
    foreach ($versaoWhereL as $c) $where[]  = $c;
    foreach ($versaoParams as $p) $params[] = $p;

    $stmt = $pdo->prepare("
      SELECT
        l.codigo_cliente,
        l.nome_cliente,
        l.versao_padrao,
        l.status,
        (SELECT COUNT(*) FROM clientes_web_mobile    m WHERE m.cod_cliente = l.codigo_cliente) AS total_mobile,
        (SELECT COUNT(*) FROM clientes_web_api_whats w WHERE w.cod_cliente = l.codigo_cliente) AS total_whats,
        (SELECT COUNT(*) FROM clientes_web_pdvoff    p WHERE p.cod_cliente = l.codigo_cliente) AS total_pdvoff,
        DATE_FORMAT(COALESCE(l.atualizado_em, l.criado_em), '%d/%m/%Y %H:%i') AS atualizado_em
      FROM clientes_web_login l
      WHERE " . implode(' AND ', $where) . "
      ORDER BY l.nome_cliente ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'rows' => $rows, 'total' => count($rows)]);
    exit;
  }

  /* =====================================================
     POR VERSÃO
  ===================================================== */
  if ($action === 'por_versao') {
    $where  = [];
    $params = [];

    foreach ($dateWhere    as $c) $where[]  = $c;
    foreach ($dateParams   as $p) $params[] = $p;
    foreach ($qWhere       as $c) $where[]  = $c;
    foreach ($qParams      as $p) $params[] = $p;
    foreach ($statusWhere  as $c) $where[]  = $c;
    foreach ($statusParams as $p) $params[] = $p;
    foreach ($versaoWhere  as $c) $where[]  = $c;
    foreach ($versaoParams as $p) $params[] = $p;

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("
      SELECT
        COALESCE(NULLIF(TRIM(versao_padrao), ''), 'Sem versão') AS versao,
        SUM(UPPER(status) = 'ATIVO')   AS ativos,
        SUM(UPPER(status) = 'INATIVO') AS inativos,
        COUNT(*)                        AS total
      FROM clientes_web_login
      {$whereSql}
      GROUP BY versao
      ORDER BY total DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'rows' => $rows, 'total' => count($rows)]);
    exit;
  }

  /* =====================================================
     DESATUALIZADOS — 90+ dias sem atualização
  ===================================================== */
  if ($action === 'desatualizados') {
    $where  = ['COALESCE(atualizado_em, criado_em) < DATE_SUB(NOW(), INTERVAL 90 DAY)'];
    $params = [];

    foreach ($dateWhere    as $c) $where[]  = $c;
    foreach ($dateParams   as $p) $params[] = $p;
    foreach ($qWhere       as $c) $where[]  = $c;
    foreach ($qParams      as $p) $params[] = $p;
    foreach ($statusWhere  as $c) $where[]  = $c;
    foreach ($statusParams as $p) $params[] = $p;
    foreach ($versaoWhere  as $c) $where[]  = $c;
    foreach ($versaoParams as $p) $params[] = $p;

    $stmt = $pdo->prepare("
      SELECT
        codigo_cliente,
        nome_cliente,
        versao_padrao,
        status,
        DATE_FORMAT(COALESCE(atualizado_em, criado_em), '%d/%m/%Y %H:%i') AS ultima_atualizacao,
        DATEDIFF(NOW(), COALESCE(atualizado_em, criado_em)) AS dias_sem_atualizacao
      FROM clientes_web_login
      WHERE " . implode(' AND ', $where) . "
      ORDER BY COALESCE(atualizado_em, criado_em) ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'rows' => $rows, 'total' => count($rows)]);
    exit;
  }

  /* =====================================================
     COM EXE
  ===================================================== */
  if ($action === 'com_exe') {
    $where  = ['possui_exe = 1'];
    $params = [];

    foreach ($dateWhere    as $c) $where[]  = $c;
    foreach ($dateParams   as $p) $params[] = $p;
    foreach ($qWhere       as $c) $where[]  = $c;
    foreach ($qParams      as $p) $params[] = $p;
    foreach ($statusWhere  as $c) $where[]  = $c;
    foreach ($statusParams as $p) $params[] = $p;
    foreach ($versaoWhere  as $c) $where[]  = $c;
    foreach ($versaoParams as $p) $params[] = $p;

    $stmt = $pdo->prepare("
      SELECT
        codigo_cliente,
        nome_cliente,
        versao_padrao,
        status,
        COALESCE(NULLIF(TRIM(exe_nome), ''), 'EXE') AS exe_nome,
        DATE_FORMAT(COALESCE(atualizado_em, criado_em), '%d/%m/%Y %H:%i') AS atualizado_em
      FROM clientes_web_login
      WHERE " . implode(' AND ', $where) . "
      ORDER BY nome_cliente ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'rows' => $rows, 'total' => count($rows)]);
    exit;
  }

  throw new Exception('Ação inválida');

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
  exit;
}
