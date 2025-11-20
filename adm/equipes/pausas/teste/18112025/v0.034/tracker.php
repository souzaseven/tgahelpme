<?php
// tracker-simple.php - Rastreador de Arquivos (v2.0)
// -------------------------------------------------
// Mostra a estrutura completa da versão atual do projeto
// Mantido SEMPRE na raiz da versão (ex: /v0.000/)

// 🟦 Configurações Globais
$IGNORAR = ['.', '..', 'tracker.php', 'tracker-simple.php', 'setup_sistema.php', '.git', '.idea', 'node_modules'];

// Detectar versão automaticamente (nome da pasta atual)
$versaoAtual = basename(__DIR__);

// Função principal de scan
function scanProject($dir, $level = 0, $IGNORAR = []) {
    $structure = [];
    $items = scandir($dir);

    foreach ($items as $item) {
        if (in_array($item, $IGNORAR)) continue;

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        $isDir = is_dir($path);

        $structure[] = [
            'name'      => $item,
            'type'      => $isDir ? 'directory' : 'file',
            'path'      => $path,
            'level'     => $level,
            'extension' => $isDir ? '' : strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            'size'      => $isDir ? '' : format_size(filesize($path) ?: 0)
        ];

        if ($isDir) {
            $structure = array_merge(
                $structure,
                scanProject($path, $level + 1, $IGNORAR)
            );
        }
    }

    // Ordenar: diretórios primeiro
    usort($structure, function($a, $b){
        if ($a["type"] === $b["type"]) {
            return strcasecmp($a["name"], $b["name"]);
        }
        return $a["type"] === "directory" ? -1 : 1;
    });

    return $structure;
}

// Formatação de tamanho
function format_size($bytes) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

// Executar scan na raiz da versão atual
$files = scanProject(__DIR__, 0, $IGNORAR);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>📂 Estrutura da Versão <?= $versaoAtual ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background:#f8f9fb; }
        h1 { color:#007ced; margin-bottom:20px; }
        .file, .directory {
            padding: 5px 0;
            margin-left: 20px;
        }
        .directory {
            font-weight: bold;
            color:#007acc;
        }
        small { color:#444; font-size:12px; }
    </style>
</head>
<body>

<h1>📁 Estrutura da Versão <strong><?= $versaoAtual ?></strong></h1>

<?php foreach ($files as $file): ?>
    <div class="<?= $file['type'] ?>" style="margin-left: <?= $file['level'] * 20 ?>px">

        <?= $file['type'] === 'directory' ? '📁' : '📄' ?>
        <?= htmlspecialchars($file['name']) ?>

        <?php if ($file['type'] === 'file'): ?>
            <small>(<?= $file['extension'] ?> - <?= $file['size'] ?>)</small>
        <?php endif; ?>

    </div>
<?php endforeach; ?>

</body>
</html>
