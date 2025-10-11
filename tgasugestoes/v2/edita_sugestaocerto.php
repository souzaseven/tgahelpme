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
        $id = $_GET['id'];

        // Buscar a sugestão com base no ID
        $stmt = $pdo->prepare("SELECT * FROM sugestoes WHERE id = ?");
        $stmt->execute([$id]);
        $sugestao = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        echo "ID da sugestão não fornecido.";
        exit();
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
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Editar Sugestão</h1>

    <?php if ($sugestao): ?>
        <form action="atualizar_sugestao.php" method="POST">
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

            <button type="submit">Salvar</button>
        </form>
    <?php else: ?>
        <p>Sugestão não encontrada.</p>
    <?php endif; ?>

</body>
</html>
