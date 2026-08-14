<?php
require_once __DIR__ . '/conexao.php';
header('Content-Type: application/json; charset=utf-8');

/* ==========================
   VALIDAÇÕES INICIAIS
========================== */
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

function norm($v) {
  return trim((string)$v);
}

function parseArr($val, $upper = false) {
  if (is_array($val)) $arr = $val;
  elseif ($val === null || $val === '') return [];
  else $arr = explode(',', (string)$val);
  $arr = array_values(array_filter(array_map('trim', $arr), fn($v) => $v !== ''));
  return $upper ? array_map('strtoupper', $arr) : $arr;
}

/* ==========================
   COLUNAS PERMITIDAS (ORDER)
========================== */
$ORDER_WHITELIST = [
  'id',
  'cod_cliente',
  'cliente',
  'acesso_server',
  'caixas',
  'observacao',
  'criado_em',
  'atualizado_em'
];

try {

  /* ==========================
     LIST
  ========================== */
  if ($action === 'list') {

    $page        = max(1, (int)($body['page'] ?? 1));
    $limit       = min(200, max(5, (int)($body['limit'] ?? 10)));
    $q           = norm($body['q'] ?? '');
    $regiaoArr   = parseArr($body['regiao']          ?? []);
    $versaoEmpArr = parseArr($body['versao_empresa'] ?? []);

    /* ORDER */
    $orderBy  = norm($body['order_by'] ?? 'id');
    $orderDir = strtoupper(norm($body['order_dir'] ?? 'DESC'));

    if (!in_array($orderBy, $ORDER_WHITELIST, true)) $orderBy = 'id';
    if (!in_array($orderDir, ['ASC', 'DESC'], true)) $orderDir = 'DESC';

    /* WHERE unificado com prefixos de tabela */
    $where  = [];
    $params = [];

    if ($q !== '') {
      $like = "%{$q}%";
      $where[] = "(p.cod_cliente LIKE ? OR p.cliente LIKE ? OR p.acesso_server LIKE ? OR p.observacao LIKE ?)";
      array_push($params, $like, $like, $like, $like);
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

    $joinSql = "LEFT JOIN clientes_web_login l ON l.codigo_cliente = p.cod_cliente";

    /* TOTAL */
    $stmtT = $pdo->prepare("
      SELECT COUNT(*) c
      FROM clientes_web_pdvoff p
      $joinSql
      $whereSql
    ");
    $stmtT->execute($params);
    $total = (int)$stmtT->fetch(PDO::FETCH_ASSOC)['c'];
    $pages = max(1, (int)ceil($total / $limit));

    /* LISTAGEM */
    $stmt = $pdo->prepare("
      SELECT
        p.id, p.cod_cliente, p.cliente, p.acesso_server, p.caixas,
        p.tipo_acesso, p.observacao, p.criado_em, p.atualizado_em,
        COALESCE(l.versao_padrao, '') AS versao_padrao,
        COALESCE(l.caminho_acesso, '') AS caminho_acesso,
        COALESCE(l.regiao, '') AS regiao,
        COALESCE(l.cidade, '') AS cidade,
        COALESCE(l.estado, '') AS estado,
        COALESCE(l.status, '') AS empresa_status,
        COALESCE(l.cnpj, '') AS empresa_cnpj
      FROM clientes_web_pdvoff p
      $joinSql
      $whereSql
      ORDER BY p.{$orderBy} {$orderDir}
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
     OPTIONS (valores distintos para os filtros)
  ========================== */
  if ($action === 'options') {
    $regioes = $pdo->query("
      SELECT DISTINCT l.regiao
      FROM clientes_web_pdvoff p
      INNER JOIN clientes_web_login l ON l.codigo_cliente = p.cod_cliente
      WHERE l.regiao IS NOT NULL AND l.regiao <> ''
      ORDER BY l.regiao
    ")->fetchAll(PDO::FETCH_COLUMN);

    $versoes_emp = $pdo->query("
      SELECT DISTINCT l.versao_padrao
      FROM clientes_web_pdvoff p
      INNER JOIN clientes_web_login l ON l.codigo_cliente = p.cod_cliente
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
    if ($id <= 0) {
      throw new Exception('ID inválido');
    }

    $stmt = $pdo->prepare("
      SELECT p.*,
        COALESCE(l.versao_padrao, '') AS versao_padrao,
        COALESCE(l.caminho_acesso, '') AS caminho_acesso,
        COALESCE(l.regiao, '') AS regiao,
        COALESCE(l.cidade, '') AS cidade,
        COALESCE(l.estado, '') AS estado,
        COALESCE(l.cnpj, '') AS empresa_cnpj
      FROM clientes_web_pdvoff p
      LEFT JOIN clientes_web_login l ON l.codigo_cliente = p.cod_cliente
      WHERE p.id = ?
      LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      throw new Exception('Registro não encontrado');
    }

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
    $cx   = (int)($d['caixas'] ?? 0);
    $tipo = 'PDVOFF';
    $obs  = norm($d['observacao'] ?? '');

    if ($cod === '' || $cli === '') {
      throw new Exception('Código e Cliente são obrigatórios');
    }

    $stmt = $pdo->prepare("
      INSERT INTO clientes_web_pdvoff
        (cod_cliente, cliente, acesso_server, caixas, tipo_acesso, observacao, criado_em, atualizado_em)
      VALUES
        (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([
      $cod,
      $cli,
      $srv,
      $cx,
      $tipo,
      ($obs === '' ? null : $obs)
    ]);

    $newId = $pdo->lastInsertId();
    logAction($pdo, 'CREATE', 'PDV OFF', $newId, null, [
      'cod_cliente'   => $cod,
      'cliente'       => $cli,
      'acesso_server' => $srv,
      'caixas'        => $cx,
    ], "Cadastrou PDV OFF \"{$cli}\"");

    echo json_encode([
      'success' => true,
      'id'      => $newId
    ]);
    exit;
  }

  /* ==========================
     UPDATE
  ========================== */
  if ($action === 'update') {

    $id = (int)($body['id'] ?? 0);
    $d  = $body['data'] ?? [];

    if ($id <= 0) {
      throw new Exception('ID inválido');
    }

    $cod = norm($d['cod_cliente'] ?? '');
    $cli = norm($d['cliente'] ?? '');
    $srv = norm($d['acesso_server'] ?? '');
    $cx  = (int)($d['caixas'] ?? 0);
    $obs = norm($d['observacao'] ?? '');

    if ($cod === '' || $cli === '') {
      throw new Exception('Código e Cliente são obrigatórios');
    }

    $stmtAntes = $pdo->prepare("SELECT cod_cliente,cliente,acesso_server,caixas FROM clientes_web_pdvoff WHERE id = ? LIMIT 1");
    $stmtAntes->execute([$id]);
    $dadosAntes = $stmtAntes->fetch(PDO::FETCH_ASSOC) ?: null;

    $stmt = $pdo->prepare("
      UPDATE clientes_web_pdvoff
      SET
        cod_cliente   = ?,
        cliente       = ?,
        acesso_server = ?,
        caixas        = ?,
        observacao    = ?,
        atualizado_em = NOW()
      WHERE id = ?
      LIMIT 1
    ");
    $stmt->execute([
      $cod,
      $cli,
      $srv,
      $cx,
      ($obs === '' ? null : $obs),
      $id
    ]);

    $dadosDepois = [
      'cod_cliente'   => $cod,
      'cliente'       => $cli,
      'acesso_server' => $srv,
      'caixas'        => $cx,
    ];
    $resumo = $dadosAntes ? resumoAlteracoes($dadosAntes, $dadosDepois) : '';
    $desc   = "Atualizou PDV OFF \"{$cli}\"" . ($resumo ? " | {$resumo}" : '');
    logAction($pdo, 'UPDATE', 'PDV OFF', $id, $dadosAntes, $dadosDepois, $desc);

    echo json_encode(['success'=>true]);
    exit;
  }

  /* ==========================
     DELETE
  ========================== */
  if ($action === 'delete') {

    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) {
      throw new Exception('ID inválido');
    }

    $stmtAntes = $pdo->prepare("SELECT cod_cliente,cliente,acesso_server,caixas FROM clientes_web_pdvoff WHERE id = ? LIMIT 1");
    $stmtAntes->execute([$id]);
    $dadosAntes = $stmtAntes->fetch(PDO::FETCH_ASSOC) ?: null;
    $nomeCliente = $dadosAntes['cliente'] ?? "ID {$id}";

    $pdo->prepare("DELETE FROM clientes_web_pdvoff WHERE id = ? LIMIT 1")->execute([$id]);

    logAction($pdo, 'DELETE', 'PDV OFF', $id, $dadosAntes, null, "Excluiu PDV OFF \"{$nomeCliente}\"");

    echo json_encode(['success'=>true]);
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
