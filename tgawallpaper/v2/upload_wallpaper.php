<?php
require 'conexao.php';
header('Content-Type: application/json');

// 1. Verificação do tipo
if (!isset($_POST['tipo']) || !in_array($_POST['tipo'], ['tga', 'outros'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tipo inválido ou ausente.']);
    exit;
}

// 2. Verificação do arquivo
if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Arquivo ausente ou com erro.']);
    exit;
}

$tipo = $_POST['tipo'];
$arquivo = $_FILES['arquivo'];
$nome = basename($arquivo['name']);
//$pastaDestino = __DIR__ . "/tgawallpaper/$tipo";
$pastaDestino = $_SERVER['DOCUMENT_ROOT'] . "/tgawallpaper/$tipo";

$destino = "$pastaDestino/$nome";

// 3. Garante que a pasta exista
if (!is_dir($pastaDestino)) {
    if (!mkdir($pastaDestino, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Falha ao criar diretório no servidor.']);
        exit;
    }
}

// 4. Salva o arquivo no servidor
if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar o arquivo no servidor.']);
    exit;
}

// 5. Lê a imagem para armazenar no banco (não remove o arquivo do servidor)
$imagemBinaria = file_get_contents($destino);

// 6. Salva no banco
try {
    $stmt = $pdo->prepare("INSERT INTO wallpapers (nome_arquivo, imagem, tipo) VALUES (?, ?, ?)");
    $stmt->execute([$nome, $imagemBinaria, $tipo]);

    echo json_encode(['success' => true, 'message' => 'Upload realizado com sucesso.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar no banco de dados.',
        'erro' => $e->getMessage()
    ]);
}
