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
    <title>Login Administrativo | Editar Sugestão</title>
    <link rel="stylesheet" href="./css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <a href="https://tgameajuda.com/#sugestoes" class="btn-voltar">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>

    <div class="login-container">
        <h1>Login Editar Sugestão</h1>
        <h4>Somente o administrador tem acesso</h4>

        <?php if ($error_message): ?>
            <p class="error"><?php echo $error_message; ?></p>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="input-group">
                <label for="username">Usuário:</label>
                <input type="text" id="username" name="username" required placeholder="Digite seu usuário">
            </div>

            <div class="input-group">
                <label for="password">Senha:</label>
                <input type="password" id="password" name="password" required placeholder="Digite sua senha">
            </div>

            <button type="submit">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </button>
        </form>
    </div>

    <div class="visitor-counter">
        <img src="https://hits.sh/tgameajuda.com/tgasugestao/loginedita.html.svg?color=007ced&label=visitas&labelColor=FFFFFF&logo=https%3A%2F%2Fraw.githubusercontent.com%2Fsouzaseven%2Ftgahelpme%2FDesafios%2Ficon%2520bot%2520tga.ico" 
             alt="Contador de visitas">
    </div>
</body>
</html>