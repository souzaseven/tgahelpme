<?php
require_once __DIR__ . '/conexao.php';
session_start();

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

    /* Validação básica */
    if (!$nome || !$email || !$senha || !$telefone || !$estado || !$cidade) {
        header("Location: ../candidato/cadastro.php?erro=Preencha todos os campos obrigatórios");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../candidato/cadastro.php?erro=Email inválido");
        exit;
    }

    if (strlen($senha) < 6) {
        header("Location: ../candidato/cadastro.php?erro=Senha deve ter no mínimo 6 caracteres");
        exit;
    }

    /* Verifica se já existe */
    $stmt = $pdo->prepare("SELECT id FROM usuarios_carreiras WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->rowCount() > 0) {
        header("Location: ../candidato/cadastro.php?erro=Email já cadastrado");
        exit;
    }

    /* 🔐 HASH DA SENHA (MUITO IMPORTANTE) */
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO usuarios_carreiras 
        (nome, email, senha, telefone, estado, cidade, tipo, ativo)
        VALUES (?, ?, ?, ?, ?, ?, 'candidato', 1)
    ");

    $stmt->execute([
        $nome,
        $email,
        $senhaHash,
        $telefone,
        $estado,
        $cidade
    ]);

    header("Location: ../candidato/login.php?success=1");
    exit;
}



/* =========================
   LOGIN
========================= */
if (isset($_POST['acao']) && $_POST['acao'] === 'login') {

    $email = trim($_POST['email']);
    $senha = trim($_POST['senha']);

    /* 🔎 Busca usuário ativo (candidato, empresa ou admin) */
    $stmt = $pdo->prepare("
        SELECT id, nome, email, senha, tipo, ativo, is_admin
        FROM usuarios_carreiras
        WHERE email = ?
        AND ativo = 1
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    /* 🔐 Validação de login (TEXTO PURO TEMPORÁRIO) */
    //if ($user && $senha === $user['senha']) {
if ($user && password_verify($senha, $user['senha'])) {
        /* ===== SESSÃO PADRÃO ===== */
        $_SESSION['usuario_id']    = $user['id'];
        $_SESSION['usuario_nome']  = $user['nome'];
        $_SESSION['usuario_email'] = $user['email'];
        $_SESSION['usuario_tipo']  = $user['tipo'];
        $_SESSION['is_admin']      = (int)$user['is_admin'];
        $_SESSION['login_time']    = time();

        /* ===== Mantém compatibilidade com dashboard candidato ===== */
        $_SESSION['candidato_id']   = $user['id'];
        $_SESSION['candidato_nome'] = $user['nome'];

        /* Atualiza último login */
        $update = $pdo->prepare("
            UPDATE usuarios_carreiras
            SET ultimo_login = NOW()
            WHERE id = ?
        ");
        $update->execute([$user['id']]);

        /* 🎯 Redirecionamento inteligente */
        if ($user['tipo'] === 'admin') {
            header("Location: ../adm/index.php");
        } else {
            header("Location: ../candidato/dashboard.php");
        }

        exit;
    }

    /* ❌ Login inválido */
    header("Location: ../candidato/login.php?erro=Login inválido ou conta inativa");
    exit;
}
