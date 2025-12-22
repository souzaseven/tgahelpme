<?php
// ============================================================
// listar_diretorios.php (v2)
// - Lista pastas/subpastas/arquivos em árvore
// - Mostra CAMINHO relativo (evita confusão)
// - Evita itens duplicados por realpath (symlink)
// ============================================================

$baseDir = realpath(__DIR__); // ou troque para __DIR__ . '/operador_filas'

if (!$baseDir) {
    die("Diretório inválido");
}

function formatarTamanho($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
    return round($bytes / 1048576, 2) . ' MB';
}

$visitados = []; // evita duplicar por realpath

function listarConteudo($dir, $nivel = 0) {
    global $baseDir, $visitados;

    $real = realpath($dir);
    if (!$real || strpos($real, $baseDir) !== 0) return;

    $itens = scandir($dir);
    if (!$itens) return;

    // Ordena: pastas primeiro, depois arquivos
    usort($itens, function ($a, $b) use ($dir) {
        if ($a === '.' || $a === '..') return 1;
        if ($b === '.' || $b === '..') return -1;

        $aDir = is_dir($dir . DIRECTORY_SEPARATOR . $a);
        $bDir = is_dir($dir . DIRECTORY_SEPARATOR . $b);

        if ($aDir !== $bDir) return $aDir ? -1 : 1;
        return strcasecmp($a, $b);
    });

    foreach ($itens as $item) {
        if ($item === '.' || $item === '..') continue;

        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        $realItem = realpath($fullPath) ?: $fullPath;

        // evita duplicados por realpath (symlink/alias)
        if (isset($visitados[$realItem])) {
            continue;
        }
        $visitados[$realItem] = true;

        $isDir = is_dir($fullPath);

        $rel = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $realItem);
        $padding = $nivel * 22;

        ?>
        <tr>
            <td>
                <span class="badge <?= $isDir ? 'pasta' : 'arquivo' ?>">
                    <?= $isDir ? 'PASTA' : 'ARQUIVO' ?>
                </span>
            </td>

            <td style="padding-left: <?= $padding ?>px">
                <?= $isDir ? '📂' : '📄' ?>
                <strong><?= htmlspecialchars($item) ?></strong>
                <div class="path"><?= htmlspecialchars($rel) ?></div>
            </td>

            <td><?= $isDir ? '-' : formatarTamanho(@filesize($fullPath) ?: 0) ?></td>
            <td><?= date('d/m/Y H:i', @filemtime($fullPath) ?: time()) ?></td>
        </tr>
        <?php

        if ($isDir) {
            listarConteudo($fullPath, $nivel + 1);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Explorador de Diretórios</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { background:#0f172a; color:#e5e7eb; font-family:system-ui,sans-serif; margin:0; padding:20px; }
h1 { font-size:22px; margin:0 0 6px; }
small { color:#94a3b8; }
table { width:100%; border-collapse:collapse; background:#020617; border-radius:10px; overflow:hidden; margin-top:14px; }
th, td { padding:12px; border-bottom:1px solid #1e293b; text-align:left; vertical-align:top; }
th { font-size:13px; color:#94a3b8; }
.badge { padding:4px 10px; border-radius:8px; font-size:12px; display:inline-block; }
.pasta { background:#1e40af; }
.arquivo { background:#065f46; }
.path { font-size:12px; color:#94a3b8; margin-top:3px; }
</style>
</head>
<body>
<h1>📁 Estrutura de Diretórios</h1>
<small><?= htmlspecialchars($baseDir) ?></small>

<table>
  <thead>
    <tr>
      <th>Tipo</th>
      <th>Nome + Caminho</th>
      <th>Tamanho</th>
      <th>Modificado em</th>
    </tr>
  </thead>
  <tbody>
    <?php listarConteudo($baseDir); ?>
  </tbody>
</table>
</body>
</html>
