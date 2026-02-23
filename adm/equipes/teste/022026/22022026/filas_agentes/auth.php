<?php
session_set_cookie_params([
    'path'     => '/telefonia-evolux/filas_agentes/',
    'secure'   => true,      // HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();
require_once "conexao.php";

$usuario = trim($_POST['usuario'] ?? '');
$senha   = trim($_POST['senha'] ?? '');

if ($usuario === '' || $senha === '') {
    header("Location: /telefonia-evolux/filas_agentes/login.php?erro=1");
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, nome, senha, editafila, is_admin
    FROM usuarios
    WHERE nome = :nome
    LIMIT 1
");
$stmt->execute([":nome" => $usuario]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$ip = $_SERVER['REMOTE_ADDR'];

// ❌ Usuário ou senha inválidos
if (!$user || $user['senha'] !== $senha) {

    $pdo->prepare("
        INSERT INTO logs_acesso (usuario_tentado, ip_origem, sucesso)
        VALUES (?, ?, 0)
    ")->execute([$usuario, $ip]);

    header("Location: /telefonia-evolux/filas_agentes/login.php?erro=1");
    exit;
}

// ❌ Sem permissão para editar fila
if ((int)$user['editafila'] !== 1) {

    $pdo->prepare("
        INSERT INTO logs_acesso (usuario_tentado, ip_origem, sucesso)
        VALUES (?, ?, 0)
    ")->execute([$usuario, $ip]);

    header("Location: /telefonia-evolux/filas_agentes/login.php?erro=perm");
    exit;
}

// ✅ Login autorizado
$_SESSION['usuario_logado'] = true;
$_SESSION['usuario_id']     = $user['id'];
$_SESSION['usuario_nome']   = $user['nome'];
$_SESSION['is_admin']       = $user['is_admin'] ?? 0;
$_SESSION['editafila']      = 1;
$_SESSION['ultimo_acesso']  = time();

// Log sucesso
$pdo->prepare("
    INSERT INTO logs_acesso (usuario_tentado, ip_origem, sucesso)
    VALUES (?, ?, 1)
")->execute([$usuario, $ip]);

header("Location: /telefonia-evolux/filas_agentes/index.php");
exit;
