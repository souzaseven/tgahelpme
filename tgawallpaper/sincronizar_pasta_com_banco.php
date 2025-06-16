<?php
require 'conexao.php';
header('Content-Type: application/json');

$tipos = ['tga', 'outros'];
$resultados = [];

foreach ($tipos as $tipo) {
    $pasta = $_SERVER['DOCUMENT_ROOT'] . "/tgawallpaper/$tipo";
    if (!is_dir($pasta)) continue;

    $arquivos = array_diff(scandir($pasta), ['.', '..']);
    foreach ($arquivos as $arquivo) {
        $nomeArquivo = basename($arquivo);

        // Verifica se já está no banco
        $verifica = $pdo->prepare("SELECT COUNT(*) FROM wallpapers WHERE nome_arquivo = ? AND tipo = ?");
        $verifica->execute([$nomeArquivo, $tipo]);
        $existe = $verifica->fetchColumn();

        if ($existe) {
            $resultados[] = [
                'arquivo' => $nomeArquivo,
                'tipo' => $tipo,
                'status' => 'já existe'
            ];
            continue;
        }

        $caminhoCompleto = "$pasta/$nomeArquivo";
        $imagemBinaria = @file_get_contents($caminhoCompleto);

        if ($imagemBinaria === false) {
            $resultados[] = [
                'arquivo' => $nomeArquivo,
                'tipo' => $tipo,
                'status' => 'erro ao ler'
            ];
            continue;
        }

        // Insere no banco
        $stmt = $pdo->prepare("INSERT INTO wallpapers (nome_arquivo, data_upload, imagem, tipo) VALUES (?, NOW(), ?, ?)");
        $stmt->execute([$nomeArquivo, $imagemBinaria, $tipo]);

        $resultados[] = [
            'arquivo' => $nomeArquivo,
            'tipo' => $tipo,
            'status' => 'inserido'
        ];
    }
}

echo json_encode([
    'success' => true,
    'resultados' => $resultados
]);
