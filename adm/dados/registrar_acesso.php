// registrar_acesso.php
require 'conexao.php';
$id = intval($_POST['id'] ?? 0);
if ($id > 0) {
  $pdo->exec("UPDATE links SET acessos = acessos + 1 WHERE id = $id");
}
