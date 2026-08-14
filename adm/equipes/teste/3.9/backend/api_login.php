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

/* Verifica se a cidade informada já existe cadastrada com outra grafia
   (mesmo nome, diferindo só em maiúsculas/minúsculas). Retorna a grafia
   já cadastrada quando encontra divergência, ou null quando não há
   conflito (cidade nova ou já cadastrada com a grafia idêntica).
   $excludeId serve para ignorar o próprio registro numa edição. */
function cidadeComOutraGrafia(PDO $pdo, string $cidade, ?int $excludeId = null): ?string {
  if ($cidade === '') return null;

  $sql = "SELECT DISTINCT cidade FROM clientes_web_login WHERE cidade IS NOT NULL AND cidade <> ''";
  $params = [];
  if ($excludeId) {
    $sql .= " AND id <> ?";
    $params[] = $excludeId;
  }
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);

  $alvo = mb_strtolower($cidade, 'UTF-8');
  foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $existente) {
    if ($existente === $cidade) return null; // grafia idêntica já cadastrada — ok, não é conflito
    if (mb_strtolower($existente, 'UTF-8') === $alvo) return $existente;
  }
  return null;
}

/* Busca o estado já cadastrado para uma cidade já existente (comparação
   ignorando maiúsculas/minúsculas). Retorna null quando a cidade é nova
   ou nenhum registro dela tem estado preenchido. Usado para auto-preencher
   o campo Estado ao digitar uma cidade já cadastrada. */
function estadoDaCidadeExistente(PDO $pdo, string $cidade, ?int $excludeId = null): ?string {
  if ($cidade === '') return null;

  $sql = "SELECT cidade, estado FROM clientes_web_login
          WHERE cidade IS NOT NULL AND cidade <> ''
            AND estado IS NOT NULL AND estado <> ''";
  $params = [];
  if ($excludeId) {
    $sql .= " AND id <> ?";
    $params[] = $excludeId;
  }
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);

  $alvo = mb_strtolower($cidade, 'UTF-8');
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (mb_strtolower($row['cidade'], 'UTF-8') === $alvo) return $row['estado'];
  }
  return null;
}

/* Converte valor recebido (array JSON ou string separada por vírgula) em array limpo */
function parseArr($val, $upper = false) {
  if (is_array($val)) $arr = $val;
  elseif ($val === null || $val === '') return [];
  else $arr = explode(',', (string)$val);
  $arr = array_values(array_filter(array_map('trim', $arr), fn($v) => $v !== ''));
  return $upper ? array_map('strtoupper', $arr) : $arr;
}

/* Monta condição "coluna IN (...)" — se o array contiver o sentinel '__none__',
   inclui também "coluna IS NULL OR coluna = ''" (filtro "Sem cidade/estado") */
