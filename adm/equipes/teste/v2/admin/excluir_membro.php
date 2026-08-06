<?php
// admin/excluir_membro.php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] !== 'maiara') {
    header('Location: login.php');
    exit;
}
require_once '../conexao.php';

$id = intval($_GET['id'] ?? 0);
if ($id) {
    $stmt = $pdo->prepare("DELETE FROM renascer_menbros WHERE id = ?");
    $stmt->execute([$id]);
}
header('Location: membros.php');
exit;
