<?php
header('Content-Type: application/json');

$diretorio = './uploads/formulas/';
$logArquivo = $diretorio . 'log.json';

if (!is_dir($diretorio)) {
    echo json_encode(['erro' => 'O diretório não existe.']);
    exit;
}

// Carrega os registros do log.json, se existir
$logs = file_exists($logArquivo) ? json_decode(file_get_contents($logArquivo), true) : [];

// Filtra apenas os arquivos no diretório, excluindo o log.json
$arquivos = array_filter(glob($diretorio . '/*.txt'), function ($arquivo) use ($logArquivo) {
    return basename($arquivo) !== basename($logArquivo);
});

if (empty($arquivos)) {
    echo json_encode(['erro' => 'Nenhum arquivo encontrado.']);
    exit;
}

$resultado = [];
$arquivosPresentesNoLog = [];

// Processa os arquivos presentes no diretório
foreach ($arquivos as $arquivo) {
    $nomeArquivo = basename($arquivo);

    // Verifica se o arquivo já está no log.json
    $infoLog = array_filter($logs, fn($log) => $log['arquivo'] === $nomeArquivo);
    $infoLog = reset($infoLog);

    if ($infoLog) {
        // Usa a data do log como referência principal
        $adicionadoPor = $infoLog['adicionadoPor'];
        $sequencial = $infoLog['sequencial'];
        $dataReferencia = strtotime(explode('em ', $adicionadoPor)[1]);
    } else {
        // Caso não esteja no log, usa a data de modificação do servidor
        $dataModificacao = filemtime($arquivo);
        $adicionadoPor = "Data do servidor: " . date('d/m/Y H:i', $dataModificacao);
        $sequencial = null; // Sequencial será calculado depois
        $dataReferencia = $dataModificacao;

        // Adiciona o arquivo ao log.json com a data real de modificação
        $logs[] = [
            'arquivo' => $nomeArquivo,
            'adicionadoPor' => $adicionadoPor,
            'sequencial' => $sequencial // Sequencial será calculado depois
        ];
    }

    $arquivosPresentesNoLog[] = $nomeArquivo;

    $resultado[] = [
        'arquivo' => $nomeArquivo,
        'caminho' => $arquivo,
        'adicionadoPor' => $adicionadoPor,
        'sequencial' => $sequencial,
        'dataReferencia' => $dataReferencia // Garante que a data usada seja consistente
    ];
}

// Remove arquivos que foram excluídos da pasta, mas ainda estão no log
$logs = array_filter($logs, fn($log) => in_array($log['arquivo'], $arquivosPresentesNoLog));

// Ordena os arquivos pela data de referência (mais recentes no topo)
usort($resultado, function ($a, $b) {
    return $b['dataReferencia'] <=> $a['dataReferencia'];
});

// Recalcula os números sequenciais com base na nova ordem (começando de 1)
$sequencialCounter = count($resultado); // Começa da quantidade total de arquivos
foreach ($resultado as &$item) {
    // A sequência agora começa de 1 e vai decrementando
    $item['sequencial'] = $sequencialCounter--; // Decrementa a sequência
}

// Atualiza o log.json com os novos arquivos e a sequência recalculada
file_put_contents($logArquivo, json_encode($logs, JSON_PRETTY_PRINT));

// Remove a chave 'dataReferencia' antes de retornar o JSON
foreach ($resultado as &$item) {
    unset($item['dataReferencia']);
}

echo json_encode($resultado, JSON_PRETTY_PRINT);
?>
