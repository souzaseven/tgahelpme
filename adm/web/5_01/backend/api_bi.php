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

function normBi($v) {
  return trim((string)$v);
}

function parseArr($val, $upper = false) {
  if (is_array($val)) $arr = $val;
  elseif ($val === null || $val === '') return [];
  else $arr = explode(',', (string)$val);
  $arr = array_values(array_filter(array_map('trim', $arr), fn($v) => $v !== ''));
  return $upper ? array_map('strtoupper', $arr) : $arr;
}

$ORDER_WHITELIST = ['id', 'cod_cliente', 'cliente', 'acesso_server', 'criado_em', 'atualizado_em'];

try {

  /* ==========================
     LIST
  ========================== */
  if ($action === 'list') {

    $page         = max(1, (int)($body['page']  ?? 1));
    $limit        = min(200, max(5, (int)($body['limit'] ?? 10)));
    $q            = normBi($body['q'] ?? '');
    $regiaoArr    = parseArr($body['regiao']          ?? []);
    $versaoEmpArr = parseArr($body['versao_empresa']  ?? []);

    $orderBy  = normBi($body['order_by']  ?? 'id');
    $orderDir = strtoupper(normBi($body['order_dir'] ?? 'DESC'));

    if (!in_array($orderBy,  $ORDER_WHITELIST,  true)) $orderBy  = 'id';
    if (!in_array($orderDir, ['ASC', 'DESC'],   true)) $orderDir = 'DESC';

    $where  = [];
    $params = [];

    if ($q !== '') {
      $like = "%{$q}%";
      $where[]  = "(b.cod_cliente LIKE ? OR b.cliente LIKE ? OR b.acesso_server LIKE ?)";
      array_push($params, $like, $like, $like);
    }

    if (!empty($regiaoArr)) {
      $ph = implode(',', array_fill(0, count($regiaoArr), '?'));
      $where[] = "l.regiao IN ({$ph})";
      foreach ($regiaoArr as $r) $params[] = $r;
    }

    if (!empty($versaoEmpArr)) {
      $ph = implode(',', array_fill(0, count($versaoEmpArr), '?'));
      $where[] = "l.versao_padrao IN ({$ph})";
      foreach ($versaoEmpArr as $v) $params[] = $v;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $offset   = ($page - 1) * $limit;
    $joinSql  = "LEFT JOIN clientes_web_login l ON l.codigo_cliente = b.cod_cliente";

    $stmtT = $pdo->prepare("
      SELECT COUNT(*) c FROM clientes_web_bi b $joinSql $whereSql
    ");
    $stmtT->execute($params);
    $total = (int)$stmtT->fetch(PDO::FETCH_ASSOC)['c'];
    $pages = max(1, (int)ceil($total / $limit));

    $stmt = $pdo->prepare("
      SELECT
        b.id, b.cod_cliente, b.cliente, b.acesso_server,
        b.criado_em, b.atualizado_em,
        COALESCE(l.versao_padrao,  '') AS versao_padrao,
        COALESCE(l.caminho_acesso, '') AS caminho_acesso,
        COALESCE(l.regiao,         '') AS regiao,
        COALESCE(l.status,         '') AS empresa_status,
        COALESCE(l.cnpj,           '') AS empresa_cnpj
      FROM clientes_web_bi b
      $joinSql
      $whereSql
      ORDER BY b.{$orderBy} {$orderDir}
      LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);

    echo json_encode([
      'success'    => true,
      'page'       => $page,
      'pages'      => $pages,
      'total'      => $total,
      'count_page' => $stmt->rowCount(),
      'rows'       => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
    exit;
  }

  /* ==========================
     OPTIONS
  ========================== */
  if ($action === 'options') {

    $regioes = $pdo->query("
      SELECT DISTINCT l.regiao
      FROM clientes_web_bi b
      INNER JOIN clientes_web_login l ON l.codigo_cliente = b.cod_cliente
      WHERE l.regiao IS NOT NULL AND l.regiao <> ''
      ORDER BY l.regiao
    ")->fetchAll(PDO::FETCH_COLUMN);

    $versoes_emp = $pdo->query("
      SELECT DISTINCT l.versao_padrao
      FROM clientes_web_bi b
      INNER JOIN clientes_web_login l ON l.codigo_cliente = b.cod_cliente
      WHERE l.versao_padrao IS NOT NULL AND l.versao_padrao <> ''
      ORDER BY l.versao_padrao
    ")->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
      'success'     => true,
      'regioes'     => $regioes,
      'versoes_emp' => $versoes_emp
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
      SELECT b.*,
        COALESCE(l.versao_padrao,  '') AS versao_padrao,
        COALESCE(l.caminho_acesso, '') AS caminho_acesso,
        COALESCE(l.regiao,         '') AS regiao,
        COALESCE(l.cnpj,           '') AS empresa_cnpj
      FROM clientes_web_bi b
      LEFT JOIN clientes_web_login l ON l.codigo_cliente = b.cod_cliente
      WHERE b.id = ?
      LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) throw new Exception('Registro não encontrado');

    echo json_encode(['success' => true, 'row' => $row]);
    exit;
  }

  /* ==========================
     CREATE
  ========================== */
  if ($action === 'create') {

    $d   = $body['data'] ?? [];
    $cod = normBi($d['cod_cliente']   ?? '');
    $cli = normBi($d['cliente']       ?? '');
    $srv = normBi($d['acesso_server'] ?? '');

    if ($cod === '' || $cli === '') {
      throw new Exception('Código e Cliente são obrigatórios');
    }

    $stmt = $pdo->prepare("
      INSERT INTO clientes_web_bi
        (cod_cliente, cliente, acesso_server, criado_em, atualizado_em)
      VALUES (?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([$cod, $cli, $srv]);

    $newId = $pdo->lastInsertId();
    logAction($pdo, 'CREATE', 'BI', $newId, null, [
      'cod_cliente'   => $cod,
      'cliente'       => $cli,
      'acesso_server' => $srv
    ], "Cadastrou BI \"{$cli}\"");

    echo json_encode(['success' => true, 'id' => $newId]);
    exit;
  }

  /* ==========================
     UPDATE
  ========================== */
  if ($action === 'update') {

    $id  = (int)($body['id'] ?? 0);
    $d   = $body['data'] ?? [];

    if ($id <= 0) throw new Exception('ID inválido');

    $cod = normBi($d['cod_cliente']   ?? '');
    $cli = normBi($d['cliente']       ?? '');
    $srv = normBi($d['acesso_server'] ?? '');

    if ($cod === '' || $cli === '') {
      throw new Exception('Código e Cliente são obrigatórios');
    }

    $stmtAntes = $pdo->prepare("SELECT cod_cliente, cliente, acesso_server FROM clientes_web_bi WHERE id = ? LIMIT 1");
    $stmtAntes->execute([$id]);
    $dadosAntes = $stmtAntes->fetch(PDO::FETCH_ASSOC) ?: null;

    $pdo->prepare("
      UPDATE clientes_web_bi
      SET cod_cliente = ?, cliente = ?, acesso_server = ?, atualizado_em = NOW()
      WHERE id = ?
      LIMIT 1
    ")->execute([$cod, $cli, $srv, $id]);

    $dadosDepois = ['cod_cliente' => $cod, 'cliente' => $cli, 'acesso_server' => $srv];
    $resumo = $dadosAntes ? resumoAlteracoes($dadosAntes, $dadosDepois) : '';
    $desc   = "Atualizou BI \"{$cli}\"" . ($resumo ? " | {$resumo}" : '');
    logAction($pdo, 'UPDATE', 'BI', $id, $dadosAntes, $dadosDepois, $desc);

    echo json_encode(['success' => true]);
    exit;
  }

  /* ==========================
     DELETE
  ========================== */
  if ($action === 'delete') {

    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido');

    $stmtAntes = $pdo->prepare("SELECT cod_cliente, cliente FROM clientes_web_bi WHERE id = ? LIMIT 1");
    $stmtAntes->execute([$id]);
    $dadosAntes  = $stmtAntes->fetch(PDO::FETCH_ASSOC) ?: null;
    $nomeCliente = $dadosAntes['cliente'] ?? "ID {$id}";

    $pdo->prepare("DELETE FROM clientes_web_bi WHERE id = ? LIMIT 1")->execute([$id]);

    logAction($pdo, 'DELETE', 'BI', $id, $dadosAntes, null, "Excluiu BI \"{$nomeCliente}\"");

    echo json_encode(['success' => true]);
    exit;
  }

  throw new Exception('Ação inválida');

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
