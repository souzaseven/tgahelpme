<?php
// Definir as credenciais de usuário
$valid_user = 'anderson';
$valid_password = 'soueu';

// Variáveis para mensagem de erro
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Verificar se as credenciais são válidas
    if ($username == $valid_user && $password == $valid_password) {
        // Redirecionar para a página de edição, passando o ID como parâmetro
        header("Location: edita_sugestao.php?id=" . $_GET['id']);
        exit();
    } else {
        $error_message = 'Usuário ou senha inválidos.';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login ADM</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
 <a href="https://tgameajuda.com/#sugestoes" class="btn-voltar">
    <i class="fas fa-arrow-left"></i> Voltar
  </a>


    <h1>Login Editar sugestão</h1>
<h4>Somente o administrador tem acesso</h4>

    <?php if ($error_message): ?>
        <p class="error"><?php echo $error_message; ?></p>
    <?php endif; ?>

    <form action="" method="POST">
        <label for="username">Usuário:</label>
        <input type="text" id="username" name="username" required><br><br>

        <label for="password">Senha:</label>
        <input type="password" id="password" name="password" required><br><br>

        <button type="submit">Entrar</button>
    </form>

</body>
</html>
