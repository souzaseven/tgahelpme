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
// Se o formulário for enviado para editar a sugestão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['excluir']) && !isset($_POST['enviar_email'])) {
    // Capturar dados do formulário
    $nome = $_POST['nome'];
    $resumo = $_POST['resumo'];
    $sugestao_texto = $_POST['sugestao'];
    $aprovado = $_POST['aprovado'];
    $versao = $_POST['versao'];

    // Inicialmente manter imagem anterior
    $imagemNome = $sugestao['imagem'];

    // Verificar se nova imagem foi enviada
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $extensao = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
        $imagemNome = uniqid('img_') . '.' . $extensao;
        move_uploaded_file($_FILES['imagem']['tmp_name'], 'img/' . $imagemNome);
    }

if ($nome && $sugestao_texto && $versao) {
    $stmt = $pdo->prepare("UPDATE sugestoes SET nome = ?, resumo = ?, sugestao = ?, aprovado = ?, versao = ?, imagem = ? WHERE id = ?");
    $stmt->execute([$nome, $resumo, $sugestao_texto, $aprovado, $versao, $imagemNome, $id]);

    // Emite HTML/JS para mostrar aviso e redirecionar
    echo "<div id='sucesso' style='
        position: fixed;
        top: 20px;
        right: 20px;
        background-color: #4CAF50;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0,0,0,0.2);
        font-family: sans-serif;
        z-index: 9999;
        '>
        Sugestão atualizada com sucesso!
    </div>
    <script>
        setTimeout(() => {
            window.location.href = 'consultasugestao.php';
        }, 2000);
    </script>";
    exit;
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
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    width: 100%;
}
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr !important;
    }

    .form-grid textarea,
    .form-grid input,
    .form-grid select {
        font-size: 16px;
        width: 100%;
    }

    body {
        padding: 10px;
        margin: 0;
    }
}


.form-grid textarea,
.form-grid input,
.form-grid select {
    width: 100%;
}

.form-grid-full {
    grid-column: 1 / -1;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}
body {
    padding: 20;
    margin: 20;
}

.container {
    width: 90%;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    box-sizing: border-box;
}

form {
    width: 100%;
    padding: 10px;
    box-sizing: border-box;
}




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
<style>
#image-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.85);
    justify-content: center;
    align-items: center;
}

#image-modal img {
    max-width: 90%;
    max-height: 90%;
    box-shadow: 0 0 20px #000;
    border-radius: 10px;
    cursor: pointer;
}

.form-wrapper {
    width: 80%;
    margin: 0 auto;
    padding: 10px 15px;
    box-sizing: border-box;
}

.form-grid-full {
    grid-column: 1 / -1;
}



</style>

</head>
<body>
    <h1>Editar Sugestão</h1>
 <div class="form-wrapper">
    <?php if ($sugestao): ?>
      <form action="" method="POST" enctype="multipart/form-data" style="width: 100%;"><!--
    <div class="form-grid">
        <div>
            <label for="nome">Nome:</label>
            <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($sugestao['nome']) ?>" required>
        </div>

        <div>
            <label for="email">E-mail cadastrado:</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($sugestao['email']) ?>" disabled>
        </div>

        <div class="form-grid-full">
            <label for="resumo">Resumo:</label>
            <textarea id="resumo" name="resumo" rows="2" required><?= htmlspecialchars($sugestao['resumo']) ?></textarea>
        </div>

        <div class="form-grid-full">
            <label for="sugestao">Sugestão:</label>
            <textarea id="sugestao" name="sugestao" rows="4" required><?= htmlspecialchars($sugestao['sugestao']) ?></textarea>
        </div>

        <div>
            <label for="aprovado">Aprovado:</label>
            <select name="aprovado" id="aprovado">
                <option value="sim" <?= ($sugestao['aprovado'] == 'sim') ? 'selected' : ''; ?>>Sim</option>
                <option value="nao" <?= ($sugestao['aprovado'] == 'nao') ? 'selected' : ''; ?>>Não</option>
            </select>
        </div>

        <div>
            <label for="versao">Versão:</label>
            <input type="text" id="versao" name="versao" value="<?= htmlspecialchars($sugestao['versao']) ?>" required>
        </div>

        <div class="form-grid-full">
            <label for="imagem">Imagem:</label>
            <?php if (!empty($sugestao['imagem']) && file_exists('img/' . $sugestao['imagem'])): ?>
                <br><img src="img/<?= htmlspecialchars($sugestao['imagem']) ?>" style="max-width: 200px;"><br>
            <?php else: ?>
                <p style="color:gray;">Nenhuma imagem enviada.</p>
            <?php endif; ?>
            <input type="file" id="imagem" name="imagem" accept="image/*">
        </div>

        <div class="form-grid-full button-container">
            <button type="submit" class="save-button">Salvar</button>
            <button type="submit" name="excluir" class="delete-button" onclick="return confirm('Tem certeza que deseja excluir esta sugestão?');">Excluir Sugestão</button>
            <button type="submit" name="enviar_email" class="email-button">Enviar E-mail de Confirmação</button>
        </div>
    </div>-->

