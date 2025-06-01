<?php
header('Content-Type: application/json');

// Evitar cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Exibir erros apenas para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    $diretorio = './uploads/workflow/';
    $logArquivo = $diretorio . 'log.json';

    if (!is_dir($diretorio)) {
        throw new Exception('O diretório não existe.');
    }

    $arquivos = array_diff(scandir($diretorio, SCANDIR_SORT_DESCENDING), ['.', '..']);
    if (empty($arquivos)) {
        throw new Exception('Nenhum arquivo encontrado.');
    }

    $resultado = [];
    foreach ($arquivos as $arquivo) {
        $caminho = $diretorio . $arquivo;
        if (is_file($caminho)) {
            $resultado[] = [
                'sequencial' => count($resultado) + 1,
                'nome_arquivo' => pathinfo($arquivo, PATHINFO_FILENAME),
                'extensao' => pathinfo($arquivo, PATHINFO_EXTENSION),
                'caminho' => $caminho
            ];
        }
    }

    echo json_encode($resultado, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['erro' => $e->getMessage()]);
    exit;
}
?>
