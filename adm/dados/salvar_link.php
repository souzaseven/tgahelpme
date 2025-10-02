<?php
require_once 'conexao.php';

$id = intval($_POST['id'] ?? 0);
$url = trim($_POST['url'] ?? '');
$titulo = trim($_POST['titulo'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$imagem = trim($_POST['imagem'] ?? '');
$categoria = trim($_POST['categoria'] ?? '');

if ($url === '' || $categoria === '') {
    die("URL e Categoria são obrigatórias.");
}

// Busca título automático se não preenchido
if ($titulo === '') {
    $html = @file_get_contents($url);
    if ($html) {
        preg_match("/<title>(.*?)<\/title>/i", $html, $matches);
        $titulo = $matches[1] ?? 'Sem título';
    }
}

if ($id > 0) {
    // Atualização
    $stmt = $pdo->prepare("UPDATE links SET titulo=?, descricao=?, url=?, imagem=?, categoria=? WHERE id=?");
    $stmt->execute([$titulo, $descricao, $url, $imagem, $categoria, $id]);
} else {
    // Novo cadastro
    $stmt = $pdo->prepare("INSERT INTO links (titulo, descricao, url, imagem, categoria) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$titulo, $descricao, $url, $imagem, $categoria]);
}

echo "OK";