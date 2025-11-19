<?php
// tracker-simple.php - Versão simplificada do rastreador
function scanProject($dir = __DIR__, $level = 0) {
    $structure = [];
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        $isDir = is_dir($path);
        
        $structure[] = [
            'name' => $item,
            'type' => $isDir ? 'directory' : 'file',
            'path' => $path,
            'level' => $level,
            'extension' => $isDir ? '' : strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            'size' => $isDir ? '' : format_size(filesize($path))
        ];
        
        if ($isDir) {
            $structure = array_merge($structure, scanProject($path, $level + 1));
        }
    }
    
    return $structure;
}

function format_size($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $base = log($bytes, 1024);
    return round(pow(1024, $base - floor($base)), 2) . ' ' . $units[floor($base)];
}

// Executar scan
$files = scanProject();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Arquivos do Projeto</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .file { color: #333; margin: 5px 0; padding-left: 20px; }
        .dir { color: #007acc; font-weight: bold; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>📁 Estrutura de Arquivos</h1>
    <?php foreach ($files as $file): ?>
        <div class="<?= $file['type'] ?>" style="margin-left: <?= $file['level'] * 20 ?>px">
            <?= $file['type'] === 'directory' ? '📁' : '📄' ?>
            <?= $file['name'] ?>
            <?php if ($file['type'] === 'file'): ?>
                <small>(<?= $file['extension'] ?> - <?= $file['size'] ?>)</small>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</body>
</html>