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

try {

  if ($action !== 'summary') {
    throw new Exception('Ação inválida');
  }

  /* =====================================================
     KPIs — LOGINS WEB
  ===================================================== */
  $logins_total = (int)$pdo->query("
    SELECT COUNT(*) c FROM clientes_web_login
  ")->fetch()['c'];

  $logins_ativos = (int)$pdo->query("
    SELECT COUNT(*) c
    FROM clientes_web_login
    WHERE UPPER(status) = 'ATIVO'
  ")->fetch()['c'];

  /* =====================================================
     KPIs — MOBILE
  ===================================================== */
  $fv_total = (int)$pdo->query("
    SELECT COUNT(*) c
    FROM clientes_web_mobile
    WHERE tipo_acesso = 'FV_SMART_CLIENT'
  ")->fetch()['c'];

  $api_total = (int)$pdo->query("
    SELECT COUNT(*) c
    FROM clientes_web_mobile
    WHERE tipo_acesso = 'API_FORCA_DE_VENDA'
  ")->fetch()['c'];

  /* =====================================================
     KPIs — WHATSAPP
  ===================================================== */
  $whatsapp_total = (int)$pdo->query("
    SELECT COUNT(*) c
    FROM clientes_web_api_whats
  ")->fetch()['c'];

  /* =====================================================
     KPIs — PDV OFF
  ===================================================== */
  $pdvoff_total = (int)$pdo->query("
    SELECT COUNT(*) c
    FROM clientes_web_pdvoff
  ")->fetch()['c'];

  /* =====================================================
     ÚLTIMOS 3 — LOGINS
  ===================================================== */
  $last_logins = $pdo->query("
    SELECT
      id,
      codigo_cliente,
      nome_cliente,
      caminho_acesso,
      versao_padrao,
      status,
      DATE_FORMAT(atualizado_em, '%Y-%m-%d %H:%i:%s') AS atualizado_em
    FROM clientes_web_login
    ORDER BY atualizado_em DESC
    LIMIT 3
  ")->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     ÚLTIMOS 3 — MOBILE
  ===================================================== */
  $last_mobile = $pdo->query("
    SELECT
      id,
      cod_cliente,
      cliente,
      acesso_server,
      tipo_acesso,
      observacao,
      DATE_FORMAT(atualizado_em, '%Y-%m-%d %H:%i:%s') AS atualizado_em
    FROM clientes_web_mobile
    ORDER BY atualizado_em DESC
    LIMIT 3
  ")->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   TOP 3 - WHATSAPP
===================================================== */
$last_whatsapp = $pdo->query("
  SELECT
    id,
    cod_cliente,
    cliente,
    acesso_server,
    tipo_acesso,
    observacao,
    DATE_FORMAT(atualizado_em, '%Y-%m-%d %H:%i:%s') AS atualizado_em
  FROM clientes_web_api_whats
  ORDER BY atualizado_em DESC
  LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);


  /* =====================================================
   TOP 3 - PDV OFF
===================================================== */
$last_pdvoff = $pdo->query("
  SELECT
    id,
    cod_cliente,
    cliente,
    acesso_server,
    tipo_acesso,
    observacao,
    DATE_FORMAT(atualizado_em, '%Y-%m-%d %H:%i:%s') AS atualizado_em
  FROM clientes_web_pdvoff
  ORDER BY atualizado_em DESC
  LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);


  /* =====================================================
     RESPONSE FINAL
  ===================================================== */
  echo json_encode([
    'success' => true,
    'kpis' => [
      'logins_total'   => $logins_total,
      'logins_ativos'  => $logins_ativos,
      'fv_total'       => $fv_total,
      'api_total'      => $api_total,
      'whatsapp_total' => $whatsapp_total,
      'pdvoff_total'   => $pdvoff_total
    ],
    'last_logins'   => $last_logins,
    'last_mobile'   => $last_mobile,
    'last_whatsapp' => $last_whatsapp,
    'last_pdvoff'   => $last_pdvoff
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
