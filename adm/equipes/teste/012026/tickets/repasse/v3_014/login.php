<?php
require_once __DIR__ . '/includes/session.php';

if (!empty($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$erro = $_GET['erro'] ?? '';
$msgs = [
    'credenciais' => 'Usuário ou senha incorretos.',
    'campos'      => 'Preencha usuário e senha.',
    'semacesso'   => 'Seu usuário não tem permissão de acesso a este sistema. Contate o administrador.',
];
$msgErro = $msgs[$erro] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repasse de Tickets — Login</title>
    <link rel="icon" href="https://tgameajuda.com/img/principal/bot-tga.webp" type="image/x-icon">
    <link rel="stylesheet" href="assets/style.css">
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-E7ZNTJSRYR"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-E7ZNTJSRYR');
    </script>
</head>
<body class="login-page">
<script src="assets/common.js?v=1"></script>

<div class="login-blob login-blob-1"></div>
<div class="login-blob login-blob-2"></div>

<div class="login-card">
    <div class="login-logo">
        <span class="login-logo-icon">🎫</span>
        <h1>Repasse de Tickets</h1>
        <p>Sistema de distribuição semanal</p>
    </div>

    <?php if ($msgErro): ?>
        <div class="alert alert-danger">⚠️ <?= htmlspecialchars($msgErro) ?></div>
    <?php endif; ?>

    <form method="POST" action="api/auth.php" autocomplete="off">
        <div class="form-group">
            <label for="usuario">Usuário</label>
            <div class="input-icon-wrap">
                <span class="input-icon">👤</span>
                <input
                    type="text"
                    id="usuario"
                    name="usuario"
                    placeholder="seu nome de usuário"
                    required
                    autofocus
                    autocomplete="username"
                    value="<?= htmlspecialchars($_GET['usuario'] ?? '') ?>"
                >
            </div>
        </div>

        <div class="form-group">
            <label for="senha">Senha</label>
            <div class="input-icon-wrap">
                <span class="input-icon">🔒</span>
                <input
                    type="password"
                    id="senha"
                    name="senha"
                    placeholder="••••••••"
                    required
                >
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block">
            Entrar <span class="btn-arrow">→</span>
        </button>
    </form>

    <p class="login-footer">Acesso restrito à equipe de suporte</p>
</div>

</body>
</html>
