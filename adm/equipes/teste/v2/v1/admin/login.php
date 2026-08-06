<?php
// admin/login.php
session_start();

if (isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = trim($_POST['senha']   ?? '');
    if ($usuario === 'maiara' && $senha === 'teste') {
        $_SESSION['usuario'] = $usuario;
        header('Location: index.php');
        exit;
    }
    $erro = 'Usuário ou senha inválidos!';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Igreja Renascer</title>
    <script>if(localStorage.getItem('tema')==='claro')document.documentElement.setAttribute('data-theme','light');</script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/app.js" defer></script>
</head>
<body style="position:relative;">
    <button class="login-tema" onclick="alternarTema()" id="btnTema">&#9728; Modo Claro</button>
    <div class="login-page">
        <div class="login-box">
            <div class="login-logo">
                <h1>Renascer</h1>
                <p>Sistema de Gestão de Membros</p>
            </div>
            <?php if ($erro): ?>
                <div class="alert alert-erro"><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <div class="form-group">
                    <label for="usuario">Usuário</label>
                    <input type="text" name="usuario" id="usuario" class="form-control" required autofocus>
                </div>
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password" name="senha" id="senha" class="form-control" required>
                </div>
                <button type="submit" class="btn" style="width:100%;margin-top:.5rem;">Entrar</button>
            </form>
        </div>
    </div>
</body>
</html>
