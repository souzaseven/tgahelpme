<?php
session_start();
require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    // Verifica se os campos estão vazios
    if (empty($usuario) || empty($senha)) {
        $_SESSION['login_error'] = 'Por favor, preencha todos os campos';
        header('Location: login.html');
        exit;
    }

    // Busca o usuário apenas pelo nome
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE nome = :nome");
    $stmt->execute(['nome' => $usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $ip = $_SERVER['REMOTE_ADDR'];

    // Verifica se encontrou o usuário e se a senha está correta
    if ($user && $user['senha'] === $senha) {
        $_SESSION['usuario_logado'] = $user['nome'];
        $_SESSION['ultimo_acesso'] = time();

        // Logar sucesso
        $log = $pdo->prepare("INSERT INTO logs_acesso (usuario_tentado, ip_origem, sucesso) VALUES (?, ?, 1)");
        $log->execute([$usuario, $ip]);

        header('Location: index.php');
        exit;
    } else {
        // Logar falha (sem registrar a senha por segurança)
        $log = $pdo->prepare("INSERT INTO logs_acesso (usuario_tentado, ip_origem, sucesso) VALUES (?, ?, 0)");
        $log->execute([$usuario, $ip]);

       header('Location: login.html?erro=1');
exit;

    
    }
} else {
    // Se tentar acessar via GET, redireciona
    header('Location: login.html');
    exit;
}