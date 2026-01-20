<?php
header('Content-Type: application/json; charset=utf-8');

$baseDir = realpath(__DIR__ . '/../txt');

function lerDiretorio($dir, $basePath) {
    $resultado = [];

    $itens = scandir($dir);
    foreach ($itens as $item) {
        if ($item === '.' || $item === '..') continue;

        $caminhoCompleto = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($caminhoCompleto)) {
            $resultado[] = [
                'tipo' => 'pasta',
                'nome' => formatarNome($item),
                'slug' => $item,
                'filhos' => lerDiretorio($caminhoCompleto, $basePath)
            ];
        } elseif (pathinfo($item, PATHINFO_EXTENSION) === 'txt') {
            $resultado[] = [
                'tipo' => 'arquivo',
                'nome' => formatarNome($item),
                'arquivo' => str_replace($basePath . DIRECTORY_SEPARATOR, '', $caminhoCompleto)
            ];
        }
    }

    return $resultado;
}

function formatarNome($texto) {
    $texto = str_replace(['_', '-'], ' ', $texto);
    $texto = preg_replace('/\.txt$/', '', $texto);
    return ucwords($texto);
}

echo json_encode(lerDiretorio($baseDir, $baseDir), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