function condColunaOuVazio($coluna, array $valores, array &$params) {
  $temNone = in_array('__none__', $valores, true);
  $reais   = array_values(array_filter($valores, fn($v) => $v !== '__none__'));

  $conds = [];
  if ($reais) {
    $ph = implode(',', array_fill(0, count($reais), '?'));
    $conds[] = "{$coluna} IN ({$ph})";
    foreach ($reais as $v) $params[] = $v;
  }
  if ($temNone) {
    $conds[] = "({$coluna} IS NULL OR {$coluna} = '')";
  }

  return $conds ? '(' . implode(' OR ', $conds) . ')' : '';
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

    $statusArr    = parseArr($body['status']     ?? [], true);
    $versaoArr    = parseArr($body['versao']      ?? []);
    $regiaoArr    = parseArr($body['regiao']      ?? []);
    $cidadeArr    = parseArr($body['cidade']      ?? []);
    $estadoArr    = parseArr($body['estado']      ?? []);
    $exeArr       = parseArr($body['exe']         ?? []);
    $integracaoArr = parseArr($body['integracao'] ?? []);

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
        OR cnpj LIKE ?
        OR cidade LIKE ?
        OR estado LIKE ?
      )";
      $like = "%{$q}%";
      array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
    }

    if (!empty($statusArr)) {
      $ph = implode(',', array_fill(0, count($statusArr), '?'));
      $where[] = "UPPER(status) IN ({$ph})";
      foreach ($statusArr as $s) $params[] = $s;
    }

    if (!empty($versaoArr)) {
      $ph = implode(',', array_fill(0, count($versaoArr), '?'));
      $where[] = "versao_padrao IN ({$ph})";
      foreach ($versaoArr as $v) $params[] = $v;
    }

    if (!empty($regiaoArr)) {
      $ph = implode(',', array_fill(0, count($regiaoArr), '?'));
      $where[] = "regiao IN ({$ph})";
      foreach ($regiaoArr as $r) $params[] = $r;
    }

    if (!empty($cidadeArr)) {
      $cond = condColunaOuVazio('cidade', $cidadeArr, $params);
      if ($cond) $where[] = $cond;
    }

    if (!empty($estadoArr)) {
      $cond = condColunaOuVazio('estado', $estadoArr, $params);
      if ($cond) $where[] = $cond;
    }

    /* EXE: se selecionar "1" e "0" juntos, sem filtro */
    if (count($exeArr) === 1) {
      if ($exeArr[0] === '1') {
        $where[] = "possui_exe = 1";
      } elseif ($exeArr[0] === '0') {
        $where[] = "(possui_exe IS NULL OR possui_exe = 0)";
      }
    }

    /* INTEGRAÇÃO: OR entre os selecionados */
    if (!empty($integracaoArr)) {
      $hasMobile   = in_array('mobile',   $integracaoArr, true);
      $hasWhatsapp = in_array('whatsapp', $integracaoArr, true);
      $hasPdvoff   = in_array('pdvoff',   $integracaoArr, true);
      $hasBi       = in_array('bi',       $integracaoArr, true);
      $hasAny      = in_array('any',      $integracaoArr, true);
      $hasNone     = in_array('none',     $integracaoArr, true);

      $intConds = [];

      if ($hasNone && count($integracaoArr) === 1) {
        /* somente "Sem integração" */
        $where[] = "(
          NOT EXISTS(SELECT 1 FROM clientes_web_mobile    m WHERE m.cod_cliente = codigo_cliente LIMIT 1)
          AND NOT EXISTS(SELECT 1 FROM clientes_web_api_whats w WHERE w.cod_cliente = codigo_cliente LIMIT 1)
          AND NOT EXISTS(SELECT 1 FROM clientes_web_pdvoff    p WHERE p.cod_cliente = codigo_cliente LIMIT 1)
          AND NOT EXISTS(SELECT 1 FROM clientes_web_bi        b WHERE b.cod_cliente = codigo_cliente LIMIT 1)
        )";
      } elseif (!$hasNone) {
        if ($hasAny || ($hasMobile && $hasWhatsapp && $hasPdvoff && $hasBi)) {
          $intConds[] = "EXISTS(SELECT 1 FROM clientes_web_mobile    m WHERE m.cod_cliente = codigo_cliente LIMIT 1)";
          $intConds[] = "EXISTS(SELECT 1 FROM clientes_web_api_whats w WHERE w.cod_cliente = codigo_cliente LIMIT 1)";
          $intConds[] = "EXISTS(SELECT 1 FROM clientes_web_pdvoff    p WHERE p.cod_cliente = codigo_cliente LIMIT 1)";
          $intConds[] = "EXISTS(SELECT 1 FROM clientes_web_bi        b WHERE b.cod_cliente = codigo_cliente LIMIT 1)";
        } else {
          if ($hasMobile)   $intConds[] = "EXISTS(SELECT 1 FROM clientes_web_mobile    m WHERE m.cod_cliente = codigo_cliente LIMIT 1)";
          if ($hasWhatsapp) $intConds[] = "EXISTS(SELECT 1 FROM clientes_web_api_whats w WHERE w.cod_cliente = codigo_cliente LIMIT 1)";
          if ($hasPdvoff)   $intConds[] = "EXISTS(SELECT 1 FROM clientes_web_pdvoff    p WHERE p.cod_cliente = codigo_cliente LIMIT 1)";
          if ($hasBi)       $intConds[] = "EXISTS(SELECT 1 FROM clientes_web_bi        b WHERE b.cod_cliente = codigo_cliente LIMIT 1)";
        }
        if ($intConds) $where[] = '(' . implode(' OR ', $intConds) . ')';
      }
    }

    $qtdUsuarios = (isset($body['qtd_usuarios']) && $body['qtd_usuarios'] !== '')
      ? (int)$body['qtd_usuarios']
      : null;

    if ($qtdUsuarios !== null && $qtdUsuarios > 0) {
      $where[]  = "(SELECT COALESCE(SUM(u.qtd_usuarios),0) FROM clientes_tga_web_usuarios u WHERE u.codigo_empresa = codigo_cliente) >= ?";
      $params[] = $qtdUsuarios;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    /* ==========================
       RESUMO (KPIs)
    ========================== */
    $whereStats  = [];
    $paramsStats = [];

    if (!empty($statusArr)) {
      $ph = implode(',', array_fill(0, count($statusArr), '?'));
      $whereStats[] = "UPPER(status) IN ({$ph})";
      foreach ($statusArr as $s) $paramsStats[] = $s;
    }

    if (!empty($versaoArr)) {
      $ph = implode(',', array_fill(0, count($versaoArr), '?'));
      $whereStats[] = "versao_padrao IN ({$ph})";
      foreach ($versaoArr as $v) $paramsStats[] = $v;
    }

    if (!empty($regiaoArr)) {
      $ph = implode(',', array_fill(0, count($regiaoArr), '?'));
      $whereStats[] = "regiao IN ({$ph})";
      foreach ($regiaoArr as $r) $paramsStats[] = $r;
    }

    if (!empty($cidadeArr)) {
      $cond = condColunaOuVazio('cidade', $cidadeArr, $paramsStats);
      if ($cond) $whereStats[] = $cond;
    }

    if (!empty($estadoArr)) {
      $cond = condColunaOuVazio('estado', $estadoArr, $paramsStats);
      if ($cond) $whereStats[] = $cond;
    }

    if (count($exeArr) === 1) {
      if ($exeArr[0] === '1') {
        $whereStats[] = "possui_exe = 1";
      } elseif ($exeArr[0] === '0') {
        $whereStats[] = "(possui_exe IS NULL OR possui_exe = 0)";
      }
    }

    $whereStatsSql = $whereStats
      ? 'WHERE ' . implode(' AND ', $whereStats)
      : '';

    $stmtS = $pdo->prepare("
      SELECT
        COUNT(*)                AS total,
        SUM(status = 'ATIVO')   AS ativos,
        SUM(status = 'INATIVO') AS inativos,
        SUM(possui_exe = 1)     AS com_exe,
        SUM(EXISTS(
          SELECT 1 FROM clientes_web_mobile m
          WHERE m.cod_cliente = clientes_web_login.codigo_cliente
            AND m.tipo_acesso = 'FV_SMART_CLIENT'
        )) AS com_mobile_fv,
        SUM(EXISTS(
          SELECT 1 FROM clientes_web_mobile m
          WHERE m.cod_cliente = clientes_web_login.codigo_cliente
            AND m.tipo_acesso = 'API_FORCA_DE_VENDA'
        )) AS com_mobile_forca,
        SUM(EXISTS(
          SELECT 1 FROM clientes_web_api_whats w WHERE w.cod_cliente = clientes_web_login.codigo_cliente
        )) AS com_whatsapp,
        SUM(EXISTS(
          SELECT 1 FROM clientes_web_pdvoff p WHERE p.cod_cliente = clientes_web_login.codigo_cliente
        )) AS com_pdvoff,
        SUM(EXISTS(
          SELECT 1 FROM clientes_web_bi b WHERE b.cod_cliente = clientes_web_login.codigo_cliente
        )) AS com_bi
      FROM clientes_web_login
      {$whereStatsSql}
    ");
    $stmtS->execute($paramsStats);

    $stats = $stmtS->fetch(PDO::FETCH_ASSOC) ?: [
      'total'            => 0,
      'ativos'           => 0,
      'inativos'         => 0,
      'com_exe'          => 0,
      'com_mobile_fv'    => 0,
      'com_mobile_forca' => 0,
      'com_whatsapp'     => 0,
      'com_pdvoff'       => 0,
      'com_bi'           => 0
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
      'cnpj',
      'caminho_acesso',
      'versao_padrao',
      'regiao',
      'cidade',
      'estado',
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
        l.id,
        l.codigo_cliente,
        l.nome_cliente,
        l.cnpj,
        l.caminho_acesso,
        l.versao_padrao,
        l.regiao,
        l.cidade,
        l.estado,
        COALESCE((
          SELECT SUM(u.qtd_usuarios)
          FROM clientes_tga_web_usuarios u
          WHERE u.codigo_empresa = l.codigo_cliente
        ), 0) AS qtd_usuarios_vinculados,
        (
          SELECT u.observacao
          FROM clientes_tga_web_usuarios u
          WHERE u.codigo_empresa = l.codigo_cliente
          ORDER BY u.criado_em ASC
          LIMIT 1
        ) AS observacao_usuario,
        l.status,
        l.possui_exe,
        l.exe_nome,
        DATE_FORMAT(
          COALESCE(l.atualizado_em, l.criado_em),
          '%d/%m/%Y %H:%i:%s'
        ) AS atualizado_em,
        EXISTS(
          SELECT 1 FROM clientes_web_mobile m
          WHERE m.cod_cliente = l.codigo_cliente
          LIMIT 1
        ) AS tem_mobile,
        (
          SELECT GROUP_CONCAT(
            CONCAT_WS(' | ',
              CONCAT('Servidor: ', COALESCE(NULLIF(m.acesso_server,''), '-')),
              CASE WHEN m.porta IS NOT NULL AND m.porta <> '' THEN CONCAT('Porta: ', m.porta) END,
              CONCAT('Tipo: ', COALESCE(NULLIF(m.tipo_acesso,''), '-'))
            )
            SEPARATOR '\n'
          )
          FROM clientes_web_mobile m
          WHERE m.cod_cliente = l.codigo_cliente
        ) AS info_mobile,
        EXISTS(
          SELECT 1 FROM clientes_web_api_whats w
          WHERE w.cod_cliente = l.codigo_cliente
          LIMIT 1
        ) AS tem_whatsapp,
        (
          SELECT CONCAT_WS(' | ',
            CONCAT('Servidor: ', COALESCE(NULLIF(w.acesso_server,''), '-')),
            CONCAT('Tipo: ', COALESCE(NULLIF(w.tipo_acesso,''), '-'))
          )
          FROM clientes_web_api_whats w
          WHERE w.cod_cliente = l.codigo_cliente
          LIMIT 1
        ) AS info_whatsapp,
        EXISTS(
          SELECT 1 FROM clientes_web_pdvoff p
          WHERE p.cod_cliente = l.codigo_cliente
          LIMIT 1
        ) AS tem_pdvoff,
        (
          SELECT CONCAT_WS(' | ',
            CONCAT('Servidor: ', COALESCE(NULLIF(p.acesso_server,''), '-')),
            CONCAT('Tipo: ', COALESCE(NULLIF(p.tipo_acesso,''), '-'))
          )
          FROM clientes_web_pdvoff p
          WHERE p.cod_cliente = l.codigo_cliente
          LIMIT 1
        ) AS info_pdvoff,
        EXISTS(
          SELECT 1 FROM clientes_web_bi b
          WHERE b.cod_cliente = l.codigo_cliente
          LIMIT 1
        ) AS tem_bi,
        (
          SELECT CONCAT('Servidor: ', COALESCE(NULLIF(b.acesso_server,''), '-'), ' | Tipo: BI')
          FROM clientes_web_bi b
          WHERE b.cod_cliente = l.codigo_cliente
          LIMIT 1
        ) AS info_bi
      FROM clientes_web_login l
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
     LISTAR REGIÕES
  ========================================================= */
  if ($action === 'regions') {
    $stmt = $pdo->query("
      SELECT DISTINCT regiao
      FROM clientes_web_login
      WHERE regiao IS NOT NULL
        AND regiao <> ''
      ORDER BY regiao ASC
    ");
    echo json_encode([
      'success'  => true,
      'regions' => $stmt->fetchAll(PDO::FETCH_COLUMN)
    ]);
    exit;
  }

  /* =========================================================
     LISTAR CIDADES
  ========================================================= */
  if ($action === 'cities') {
    $stmt = $pdo->query("
      SELECT DISTINCT cidade
      FROM clientes_web_login
      WHERE cidade IS NOT NULL
        AND cidade <> ''
      ORDER BY cidade ASC
    ");
    echo json_encode([
      'success' => true,
      'cities'  => $stmt->fetchAll(PDO::FETCH_COLUMN)
    ]);
    exit;
  }

  /* =========================================================
     LISTAR ESTADOS
  ========================================================= */
  if ($action === 'states') {
    $stmt = $pdo->query("
      SELECT DISTINCT estado
      FROM clientes_web_login
      WHERE estado IS NOT NULL
        AND estado <> ''
      ORDER BY estado ASC
    ");
    echo json_encode([
      'success' => true,
      'states'  => $stmt->fetchAll(PDO::FETCH_COLUMN)
    ]);
    exit;
  }

  /* =========================================================
     ESTADO DE UMA CIDADE JÁ CADASTRADA
     Usado para auto-preencher o Estado ao digitar uma cidade
     que já existe em outro cadastro (ignora maiúsc./minúsc.).
  ========================================================= */
  if ($action === 'estado_da_cidade') {
    $cidade    = norm($body['cidade'] ?? '');
    $excludeId = (int)($body['excluir_id'] ?? 0) ?: null;

    echo json_encode([
      'success' => true,
      'estado'  => estadoDaCidadeExistente($pdo, $cidade, $excludeId)
    ]);
    exit;
  }

  /* =========================================================
     GET
  ========================================================= */
  if ($action === 'get') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido');

    $stmt = $pdo->prepare("
      SELECT
        l.*,
        COALESCE((
          SELECT SUM(u.qtd_usuarios)
          FROM clientes_tga_web_usuarios u
          WHERE u.codigo_empresa = l.codigo_cliente
        ), 0) AS qtd_usuarios_vinculados,
        (
          SELECT COUNT(*)
          FROM clientes_tga_web_usuarios u
          WHERE u.codigo_empresa = l.codigo_cliente
        ) AS qtd_registros_usuarios,
        (
          SELECT u.id
          FROM clientes_tga_web_usuarios u
          WHERE u.codigo_empresa = l.codigo_cliente
          ORDER BY criado_em ASC
          LIMIT 1
        ) AS id_registro_usuario,
        (
          SELECT u.observacao
          FROM clientes_tga_web_usuarios u
          WHERE u.codigo_empresa = l.codigo_cliente
          ORDER BY criado_em ASC
          LIMIT 1
        ) AS observacao_usuario
      FROM clientes_web_login l
      WHERE l.id = ?
      LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) throw new Exception('Registro não encontrado');

    // Busca status das integrações por código de cliente
    // Mobile ON = FV_SMART_CLIENT | Mobile OFF = API_FORCA_DE_VENDA
    $stmtInt = $pdo->prepare("
      SELECT
        EXISTS(SELECT 1 FROM clientes_web_api_whats  WHERE cod_cliente = ? LIMIT 1) AS tem_whatsapp,
        EXISTS(SELECT 1 FROM clientes_web_mobile     WHERE cod_cliente = ? AND tipo_acesso = 'FV_SMART_CLIENT'    LIMIT 1) AS tem_mobile_on,
        EXISTS(SELECT 1 FROM clientes_web_mobile     WHERE cod_cliente = ? AND tipo_acesso = 'API_FORCA_DE_VENDA' LIMIT 1) AS tem_mobile_off,
        EXISTS(SELECT 1 FROM clientes_web_pdvoff     WHERE cod_cliente = ? LIMIT 1) AS tem_pdvoff,
        EXISTS(SELECT 1 FROM clientes_web_bi         WHERE cod_cliente = ? LIMIT 1) AS tem_bi,
        (SELECT porta FROM clientes_web_mobile WHERE cod_cliente = ? AND tipo_acesso = 'FV_SMART_CLIENT' LIMIT 1) AS porta_mobile_on
    ");
    $cod = $row['codigo_cliente'];
    $stmtInt->execute([$cod, $cod, $cod, $cod, $cod, $cod]);
    $integStatus = $stmtInt->fetch(PDO::FETCH_ASSOC) ?: [];
    $row = array_merge($row, $integStatus);

    $stmtU = $pdo->prepare("
      SELECT id, qtd_usuarios, observacao, status
      FROM clientes_tga_web_usuarios
      WHERE codigo_empresa = ?
      ORDER BY criado_em ASC
    ");
    $stmtU->execute([$row['codigo_cliente']]);
    $usuarios = $stmtU->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'row' => $row, 'usuarios' => $usuarios]);
    exit;
  }

  /* =========================================================
     CREATE
  ========================================================= */
  if ($action === 'create') {

    $d = $body['data'] ?? [];

    $codigo  = norm($d['codigo_cliente'] ?? '');
    $nome    = norm($d['nome_cliente'] ?? '');
    $cnpj    = norm($d['cnpj'] ?? '') ?: null;
    $caminho = norm($d['caminho_acesso'] ?? '');
    $versao  = norm($d['versao_padrao'] ?? '');
    $regiao  = norm($d['regiao'] ?? '') ?: null;
    $cidade  = norm($d['cidade'] ?? '') ?: null;
    $estado  = norm($d['estado'] ?? '') ?: null;
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

    if ($cidade !== null) {
      $grafiaExistente = cidadeComOutraGrafia($pdo, $cidade);
      if ($grafiaExistente !== null) {
        throw new Exception("A cidade \"{$grafiaExistente}\" já está cadastrada. Utilize a mesma grafia (verifique maiúsculas/minúsculas).");
      }
    }

    $stmt = $pdo->prepare("
      INSERT INTO clientes_web_login
      (
        codigo_cliente,
        nome_cliente,
        cnpj,
        caminho_acesso,
        versao_padrao,
        regiao,
        cidade,
        estado,
        status,
        possui_exe,
        exe_nome,
        criado_em
      )
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
      $codigo,
      $nome,
      $cnpj,
      $caminho,
      $versao,
      $regiao,
      $cidade,
      $estado,
      $status,
      $possui_exe,
      $exe_nome
    ]);

    $newId = $pdo->lastInsertId();
    logAction($pdo, 'CREATE', 'Empresas TGA Web', $newId, null, [
      'codigo_cliente' => $codigo,
      'nome_cliente'   => $nome,
      'cnpj'           => $cnpj,
      'caminho_acesso' => $caminho,
      'versao_padrao'  => $versao,
      'regiao'         => $regiao,
      'cidade'         => $cidade,
      'estado'         => $estado,
      'status'         => $status,
      'possui_exe'     => $possui_exe,
      'exe_nome'       => $exe_nome,
    ], "Cadastrou empresa \"{$nome}\"");

    // Cria registro de usuários se informado
    $qtdU = (int)($d['qtd_usuarios'] ?? 0);
    $obsU = trim($d['observacao'] ?? '');
    if ($qtdU > 0 || $obsU !== '') {
      $pdo->prepare("
        INSERT INTO clientes_tga_web_usuarios
          (codigo_empresa, nome_empresa, qtd_usuarios, observacao, status)
        VALUES (?, ?, ?, ?, 'ATIVO')
      ")->execute([$codigo, $nome, $qtdU, $obsU !== '' ? $obsU : null]);
      logAction($pdo, 'CREATE', 'Usuários Web', (int)$pdo->lastInsertId(), null,
        ['codigo_empresa' => $codigo, 'qtd_usuarios' => $qtdU, 'observacao' => $obsU],
        "Usuários criados ao cadastrar empresa \"{$nome}\"");
    }

    // Cria registros de integração se marcados
    if (array_key_exists('integracoes', $body)) {
      $integ = $body['integracoes'];

      if (!empty($integ['whatsapp'])) {
        $pdo->prepare("
          INSERT INTO clientes_web_api_whats
            (cod_cliente, cliente, acesso_server, tipo_acesso, criado_em, atualizado_em)
          VALUES (?, ?, '', 'API_WHATS', NOW(), NOW())
        ")->execute([$codigo, $nome]);
        logAction($pdo, 'CREATE', 'WhatsApp', (int)$pdo->lastInsertId(), null,
          ['cod_cliente' => $codigo, 'cliente' => $nome],
          "WhatsApp adicionado ao cadastrar empresa \"{$nome}\"");
      }

      if (!empty($integ['mobile_on'])) {
        $portaMon = (isset($integ['porta_mobile_on']) && (int)$integ['porta_mobile_on'] > 0)
          ? (int)$integ['porta_mobile_on'] : null;
        $pdo->prepare("
          INSERT INTO clientes_web_mobile
            (cod_cliente, cliente, acesso_server, porta, tipo_acesso, status, criado_em, atualizado_em)
          VALUES (?, ?, '', ?, 'FV_SMART_CLIENT', 'OFF', NOW(), NOW())
        ")->execute([$codigo, $nome, $portaMon]);
        logAction($pdo, 'CREATE', 'FV / Mobile', (int)$pdo->lastInsertId(), null,
          ['cod_cliente' => $codigo, 'tipo_acesso' => 'FV_SMART_CLIENT', 'porta' => $portaMon],
          "Mobile ON adicionado ao cadastrar empresa \"{$nome}\"");
      }

      if (!empty($integ['mobile_off'])) {
        $pdo->prepare("
          INSERT INTO clientes_web_mobile
            (cod_cliente, cliente, acesso_server, tipo_acesso, status, criado_em, atualizado_em)
          VALUES (?, ?, '', 'API_FORCA_DE_VENDA', 'OFF', NOW(), NOW())
        ")->execute([$codigo, $nome]);
        logAction($pdo, 'CREATE', 'FV / Mobile', (int)$pdo->lastInsertId(), null,
          ['cod_cliente' => $codigo, 'tipo_acesso' => 'API_FORCA_DE_VENDA'],
          "Mobile OFF adicionado ao cadastrar empresa \"{$nome}\"");
      }

      if (!empty($integ['pdvoff'])) {
        $pdo->prepare("
          INSERT INTO clientes_web_pdvoff
            (cod_cliente, cliente, acesso_server, caixas, tipo_acesso, criado_em, atualizado_em)
          VALUES (?, ?, '', 0, 'PDVOFF', NOW(), NOW())
        ")->execute([$codigo, $nome]);
        logAction($pdo, 'CREATE', 'PDV OFF', (int)$pdo->lastInsertId(), null,
          ['cod_cliente' => $codigo],
          "PDV OFF adicionado ao cadastrar empresa \"{$nome}\"");
      }

      if (!empty($integ['bi'])) {
        $pdo->prepare("
          INSERT INTO clientes_web_bi
            (cod_cliente, cliente, criado_em, atualizado_em)
          VALUES (?, ?, NOW(), NOW())
        ")->execute([$codigo, $nome]);
        logAction($pdo, 'CREATE', 'BI', (int)$pdo->lastInsertId(), null,
          ['cod_cliente' => $codigo],
          "BI adicionado ao cadastrar empresa \"{$nome}\"");
      }
    }

    echo json_encode(['success' => true, 'id' => $newId]);
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
    $cnpj    = norm($d['cnpj'] ?? '') ?: null;
    $caminho = norm($d['caminho_acesso'] ?? '');
    $versao  = norm($d['versao_padrao'] ?? '');
    $regiao  = norm($d['regiao'] ?? '') ?: null;
    $cidade  = norm($d['cidade'] ?? '') ?: null;
    $estado  = norm($d['estado'] ?? '') ?: null;
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

    if ($cidade !== null) {
      $grafiaExistente = cidadeComOutraGrafia($pdo, $cidade, $id);
      if ($grafiaExistente !== null) {
        throw new Exception("A cidade \"{$grafiaExistente}\" já está cadastrada. Utilize a mesma grafia (verifique maiúsculas/minúsculas).");
      }
    }

    // Busca dados antes da alteração
    $stmtAntes = $pdo->prepare("SELECT codigo_cliente,nome_cliente,cnpj,caminho_acesso,versao_padrao,regiao,cidade,estado,status,possui_exe,exe_nome FROM clientes_web_login WHERE id = ? LIMIT 1");
    $stmtAntes->execute([$id]);
    $dadosAntes = $stmtAntes->fetch(PDO::FETCH_ASSOC) ?: null;

    $stmt = $pdo->prepare("
      UPDATE clientes_web_login
      SET
        codigo_cliente = ?,
        nome_cliente   = ?,
        cnpj           = ?,
        caminho_acesso = ?,
        versao_padrao  = ?,
        regiao         = ?,
        cidade         = ?,
        estado         = ?,
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
      $cnpj,
      $caminho,
      $versao,
      $regiao,
      $cidade,
      $estado,
      $status,
      $possui_exe,
      $exe_nome,
      $id
    ]);

    $dadosDepois = [
      'codigo_cliente' => $codigo,
      'nome_cliente'   => $nome,
      'cnpj'           => $cnpj,
      'caminho_acesso' => $caminho,
      'versao_padrao'  => $versao,
      'regiao'         => $regiao,
      'cidade'         => $cidade,
      'estado'         => $estado,
      'status'         => $status,
      'possui_exe'     => $possui_exe,
      'exe_nome'       => $exe_nome,
    ];
    $resumo = $dadosAntes ? resumoAlteracoes($dadosAntes, $dadosDepois) : '';
    $desc   = "Atualizou empresa \"{$nome}\"" . ($resumo ? " | {$resumo}" : '');
    logAction($pdo, 'UPDATE', 'Empresas TGA Web', $id, $dadosAntes, $dadosDepois, $desc);

    // Sincroniza clientes_tga_web_usuarios com o novo nome/código da empresa
    $oldCodigo = $dadosAntes['codigo_cliente'] ?? $codigo;
    $pdo->prepare("
      UPDATE clientes_tga_web_usuarios
      SET nome_empresa = ?, codigo_empresa = ?
      WHERE codigo_empresa = ?
    ")->execute([$nome, $codigo, $oldCodigo]);

    // Atualiza qtd_usuarios e/ou observacao se enviado pelo modal
    $qtdNovaSet = isset($d['qtd_usuarios']) && $d['qtd_usuarios'] !== '';
    $obsUSet    = array_key_exists('observacao', $d);
    $obsU       = $obsUSet ? trim($d['observacao']) : null;

    if ($qtdNovaSet || $obsUSet) {
      $qtdNova = $qtdNovaSet ? max(0, (int)$d['qtd_usuarios']) : null;

      $stmtCnt = $pdo->prepare("SELECT id, qtd_usuarios FROM clientes_tga_web_usuarios WHERE codigo_empresa = ? ORDER BY criado_em ASC LIMIT 1");
      $stmtCnt->execute([$codigo]);
      $rowUser = $stmtCnt->fetch();

      if ($rowUser) {
        $qtdAnterior = (int)$rowUser['qtd_usuarios'];
        $sets   = ['atualizado_em = NOW()'];
        $params = [];
        if ($qtdNovaSet) { $sets[] = 'qtd_usuarios = ?'; $params[] = $qtdNova; }
        if ($obsUSet)    { $sets[] = 'observacao = ?';   $params[] = ($obsU !== '' ? $obsU : null); }
        $params[] = $rowUser['id'];
        $pdo->prepare(
          "UPDATE clientes_tga_web_usuarios SET " . implode(', ', $sets) . " WHERE id = ? LIMIT 1"
        )->execute($params);
        if ($qtdNovaSet && $qtdNova !== $qtdAnterior) {
          logAction($pdo, 'UPDATE', 'Usuários Web', (int)$rowUser['id'],
            ['qtd_usuarios' => $qtdAnterior],
            ['qtd_usuarios' => $qtdNova],
            "Qtd. usuários alterada de {$qtdAnterior} para {$qtdNova} via empresa \"{$nome}\"");
        }
      } else {
        $pdo->prepare("
          INSERT INTO clientes_tga_web_usuarios
            (codigo_empresa, nome_empresa, qtd_usuarios, observacao, status)
          VALUES (?, ?, ?, ?, 'ATIVO')
        ")->execute([$codigo, $nome, $qtdNova ?? 0, ($obsU !== '' ? $obsU : null)]);
      }
    }

    // Processa integrações (checkboxes do modal de edição)
    if (array_key_exists('integracoes', $body)) {
      $integ = $body['integracoes'];

      // ─── WhatsApp ───────────────────────────────────────────
      $stW = $pdo->prepare("SELECT id FROM clientes_web_api_whats WHERE cod_cliente = ? LIMIT 1");
      $stW->execute([$codigo]);
      $existeW = $stW->fetch();

      if (!empty($integ['whatsapp']) && !$existeW) {
        $pdo->prepare("
          INSERT INTO clientes_web_api_whats
            (cod_cliente, cliente, acesso_server, tipo_acesso, criado_em, atualizado_em)
          VALUES (?, ?, '', 'API_WHATS', NOW(), NOW())
        ")->execute([$codigo, $nome]);
        logAction($pdo, 'CREATE', 'WhatsApp', (int)$pdo->lastInsertId(), null,
          ['cod_cliente' => $codigo, 'cliente' => $nome],
          "WhatsApp adicionado via edição de empresa \"{$nome}\"");

      } elseif (empty($integ['whatsapp']) && $existeW) {
        $stWD = $pdo->prepare("SELECT * FROM clientes_web_api_whats WHERE cod_cliente = ?");
        $stWD->execute([$codigo]);
        $rowsW = $stWD->fetchAll(PDO::FETCH_ASSOC);
        $pdo->prepare("DELETE FROM clientes_web_api_whats WHERE cod_cliente = ?")->execute([$codigo]);
        logAction($pdo, 'DELETE', 'WhatsApp', (int)($rowsW[0]['id'] ?? 0), $rowsW, null,
          "WhatsApp removido via edição de empresa \"{$nome}\"");
      }

      // ─── Mobile ON (FV_SMART_CLIENT) ─────────────────────────
      $portaMon = (isset($integ['porta_mobile_on']) && (int)$integ['porta_mobile_on'] > 0)
        ? (int)$integ['porta_mobile_on'] : null;

      $stMon = $pdo->prepare("SELECT id FROM clientes_web_mobile WHERE cod_cliente = ? AND tipo_acesso = 'FV_SMART_CLIENT' LIMIT 1");
      $stMon->execute([$codigo]);
      $existeMon = $stMon->fetch();

      if (!empty($integ['mobile_on']) && !$existeMon) {
        $pdo->prepare("
          INSERT INTO clientes_web_mobile
            (cod_cliente, cliente, acesso_server, porta, tipo_acesso, status, criado_em, atualizado_em)
          VALUES (?, ?, '', ?, 'FV_SMART_CLIENT', 'OFF', NOW(), NOW())
        ")->execute([$codigo, $nome, $portaMon]);
        logAction($pdo, 'CREATE', 'FV / Mobile', (int)$pdo->lastInsertId(), null,
          ['cod_cliente' => $codigo, 'tipo_acesso' => 'FV_SMART_CLIENT', 'porta' => $portaMon],
          "Mobile ON adicionado via edição de empresa \"{$nome}\"");

      } elseif (!empty($integ['mobile_on']) && $existeMon && $portaMon !== null) {
        // Já existe — atualiza a porta se informada
        $pdo->prepare("UPDATE clientes_web_mobile SET porta = ?, atualizado_em = NOW() WHERE cod_cliente = ? AND tipo_acesso = 'FV_SMART_CLIENT'")
          ->execute([$portaMon, $codigo]);

      } elseif (empty($integ['mobile_on']) && $existeMon) {
        $stMonD = $pdo->prepare("SELECT * FROM clientes_web_mobile WHERE cod_cliente = ? AND tipo_acesso = 'FV_SMART_CLIENT'");
        $stMonD->execute([$codigo]);
        $rowsMon = $stMonD->fetchAll(PDO::FETCH_ASSOC);
        $pdo->prepare("DELETE FROM clientes_web_mobile WHERE cod_cliente = ? AND tipo_acesso = 'FV_SMART_CLIENT'")->execute([$codigo]);
        logAction($pdo, 'DELETE', 'FV / Mobile', (int)($rowsMon[0]['id'] ?? 0), $rowsMon, null,
          "Mobile ON removido via edição de empresa \"{$nome}\"");
      }

      // ─── Mobile OFF (API_FORCA_DE_VENDA) ─────────────────────
      $stMoff = $pdo->prepare("SELECT id FROM clientes_web_mobile WHERE cod_cliente = ? AND tipo_acesso = 'API_FORCA_DE_VENDA' LIMIT 1");
      $stMoff->execute([$codigo]);
      $existeMoff = $stMoff->fetch();

      if (!empty($integ['mobile_off']) && !$existeMoff) {
        $pdo->prepare("
          INSERT INTO clientes_web_mobile
            (cod_cliente, cliente, acesso_server, tipo_acesso, status, criado_em, atualizado_em)
          VALUES (?, ?, '', 'API_FORCA_DE_VENDA', 'OFF', NOW(), NOW())
        ")->execute([$codigo, $nome]);
        logAction($pdo, 'CREATE', 'FV / Mobile', (int)$pdo->lastInsertId(), null,
          ['cod_cliente' => $codigo, 'tipo_acesso' => 'API_FORCA_DE_VENDA'],
          "Mobile OFF adicionado via edição de empresa \"{$nome}\"");

      } elseif (empty($integ['mobile_off']) && $existeMoff) {
        $stMoffD = $pdo->prepare("SELECT * FROM clientes_web_mobile WHERE cod_cliente = ? AND tipo_acesso = 'API_FORCA_DE_VENDA'");
        $stMoffD->execute([$codigo]);
        $rowsMoff = $stMoffD->fetchAll(PDO::FETCH_ASSOC);
        $pdo->prepare("DELETE FROM clientes_web_mobile WHERE cod_cliente = ? AND tipo_acesso = 'API_FORCA_DE_VENDA'")->execute([$codigo]);
        logAction($pdo, 'DELETE', 'FV / Mobile', (int)($rowsMoff[0]['id'] ?? 0), $rowsMoff, null,
          "Mobile OFF removido via edição de empresa \"{$nome}\"");
      }

      // ─── PDV OFF ─────────────────────────────────────────────
      $stP = $pdo->prepare("SELECT id FROM clientes_web_pdvoff WHERE cod_cliente = ? LIMIT 1");
      $stP->execute([$codigo]);
      $existeP = $stP->fetch();

      if (!empty($integ['pdvoff']) && !$existeP) {
        $pdo->prepare("
          INSERT INTO clientes_web_pdvoff
            (cod_cliente, cliente, acesso_server, caixas, tipo_acesso, criado_em, atualizado_em)
          VALUES (?, ?, '', 0, 'PDVOFF', NOW(), NOW())
        ")->execute([$codigo, $nome]);
        logAction($pdo, 'CREATE', 'PDV OFF', (int)$pdo->lastInsertId(), null,
          ['cod_cliente' => $codigo],
          "PDV OFF adicionado via edição de empresa \"{$nome}\"");

      } elseif (empty($integ['pdvoff']) && $existeP) {
        $stPD = $pdo->prepare("SELECT * FROM clientes_web_pdvoff WHERE cod_cliente = ?");
        $stPD->execute([$codigo]);
        $rowsP = $stPD->fetchAll(PDO::FETCH_ASSOC);
        $pdo->prepare("DELETE FROM clientes_web_pdvoff WHERE cod_cliente = ?")->execute([$codigo]);
        logAction($pdo, 'DELETE', 'PDV OFF', (int)($rowsP[0]['id'] ?? 0), $rowsP, null,
          "PDV OFF removido via edição de empresa \"{$nome}\"");
      }

      // ─── BI ──────────────────────────────────────────────────
      $stBI = $pdo->prepare("SELECT id FROM clientes_web_bi WHERE cod_cliente = ? LIMIT 1");
      $stBI->execute([$codigo]);
      $existeBI = $stBI->fetch();

      if (!empty($integ['bi']) && !$existeBI) {
        $pdo->prepare("
          INSERT INTO clientes_web_bi
            (cod_cliente, cliente, criado_em, atualizado_em)
          VALUES (?, ?, NOW(), NOW())
        ")->execute([$codigo, $nome]);
        logAction($pdo, 'CREATE', 'BI', (int)$pdo->lastInsertId(), null,
          ['cod_cliente' => $codigo],
          "BI adicionado via edição de empresa \"{$nome}\"");

      } elseif (empty($integ['bi']) && $existeBI) {
        $stBID = $pdo->prepare("SELECT * FROM clientes_web_bi WHERE cod_cliente = ?");
        $stBID->execute([$codigo]);
        $rowsBI = $stBID->fetchAll(PDO::FETCH_ASSOC);
        $pdo->prepare("DELETE FROM clientes_web_bi WHERE cod_cliente = ?")->execute([$codigo]);
        logAction($pdo, 'DELETE', 'BI', (int)($rowsBI[0]['id'] ?? 0), $rowsBI, null,
          "BI removido via edição de empresa \"{$nome}\"");
      }
    }

    echo json_encode(['success' => true]);
    exit;
  }

  /* =========================================================
     DELETE CHECK — verifica dados associados antes de excluir
  ========================================================= */
  if ($action === 'delete_check') {

    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido');

    $stmtRow = $pdo->prepare("SELECT id, codigo_cliente, nome_cliente FROM clientes_web_login WHERE id = ? LIMIT 1");
    $stmtRow->execute([$id]);
    $row = $stmtRow->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new Exception('Empresa não encontrada');

    $cod = $row['codigo_cliente'];

    $stmtChk = $pdo->prepare("
      SELECT
        (SELECT COUNT(*) FROM clientes_tga_web_usuarios WHERE codigo_empresa = ?) AS qtd_usuarios,
        EXISTS(SELECT 1 FROM clientes_web_api_whats WHERE cod_cliente = ?)                                      AS tem_whatsapp,
        EXISTS(SELECT 1 FROM clientes_web_mobile    WHERE cod_cliente = ? AND tipo_acesso = 'FV_SMART_CLIENT')  AS tem_mobile_on,
        EXISTS(SELECT 1 FROM clientes_web_mobile    WHERE cod_cliente = ? AND tipo_acesso = 'API_FORCA_DE_VENDA') AS tem_mobile_off,
        EXISTS(SELECT 1 FROM clientes_web_pdvoff    WHERE cod_cliente = ?)                                      AS tem_pdvoff,
        EXISTS(SELECT 1 FROM clientes_web_bi        WHERE cod_cliente = ?)                                      AS tem_bi
    ");
    $stmtChk->execute([$cod, $cod, $cod, $cod, $cod, $cod]);
    $chk = $stmtChk->fetch(PDO::FETCH_ASSOC);

    $qtdUsuarios  = (int)$chk['qtd_usuarios'];
    $temWhatsapp  = (bool)(int)$chk['tem_whatsapp'];
    $temMobileOn  = (bool)(int)$chk['tem_mobile_on'];
    $temMobileOff = (bool)(int)$chk['tem_mobile_off'];
    $temPdvoff    = (bool)(int)$chk['tem_pdvoff'];
    $temBi        = (bool)(int)$chk['tem_bi'];

    $hasChildren = $qtdUsuarios > 0 || $temWhatsapp || $temMobileOn || $temMobileOff || $temPdvoff || $temBi;

    echo json_encode([
      'success'      => true,
      'nome'         => $row['nome_cliente'],
      'codigo'       => $cod,
      'has_children' => $hasChildren,
      'qtd_usuarios' => $qtdUsuarios,
      'whatsapp'     => $temWhatsapp,
      'mobile_on'    => $temMobileOn,
      'mobile_off'   => $temMobileOff,
      'pdvoff'       => $temPdvoff,
      'bi'           => $temBi
    ]);
    exit;
  }

  /* =========================================================
     DELETE
  ========================================================= */
  if ($action === 'delete') {

    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido');

    $deleteChildren = !empty($body['delete_children']);

    $stmtAntes = $pdo->prepare("SELECT codigo_cliente,nome_cliente,cnpj,caminho_acesso,versao_padrao,regiao,status FROM clientes_web_login WHERE id = ? LIMIT 1");
    $stmtAntes->execute([$id]);
    $dadosAntes  = $stmtAntes->fetch(PDO::FETCH_ASSOC) ?: null;
    $nomeEmpresa = $dadosAntes['nome_cliente'] ?? "ID {$id}";
    $codigo      = $dadosAntes['codigo_cliente'] ?? '';

    if ($deleteChildren && $codigo !== '') {

      // Usuários — log individual por registro para preservar qtd_usuarios no JSON
      $stmtU = $pdo->prepare("SELECT * FROM clientes_tga_web_usuarios WHERE codigo_empresa = ?");
      $stmtU->execute([$codigo]);
      $rowsU = $stmtU->fetchAll(PDO::FETCH_ASSOC);
      if ($rowsU) {
        $pdo->prepare("DELETE FROM clientes_tga_web_usuarios WHERE codigo_empresa = ?")->execute([$codigo]);
        foreach ($rowsU as $uRow) {
          logAction($pdo, 'DELETE', 'Usuários Web', (int)($uRow['id'] ?? 0), $uRow, null,
            "Usuário web excluído junto com empresa \"{$nomeEmpresa}\"");
        }
      }

      // WhatsApp
      $stmtW = $pdo->prepare("SELECT * FROM clientes_web_api_whats WHERE cod_cliente = ?");
      $stmtW->execute([$codigo]);
      $rowsW = $stmtW->fetchAll(PDO::FETCH_ASSOC);
      if ($rowsW) {
        $pdo->prepare("DELETE FROM clientes_web_api_whats WHERE cod_cliente = ?")->execute([$codigo]);
        logAction($pdo, 'DELETE', 'WhatsApp', (int)($rowsW[0]['id'] ?? 0), $rowsW, null,
          "WhatsApp excluído junto com empresa \"{$nomeEmpresa}\"");
      }

      // Mobile (ON + OFF)
      $stmtM = $pdo->prepare("SELECT * FROM clientes_web_mobile WHERE cod_cliente = ?");
      $stmtM->execute([$codigo]);
      $rowsM = $stmtM->fetchAll(PDO::FETCH_ASSOC);
      if ($rowsM) {
        $pdo->prepare("DELETE FROM clientes_web_mobile WHERE cod_cliente = ?")->execute([$codigo]);
        logAction($pdo, 'DELETE', 'FV / Mobile', (int)($rowsM[0]['id'] ?? 0), $rowsM, null,
          "Mobile excluído junto com empresa \"{$nomeEmpresa}\"");
      }

      // PDV OFF
      $stmtP = $pdo->prepare("SELECT * FROM clientes_web_pdvoff WHERE cod_cliente = ?");
      $stmtP->execute([$codigo]);
      $rowsP = $stmtP->fetchAll(PDO::FETCH_ASSOC);
      if ($rowsP) {
        $pdo->prepare("DELETE FROM clientes_web_pdvoff WHERE cod_cliente = ?")->execute([$codigo]);
        logAction($pdo, 'DELETE', 'PDV OFF', (int)($rowsP[0]['id'] ?? 0), $rowsP, null,
          "PDV OFF excluído junto com empresa \"{$nomeEmpresa}\"");
      }

      // BI
      $stmtB = $pdo->prepare("SELECT * FROM clientes_web_bi WHERE cod_cliente = ?");
      $stmtB->execute([$codigo]);
      $rowsB = $stmtB->fetchAll(PDO::FETCH_ASSOC);
      if ($rowsB) {
        $pdo->prepare("DELETE FROM clientes_web_bi WHERE cod_cliente = ?")->execute([$codigo]);
        logAction($pdo, 'DELETE', 'BI', (int)($rowsB[0]['id'] ?? 0), $rowsB, null,
          "BI excluído junto com empresa \"{$nomeEmpresa}\"");
      }
    }

    $stmt = $pdo->prepare("DELETE FROM clientes_web_login WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);

    logAction($pdo, 'DELETE', 'Empresas TGA Web', $id, $dadosAntes, null, "Excluiu empresa \"{$nomeEmpresa}\"");

    echo json_encode(['success' => true]);
    exit;
  }

  /* =========================================================
     TOGGLE STATUS USUÁRIO VINCULADO
  ========================================================= */
  if ($action === 'toggle_usuario_status') {
    $userId = (int)($body['id'] ?? 0);
    if ($userId <= 0) throw new Exception('ID inválido');

    $stmt = $pdo->prepare("SELECT id, status, qtd_usuarios FROM clientes_tga_web_usuarios WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) throw new Exception('Usuário não encontrado');

    $novoStatus = $u['status'] === 'ATIVO' ? 'INATIVO' : 'ATIVO';
    $qtdU       = (int)$u['qtd_usuarios'];
    $pdo->prepare("UPDATE clientes_tga_web_usuarios SET status = ?, atualizado_em = NOW() WHERE id = ? LIMIT 1")
        ->execute([$novoStatus, $userId]);

    logAction($pdo, 'UPDATE', 'Usuários Web', $userId,
      ['status' => $u['status'], 'qtd_usuarios' => $qtdU],
      ['status' => $novoStatus,  'qtd_usuarios' => $qtdU],
      "Status do usuário alterado para {$novoStatus}");

    echo json_encode(['success' => true, 'novo_status' => $novoStatus]);
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
