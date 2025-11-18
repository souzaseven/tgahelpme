<?php
/**
 * tracker-simple.php
 * Versão otimizada — Scanner de arquivos e diretórios
 */

ini_set('display_errors', 0);

/* ============================================================
   FUNÇÃO - FORMATAR TAMANHO
   ============================================================ */
function formatSize($bytes)
{
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
}

/* ============================================================
   FUNÇÃO - SCAN RECURSIVO
   ============================================================ */
function scanProject($dir = __DIR__, $level = 0)
{
    if (!is_dir($dir)) return [];

    $items = array_diff(scandir($dir), ['.', '..']);
    $structure = [];

    // Pastas primeiro, arquivos depois
    usort($items, function ($a, $b) use ($dir) {
        return is_dir("$dir/$b") <=> is_dir("$dir/$a") ?: strnatcasecmp($a, $b);
    });

    foreach ($items as $item) {

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        $isDir = is_dir($path);

        $structure[] = [
            'name'      => $item,
            'type'      => $isDir ? 'directory' : 'file',
            'path'      => $path,
            'level'     => $level,
            'extension' => $isDir ? '' : strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            'size'      => $isDir ? '' : formatSize(@filesize($path)),
        ];

        // Recursão
        if ($isDir)
            $structure = array_merge($structure, scanProject($path, $level + 1));
    }

    return $structure;
}

/* ============================================================
   EXECUTAR SCAN
   ============================================================ */
$files = scanProject();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>📁 Estrutura do Projeto</title>

    <style>
        body {
            font-family: "Segoe UI", Roboto, Arial;
            margin: 20px;
            background: #f6f8fa;
        }

        h1 {
            color: #1a73e8;
            margin-bottom: 20px;
        }

        .wrapper {
            background: white;
            padding: 20px 25px;
            border-radius: 10px;
            max-width: 900px;
            margin: auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .item {
            margin: 5px 0;
            padding: 6px 10px;
            border-radius: 6px;
            transition: 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
        }

        .item:hover {
            background: #eef3ff;
        }

        .directory {
            font-weight: bold;
            color: #0055b3;
        }

        .file {
            color: #333;
        }

        .info {
            font-size: 0.8rem;
            color: #666;
        }

        .indent {
            margin-left: 18px;
        }
    </style>
</head>

<body>

<div class="wrapper">
    <h1>📁 Estrutura de Arquivos do Projeto</h1>

    <?php foreach ($files as $file): ?>
        <div class="item <?= $file['type'] ?> indent" style="margin-left: <?= $file['level'] * 20 ?>px">
            
            <?= $file['type'] === 'directory' ? '📂' : '📄' ?>

            <span><?= htmlspecialchars($file['name']) ?></span>

            <?php if ($file['type'] === 'file'): ?>
                <span class="info">(<?= $file['extension'] ?> • <?= $file['size'] ?>)</span>
            <?php endif; ?>

        </div>
    <?php endforeach; ?>

</div>

</body>
</html>
