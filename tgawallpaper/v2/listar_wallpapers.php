<?php
require 'conexao.php';

$tipo = $_GET['tipo'] ?? null;

if (!$tipo || !in_array($tipo, ['tga', 'outros'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tipo inválido ou ausente.']);
    exit;
}

try {
   // $stmt = $pdo->prepare("SELECT id, nome_arquivo, data_upload FROM wallpapers WHERE tipo = ? ORDER BY data_upload DESC");

$stmt = $pdo->prepare("SELECT id, nome_arquivo, data_upload FROM wallpapers WHERE tipo = ? ORDER BY ordem ASC, data_upload DESC");

    $stmt->execute([$tipo]);
    $resultados = $stmt->fetchAll();

    // Constrói a resposta com os caminhos dos arquivos
    foreach ($resultados as &$item) {
       $item['caminho'] = "/tgawallpaper/$tipo/" . $item['nome_arquivo'];

    }

    echo json_encode([
        'success' => true,
        'data' => $resultados
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar dados',
        'error' => $e->getMessage()
    ]);
}
