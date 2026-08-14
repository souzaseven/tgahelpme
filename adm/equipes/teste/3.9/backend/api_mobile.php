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

function parseArr($val, $upper = false) {
  if (is_array($val)) $arr = $val;
  elseif ($val === null || $val === '') return [];
  else $arr = explode(',', (string)$val);
  $arr = array_values(array_filter(array_map('trim', $arr), fn($v) => $v !== ''));
  return $upper ? array_map('strtoupper', $arr) : $arr;
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

    $page          = max(1, (int)($body['page'] ?? 1));
    $limit         = min(200, max(5, (int)($body['limit'] ?? 10)));
    $q             = norm($body['q'] ?? '');
    $tipoArr        = parseArr($body['tipo']           ?? [], true);
    $versaoAppArr   = parseArr($body['versao_app']     ?? []);
    $regiaoArr      = parseArr($body['regiao']         ?? []);
    $versaoEmpArr   = parseArr($body['versao_empresa'] ?? []);

    /* ORDENAÇÃO */
    $orderBy  = $body['order_by'] ?? 'id';
    $orderDir = strtoupper($body['order_dir'] ?? 'DESC');

    if (!in_array($orderBy, $ORDERABLE_FIELDS, true)) $orderBy = 'id';
    if (!in_array($orderDir, ['ASC','DESC'], true))   $orderDir = 'DESC';

    /* WHERE unificado com prefixos de tabela (JOIN em todas as queries) */
    $where  = [];
    $params = [];

    if ($q !== '') {
      $like = "%{$q}%";
      $where[] = "(m.cod_cliente LIKE ? OR m.cliente LIKE ? OR m.acesso_server LIKE ? OR m.porta LIKE ? OR m.observacao LIKE ? OR l.cnpj LIKE ?)";
      array_push($params, $like, $like, $like, $like, $like, $like);
    }

    if (!empty($tipoArr)) {
      $tiposFiltrados = array_filter($tipoArr, fn($t) => in_array($t, TIPOS_PERMITIDOS, true));
      if ($tiposFiltrados) {
        $ph = implode(',', array_fill(0, count($tiposFiltrados), '?'));
        $where[] = "m.tipo_acesso IN ({$ph})";
        foreach ($tiposFiltrados as $t) $params[] = $t;
      }
    }

    if (!empty($versaoAppArr)) {
      /* Busca versão global uma vez e aplica lógica para cada item */
      $stmtGV = $pdo->prepare("SELECT valor FROM clientes_web_config WHERE chave = 'mobile_apifv_versao' LIMIT 1");
      $stmtGV->execute();
      $globalVersao = ($stmtGV->fetch(PDO::FETCH_ASSOC) ?: [])['valor'] ?? '';

      $vaConds = [];
      foreach ($versaoAppArr as $va) {
        if ($globalVersao !== '' && $va === $globalVersao) {
          $vaConds[] = "(m.versao = ? OR ((m.versao IS NULL OR m.versao = '') AND m.tipo_acesso = 'API_FORCA_DE_VENDA'))";
        } else {
          $vaConds[] = "m.versao = ?";
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

    $joinSql = "LEFT JOIN clientes_web_login l ON l.codigo_cliente = m.cod_cliente";

    /* RESUMO (MINI CARDS) — agora também usa JOIN para filtros de l.* */
    $stmtS = $pdo->prepare("
      SELECT
        COUNT(*) AS total,
        SUM(m.tipo_acesso = 'API_FORCA_DE_VENDA') AS api,
        SUM(m.tipo_acesso = 'FV_SMART_CLIENT') AS fv
      FROM clientes_web_mobile m
      $joinSql
      $whereSql
    ");
    $stmtS->execute($params);
    $stats = $stmtS->fetch(PDO::FETCH_ASSOC);

    $total = (int)($stats['total'] ?? 0);
    $pages = max(1, (int)ceil($total / $limit));

    /* DADOS */
    $stmt = $pdo->prepare("
      SELECT
        m.id, m.cod_cliente, m.cliente, m.acesso_server, m.porta,
        m.tipo_acesso, m.versao, m.status, m.observacao,
        m.criado_em, m.atualizado_em,
        COALESCE(l.versao_padrao, '') AS versao_padrao,
        COALESCE(l.caminho_acesso, '') AS caminho_acesso,
        COALESCE(l.regiao, '') AS regiao,
        COALESCE(l.cidade, '') AS cidade,
        COALESCE(l.estado, '') AS estado,
        COALESCE(l.status, '') AS empresa_status,
        COALESCE(l.cnpj, '') AS empresa_cnpj
      FROM clientes_web_mobile m
      $joinSql
      $whereSql
      ORDER BY m.{$orderBy} {$orderDir}
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
     OPTIONS (valores distintos para os filtros)
  ===================================================== */
  if ($action === 'options') {
    $versoes_app = $pdo->query("
      SELECT DISTINCT versao FROM clientes_web_mobile
      WHERE versao IS NOT NULL AND versao <> ''
      ORDER BY versao
    ")->fetchAll(PDO::FETCH_COLUMN);

    /* Inclui a versão padrão global no dropdown se estiver definida */
    $stmtGV = $pdo->prepare("SELECT valor FROM clientes_web_config WHERE chave = 'mobile_apifv_versao' LIMIT 1");
    $stmtGV->execute();
    $globalVersao = ($stmtGV->fetch(PDO::FETCH_ASSOC) ?: [])['valor'] ?? '';
    if ($globalVersao !== '' && !in_array($globalVersao, $versoes_app, true)) {
      $versoes_app[] = $globalVersao;
      sort($versoes_app);
    }

    $regioes = $pdo->query("
      SELECT DISTINCT l.regiao
      FROM clientes_web_mobile m
      INNER JOIN clientes_web_login l ON l.codigo_cliente = m.cod_cliente
      WHERE l.regiao IS NOT NULL AND l.regiao <> ''
      ORDER BY l.regiao
    ")->fetchAll(PDO::FETCH_COLUMN);

    $versoes_emp = $pdo->query("
      SELECT DISTINCT l.versao_padrao
      FROM clientes_web_mobile m
      INNER JOIN clientes_web_login l ON l.codigo_cliente = m.cod_cliente
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

  /* =====================================================
     GET
  ===================================================== */
  if ($action === 'get') {

    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido');

    $stmt = $pdo->prepare("
      SELECT
        m.*,
        COALESCE(l.versao_padrao, '') AS versao_padrao,
        COALESCE(l.caminho_acesso, '') AS caminho_acesso,
        COALESCE(l.regiao, '') AS regiao,
        COALESCE(l.cidade, '') AS cidade,
        COALESCE(l.estado, '') AS estado,
        COALESCE(l.cnpj, '') AS empresa_cnpj
      FROM clientes_web_mobile m
      LEFT JOIN clientes_web_login l ON l.codigo_cliente = m.cod_cliente
      WHERE m.id = ?
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
    $versao  = norm($d['versao'] ?? '');
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
      (cod_cliente, cliente, acesso_server, porta, tipo_acesso, versao, status, observacao, criado_em, atualizado_em)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");

    $stmt->execute([
      $cod,
      $cliente,
      $server,
      $porta !== '' ? (int)$porta : null,
      $tipo,
      $versao !== '' ? $versao : null,
      $status,
      $obs
    ]);

    $newId = $pdo->lastInsertId();
    logAction($pdo, 'CREATE', 'FV / Mobile', $newId, null, [
      'cod_cliente'   => $cod,
      'cliente'       => $cliente,
      'acesso_server' => $server,
      'porta'         => $porta,
      'tipo_acesso'   => $tipo,
      'versao'        => $versao,
      'status'        => $status,
    ], "Cadastrou mobile \"{$cliente}\"");

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
    $versao  = norm($d['versao'] ?? '');
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

    $stmtAntes = $pdo->prepare("SELECT cod_cliente,cliente,acesso_server,porta,tipo_acesso,versao,status FROM clientes_web_mobile WHERE id = ? LIMIT 1");
    $stmtAntes->execute([$id]);
    $dadosAntes = $stmtAntes->fetch(PDO::FETCH_ASSOC) ?: null;

    $stmt = $pdo->prepare("
      UPDATE clientes_web_mobile
      SET
        cod_cliente   = ?,
        cliente       = ?,
        acesso_server = ?,
        porta         = ?,
        tipo_acesso   = ?,
        versao        = ?,
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
      $versao !== '' ? $versao : null,
      $status,
      $obs,
      $id
    ]);

    $dadosDepois = [
      'cod_cliente'   => $cod,
      'cliente'       => $cliente,
      'acesso_server' => $server,
      'porta'         => $porta,
      'tipo_acesso'   => $tipo,
      'versao'        => $versao,
      'status'        => $status,
    ];
    $resumo = $dadosAntes ? resumoAlteracoes($dadosAntes, $dadosDepois) : '';
    $desc   = "Atualizou mobile \"{$cliente}\"" . ($resumo ? " | {$resumo}" : '');
    logAction($pdo, 'UPDATE', 'FV / Mobile', $id, $dadosAntes, $dadosDepois, $desc);

    echo json_encode(['success'=>true]);
    exit;
  }

  /* =====================================================
     DELETE
  ===================================================== */
  if ($action === 'delete') {

    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido');

    $stmtAntes = $pdo->prepare("SELECT cod_cliente,cliente,acesso_server,porta,tipo_acesso,status FROM clientes_web_mobile WHERE id = ? LIMIT 1");
    $stmtAntes->execute([$id]);
    $dadosAntes = $stmtAntes->fetch(PDO::FETCH_ASSOC) ?: null;
    $nomeCliente = $dadosAntes['cliente'] ?? "ID {$id}";

    $pdo->prepare("DELETE FROM clientes_web_mobile WHERE id = ? LIMIT 1")->execute([$id]);

    logAction($pdo, 'DELETE', 'FV / Mobile', $id, $dadosAntes, null, "Excluiu mobile \"{$nomeCliente}\"");

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
