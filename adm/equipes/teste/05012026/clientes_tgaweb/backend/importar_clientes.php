<?php
require __DIR__ . "/conexao.php";

header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(["success" => false, "error" => "Método inválido"]);
  exit;
}

if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
  echo json_encode(["success" => false, "error" => "Arquivo inválido"]);
  exit;
}

$arquivo = $_FILES['arquivo']['tmp_name'];
$handle  = fopen($arquivo, "r");

if (!$handle) {
  echo json_encode(["success" => false, "error" => "Erro ao abrir arquivo"]);
  exit;
}

$inseridos   = 0;
$atualizados = 0;
$erros       = 0;

$pdo->beginTransaction();

try {

  $cabecalho = fgetcsv($handle, 0, ";"); // pula header

  while (($linha = fgetcsv($handle, 0, ";")) !== false) {

    [
      $codigotga,
      $nome,
      $cnpj,
      $versao,
      $firebird,
      $info,
      $usuarios,
      $senha
    ] = array_map('trim', $linha);

    if (!$codigotga || !$nome) {
      $erros++;
      continue;
    }

    /* EXISTE? */
    $check = $pdo->prepare("
      SELECT id FROM clientes_tga_web
      WHERE codigotga = :cod OR cnpj = :cnpj
      LIMIT 1
    ");
    $check->execute([
      ":cod"  => $codigotga,
      ":cnpj"=> $cnpj
    ]);

    $id = $check->fetchColumn();

    if ($id) {
      /* UPDATE */
      $stmt = $pdo->prepare("
        UPDATE clientes_tga_web SET
          nome_empresa   = :nome,
          cnpj           = :cnpj,
          versao         = :versao,
          firebird       = :firebird,
          info_adicional = :info,
          qntusuarios    = :usuarios,
          senhapadrao    = :senha,
          updated_at     = NOW()
        WHERE id = :id
      ");
      $stmt->execute([
        ":id"       => $id,
        ":nome"     => $nome,
        ":cnpj"     => $cnpj,
        ":versao"   => $versao,
        ":firebird" => $firebird,
        ":info"     => $info,
        ":usuarios" => (int)$usuarios,
        ":senha"    => $senha
      ]);
      $atualizados++;

    } else {
      /* INSERT */
      $stmt = $pdo->prepare("
        INSERT INTO clientes_tga_web
          (codigotga, nome_empresa, cnpj, versao, firebird,
           info_adicional, qntusuarios, senhapadrao,
           created_at, updated_at)
        VALUES
          (:cod, :nome, :cnpj, :versao, :firebird,
           :info, :usuarios, :senha,
           NOW(), NOW())
      ");
      $stmt->execute([
        ":cod"      => $codigotga,
        ":nome"     => $nome,
        ":cnpj"     => $cnpj,
        ":versao"   => $versao,
        ":firebird" => $firebird,
        ":info"     => $info,
        ":usuarios" => (int)$usuarios,
        ":senha"    => $senha
      ]);
      $inseridos++;
    }
  }

  $pdo->commit();

  echo json_encode([
    "success"     => true,
    "inseridos"   => $inseridos,
    "atualizados" => $atualizados,
    "erros"       => $erros
  ]);

} catch (Throwable $e) {
  $pdo->rollBack();
  echo json_encode([
    "success" => false,
    "error"   => $e->getMessage()
  ]);
}
