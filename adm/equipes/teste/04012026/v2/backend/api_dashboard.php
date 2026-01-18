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

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $body['action'] ?? '';

try {
  if ($action !== 'summary') {
    throw new Exception('Ação inválida');
  }

  /* =====================================================
     LOGINS
  ===================================================== */
  $logins_total = (int)$pdo->query("
    SELECT COUNT(*) c FROM clientes_web_login
  ")->fetch()['c'];

  $logins_ativos = (int)$pdo->query("
    SELECT COUNT(*) c FROM clientes_web_login
    WHERE UPPER(status)='ATIVO'
  ")->fetch()['c'];
/* =====================================================
   CONEXÕES - FV MOBILE ON  (equivale a SMART_CLIENTE)
===================================================== */
$fv_on = (int)$pdo->query("
  SELECT COUNT(*) c
  FROM clientes_web_conexoes
  WHERE UPPER(tipo_acesso) = 'SMART_CLIENTE'
    AND UPPER(status) = 'ON'
")->fetch()['c'];

$fv_total = (int)$pdo->query("
  SELECT COUNT(*) c
  FROM clientes_web_conexoes
  WHERE UPPER(tipo_acesso) = 'SMART_CLIENTE'
")->fetch()['c'];

/* =====================================================
   API - MOBILE OFF (equivale a API)
===================================================== */
$api_on = (int)$pdo->query("
  SELECT COUNT(*) c
  FROM clientes_web_conexoes
  WHERE UPPER(tipo_acesso) = 'API'
    AND UPPER(status) = 'ON'
")->fetch()['c'];

$api_off = (int)$pdo->query("
  SELECT COUNT(*) c
  FROM clientes_web_conexoes
  WHERE UPPER(tipo_acesso) = 'API'
    AND UPPER(status) = 'OFF'
")->fetch()['c'];

$api_total = (int)$pdo->query("
  SELECT COUNT(*) c
  FROM clientes_web_conexoes
  WHERE UPPER(tipo_acesso) = 'API'
")->fetch()['c'];

  /* =====================================================
     WHATSAPP
     (ajuste o WHERE conforme sua regra real)
  ===================================================== */
$whatsapp_total = (int)$pdo->query("
  SELECT COUNT(*) c
  FROM clientes_web_api_whats
")->fetch()['c'];


  /* =====================================================
     TOP 10
  ===================================================== */
  $last_logins = $pdo->query("
    SELECT id, codigo_cliente, nome_cliente, caminho_acesso, versao_padrao, status,
           DATE_FORMAT(criado_em, '%Y-%m-%d %H:%i:%s') criado_em
    FROM clientes_web_login
    ORDER BY id DESC
    LIMIT 10
  ")->fetchAll();

  $last_conexoes = $pdo->query("
    SELECT id, cod_cliente, cliente, acesso_server, porta, tipo_acesso, status
    FROM clientes_web_conexoes
    ORDER BY id DESC
    LIMIT 10
  ")->fetchAll();

  echo json_encode([
    'success' => true,
    'kpis' => [
      'logins_total'   => $logins_total,
      'logins_ativos'  => $logins_ativos,

      'fv_total'       => $fv_total,
      'fv_on'          => $fv_on,

      'api_total'      => $api_total,
      'api_on'         => $api_on,
      'api_off'        => $api_off,

      'whatsapp_total' => $whatsapp_total
    ],
    'last_logins'   => $last_logins,
    'last_conexoes' => $last_conexoes
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode([
    'success'=>false,
    'message'=>$e->getMessage()
  ]);
  exit;
}
