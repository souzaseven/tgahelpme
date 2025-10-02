<?php require_once 'conexao.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel Admin - Links</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 20px;
            background: #f4f6f9;
        }

        h2, h3 {
            color: #2c3e50;
        }

        form {
            background: #ffffff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            max-width: 600px;
            margin-bottom: 40px;
        }

        input, textarea {
            width: 100%;
            padding: 12px;
            margin-top: 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 15px;
        }

        button {
            background-color: #007ced;
            color: white;
            border: none;
            padding: 12px 20px;
            font-size: 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #005bb5;
        }

        .card {
            background: white;
            padding: 16px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            max-width: 600px;
        }

        .card img {
            max-height: 70px;
            display: block;
            margin-bottom: 10px;
        }

        .card a {
            font-size: 18px;
            font-weight: bold;
            color: #007ced;
            text-decoration: none;
        }

        .card small {
            color: #555;
            display: block;
            margin-top: 5px;
            margin-bottom: 10px;
        }

        .card form {
            display: inline;
        }

        .container {
            max-width: 700px;
            margin: auto;
        }

        hr {
            margin: 40px 0;
        }
    </style>
</head>
<body>

<!--souza system-->
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044"
    crossorigin="anonymous"></script>
<div style="display: flex; justify-content: center; margin: 10px 0;"> 
  <img alt="visitas" src="https://hits.sh/tgameajuda.com/consultasugestaophp.html.svg?color=007ced&label=visitas&labelColor=FFFFFF&logo=https%3A%2F%2Fraw.githubusercontent.com%2Fsouzaseven%2Ftgahelpme%2FDesafios%2Ficon%2520bot%2520tga.ico"/>
</div>

<div class="container">
    <h2>Adicionar novo link</h2>
    <form method="post" action="salvar_link.php">
        <input type="text" name="url" placeholder="Cole o link aqui" required>
        <input type="text" name="titulo" placeholder="Título (opcional)">
        <textarea name="descricao" rows="3" placeholder="Descrição (opcional)"></textarea>
        <input type="text" name="imagem" placeholder="URL da imagem (opcional)">
        <button type="submit">Salvar link</button>
    </form>

    <hr>

    <h3>Links cadastrados:</h3>

    <?php
    $links = $pdo->query("SELECT * FROM links ORDER BY data_criacao DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($links as $link):
    ?>
    <div class="card">
        <?php if ($link['imagem']): ?>
            <img src="<?= htmlspecialchars($link['imagem']) ?>" alt="Imagem">
        <?php endif; ?>
        <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank"><?= htmlspecialchars($link['titulo']) ?></a>
        <small><?= htmlspecialchars($link['descricao']) ?></small>
        <form method="post" action="excluir.php" onsubmit="return confirm('Tem certeza que deseja excluir este link?');">
            <input type="hidden" name="id" value="<?= $link['id'] ?>">
            <button type="submit">Excluir</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>

</body>
</html>
