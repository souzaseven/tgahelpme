<?php
require_once __DIR__ . '/conexao.php';

header('Content-Type: application/json; charset=utf-8');

/* =====================================================
   VALIDAÇÕES BÁSICAS
===================================================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode([
    'success' => false,
    'message' => 'Método não permitido'
  ]);
  exit;
}

if (!validateCSRF()) {
  http_response_code(403);
  echo json_encode([
    'success' => false,
    'message' => 'CSRF inválido'
  ]);
  exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $body['action'] ?? '';

try {

  /* =====================================================
     CHART CADASTROS — filtros dinâmicos
  ===================================================== */
  if ($action === 'chart_cadastros') {

    $periodo     = $body['periodo']     ?? '12m';
    $agrupamento = $body['agrupamento'] ?? 'mes';
    $tipo        = $body['tipo']        ?? 'cadastros';

    $allowedPeriodos     = ['7d','30d','3m','6m','12m','24m','all'];
    $allowedAgrupamentos = ['dia','mes','ano'];
    $allowedTipos        = ['cadastros','alteracoes','ambos'];

    if (!in_array($periodo,     $allowedPeriodos,     true)) $periodo     = '12m';
    if (!in_array($agrupamento, $allowedAgrupamentos, true)) $agrupamento = 'mes';
    if (!in_array($tipo,        $allowedTipos,        true)) $tipo        = 'cadastros';

    // Intervalo de datas genérico (sem campo fixo)
    $intervalSql = match($periodo) {
      '7d'  => "DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
      '30d' => "DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
      '3m'  => "DATE_SUB(CURDATE(), INTERVAL 3 MONTH)",
      '6m'  => "DATE_SUB(CURDATE(), INTERVAL 6 MONTH)",
      '12m' => "DATE_SUB(CURDATE(), INTERVAL 12 MONTH)",
      '24m' => "DATE_SUB(CURDATE(), INTERVAL 24 MONTH)",
      'all' => null,
      default => "DATE_SUB(CURDATE(), INTERVAL 12 MONTH)"
    };

    // Expressões de agrupamento/label por campo de data
    $buildExprs = function(string $campo) use ($agrupamento): array {
      if ($agrupamento === 'dia') {
        return [
          "DATE_FORMAT({$campo}, '%Y-%m-%d')",
          "DATE_FORMAT({$campo}, '%d/%m/%Y')"
        ];
      } elseif ($agrupamento === 'ano') {
        return [
          "DATE_FORMAT({$campo}, '%Y')",
          "DATE_FORMAT({$campo}, '%Y')"
        ];
      }
      return [
        "DATE_FORMAT({$campo}, '%Y-%m')",
        "DATE_FORMAT({$campo}, '%b/%Y')"
      ];
    };

    // Monta SELECT para um campo de data específico
    $buildQuery = function(string $campo) use ($buildExprs, $intervalSql): array {
      [$groupExpr, $labelExpr] = $buildExprs($campo);
      $where = $intervalSql
        ? "{$campo} IS NOT NULL AND {$campo} >= {$intervalSql}"
        : "{$campo} IS NOT NULL";

      // Não há parâmetros variáveis — $intervalSql é gerado internamente
      // phpcs:ignore
      global $pdo;
      $stmt = $pdo->query("
        SELECT
          {$groupExpr} AS periodo,
          {$labelExpr} AS label,
          COUNT(*)      AS total
        FROM clientes_web_login
        WHERE {$where}
        GROUP BY periodo, label
        ORDER BY periodo ASC
      ");
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    if ($tipo === 'ambos') {
      $rowsCad = $buildQuery('criado_em');
      $rowsAlt = $buildQuery('atualizado_em');

      // Merge em mapa ordenado por período
      $mapa = [];
      foreach ($rowsCad as $r) {
        $mapa[$r['periodo']] = ['label' => $r['label'], 'cadastros' => (int)$r['total'], 'alteracoes' => 0];
      }
      foreach ($rowsAlt as $r) {
        if (!isset($mapa[$r['periodo']])) {
          $mapa[$r['periodo']] = ['label' => $r['label'], 'cadastros' => 0, 'alteracoes' => 0];
        }
        $mapa[$r['periodo']]['alteracoes'] = (int)$r['total'];
      }
      ksort($mapa);

      echo json_encode(['success' => true, 'tipo' => 'ambos', 'data' => array_values($mapa)]);
      exit;
    }

    $campo = $tipo === 'alteracoes' ? 'atualizado_em' : 'criado_em';
    $rows  = $buildQuery($campo);

    echo json_encode(['success' => true, 'tipo' => $tipo, 'data' => $rows]);
    exit;
  }

  /* =====================================================
     CHART INTEGRAÇÕES — evolução por mês
  ===================================================== */
  if ($action === 'chart_integracoes') {

    $periodo = $body['periodo'] ?? '12m';
    $allowedPeriodos = ['3m','6m','12m','24m','all'];
    if (!in_array($periodo, $allowedPeriodos, true)) $periodo = '12m';

    $intervalSql = match($periodo) {
      '3m'  => "DATE_SUB(CURDATE(), INTERVAL 3 MONTH)",
      '6m'  => "DATE_SUB(CURDATE(), INTERVAL 6 MONTH)",
      '12m' => "DATE_SUB(CURDATE(), INTERVAL 12 MONTH)",
      '24m' => "DATE_SUB(CURDATE(), INTERVAL 24 MONTH)",
      'all' => null,
      default => "DATE_SUB(CURDATE(), INTERVAL 12 MONTH)"
    };

    $fetchInteg = function(string $table, string $extraWhere = '') use ($intervalSql): array {
      global $pdo;
      $conditions = ["criado_em IS NOT NULL"];
      if ($intervalSql)  $conditions[] = "criado_em >= {$intervalSql}";
      if ($extraWhere)   $conditions[] = $extraWhere;
      $where = 'WHERE ' . implode(' AND ', $conditions);
      $stmt = $pdo->query("
        SELECT
          DATE_FORMAT(criado_em, '%Y-%m') AS periodo,
          DATE_FORMAT(criado_em, '%b/%Y') AS label,
          COUNT(*) AS total
        FROM {$table}
        {$where}
        GROUP BY periodo, label
        ORDER BY periodo ASC
      ");
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    $rowsWA   = $fetchInteg('clientes_web_api_whats');
    $rowsMon  = $fetchInteg('clientes_web_mobile', "tipo_acesso = 'FV_SMART_CLIENT'");
    $rowsMoff = $fetchInteg('clientes_web_mobile', "tipo_acesso = 'API_FORCA_DE_VENDA'");
    $rowsPdv  = $fetchInteg('clientes_web_pdvoff');

    $mapa = [];
    $addRows = function(array $rows, string $key) use (&$mapa) {
      foreach ($rows as $r) {
        if (!isset($mapa[$r['periodo']])) {
          $mapa[$r['periodo']] = [
            'label'      => $r['label'],
            'whatsapp'   => 0,
            'mobile_on'  => 0,
            'mobile_off' => 0,
            'pdvoff'     => 0,
            'bi'         => 0
          ];
        }
        $mapa[$r['periodo']][$key] = (int)$r['total'];
      }
    };

    $rowsBI   = $fetchInteg('clientes_web_bi');

    $addRows($rowsWA,   'whatsapp');
    $addRows($rowsMon,  'mobile_on');
    $addRows($rowsMoff, 'mobile_off');
    $addRows($rowsPdv,  'pdvoff');
    $addRows($rowsBI,   'bi');
    ksort($mapa);

    echo json_encode(['success' => true, 'data' => array_values($mapa)]);
    exit;
  }

  /* =====================================================
     CHART USUÁRIOS — adicionados e removidos por mês
     Lógica:
       CREATE  → dados_depois.qtd_usuarios          → adicionados
       UPDATE↑ → dados_depois.qtd - dados_antes.qtd → adicionados
       UPDATE↓ → dados_antes.qtd - dados_depois.qtd → removidos
       DELETE  → dados_antes.qtd_usuarios           → removidos
  ===================================================== */
  if ($action === 'chart_usuarios') {

    $periodo = $body['periodo'] ?? '12m';
    $allowedPeriodos = ['3m','6m','12m','24m','all'];
    if (!in_array($periodo, $allowedPeriodos, true)) $periodo = '12m';

    $intervalSql = match($periodo) {
      '3m'  => "DATE_SUB(CURDATE(), INTERVAL 3 MONTH)",
      '6m'  => "DATE_SUB(CURDATE(), INTERVAL 6 MONTH)",
      '12m' => "DATE_SUB(CURDATE(), INTERVAL 12 MONTH)",
      '24m' => "DATE_SUB(CURDATE(), INTERVAL 24 MONTH)",
      'all' => null,
      default => "DATE_SUB(CURDATE(), INTERVAL 12 MONTH)"
    };

    $whereInterval = $intervalSql ? "AND criado_em >= {$intervalSql}" : '';

    // Extrai qtd_usuarios de um campo JSON como SIGNED (seguro contra nulos/vazio)
    $jq = fn(string $col) =>
      "CAST(IFNULL(NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$col}, '$.qtd_usuarios')), ''), '0') AS SIGNED)";

    $dd = $jq('dados_depois');
    $da = $jq('dados_antes');

    $jsa = "IFNULL(JSON_UNQUOTE(JSON_EXTRACT(dados_antes, '$.status')), '')";
    $jsd = "IFNULL(JSON_UNQUOTE(JSON_EXTRACT(dados_depois, '$.status')), '')";

    $stmt = $pdo->query("
      SELECT
        DATE_FORMAT(criado_em, '%Y-%m') AS periodo,
        DATE_FORMAT(criado_em, '%b/%Y') AS label,
        SUM(
          CASE
            WHEN acao = 'CREATE' THEN {$dd}
            WHEN acao = 'UPDATE' THEN
              CASE
                WHEN {$jsa} = 'INATIVO' AND {$jsd} = 'ATIVO' THEN {$dd}
                ELSE GREATEST(0, {$dd} - {$da})
              END
            ELSE 0
          END
        ) AS adicionados,
        SUM(
          CASE
            WHEN acao = 'DELETE' THEN {$da}
            WHEN acao = 'UPDATE' THEN
              CASE
                WHEN {$jsa} = 'ATIVO' AND {$jsd} = 'INATIVO' THEN {$da}
                ELSE GREATEST(0, {$da} - {$dd})
              END
            ELSE 0
          END
        ) AS removidos
      FROM auditoria
      WHERE modulo = 'Usuários Web'
        AND (dados_antes IS NOT NULL OR dados_depois IS NOT NULL)
        {$whereInterval}
      GROUP BY periodo, label
      ORDER BY periodo ASC
    ");

    $data = array_map(fn($r) => [
      'label'       => $r['label'],
      'adicionados' => max(0, (int)$r['adicionados']),
      'removidos'   => max(0, (int)$r['removidos']),
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
  }

  /* =====================================================
     CHART — ATIVIDADE POR DIA DA SEMANA
  ===================================================== */
  if ($action === 'chart_atividade_semana') {

    $periodo = $body['periodo'] ?? '12m';
    $allowedPeriodos = ['30d','3m','6m','12m','24m','all'];
    if (!in_array($periodo, $allowedPeriodos, true)) $periodo = '12m';

    $intervalSql = match($periodo) {
      '30d' => "DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
      '3m'  => "DATE_SUB(CURDATE(), INTERVAL 3 MONTH)",
      '6m'  => "DATE_SUB(CURDATE(), INTERVAL 6 MONTH)",
      '12m' => "DATE_SUB(CURDATE(), INTERVAL 12 MONTH)",
      '24m' => "DATE_SUB(CURDATE(), INTERVAL 24 MONTH)",
      'all' => null,
      default => "DATE_SUB(CURDATE(), INTERVAL 12 MONTH)"
    };

    $where = $intervalSql ? "WHERE criado_em >= {$intervalSql}" : '';

    $stmt = $pdo->query("
      SELECT
        DAYOFWEEK(criado_em)      AS dia_num,
        SUM(acao = 'CREATE')      AS cadastros,
        SUM(acao = 'UPDATE')      AS alteracoes
      FROM auditoria
      {$where}
      GROUP BY dia_num
      ORDER BY dia_num ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Monta mapa por número do dia (1=Dom … 7=Sáb no MySQL)
    $mapa = [];
    foreach ($rows as $r) {
      $mapa[(int)$r['dia_num']] = $r;
    }

    // Ordena Seg→Dom (2,3,4,5,6,7,1) com nomes em PT-BR
    $ordem = [2,3,4,5,6,7,1];
    $nomes = [2=>'Seg', 3=>'Ter', 4=>'Qua', 5=>'Qui', 6=>'Sex', 7=>'Sáb', 1=>'Dom'];

    $data = [];
    foreach ($ordem as $d) {
      $data[] = [
        'label'      => $nomes[$d],
        'cadastros'  => (int)($mapa[$d]['cadastros']  ?? 0),
        'alteracoes' => (int)($mapa[$d]['alteracoes'] ?? 0),
      ];
    }

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
  }

  /* =====================================================
     CHART — CALENDÁRIO HEATMAP (por dia)
  ===================================================== */
  if ($action === 'chart_calendario') {

    $periodo = $body['periodo'] ?? '30d';
    $allowed = ['30d','3m','6m','12m','all','mes'];
    if (!in_array($periodo, $allowed, true)) $periodo = '30d';

    if ($periodo === 'mes') {
      $mes = $body['mes'] ?? date('Y-m');
      if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');
      $stmt = $pdo->prepare("
        SELECT
          DATE_FORMAT(criado_em, '%Y-%m-%d') AS dia,
          SUM(acao = 'CREATE')               AS cadastros,
          SUM(acao = 'UPDATE')               AS alteracoes
        FROM auditoria
        WHERE DATE_FORMAT(criado_em, '%Y-%m') = ?
        GROUP BY dia
        ORDER BY dia ASC
      ");
      $stmt->execute([$mes]);
    } else {
      $intervalSql = match($periodo) {
        '30d' => "DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
        '3m'  => "DATE_SUB(CURDATE(), INTERVAL 3 MONTH)",
        '6m'  => "DATE_SUB(CURDATE(), INTERVAL 6 MONTH)",
        '12m' => "DATE_SUB(CURDATE(), INTERVAL 12 MONTH)",
        'all' => null,
        default => "DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
      };
      $where = $intervalSql ? "WHERE criado_em >= {$intervalSql}" : '';
      $stmt = $pdo->query("
        SELECT
          DATE_FORMAT(criado_em, '%Y-%m-%d') AS dia,
          SUM(acao = 'CREATE')               AS cadastros,
          SUM(acao = 'UPDATE')               AS alteracoes
        FROM auditoria
        {$where}
        GROUP BY dia
        ORDER BY dia ASC
      ");
    }

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
  }

  if ($action !== 'summary') {
    throw new Exception('Ação inválida');
  }

/* =====================================================
   KPIs — LOGINS WEB
===================================================== */
$logins_total = (int)$pdo->query("
  SELECT COUNT(*) 
  FROM clientes_web_login
")->fetchColumn();

$logins_ativos = (int)$pdo->query("
  SELECT COUNT(*)
  FROM clientes_web_login
  WHERE UPPER(status) = 'ATIVO'
")->fetchColumn();

/* 🔴 NOVO — LOGINS INATIVOS */
$logins_inativos = (int)$pdo->query("
  SELECT COUNT(*)
  FROM clientes_web_login
  WHERE UPPER(status) = 'INATIVO'
")->fetchColumn();

$logins_com_exe = (int)$pdo->query("
  SELECT COUNT(*)
  FROM clientes_web_login
  WHERE possui_exe = 1
")->fetchColumn();

/* Empresas ATIVAS sem nenhuma das 5 integrações — oportunidade de ativação */
$logins_sem_integracao = (int)$pdo->query("
  SELECT COUNT(*)
  FROM clientes_web_login l
  WHERE UPPER(l.status) = 'ATIVO'
    AND NOT EXISTS(SELECT 1 FROM clientes_web_mobile    m WHERE m.cod_cliente = l.codigo_cliente LIMIT 1)
    AND NOT EXISTS(SELECT 1 FROM clientes_web_api_whats w WHERE w.cod_cliente = l.codigo_cliente LIMIT 1)
    AND NOT EXISTS(SELECT 1 FROM clientes_web_pdvoff    p WHERE p.cod_cliente = l.codigo_cliente LIMIT 1)
    AND NOT EXISTS(SELECT 1 FROM clientes_web_bi        b WHERE b.cod_cliente = l.codigo_cliente LIMIT 1)
")->fetchColumn();

  /* =====================================================
     KPIs — USUÁRIOS WEB (OFICIAL)
     🔒 Fonte única da verdade
  ===================================================== */

  /* Cadastros (empresas) */
  $usuarios_web_cadastros = (int)$pdo->query("
    SELECT COUNT(*)
    FROM clientes_tga_web_usuarios
  ")->fetchColumn();

  /* Usuários ATIVOS (soma real) */
  $usuarios_web_ativos = (int)$pdo->query("
    SELECT COALESCE(SUM(qtd_usuarios), 0)
    FROM clientes_tga_web_usuarios
    WHERE UPPER(status) = 'ATIVO'
  ")->fetchColumn();

  /* Usuários INATIVOS (soma real) */
  $usuarios_web_inativos = (int)$pdo->query("
    SELECT COALESCE(SUM(qtd_usuarios), 0)
    FROM clientes_tga_web_usuarios
    WHERE UPPER(status) = 'INATIVO'
  ")->fetchColumn();

  /* Total de usuários (ativos + inativos) */
  $usuarios_web_total = $usuarios_web_ativos + $usuarios_web_inativos;

  /* =====================================================
     KPIs — MOBILE
  ===================================================== */
  $fv_total = (int)$pdo->query("
    SELECT COUNT(*)
    FROM clientes_web_mobile
    WHERE tipo_acesso = 'FV_SMART_CLIENT'
  ")->fetchColumn();

  $api_total = (int)$pdo->query("
    SELECT COUNT(*)
    FROM clientes_web_mobile
    WHERE tipo_acesso = 'API_FORCA_DE_VENDA'
  ")->fetchColumn();

  /* =====================================================
     KPIs — WHATSAPP
  ===================================================== */
  $whatsapp_total = (int)$pdo->query("
    SELECT COUNT(*)
    FROM clientes_web_api_whats
  ")->fetchColumn();

  /* =====================================================
     KPIs — PDV OFF
  ===================================================== */
  $pdvoff_total = (int)$pdo->query("
    SELECT COUNT(*)
    FROM clientes_web_pdvoff
  ")->fetchColumn();

  /* =====================================================
     KPIs — BI
  ===================================================== */
  $bi_total = (int)$pdo->query("
    SELECT COUNT(*)
    FROM clientes_web_bi
  ")->fetchColumn();

  /* =====================================================
     RESUMO POR VERSÃO — LOGINS WEB (com status)
  ===================================================== */
  $versoes_stmt = $pdo->query("
    SELECT
      COALESCE(NULLIF(TRIM(versao_padrao), ''), 'Sem versão') AS versao,
      UPPER(TRIM(status)) AS status,
      COUNT(*) AS total
    FROM clientes_web_login
    GROUP BY versao, status
    ORDER BY versao, status
  ");
  $versoes_resumo = $versoes_stmt->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     RESUMO POR REGIÃO — LOGINS WEB (com status)
  ===================================================== */
  $regioes_stmt = $pdo->query("
    SELECT
      COALESCE(NULLIF(TRIM(regiao), ''), 'Sem região') AS regiao,
      UPPER(TRIM(status)) AS status,
      COUNT(*) AS total
    FROM clientes_web_login
    GROUP BY regiao, status
    ORDER BY regiao, status
  ");
  $regioes_resumo = $regioes_stmt->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     RESUMO POR CIDADE — LOGINS WEB (com status)
  ===================================================== */
  $cidades_stmt = $pdo->query("
    SELECT
      COALESCE(NULLIF(TRIM(cidade), ''), 'Sem cidade') AS cidade,
      COALESCE(NULLIF(TRIM(estado), ''), '') AS estado,
      UPPER(TRIM(status)) AS status,
      COUNT(*) AS total
    FROM clientes_web_login
    GROUP BY cidade, estado, status
    ORDER BY cidade, estado, status
  ");
  $cidades_resumo = $cidades_stmt->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     RESUMO POR ESTADO — LOGINS WEB (com status)
  ===================================================== */
  $estados_stmt = $pdo->query("
    SELECT
      COALESCE(NULLIF(TRIM(estado), ''), 'Sem estado') AS estado,
      UPPER(TRIM(status)) AS status,
      COUNT(*) AS total
    FROM clientes_web_login
    GROUP BY estado, status
    ORDER BY estado, status
  ");
  $estados_resumo = $estados_stmt->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     ÚLTIMOS 3 — LOGINS WEB
  ===================================================== */
  $last_logins = $pdo->query("
    SELECT
      l.codigo_cliente,
      l.nome_cliente,
      COALESCE(l.cnpj, '') AS cnpj,
      l.versao_padrao,
      l.status,
      DATE_FORMAT(l.criado_em, '%d/%m/%Y, %H:%i:%s') AS atualizado_em,
      COALESCE((
        SELECT SUM(u.qtd_usuarios)
        FROM clientes_tga_web_usuarios u
        WHERE u.codigo_empresa = l.codigo_cliente
          AND UPPER(u.status) = 'ATIVO'
      ), 0) AS qtd_usuarios,
      EXISTS(
        SELECT 1 FROM clientes_web_mobile m
        WHERE m.cod_cliente = l.codigo_cliente
      ) AS tem_mobile,
      EXISTS(
        SELECT 1 FROM clientes_web_api_whats w
        WHERE w.cod_cliente = l.codigo_cliente
      ) AS tem_whatsapp,
      EXISTS(
        SELECT 1 FROM clientes_web_pdvoff p
        WHERE p.cod_cliente = l.codigo_cliente
      ) AS tem_pdvoff,
      EXISTS(
        SELECT 1 FROM clientes_web_bi b
        WHERE b.cod_cliente = l.codigo_cliente
      ) AS tem_bi
    FROM clientes_web_login l
    ORDER BY l.criado_em DESC
    LIMIT 3
  ")->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     ÚLTIMOS 3 — USUÁRIOS WEB
  ===================================================== */
  $last_usuarios_web = $pdo->query("
    SELECT
      u.id,
      u.nome_empresa,
      u.codigo_empresa,
      u.qtd_usuarios,
      u.observacao,
      u.status,
      DATE_FORMAT(u.criado_em, '%d/%m/%Y, %H:%i:%s') AS atualizado_em,
      COALESCE(l.cnpj, '') AS empresa_cnpj
    FROM clientes_tga_web_usuarios u
    LEFT JOIN clientes_web_login l ON l.codigo_cliente = u.codigo_empresa
    ORDER BY u.criado_em DESC
    LIMIT 3
  ")->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     ÚLTIMOS 3 — MOBILE
  ===================================================== */
  $last_mobile = $pdo->query("
    SELECT
      m.cod_cliente,
      m.cliente,
      COALESCE(l.cnpj, '') AS empresa_cnpj,
      m.acesso_server,
      m.tipo_acesso,
      DATE_FORMAT(m.criado_em, '%d/%m/%Y, %H:%i:%s') AS atualizado_em
    FROM clientes_web_mobile m
    LEFT JOIN clientes_web_login l ON l.codigo_cliente = m.cod_cliente
    ORDER BY m.criado_em DESC
    LIMIT 3
  ")->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     ÚLTIMOS 3 — WHATSAPP
  ===================================================== */
  $last_whatsapp = $pdo->query("
    SELECT
      w.cod_cliente,
      w.cliente,
      COALESCE(l.cnpj, '') AS empresa_cnpj,
      w.acesso_server,
      DATE_FORMAT(w.criado_em, '%d/%m/%Y, %H:%i:%s') AS atualizado_em
    FROM clientes_web_api_whats w
    LEFT JOIN clientes_web_login l ON l.codigo_cliente = w.cod_cliente
    ORDER BY w.criado_em DESC
    LIMIT 3
  ")->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     ÚLTIMOS 3 — PDV OFF
  ===================================================== */
  $last_pdvoff = $pdo->query("
    SELECT
      p.cod_cliente,
      p.cliente,
      COALESCE(l.cnpj, '') AS empresa_cnpj,
      p.acesso_server,
      DATE_FORMAT(p.criado_em, '%d/%m/%Y, %H:%i:%s') AS atualizado_em
    FROM clientes_web_pdvoff p
    LEFT JOIN clientes_web_login l ON l.codigo_cliente = p.cod_cliente
    ORDER BY p.criado_em DESC
    LIMIT 3
  ")->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     ÚLTIMOS 3 — BI
  ===================================================== */
  $last_bi = $pdo->query("
    SELECT
      b.cod_cliente,
      b.cliente,
      COALESCE(l.cnpj, '') AS empresa_cnpj,
      DATE_FORMAT(b.criado_em, '%d/%m/%Y, %H:%i:%s') AS atualizado_em
    FROM clientes_web_bi b
    LEFT JOIN clientes_web_login l ON l.codigo_cliente = b.cod_cliente
    ORDER BY b.criado_em DESC
    LIMIT 3
  ")->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     ÚLTIMOS 3 ATUALIZADOS — LOGINS WEB
  ===================================================== */
  $last_updated_logins = $pdo->query("
    SELECT
      l.codigo_cliente,
      l.nome_cliente,
      l.status,
      DATE_FORMAT(l.atualizado_em, '%d/%m/%Y, %H:%i') AS atualizado_em
    FROM clientes_web_login l
    WHERE l.atualizado_em IS NOT NULL
    ORDER BY l.atualizado_em DESC
    LIMIT 3
  ")->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     ÚLTIMOS 3 ATUALIZADOS — USUÁRIOS WEB
  ===================================================== */
  $last_updated_usuarios = $pdo->query("
    SELECT
      u.nome_empresa,
      u.codigo_empresa,
      u.qtd_usuarios,
      u.status,
      DATE_FORMAT(u.atualizado_em, '%d/%m/%Y, %H:%i') AS atualizado_em
    FROM clientes_tga_web_usuarios u
    WHERE u.atualizado_em IS NOT NULL
    ORDER BY u.atualizado_em DESC
    LIMIT 3
  ")->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     ÚLTIMOS 3 ATUALIZADOS — MOBILE
  ===================================================== */
  $last_updated_mobile = $pdo->query("
    SELECT
      m.cod_cliente,
      m.cliente,
      m.tipo_acesso,
      DATE_FORMAT(m.atualizado_em, '%d/%m/%Y, %H:%i') AS atualizado_em
    FROM clientes_web_mobile m
    WHERE m.atualizado_em IS NOT NULL
    ORDER BY m.atualizado_em DESC
    LIMIT 3
  ")->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     ÚLTIMOS 3 ATUALIZADOS — WHATSAPP
  ===================================================== */
  $last_updated_whatsapp = $pdo->query("
    SELECT
      w.cod_cliente,
      w.cliente,
      DATE_FORMAT(w.atualizado_em, '%d/%m/%Y, %H:%i') AS atualizado_em
    FROM clientes_web_api_whats w
    WHERE w.atualizado_em IS NOT NULL
    ORDER BY w.atualizado_em DESC
    LIMIT 3
  ")->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     ÚLTIMOS 3 ATUALIZADOS — PDV OFF
  ===================================================== */
  $last_updated_pdvoff = $pdo->query("
    SELECT
      p.cod_cliente,
      p.cliente,
      DATE_FORMAT(p.atualizado_em, '%d/%m/%Y, %H:%i') AS atualizado_em
    FROM clientes_web_pdvoff p
    WHERE p.atualizado_em IS NOT NULL
    ORDER BY p.atualizado_em DESC
    LIMIT 3
  ")->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     ÚLTIMOS 3 ATUALIZADOS — BI
  ===================================================== */
  $last_updated_bi = $pdo->query("
    SELECT
      b.cod_cliente,
      b.cliente,
      DATE_FORMAT(b.atualizado_em, '%d/%m/%Y, %H:%i') AS atualizado_em
    FROM clientes_web_bi b
    WHERE b.atualizado_em IS NOT NULL
    ORDER BY b.atualizado_em DESC
    LIMIT 3
  ")->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     COMPARATIVO MENSAL — novos cadastros este mês vs anterior
  ===================================================== */
  $compMes = function(string $table, string $extra = '') use ($pdo): array {
    $w = $extra ? "AND {$extra}" : '';
    $atual = (int)$pdo->query("
      SELECT COUNT(*) FROM {$table}
      WHERE YEAR(criado_em)=YEAR(NOW())
        AND MONTH(criado_em)=MONTH(NOW()) {$w}
    ")->fetchColumn();
    $ant   = (int)$pdo->query("
      SELECT COUNT(*) FROM {$table}
      WHERE YEAR(criado_em)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH))
        AND MONTH(criado_em)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH)) {$w}
    ")->fetchColumn();
    return ['atual' => $atual, 'anterior' => $ant, 'diff' => $atual - $ant];
  };

  $comparativo = [
    'logins'     => $compMes('clientes_web_login'),
    'mobile_fv'  => $compMes('clientes_web_mobile', "tipo_acesso='FV_SMART_CLIENT'"),
    'mobile_api' => $compMes('clientes_web_mobile', "tipo_acesso='API_FORCA_DE_VENDA'"),
    'whatsapp'   => $compMes('clientes_web_api_whats'),
    'pdvoff'     => $compMes('clientes_web_pdvoff'),
    'bi'         => $compMes('clientes_web_bi'),
    'usuarios'   => $compMes('clientes_tga_web_usuarios'),
  ];

  /* =====================================================
     CHART — CADASTROS POR MÊS (últimos 12 meses)
  ===================================================== */
  $chart_stmt = $pdo->query("
    SELECT
      DATE_FORMAT(criado_em, '%Y-%m')   AS mes,
      DATE_FORMAT(criado_em, '%b/%Y')   AS label,
      COUNT(*)                           AS total
    FROM clientes_web_login
    WHERE criado_em >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY mes, label
    ORDER BY mes ASC
  ");
  $chart_meses = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     RESPONSE FINAL
  ===================================================== */
  echo json_encode([
    'success' => true,
    'kpis' => [
      /* Logins */
      'logins_total'        => $logins_total,
      'logins_ativos'       => $logins_ativos,
'logins_inativos' => $logins_inativos,
      'logins_com_exe'      => $logins_com_exe,
      'logins_sem_integracao' => $logins_sem_integracao,

      /* Usuários Web */
      'usuarios_web_total'      => $usuarios_web_total,
      'usuarios_web_cadastros' => $usuarios_web_cadastros,
      'usuarios_web_ativos'    => $usuarios_web_ativos,
      'usuarios_web_inativos'  => $usuarios_web_inativos,

      /* Outros */
      'fv_total'            => $fv_total,
      'api_total'           => $api_total,
      'whatsapp_total'      => $whatsapp_total,
      'pdvoff_total'        => $pdvoff_total,
      'bi_total'            => $bi_total
    ],
    'versoes_resumo'    => $versoes_resumo,
    'regioes_resumo'    => $regioes_resumo,
    'cidades_resumo'    => $cidades_resumo,
    'estados_resumo'    => $estados_resumo,
    'last_logins'       => $last_logins,
    'last_usuarios_web' => $last_usuarios_web,
    'last_mobile'       => $last_mobile,
    'last_whatsapp'     => $last_whatsapp,
    'last_pdvoff'       => $last_pdvoff,
    'last_bi'           => $last_bi,
    'last_updated_logins'    => $last_updated_logins,
    'last_updated_usuarios'  => $last_updated_usuarios,
    'last_updated_mobile'    => $last_updated_mobile,
    'last_updated_whatsapp'  => $last_updated_whatsapp,
    'last_updated_pdvoff'    => $last_updated_pdvoff,
    'last_updated_bi'        => $last_updated_bi,
    'chart_meses'       => $chart_meses,
    'comparativo'       => $comparativo
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'message' => $e->getMessage()
  ]);
  exit;
}
