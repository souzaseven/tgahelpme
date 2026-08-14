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

function parseArr($val, $upper = false) {
  if (is_array($val)) $arr = $val;
  elseif ($val === null || $val === '') return [];
  else $arr = explode(',', (string)$val);
  $arr = array_values(array_filter(array_map('trim', $arr), fn($v) => $v !== ''));
  return $upper ? array_map('strtoupper', $arr) : $arr;
}

try {

  /* =========================================================
     LISTAGEM + KPIs
  ========================================================= */
  if ($action === 'list') {

    $q         = n($body['q'] ?? '');
    $statusArr = parseArr($body['status'] ?? [], true);

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

    $joinSql = "LEFT JOIN clientes_web_login l ON l.codigo_cliente = u.codigo_empresa";

    /* 🔎 Busca textual */
    if ($q !== '') {
      $where[] = "(
        u.codigo_empresa LIKE ?
        OR u.nome_empresa LIKE ?
        OR CAST(u.qtd_usuarios AS CHAR) LIKE ?
        OR u.observacao LIKE ?
        OR l.cnpj LIKE ?
      )";
      $like = "%{$q}%";
      array_push($params, $like, $like, $like, $like, $like);
    }

    /* 🔘 Status */
    $statusFiltrados = array_filter($statusArr, fn($s) => in_array($s, ['ATIVO', 'INATIVO'], true));
    $statusFiltrados = array_values($statusFiltrados);
    if (!empty($statusFiltrados)) {
      $ph = implode(',', array_fill(0, count($statusFiltrados), '?'));
      $where[] = "u.status IN ({$ph})";
      foreach ($statusFiltrados as $s) $params[] = $s;
    }

    /* 👥 Quantidade EXATA de usuários */
    if ($minUsers !== null) {
      $where[]  = "u.qtd_usuarios = ?";
      $params[] = $minUsers;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    /* ===== KPIs (respeitando filtros) ===== */
    $stmtS = $pdo->prepare("
      SELECT
        COUNT(*)                   AS total,
        SUM(u.status = 'ATIVO')    AS ativos,
        SUM(u.status = 'INATIVO')  AS inativos,
        COALESCE(SUM(u.qtd_usuarios),0) AS total_usuarios
      FROM clientes_tga_web_usuarios u
      {$joinSql}
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
        u.id,
        u.codigo_empresa,
        u.nome_empresa,
        u.qtd_usuarios,
        u.status,
        u.observacao,
        u.criado_em,
        u.atualizado_em,
        COALESCE(l.cnpj, '') AS empresa_cnpj
      FROM clientes_tga_web_usuarios u
      {$joinSql}
      {$whereSql}
      ORDER BY u.{$orderBy} {$orderDir}
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

    $newId = $pdo->lastInsertId();
    logAction($pdo, 'CREATE', 'Usuários Web', $newId, null, [
      'codigo_empresa' => $codigo,
      'nome_empresa'   => $nome,
      'qtd_usuarios'   => $qtd,
      'status'         => $status,
    ], "Cadastrou usuário web \"{$nome}\"");

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

    $stmtAntes = $pdo->prepare("SELECT codigo_empresa,nome_empresa,qtd_usuarios,status,observacao FROM clientes_tga_web_usuarios WHERE id = ? LIMIT 1");
    $stmtAntes->execute([$id]);
    $dadosAntes = $stmtAntes->fetch(PDO::FETCH_ASSOC) ?: null;

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

    $dadosDepois = [
      'codigo_empresa' => $codigo,
      'nome_empresa'   => $nome,
      'qtd_usuarios'   => $qtd,
      'status'         => $status,
    ];
    $resumo = $dadosAntes ? resumoAlteracoes($dadosAntes, $dadosDepois) : '';
    $desc   = "Atualizou usuário web \"{$nome}\"" . ($resumo ? " | {$resumo}" : '');
    logAction($pdo, 'UPDATE', 'Usuários Web', $id, $dadosAntes, $dadosDepois, $desc);

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
      SELECT status, nome_empresa, codigo_empresa, qtd_usuarios
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

    $nomeEmpresa = $row['nome_empresa'] ?? "ID {$id}";

    $stmt = $pdo->prepare("
      DELETE FROM clientes_tga_web_usuarios
      WHERE id = ?
      LIMIT 1
    ");
    $stmt->execute([$id]);

    logAction($pdo, 'DELETE', 'Usuários Web', $id, $row, null, "Excluiu usuário web \"{$nomeEmpresa}\"");

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