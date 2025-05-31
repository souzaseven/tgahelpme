<?php
// Conexão com o banco de dados
$host = '108.167.151.50';
$dbname = 'tgamea80_SUPORTE';
$user = 'tgamea80_tgamea80';
$password = 'anderson@2250';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verifica se os dados necessários foram recebidos
    if (isset($_POST['nome']) && isset($_POST['email']) && isset($_POST['sugestao'])) {
        $nome = $_POST['nome'];
        $email = $_POST['email'];
        $sugestao = $_POST['sugestao'];

        // Insere os dados no banco de dados
        $stmt = $pdo->prepare("INSERT INTO sugestoes (nome, email, sugestao, data_criacao) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$nome, $email, $sugestao]);

        echo "Sugestão enviada com sucesso!";
    } else {
        echo "Todos os campos são obrigatórios.";
    }
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>
