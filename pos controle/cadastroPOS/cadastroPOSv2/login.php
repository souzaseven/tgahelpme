<?php
session_start();
require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $senha = $_POST['senha'] ?? '';

    if (!$usuario || !$senha) {
        header('Location: login.html?erro=1');
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE nome = :nome AND senha = :senha");
    $stmt->execute(['nome' => $usuario, 'senha' => $senha]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['usuario'] = $user['nome'];
        header('Location: cadastropos.html');
    } else {
        header('Location: login.html?erro=1');
    }
    exit;
} else {
    header('Location: login.html');
    exit;
}
?>
