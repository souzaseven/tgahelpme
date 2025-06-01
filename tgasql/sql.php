<?php
header('Content-Type: application/json');

$uploadDirectory = './uploads/';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!is_dir($uploadDirectory)) {
        echo json_encode(['erro' => 'Diretório de uploads não encontrado.']);
        exit;
    }

    $arquivos = array_diff(scandir($uploadDirectory, SCANDIR_SORT_DESCENDING), ['.', '..']);

    if (empty($arquivos)) {
        echo json_encode(['erro' => 'Nenhum arquivo encontrado.']);
        exit;
    }

    // Ordenar os arquivos pela data de modificação, mais recentes primeiro
    $arquivosOrdenados = [];
    foreach ($arquivos as $arquivo) {
        $caminhoArquivo = $uploadDirectory . $arquivo;
        if (is_file($caminhoArquivo) && pathinfo($arquivo, PATHINFO_EXTENSION) === 'sql') {
            $arquivosOrdenados[] = [
                'nome_arquivo' => pathinfo($arquivo, PATHINFO_FILENAME),
                'extensao' => 'sql',
                'caminho' => $caminhoArquivo,
                'data_modificacao' => filemtime($caminhoArquivo)
            ];
        }
    }

    if (empty($arquivosOrdenados)) {
        echo json_encode(['erro' => 'Nenhum arquivo .sql encontrado.']);
        exit;
    }

    // Ordena os arquivos pela data de modificação
    usort($arquivosOrdenados, function($a, $b) {
        return $b['data_modificacao'] - $a['data_modificacao'];
    });

    // Atribui um número sequencial aos arquivos
    foreach ($arquivosOrdenados as $index => &$arquivo) {
        $arquivo['sequencial'] = $index + 1;
    }

    echo json_encode($arquivosOrdenados);
}
?>