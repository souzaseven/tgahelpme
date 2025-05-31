<?php
// Conexão com o banco de dados
$host = '108.167.151.50';
$dbname = 'tgamea80_SUPORTE';
$user = 'tgamea80_tgamea80';
$password = 'anderson@2250';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verificar se o ID da sugestão foi passado
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);  // Garantir que o ID seja um número inteiro

        // Buscar a sugestão com base no ID
        $stmt = $pdo->prepare("SELECT * FROM sugestoes WHERE id = ?");
        $stmt->execute([$id]);
        $sugestao = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        echo "ID da sugestão não fornecido.";
        exit();
    }

    // Se o formulário for enviado para editar a sugestão
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Capturar dados do formulário
        $nome = $_POST['nome'];
        $sugestao_texto = $_POST['sugestao'];
        $aprovado = $_POST['aprovado'];
        $versao = $_POST['versao'];

        // Atualizar os dados no banco de dados
        if ($nome && $sugestao_texto && $versao) {
            $stmt = $pdo->prepare("UPDATE sugestoes SET nome = ?, sugestao = ?, aprovado = ?, versao = ? WHERE id = ?");
            $stmt->execute([$nome, $sugestao_texto, $aprovado, $versao, $id]);

            echo "Sugestão atualizada com sucesso!";
        }
    }

    // Excluir a sugestão
    if (isset($_POST['excluir'])) {
        $stmt = $pdo->prepare("DELETE FROM sugestoes WHERE id = ?");
        $stmt->execute([$id]);
        echo "Sugestão excluída com sucesso!";
        exit();
    }

    // Verificar se a sugestão foi aprovada e enviar o e-mail
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_email'])) {
        $email_cadastrado = $sugestao['email']; // E-mail do cadastrado
        $user_id = "G_JGgTp4G6U18OwfN";  // Substitua com o seu User ID real
        $service_id = "service_s0mt0kb";  // Seu Service ID
        $template_id = "template_pzw2end";  // Seu Template ID

        // Dados para enviar o e-mail
        $email_data = [
            "from_name" => "Sistema de Sugestões", // Nome do remetente
            "to_name" => $sugestao['nome'], // Nome do destinatário
            "to_email" => $email_cadastrado, // E-mail do destinatário
            "subject" => "Sugestão Aprovada",
            "message" => "Sua sugestão foi aprovada e estará disponível na versão " . $versao . ".",
            "versao" => $versao
        ];

        // Enviar o e-mail via API EmailJS
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.emailjs.com/api/v1.0/email/send");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);

        $post_fields = json_encode([
            'user_id' => $user_id,
            'service_id' => $service_id,
            'template_id' => $template_id,
            'template_params' => $email_data,
        ]);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) {
            echo "Erro ao enviar e-mail.";
        } else {
            echo "E-mail enviado com sucesso!";
        }
    }

} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Sugestão</title>

<link rel="icon" href="./icon bot tga.ico" type="image/x-icon">

<!--souza system-->
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044"
     crossorigin="anonymous"></script>

<!--meajudatga-->
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-E7ZNTJSRYR"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-E7ZNTJSRYR');
</script>

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-S8EC5C2WTG"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-S8EC5C2WTG');
</script>

    <link rel="stylesheet" href="style.css">
    <style>
        /* CSS para organizar os botões lado a lado e com cores diferentes */
        .button-container {
            display: flex;
            gap: 10px; /* Espaçamento entre os botões */
            justify-content: flex-start; /* Alinhamento à esquerda */
        }

        .button-container button {
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        /* Botão de salvar */
        .save-button {
            background-color: #4CAF50;
            color: white;
        }

        .save-button:hover {
            background-color: #45a049;
        }

        /* Botão de excluir */
        .delete-button {
            background-color: #f44336;
            color: white;
        }

        .delete-button:hover {
            background-color: #e53935;
        }

        /* Botão de enviar e-mail */
        .email-button {
            background-color: #008CBA;
            color: white;
        }

        .email-button:hover {
            background-color: #007bb5;
        }
    </style>
</head>
<body>
    <h1>Editar Sugestão</h1>

    <?php if ($sugestao): ?>
        <form action="" method="POST">
            <input type="hidden" name="id" value="<?php echo $sugestao['id']; ?>">

            <label for="nome">Nome:</label>
            <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($sugestao['nome']); ?>" required><br><br>

            <label for="sugestao">Sugestão:</label>
            <textarea id="sugestao" name="sugestao" required><?php echo htmlspecialchars($sugestao['sugestao']); ?></textarea><br><br>

            <label for="aprovado">Aprovado:</label>
            <select name="aprovado" id="aprovado">
                <option value="sim" <?php echo ($sugestao['aprovado'] == 'sim') ? 'selected' : ''; ?>>Sim</option>
                <option value="nao" <?php echo ($sugestao['aprovado'] == 'nao') ? 'selected' : ''; ?>>Não</option>
            </select><br><br>

            <label for="versao">Versão:</label>
            <input type="text" id="versao" name="versao" value="<?php echo htmlspecialchars($sugestao['versao']); ?>" required><br><br>

            <label for="email">E-mail cadastrado:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($sugestao['email']); ?>" disabled><br><br> <!-- Exibindo o e-mail cadastrado -->

            <div class="button-container">
                <button type="submit" class="save-button">Salvar</button>
                <button type="submit" name="excluir" class="delete-button" onclick="return confirm('Tem certeza que deseja excluir esta sugestão?');">Excluir Sugestão</button>
                <button type="submit" name="enviar_email" class="email-button">Enviar E-mail de Confirmação</button>
            </div>
        </form>
    <?php else: ?>
        <p>Sugestão não encontrada.</p>
    <?php endif; ?>

</body>
</html>
