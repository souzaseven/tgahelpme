<?php
$diretorio = __DIR__ . '/arquivos';

if (!is_dir($diretorio)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Diretório não encontrado']);
    exit;
}

// Busca os arquivos e ordena por data de modificação, mas com menos operações lentas
$arquivos = array_filter(scandir($diretorio), fn($f) => is_file("$diretorio/$f"));
$dados = [];

foreach ($arquivos as $arquivo) {
    $dados[] = [
        'nome' => $arquivo,
        'url' => './arquivos/' . rawurlencode($arquivo),
        'data' => filemtime("$diretorio/$arquivo")
    ];
}

// Ordenar: mais recentes primeiro (por timestamp da modificação)
usort($dados, fn($a, $b) => $b['data'] - $a['data']);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $dados]);
