<?php
require __DIR__ . "/conexao.php";

/* Evita warnings/notices quebrarem o JSON */
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header("Content-Type: application/json; charset=utf-8");

try {

  /* =====================================================
     POST — CREATE / UPDATE / DELETE
  ===================================================== */
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $dados = json_decode(file_get_contents("php://input"), true);

    if (!is_array($dados)) {
      throw new Exception("JSON inválido");
    }

    /* ---------- DELETE ---------- */
    if (!empty($dados['excluir']) && !empty($dados['id'])) {

      $stmt = $pdo->prepare(
        "DELETE FROM clientes_tga_web WHERE id = :id"
      );
      $stmt->execute([
        ":id" => (int)$dados['id']
      ]);

      echo json_encode(["success" => true], JSON_UNESCAPED_UNICODE);
      exit;
    }

    /* ---------- CREATE / UPDATE ---------- */
    $id = !empty($dados['id']) ? (int)$dados['id'] : null;

    if ($id) {
      $sql = "
        UPDATE clientes_tga_web SET
          codigotga      = :codigotga,
          nome_empresa   = :nome_empresa,
          cnpj           = :cnpj,
          versao         = :versao,
          firebird       = :firebird,
          info_adicional = :info,
          qntusuarios    = :usuarios,
          senhapadrao    = :senha,
          updated_at     = NOW()
        WHERE id = :id
      ";
    } else {
      $sql = "
        INSERT INTO clientes_tga_web
          (codigotga, nome_empresa, cnpj, versao, firebird,
           info_adicional, qntusuarios, senhapadrao,
           created_at, updated_at)
        VALUES
          (:codigotga, :nome_empresa, :cnpj, :versao, :firebird,
           :info, :usuarios, :senha,
           NOW(), NOW())
      ";
    }

    $stmt = $pdo->prepare($sql);

    $params = [
      ":codigotga"    => (string)$dados['codigotga'],
      ":nome_empresa" => (string)$dados['nome_empresa'],
      ":cnpj"         => (string)$dados['cnpj'],
      ":versao"       => (string)$dados['versao'],
      ":firebird"     => (string)$dados['firebird'],
      ":info"         => (string)$dados['info_adicional'],
      ":usuarios"     => (int)$dados['qntusuarios'],
      ":senha"        => (string)$dados['senhapadrao']
    ];

    if ($id) {
      $params[':id'] = $id;
    }

    $stmt->execute($params);

    echo json_encode(["success" => true], JSON_UNESCAPED_UNICODE);
    exit;
  }

  /* =====================================================
     GET — LISTAGEM + FILTROS + PAGINAÇÃO
  ===================================================== */

  $codigo   = trim((string)($_GET['codigo']   ?? ''));
  $empresa  = trim((string)($_GET['empresa']  ?? ''));
  $cnpj     = trim((string)($_GET['cnpj']     ?? ''));
  $versao   = trim((string)($_GET['versao']   ?? ''));
  $firebird = trim((string)($_GET['firebird'] ?? ''));
  $info     = trim((string)($_GET['info']     ?? ''));
  $usuarios = trim((string)($_GET['usuarios'] ?? ''));

/* PAGINAÇÃO */
$page = max(1, (int)($_GET['page'] ?? 1));

$limitRaw = $_GET['limit'] ?? '10';

/* "Tudo" */
if ($limitRaw === 'all') {
  $limit  = 1000000; // número alto, controlado
  $offset = 0;
} else {
  $limit  = max(1, (int)$limitRaw);
  $offset = ($page - 1) * $limit;
}


  $where  = [];
  $params = [];

  if ($codigo !== '') {
    $where[] = "codigotga LIKE :codigo";
    $params[':codigo'] = "%$codigo%";
  }

  if ($empresa !== '') {
    $where[] = "nome_empresa LIKE :empresa";
    $params[':empresa'] = "%$empresa%";
  }

  if ($cnpj !== '') {
    $where[] = "cnpj LIKE :cnpj";
    $params[':cnpj'] = "%$cnpj%";
  }

  if ($versao !== '') {
    $where[] = "versao = :versao";
    $params[':versao'] = $versao;
  }

  if ($firebird !== '') {
    $where[] = "firebird = :firebird";
    $params[':firebird'] = $firebird;
  }

  if ($info !== '') {
    $where[] = "info_adicional LIKE :info";
    $params[':info'] = "%$info%";
  }

  if ($usuarios !== '' && ctype_digit($usuarios)) {
    $where[] = "qntusuarios = :usuarios";
    $params[':usuarios'] = (int)$usuarios;
  }

  /* TOTAL PARA PAGINAÇÃO */
  $sqlCount = "SELECT COUNT(*) FROM clientes_tga_web";
  if ($where) {
    $sqlCount .= " WHERE " . implode(" AND ", $where);
  }

  $stmtCount = $pdo->prepare($sqlCount);
  $stmtCount->execute($params);
  $total = (int)$stmtCount->fetchColumn();

  /* LISTAGEM */
  $sql = "
    SELECT
      id,
      codigotga,
      nome_empresa,
      cnpj,
      versao,
      firebird,
      info_adicional,
      qntusuarios,
      senhapadrao
    FROM clientes_tga_web
  ";

  if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
  }

  $sql .= " ORDER BY nome_empresa LIMIT :limit OFFSET :offset";

  $stmt = $pdo->prepare($sql);

  foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
  }

  $stmt->bindValue(":limit",  $limit,  PDO::PARAM_INT);
  $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);

  $stmt->execute();

  echo json_encode([
    "data"  => $stmt->fetchAll(PDO::FETCH_ASSOC),
    "total" => $total,
    "page"  => $page,
    "limit" => $limitRaw

  ], JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {

  http_response_code(500);
  echo json_encode([
    "success" => false,
    "error"   => "Erro interno no servidor",
    "detail"  => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}
