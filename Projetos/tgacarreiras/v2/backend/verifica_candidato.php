<?php
require_once __DIR__ . '/conexao.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* =========================
   CADASTRO
========================= */
if (isset($_POST['acao']) && $_POST['acao'] === 'cadastro') {

    $nome     = trim($_POST['nome'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $senha    = trim($_POST['senha'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $estado   = trim($_POST['estado'] ?? '');
    $cidade   = trim($_POST['cidade'] ?? '');

    if (!$nome || !$email || !$senha || !$telefone || !$estado || !$cidade) {
        header("Location: ../candidato/cadastro.php?erro=" . urlencode("Preencha todos os campos obrigatórios"));
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../candidato/cadastro.php?erro=" . urlencode("Email inválido"));
        exit;
    }

    if (strlen($senha) < 6) {
        header("Location: ../candidato/cadastro.php?erro=" . urlencode("Senha deve ter no mínimo 6 caracteres"));
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM usuarios_carreiras WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        header("Location: ../candidato/cadastro.php?erro=" . urlencode("Email já cadastrado"));
        exit;
    }

    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO usuarios_carreiras
        (nome, email, senha, telefone, estado, cidade, tipo, ativo)
        VALUES (?, ?, ?, ?, ?, ?, 'candidato', 1)
    ");

    $stmt->execute([$nome, $email, $senhaHash, $telefone, $estado, $cidade]);

    header("Location: ../candidato/login.php?success=1");
    exit;
}


/* =========================
   LOGIN
========================= */
if (isset($_POST['acao']) && $_POST['acao'] === 'login') {

    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if ($email === '' || $senha === '') {
        header("Location: ../candidato/login.php?erro=" . urlencode("Informe email e senha"));
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, nome, email, senha, tipo, ativo, is_admin
        FROM usuarios_carreiras
        WHERE email = ?
          AND ativo = 1
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && !empty($user['senha']) && password_verify($senha, $user['senha'])) {

        // reforça sessão segura
        session_regenerate_id(true);

        $_SESSION['usuario_id']    = (int)$user['id'];
        $_SESSION['usuario_nome']  = (string)$user['nome'];
        $_SESSION['usuario_email'] = (string)$user['email'];
        $_SESSION['usuario_tipo']  = (string)$user['tipo'];
        $_SESSION['is_admin']      = (int)($user['is_admin'] ?? 0);
        $_SESSION['login_time']    = time();

        // Compat candidato
        $_SESSION['candidato_id']   = (int)$user['id'];
        $_SESSION['candidato_nome'] = (string)$user['nome'];
        $_SESSION['candidato_email'] = (string)$user['email'];

        $update = $pdo->prepare("UPDATE usuarios_carreiras SET ultimo_login = NOW() WHERE id = ? LIMIT 1");
        $update->execute([(int)$user['id']]);

        if ($user['tipo'] === 'admin') {
            header("Location: ../adm/index.php");
        } else {
            header("Location: ../candidato/dashboard.php");
        }
        exit;
    }

    header("Location: ../candidato/login.php?erro=" . urlencode("Login inválido ou conta inativa"));
    exit;
}