<?php
// Ativa exibição de erros para depuração (comente estas linhas em produção)
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// Define cabeçalho para retorno JSON
header('Content-Type: application/json');

try {
    // Caminho do diretório onde os arquivos estão armazenados
    $diretorio = __DIR__ . '/uploads/';
    
    // Verifica se o diretório existe
    if (!is_dir($diretorio)) {
        echo json_encode(['erro' => 'Diretório não encontrado']);
        exit;
    }

    // Lista os arquivos no diretório
    $arquivos = scandir($diretorio);

    // Adiciona log para verificar arquivos listados
    // file_put_contents('debug.log', "Arquivos encontrados: " . print_r($arquivos, true), FILE_APPEND);

    // Filtra os arquivos permitidos (ex.: .sql e .txt)
    $listaArquivos = array_filter($arquivos, function ($arquivo) use ($diretorio) {
        return is_file($diretorio . $arquivo) && in_array(strtolower(pathinfo($arquivo, PATHINFO_EXTENSION)), ['sql', 'txt']);
    });

    // Adiciona log para verificar arquivos filtrados
    // file_put_contents('debug.log', "Arquivos filtrados: " . print_r($listaArquivos, true), FILE_APPEND);

    // Constrói a resposta com os dados dos arquivos
    $resultado = [];
    foreach ($listaArquivos as $arquivo) {
        $resultado[] = [
            'arquivo' => $arquivo,
            'adicionadoPor' => '', // Substitua por informações dinâmicas se necessário
            'caminho' => './uploads/' . $arquivo
        ];
    }

    // Retorna os arquivos em JSON
    echo json_encode($resultado);
} catch (Exception $e) {
    // Captura erros inesperados
    echo json_encode(['erro' => 'Erro ao listar os arquivos: ' . $e->getMessage()]);
}
?>
