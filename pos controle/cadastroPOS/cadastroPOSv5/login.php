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

    $ip = $_SERVER['REMOTE_ADDR'];

    if ($user) {
        $_SESSION['usuario_logado'] = $user['nome'];
        $_SESSION['ultimo_acesso'] = time();

        // Logar sucesso
        $log = $pdo->prepare("INSERT INTO logs_acesso (usuario_tentado, senha_tentada, ip_origem, sucesso) VALUES (?, ?, ?, 1)");
        $log->execute([$usuario, $senha, $ip]);

        header('Location: cadastropos.php');
        exit;
    } else {
        // Logar falha
        $log = $pdo->prepare("INSERT INTO logs_acesso (usuario_tentado, senha_tentada, ip_origem, sucesso) VALUES (?, ?, ?, 0)");
        $log->execute([$usuario, $senha, $ip]);

        header('Location: login.html?erro=1');
        exit;
    }
} else {
    // Se tentar acessar via GET, redireciona
    header('Location: login.html');
    exit;
}