<div class="form-grid" style="grid-template-columns: repeat(4, 1fr);">
    <!-- Linha 1: Nome, Email, Aprovado, Versão -->
    <div>
        <label for="nome">Nome:</label>
        <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($sugestao['nome']) ?>" required>
    </div>
    <div>
        <label for="email">E-mail cadastrado:</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($sugestao['email']) ?>" disabled>
    </div>
    <div>
        <label for="aprovado">Aprovado:</label>
        <select name="aprovado" id="aprovado">
            <option value="sim" <?= ($sugestao['aprovado'] == 'sim') ? 'selected' : ''; ?>>Sim</option>
            <option value="nao" <?= ($sugestao['aprovado'] == 'nao') ? 'selected' : ''; ?>>Não</option>
        </select>
    </div>
    <div>
        <label for="versao">Versão:</label>
        <input type="text" id="versao" name="versao" value="<?= htmlspecialchars($sugestao['versao']) ?>" required>
    </div>

    <!-- Linha 2: Resumo -->
    <div class="form-grid-full">
        <label for="resumo">Resumo:</label>
        <textarea id="resumo" name="resumo" rows="2" required><?= htmlspecialchars($sugestao['resumo']) ?></textarea>
    </div>

    <!-- Linha 3: Sugestão -->
    <div class="form-grid-full">
        <label for="sugestao">Sugestão:</label>
        <textarea id="sugestao" name="sugestao" rows="4" required><?= htmlspecialchars($sugestao['sugestao']) ?></textarea>
    </div>

    <!-- Linha 4: Imagem -->
    <div class="form-grid-full">
        <label for="imagem">Imagem:</label>
        <?php if (!empty($sugestao['imagem']) && file_exists('img/' . $sugestao['imagem'])): ?>
            <br><img src="img/<?= htmlspecialchars($sugestao['imagem']) ?>" style="max-width: 200px;"><br>
        <?php else: ?>
            <p style="color:gray;">Nenhuma imagem enviada.</p>
        <?php endif; ?>
        <input type="file" id="imagem" name="imagem" accept="image/*">
    </div>

    <!-- Linha 5: Botões -->
    <div class="form-grid-full button-container">
        <button type="submit" class="save-button">Salvar</button>
        <button type="submit" name="excluir" class="delete-button" onclick="return confirm('Tem certeza que deseja excluir esta sugestão?');">Excluir Sugestão</button>
        <button type="submit" name="enviar_email" class="email-button">Enviar E-mail de Confirmação</button>
    </div>
</div>

</div>


<br><br> <!-- Exibindo o e-mail cadastrado 

            <div class="button-container">
                <button type="submit" class="save-button">Salvar</button>
                <button type="submit" name="excluir" class="delete-button" onclick="return confirm('Tem certeza que deseja excluir esta sugestão?');">Excluir Sugestão</button>
                <button type="submit" name="enviar_email" class="email-button">Enviar E-mail de Confirmação</button>
            </div>-->
        </form>
    <?php else: ?>
        <p>Sugestão não encontrada.</p>
    <?php endif; ?>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('image-modal');
    const modalImg = modal.querySelector('img');

    // Clique para ampliar qualquer imagem da página
    document.querySelectorAll('img').forEach(img => {
        img.addEventListener('click', function (e) {
            e.preventDefault(); // Impede comportamento padrão
            modalImg.src = this.src;
            modal.style.display = 'flex';
        });
    });

    // Clicar fora da imagem ou na própria imagem fecha o modal
    modal.addEventListener('click', function () {
        modal.style.display = 'none';
        modalImg.src = '';
    });
});
</script>

<div id="image-modal" onclick="this.style.display='none'">
    <img src="" alt="Visualização">
</div>

<div id="image-modal">
    <img src="" alt="Imagem Ampliada">
</div>
<img src="https://profile-counter.glitch.me/tgahelpme-editasugestao/count.svg" alt="Contador de Visitantes" style="border: 2px solid; border-radius: 8px; background: transparent; padding: 5px;">
</body>
</html>
