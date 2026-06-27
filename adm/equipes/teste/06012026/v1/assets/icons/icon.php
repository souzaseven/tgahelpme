<?php
// Gera ícone PNG dinamicamente via GD para o PWA manifest
$size = max(32, min(512, (int)($_GET['size'] ?? 192)));

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');

if (!function_exists('imagecreatetruecolor')) {
    // GD não disponível — retorna imagem 1x1 transparente
    $empty = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    echo $empty;
    exit;
}

$img = imagecreatetruecolor($size, $size);
imageantialias($img, true);

// Cores
$bg     = imagecolorallocate($img, 15,  23,  42);   // #0f172a (dark)
$indigo = imagecolorallocate($img, 99, 102, 241);   // #6366f1
$white  = imagecolorallocate($img, 248, 250, 252);  // #f8fafc

// Fundo arredondado (simulado com círculo + quadrado)
imagefilledrectangle($img, 0, 0, $size - 1, $size - 1, $bg);

// Círculo de fundo
$pad = (int)($size * 0.08);
imagefilledellipse($img, $size / 2, $size / 2, $size - $pad * 2, $size - $pad * 2, $indigo);

// Símbolo "$" centralizado
$font = 5;
$fW   = imagefontwidth($font);
$fH   = imagefontheight($font);
$x    = (int)(($size - $fW) / 2);
$y    = (int)(($size - $fH) / 2);

imagestring($img, $font, $x, $y, '$', $white);

imagepng($img, null, 6);
imagedestroy($img);
