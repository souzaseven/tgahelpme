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

$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $body['action'] ?? '';

function norm($v){ return trim((string)$v); }

function parseArr($val, $upper = false) {
  if (is_array($val)) $arr = $val;
  elseif ($val === null || $val === '') return [];
  else $arr = explode(',', (string)$val);
  $arr = array_values(array_filter(array_map('trim', $arr), fn($v) => $v !== ''));
  return $upper ? array_map('strtoupper', $arr) : $arr;
}

try {

/* ==========================
   LIST (COM ORDENAÇÃO)
========================== */
if ($action === 'list') {

  $page           = max(1, (int)($body['page'] ?? 1));
  $limit          = min(200, max(5, (int)($body['limit'] ?? 10)));
  $q              = norm($body['q'] ?? '');
  $versaoAppArr   = parseArr($body['versao_app']     ?? []);
  $regiaoArr      = parseArr($body['regiao']         ?? []);
  $versaoEmpArr   = parseArr($body['versao_empresa'] ?? []);

  /* ORDENAÇÃO */
  $allowedOrders = ['id', 'cod_cliente', 'cliente', 'acesso_server', 'versao'];

  $orderBy  = $body['order_by'] ?? 'id';
  $orderDir = strtoupper($body['order_dir'] ?? 'DESC');

  if (!in_array($orderBy, $allowedOrders, true)) $orderBy = 'id';
  if (!in_array($orderDir, ['ASC', 'DESC'], true)) $orderDir = 'DESC';

  /* WHERE unificado com prefixos de tabela */
  $where  = [];
  $params = [];

  if ($q !== '') {
    $like = "%$q%";
    $where[] = "(w.cod_cliente LIKE ? OR w.cliente LIKE ? OR w.acesso_server LIKE ? OR l.cnpj LIKE ?)";
    array_push($params, $like, $like, $like, $like);
  }

  if (!empty($versaoAppArr)) {
    $stmtGV = $pdo->prepare("SELECT valor FROM clientes_web_config WHERE chave = 'whatsapp_versao' LIMIT 1");
    $stmtGV->execute();
    $globalVersao = ($stmtGV->fetch(PDO::FETCH_ASSOC) ?: [])['valor'] ?? '';

    $vaConds = [];
    foreach ($versaoAppArr as $va) {
      if ($globalVersao !== '' && $va === $globalVersao) {
        $vaConds[] = "(w.versao = ? OR (w.versao IS NULL OR w.versao = ''))";
      } else {
        $vaConds[] = "w.versao = ?";
      }
      $params[] = $va;
    }
    if ($vaConds) $where[] = '(' . implode(' OR ', $vaConds) . ')';
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

  $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
  $offset   = ($page - 1) * $limit;

  $joinSql = "LEFT JOIN clientes_web_login l ON l.codigo_cliente = w.cod_cliente";

  /* TOTAL */
  $stmtT = $pdo->prepare("
    SELECT COUNT(*) c
    FROM clientes_web_api_whats w
    $joinSql
    $whereSql
  ");
  $stmtT->execute($params);
  $total = (int)$stmtT->fetch(PDO::FETCH_ASSOC)['c'];
  $pages = max(1, (int)ceil($total / $limit));

  /* LISTAGEM */
  $stmt = $pdo->prepare("
    SELECT w.id, w.cod_cliente, w.cliente, w.acesso_server, w.tipo_acesso, w.versao, w.observacao,
      COALESCE(l.versao_padrao, '') AS versao_padrao,
      COALESCE(l.caminho_acesso, '') AS caminho_acesso,
      COALESCE(l.regiao, '') AS regiao,
      COALESCE(l.status, '') AS empresa_status,
      COALESCE(l.cnpj, '') AS empresa_cnpj
    FROM clientes_web_api_whats w
    $joinSql
    $whereSql
    ORDER BY w.$orderBy $orderDir
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
  $versoes_app = $pdo->query("
    SELECT DISTINCT versao FROM clientes_web_api_whats
    WHERE versao IS NOT NULL AND versao <> ''
    ORDER BY versao
  ")->fetchAll(PDO::FETCH_COLUMN);

  /* Inclui versão padrão global no dropdown */
  $stmtGV = $pdo->prepare("SELECT valor FROM clientes_web_config WHERE chave = 'whatsapp_versao' LIMIT 1");
  $stmtGV->execute();
  $globalVersao = ($stmtGV->fetch(PDO::FETCH_ASSOC) ?: [])['valor'] ?? '';
  if ($globalVersao !== '' && !in_array($globalVersao, $versoes_app, true)) {
    $versoes_app[] = $globalVersao;
    sort($versoes_app);
  }

  $regioes = $pdo->query("
    SELECT DISTINCT l.regiao
    FROM clientes_web_api_whats w
    INNER JOIN clientes_web_login l ON l.codigo_cliente = w.cod_cliente
    WHERE l.regiao IS NOT NULL AND l.regiao <> ''
    ORDER BY l.regiao
  ")->fetchAll(PDO::FETCH_COLUMN);

  $versoes_emp = $pdo->query("
    SELECT DISTINCT l.versao_padrao
    FROM clientes_web_api_whats w
    INNER JOIN clientes_web_login l ON l.codigo_cliente = w.cod_cliente
    WHERE l.versao_padrao IS NOT NULL AND l.versao_padrao <> ''
    ORDER BY l.versao_padrao
  ")->fetchAll(PDO::FETCH_COLUMN);

  echo json_encode([
    'success'     => true,
    'versoes_app' => $versoes_app,
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
      SELECT w.*,
        COALESCE(l.versao_padrao, '') AS versao_padrao,
        COALESCE(l.caminho_acesso, '') AS caminho_acesso,
        COALESCE(l.regiao, '') AS regiao,
        COALESCE(l.cnpj, '') AS empresa_cnpj
      FROM clientes_web_api_whats w
      LEFT JOIN clientes_web_login l ON l.codigo_cliente = w.cod_cliente
      WHERE w.id = ?
      LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) throw new Exception('Registro não encontrado');

    echo json_encode(['success'=>true,'row'=>$row]);
    exit;
  }

  /* ==========================
     CREATE
  ========================== */
  if ($action === 'create') {
    $d = $body['data'] ?? [];

    $cod    = norm($d['cod_cliente'] ?? '');
    $cli    = norm($d['cliente'] ?? '');
    $srv    = norm($d['acesso_server'] ?? '');
    $versao = norm($d['versao'] ?? '');
    $obs    = norm($d['observacao'] ?? '');
    $tipo   = 'API_WHATS';

    if ($cod === '' || $cli === '') {
      throw new Exception('Código e Cliente são obrigatórios');
    }

    $stmt = $pdo->prepare("
      INSERT INTO clientes_web_api_whats
      (cod_cliente, cliente, acesso_server, tipo_acesso, versao, observacao, criado_em, atualizado_em)
      VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([$cod, $cli, $srv, $tipo, $versao !== '' ? $versao : null, $obs]);

    $newId = $pdo->lastInsertId();
    logAction($pdo, 'CREATE', 'WhatsApp', $newId, null, [
      'cod_cliente'   => $cod,
      'cliente'       => $cli,
      'acesso_server' => $srv,
      'versao'        => $versao,
    ], "Cadastrou WhatsApp \"{$cli}\"");

    echo json_encode(['success'=>true]);
    exit;
  }

  /* ==========================
     UPDATE
  ========================== */
  if ($action === 'update') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido');

    $d = $body['data'] ?? [];

    $cod    = norm($d['cod_cliente'] ?? '');
    $cli    = norm($d['cliente'] ?? '');
    $srv    = norm($d['acesso_server'] ?? '');
    $versao = norm($d['versao'] ?? '');
    $obs    = norm($d['observacao'] ?? '');

    if ($cod === '' || $cli === '') {
      throw new Exception('Código e Cliente são obrigatórios');
    }

    $stmtAntes = $pdo->prepare("SELECT cod_cliente,cliente,acesso_server,versao FROM clientes_web_api_whats WHERE id = ? LIMIT 1");
    $stmtAntes->execute([$id]);
    $dadosAntes = $stmtAntes->fetch(PDO::FETCH_ASSOC) ?: null;

    $stmt = $pdo->prepare("
      UPDATE clientes_web_api_whats
      SET cod_cliente=?, cliente=?, acesso_server=?, versao=?, observacao=?, atualizado_em=NOW()
      WHERE id=?
      LIMIT 1
    ");
    $stmt->execute([$cod, $cli, $srv, $versao !== '' ? $versao : null, $obs, $id]);

    $dadosDepois = [
      'cod_cliente'   => $cod,
      'cliente'       => $cli,
      'acesso_server' => $srv,
      'versao'        => $versao,
    ];
    $resumo = $dadosAntes ? resumoAlteracoes($dadosAntes, $dadosDepois) : '';
    $desc   = "Atualizou WhatsApp \"{$cli}\"" . ($resumo ? " | {$resumo}" : '');
    logAction($pdo, 'UPDATE', 'WhatsApp', $id, $dadosAntes, $dadosDepois, $desc);

    echo json_encode(['success'=>true]);
    exit;
  }

  /* ==========================
     DELETE
  ========================== */
  if ($action === 'delete') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido');

    $stmtAntes = $pdo->prepare("SELECT cod_cliente,cliente,acesso_server FROM clientes_web_api_whats WHERE id = ? LIMIT 1");
    $stmtAntes->execute([$id]);
    $dadosAntes = $stmtAntes->fetch(PDO::FETCH_ASSOC) ?: null;
    $nomeCliente = $dadosAntes['cliente'] ?? "ID {$id}";

    $stmt = $pdo->prepare("DELETE FROM clientes_web_api_whats WHERE id=? LIMIT 1");
    $stmt->execute([$id]);

    logAction($pdo, 'DELETE', 'WhatsApp', $id, $dadosAntes, null, "Excluiu WhatsApp \"{$nomeCliente}\"");

    echo json_encode(['success'=>true]);
    exit;
  }

  throw new Exception('Ação inválida');

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
