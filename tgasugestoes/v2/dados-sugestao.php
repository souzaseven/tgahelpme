<?php
// dados-sugestao.php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nome'], $_POST['sugestao'])) {
    $nome = $_POST['nome'];
    $sugestao = $_POST['sugestao'];

    // Conexão com o banco de dados
    $host = '108.167.151.50';
    $dbname = 'tgamea80_SUPORTE';
    $user = 'tgamea80_tgamea80';
    $password = 'anderson@2250';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Inserir dados no banco
        $stmt = $pdo->prepare("INSERT INTO sugestoes (nome, sugestao, aprovado, versao, data_criacao) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nome, $sugestao, 'nao', '1.0', date('Y-m-d H:i:s')]);

        echo "Sugestão enviada com sucesso!";
    } catch (PDOException $e) {
        echo "Erro ao enviar sugestão: " . $e->getMessage();
    }
} else {
    echo "Por favor, preencha todos os campos.";
}
