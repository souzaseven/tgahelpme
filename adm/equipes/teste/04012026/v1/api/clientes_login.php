<?php
require_once '../conexao.php';

header('Content-Type: application/json');

// ===============================
// CSRF
// ===============================
if (!validateCSRF()) {
  echo json_encode([
    'success' => false,
    'message' => 'CSRF inválido'
  ]);
  exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

  /* ===============================
     LISTAR
  =============================== */
  case 'GET':

    $stmt = $pdo->query("
      SELECT
        id,
        codigo_cliente,
        nome_cliente,
        caminho_acesso,
        versao_padrao,
        status
      FROM clientes_web_login
      ORDER BY nome_cliente
    ");

    echo json_encode([
      'success' => true,
      'data' => $stmt->fetchAll()
    ]);
    break;

  /* ===============================
     INSERIR
  =============================== */
  case 'POST':

    $data = json_decode(file_get_contents("php://input"), true);

    $sql = "
      INSERT INTO clientes_web_login
        (codigo_cliente, nome_cliente, caminho_acesso, versao_padrao, status)
      VALUES (?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        nome_cliente   = VALUES(nome_cliente),
        caminho_acesso = VALUES(caminho_acesso),
        versao_padrao  = VALUES(versao_padrao),
        status         = VALUES(status)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      $data['codigo_cliente'],
      $data['nome_cliente'],
      $data['caminho_acesso'],
      $data['versao_padrao'],
      $data['status'] ?? 'ATIVO'
    ]);

    logAction(
      $pdo,
      'UPSERT',
      'clientes_web_login',
      null,
      json_encode($data)
    );

    echo json_encode(['success' => true]);
    break;

  /* ===============================
     ATUALIZAR
  =============================== */
  case 'PUT':

    $data = json_decode(file_get_contents("php://input"), true);

    $sql = "
      UPDATE clientes_web_login SET
        codigo_cliente = ?,
        nome_cliente   = ?,
        caminho_acesso = ?,
        versao_padrao  = ?,
        status         = ?
      WHERE id = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      $data['codigo_cliente'],
      $data['nome_cliente'],
      $data['caminho_acesso'],
      $data['versao_padrao'],
      $data['status'],
      $data['id']
    ]);

    logAction(
      $pdo,
      'UPDATE',
      'clientes_web_login',
      $data['id'],
      json_encode($data)
    );

    echo json_encode(['success' => true]);
    break;

  /* ===============================
     EXCLUIR
  =============================== */
  case 'DELETE':

    parse_str($_SERVER['QUERY_STRING'], $params);

    $stmt = $pdo->prepare("
      DELETE FROM clientes_web_login WHERE id = ?
    ");
    $stmt->execute([$params['id']]);

    logAction(
      $pdo,
      'DELETE',
      'clientes_web_login',
      $params['id']
    );

    echo json_encode(['success' => true]);
    break;
}
