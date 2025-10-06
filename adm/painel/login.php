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

    // VERIFICAÇÃO ESPECÍFICA PARA O USUÁRIO ANDERSON
    if ($usuario !== 'anderson') {
        $ip = $_SERVER['REMOTE_ADDR'];
        // Logar tentativa de acesso com usuário não autorizado
        $log = $pdo->prepare("INSERT INTO logs_acesso (usuario_tentado, ip_origem, sucesso, detalhes) VALUES (?, ?, 0, ?)");
        $log->execute([$usuario, $ip, 'Tentativa de acesso com usuário não autorizado']);
        
        header('Location: login.html?erro=1');
        exit;
    }

    // Busca APENAS o usuário anderson
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE nome = :nome");
    $stmt->execute(['nome' => $usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $ip = $_SERVER['REMOTE_ADDR'];

    // Verifica se encontrou o usuário e se a senha está correta
    if ($user && $user['senha'] === $senha) {
        $_SESSION['usuario_logado'] = $user['nome'];
        $_SESSION['ultimo_acesso'] = time();
        $_SESSION['is_admin'] = true; // Flag para identificar que é admin
        
        // ✅ ADICIONAR CSRF TOKEN SE PRECISAR USAR NO FUTURO
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        // Marcar que é um novo acesso válido (não refresh)
        $_SESSION['acesso_valido'] = true;
        $_SESSION['pagina_atual'] = 'index';

        // Logar sucesso
        $log = $pdo->prepare("INSERT INTO logs_acesso (usuario_tentado, ip_origem, sucesso, detalhes) VALUES (?, ?, 1, ?)");
        $log->execute([$usuario, $ip, 'Acesso administrativo concedido']);

        header('Location: index.php');
        exit;
    } else {
        // Logar falha
        $log = $pdo->prepare("INSERT INTO logs_acesso (usuario_tentado, ip_origem, sucesso, detalhes) VALUES (?, ?, 0, ?)");
        $log->execute([$usuario, $ip, 'Senha incorreta para usuário administrativo']);

       header('Location: login.html?erro=1');
        exit;
    }
} else {
    // Se tentar acessar via GET, redireciona
    header('Location: login.html');
    exit;
}
?>