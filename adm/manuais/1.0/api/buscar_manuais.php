<?php
header('Content-Type: application/json; charset=utf-8');

$base = realpath(__DIR__ . '/../txt');
$q = strtolower(trim($_GET['q'] ?? ''));

function scanDirRec($dir, $base, $q) {
  $out = [];

  foreach (scandir($dir) as $f) {
    if ($f === '.' || $f === '..') continue;
    $path = $dir . DIRECTORY_SEPARATOR . $f;

    if (is_dir($path)) {
      $child = scanDirRec($path, $base, $q);
      if ($child) {
        $out[] = [
          'tipo' => 'pasta',
          'nome' => ucwords(str_replace('_',' ',$f)),
          'filhos' => $child
        ];
      }
    }

    if (is_file($path) && pathinfo($f, PATHINFO_EXTENSION) === 'txt') {
      if (str_contains(strtolower(file_get_contents($path)), $q)) {
        $out[] = [
          'tipo' => 'arquivo',
          'nome' => ucwords(str_replace('_',' ',pathinfo($f, PATHINFO_FILENAME))),
          'arquivo' => str_replace($base . DIRECTORY_SEPARATOR, '', $path),
          'breadcrumb' => str_replace($base . DIRECTORY_SEPARATOR, '', dirname($path))
        ];
      }
    }
  }
  return $out;
}

echo json_encode(scanDirRec($base, $base, $q), JSON_UNESCAPED_UNICODE);
