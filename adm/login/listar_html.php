<?php
function listarArquivosHtml($pasta) {
    $arquivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pasta));
    $htmls = [];

    foreach ($arquivos as $arquivo) {
        if ($arquivo->isFile() && preg_match('/\.html$/', $arquivo->getFilename())) {
            $htmls[] = $arquivo->getPathname();
        }
    }

    return $htmls;
}

echo "🔍 Procurando arquivos .html...\n\n";

$lista = listarArquivosHtml(__DIR__);

if (count($lista) === 0) {
    echo "✅ Nenhum arquivo .html encontrado.\n";
} else {
    foreach ($lista as $arquivo) {
        echo "📄 " . $arquivo . "\n";
    }
    echo "\n🔢 Total: " . count($lista) . " arquivos .html encontrados.\n";
}
