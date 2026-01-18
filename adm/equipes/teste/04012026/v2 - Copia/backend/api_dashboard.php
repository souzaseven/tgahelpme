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

  // KPIs Logins
  $logins_total = (int)$pdo->query("SELECT COUNT(*) c FROM clientes_web_login")->fetch()['c'];
  $logins_ativos = (int)$pdo->query("SELECT COUNT(*) c FROM clientes_web_login WHERE UPPER(status)='ATIVO'")->fetch()['c'];

  // KPIs Conexões
  $con_total = (int)$pdo->query("SELECT COUNT(*) c FROM clientes_web_conexoes")->fetch()['c'];
  $con_on = (int)$pdo->query("SELECT COUNT(*) c FROM clientes_web_conexoes WHERE UPPER(status)='ON'")->fetch()['c'];

  // API vs Mobile (por tipo_acesso)
  $apis_total = (int)$pdo->query("SELECT COUNT(*) c FROM clientes_web_conexoes WHERE UPPER(tipo_acesso)='API'")->fetch()['c'];
  $apis_on = (int)$pdo->query("SELECT COUNT(*) c FROM clientes_web_conexoes WHERE UPPER(tipo_acesso)='API' AND UPPER(status)='ON'")->fetch()['c'];
  $apis_off = (int)$pdo->query("SELECT COUNT(*) c FROM clientes_web_conexoes WHERE UPPER(tipo_acesso)='API' AND UPPER(status)='OFF'")->fetch()['c'];

  $mobile_total = (int)$pdo->query("SELECT COUNT(*) c FROM clientes_web_conexoes WHERE UPPER(tipo_acesso) IN ('FV_MOBILE_ON','MOBILE_OFF')")->fetch()['c'];
  $mobile_on = (int)$pdo->query("SELECT COUNT(*) c FROM clientes_web_conexoes WHERE UPPER(tipo_acesso) IN ('FV_MOBILE_ON','MOBILE_OFF') AND UPPER(status)='ON'")->fetch()['c'];
  $mobile_off = (int)$pdo->query("SELECT COUNT(*) c FROM clientes_web_conexoes WHERE UPPER(tipo_acesso) IN ('FV_MOBILE_ON','MOBILE_OFF') AND UPPER(status)='OFF'")->fetch()['c'];

  // Últimos (8)
  $last_logins = $pdo->query("
    SELECT id, codigo_cliente, nome_cliente, caminho_acesso, versao_padrao, status,
           DATE_FORMAT(criado_em, '%Y-%m-%d %H:%i:%s') criado_em
    FROM clientes_web_login
    ORDER BY id DESC
    LIMIT 8
  ")->fetchAll();

  $last_conexoes = $pdo->query("
    SELECT id, cod_cliente, cliente, acesso_server, porta, tipo_acesso, status
    FROM clientes_web_conexoes
    ORDER BY id DESC
    LIMIT 8
  ")->fetchAll();

  echo json_encode([
    'success' => true,
    'kpis' => [
      'logins_total' => $logins_total,
      'logins_ativos' => $logins_ativos,
      'conexoes_total' => $con_total,
      'conexoes_on' => $con_on,
      'apis_total' => $apis_total,
      'apis_on' => $apis_on,
      'apis_off' => $apis_off,
      'mobile_total' => $mobile_total,
      'mobile_on' => $mobile_on,
      'mobile_off' => $mobile_off,
    ],
    'last_logins' => $last_logins,
    'last_conexoes' => $last_conexoes
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
  exit;
}
