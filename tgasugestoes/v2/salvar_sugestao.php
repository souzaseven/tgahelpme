<?php
require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $resumo = $_POST['resumo'] ?? '';
    $sugestao = $_POST['sugestao'] ?? '';
    $imagemNome = '';

    // Upload da imagem (se houver)
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $extensao = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
        $imagemNome = uniqid('img_') . '.' . $extensao;
        move_uploaded_file($_FILES['imagem']['tmp_name'], 'img/' . $imagemNome);
    }

    // Inserir no banco
    $sql = "INSERT INTO sugestoes (nome, email, resumo, sugestao, imagem, aprovado, versao) 
            VALUES (:nome, :email, :resumo, :sugestao, :imagem, 'nao', '')";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nome' => $nome,
        ':email' => $email,
        ':resumo' => $resumo,
        ':sugestao' => $sugestao,
        ':imagem' => $imagemNome
    ]);

    // Redirecionar para o painel após salvar
    header("Location: sugestoes.html");
    exit;

} else {
    // Requisição inválida
    header("Location: sugestoes.html?erro=1");
    exit;
}
?>
