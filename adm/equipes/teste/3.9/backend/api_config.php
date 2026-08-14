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

$CHAVES = ['whatsapp_versao', 'mobile_apifv_versao'];

try {

  /* ==========================
     GET_ALL
  ========================== */
  if ($action === 'get_all') {
    $result = [];
    foreach ($CHAVES as $chave) {
      $stmt = $pdo->prepare("SELECT valor FROM clientes_web_config WHERE chave = ? LIMIT 1");
      $stmt->execute([$chave]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      $result[$chave] = $row ? $row['valor'] : '';
    }
    echo json_encode(['success'=>true, 'config'=>$result]);
    exit;
  }

  /* ==========================
     GET
  ========================== */
  if ($action === 'get') {
    $chave = trim($body['chave'] ?? '');
    if (!in_array($chave, $CHAVES, true)) throw new Exception('Chave inválida');

    $stmt = $pdo->prepare("SELECT valor FROM clientes_web_config WHERE chave = ? LIMIT 1");
    $stmt->execute([$chave]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success'=>true, 'valor'=> $row ? $row['valor'] : '']);
    exit;
  }

  /* ==========================
     SET
  ========================== */
  if ($action === 'set') {
    $chave = trim($body['chave'] ?? '');
    $valor = trim($body['valor'] ?? '');

    if (!in_array($chave, $CHAVES, true)) throw new Exception('Chave inválida');

    $stmt = $pdo->prepare("
      INSERT INTO clientes_web_config (chave, valor, atualizado_em)
      VALUES (?, ?, NOW())
      ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_em = NOW()
    ");
    $stmt->execute([$chave, $valor]);

    logAction($pdo, 'UPDATE', 'Config', 0, null,
      ['chave'=>$chave,'valor'=>$valor],
      "Atualizou configuração \"{$chave}\" → \"{$valor}\""
    );

    echo json_encode(['success'=>true]);
    exit;
  }

  throw new Exception('Ação inválida');

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
