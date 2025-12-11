<?php
// config.php - v3 com detecção automática

$versao = basename(__DIR__);
$base   = "/adm/equipes/pausas/{$versao}/";

$config = [
    "versao"      => $versao,
    "base_url"    => $base,
    "login_url"   => $base . "login/login.php",
];

// Detectar subpastas e gerar URLs automaticamente
$root = $_SERVER['DOCUMENT_ROOT'] . $base;
$dirs = glob($root . "*", GLOB_ONLYDIR);

if ($dirs !== false) {
    foreach ($dirs as $dir) {
        $nome = basename($dir);
        $config[$nome . "_url"] = $base . $nome . "/";
    }
}

return $config;
