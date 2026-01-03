<?php
if (!isset($_FILES['imagem'])) exit;

$img = $_FILES['imagem']['tmp_name'];
$formato = $_POST['formato'];

$info = getimagesize($img);
$mime = $info['mime'];

switch ($mime) {
  case 'image/png':
    $image = imagecreatefrompng($img);
    break;
  case 'image/jpeg':
    $image = imagecreatefromjpeg($img);
    break;
  case 'image/webp':
    $image = imagecreatefromwebp($img);
    break;
  case 'image/gif':
    $image = imagecreatefromgif($img);
    break;
  default:
    exit('Formato não suportado');
}

header("Content-Type: image/$formato");
header("Content-Disposition: attachment; filename=imagem_convertida.$formato");

switch ($formato) {
  case 'png':
    imagepng($image);
    break;
  case 'jpg':
    imagejpeg($image, null, 90);
    break;
  case 'webp':
    imagewebp($image, null, 90);
    break;
  case 'gif':
    imagegif($image);
    break;
}

imagedestroy($image);
