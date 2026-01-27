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
     KPIs
  ===================================================== */

  $logins_total = (int)$pdo->query("
    SELECT COUNT(*) FROM clientes_web_login
  ")->fetchColumn();

  $logins_ativos = (int)$pdo->query("
    SELECT COUNT(*) FROM clientes_web_login
    WHERE UPPER(status) = 'ATIVO'
  ")->fetchColumn();

  $fv_total = (int)$pdo->query("
    SELECT COUNT(*) FROM clientes_web_mobile
    WHERE tipo_acesso = 'FV_SMART_CLIENT'
  ")->fetchColumn();

  $api_total = (int)$pdo->query("
    SELECT COUNT(*) FROM clientes_web_mobile
    WHERE tipo_acesso = 'API_FORCA_DE_VENDA'
  ")->fetchColumn();

  $whatsapp_total = (int)$pdo->query("
    SELECT COUNT(*) FROM clientes_web_api_whats
  ")->fetchColumn();

  $pdvoff_total = (int)$pdo->query("
    SELECT COUNT(*) FROM clientes_web_pdvoff
  ")->fetchColumn();

  /* =====================================================
   ÚLTIMOS 3 CADASTROS ATUALIZADOS
===================================================== */

// LOGINS
$last_logins = $pdo->query("
  SELECT
    codigo_cliente AS cod_cliente,
    nome_cliente   AS cliente,
    DATE_FORMAT(atualizado_em, '%d/%m/%Y %H:%i') AS atualizado_em
  FROM clientes_web_login
  ORDER BY atualizado_em DESC
  LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

// FV SMART CLIENT
$last_fv = $pdo->query("
  SELECT
    cod_cliente,
    cliente,
    DATE_FORMAT(atualizado_em, '%d/%m/%Y %H:%i') AS atualizado_em
  FROM clientes_web_mobile
  WHERE tipo_acesso = 'FV_SMART_CLIENT'
  ORDER BY atualizado_em DESC
  LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

// API FORÇA DE VENDAS
$last_api = $pdo->query("
  SELECT
    cod_cliente,
    cliente,
    DATE_FORMAT(atualizado_em, '%d/%m/%Y %H:%i') AS atualizado_em
  FROM clientes_web_mobile
  WHERE tipo_acesso = 'API_FORCA_DE_VENDA'
  ORDER BY atualizado_em DESC
  LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

// WHATSAPP
$last_whatsapp = $pdo->query("
  SELECT
    cod_cliente,
    cliente,
    DATE_FORMAT(atualizado_em, '%d/%m/%Y %H:%i') AS atualizado_em
  FROM clientes_web_api_whats
  ORDER BY atualizado_em DESC
  LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

// PDV OFF
$last_pdvoff = $pdo->query("
  SELECT
    cod_cliente,
    cliente,
    DATE_FORMAT(atualizado_em, '%d/%m/%Y %H:%i') AS atualizado_em
  FROM clientes_web_pdvoff
  ORDER BY atualizado_em DESC
  LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);


  /* =====================================================
     RESPONSE
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

    'listas' => [
      'logins'    => $last_logins,
      'fv_smart'  => $last_fv,
      'api_forca' => $last_api,
      'whatsapp'  => $last_whatsapp,
      'pdvoff'    => $last_pdvoff
    ]
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
