<?php
$diretorio = './uploads/';

if (!is_dir($diretorio)) {
    die("Diretório não encontrado.");
}

$arquivos = glob($diretorio . '*.sql'); // Busca todos os arquivos .sql

foreach ($arquivos as $arquivo) {
    $novoNome = preg_replace('/\.sql$/', '.txt', $arquivo); // Substitui .sql por .txt

    if (rename($arquivo, $novoNome)) {
        echo "Renomeado: " . basename($arquivo) . " para " . basename($novoNome) . "<br>";
    } else {
        echo "Falha ao renomear: " . basename($arquivo) . "<br>";
    }
}

echo "Processo concluído.";
?>
