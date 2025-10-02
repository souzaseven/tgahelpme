<?php
require_once 'conexao.php';

$id = intval($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM links WHERE id = ?");
    $stmt->execute([$id]);
}

echo "OK";
